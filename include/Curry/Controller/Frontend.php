<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */

namespace Curry\Controller;
use Curry\App;
use Curry\URL;
use Curry\Util\StringHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Main class for the frontend.
 * 
 * The purpose of this class is to handle the current request, use routes to find
 * what page is being requested and then output the page to the client.
 * 
 * @package Curry
 */
class Frontend implements EventSubscriberInterface {
	/**
	 * @var App
	 */
	private $app;

	/**
	 * Initializes the application. Sets up default routes.
	 * @param App $app
	 * @throws \Exception
	 */
	public function __construct(App $app)
	{
		$this->app = $app;
		if($app['pageCache'] && class_exists('\\Page')) {
			\Page::getCachedPages();
		}
		//\Curry\URL::setReverseRouteCallback(array($this, 'reverseRoute'));
	}

	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::REQUEST => 'onKernelRequest',
		);
	}

	public function onKernelRequest(GetResponseEvent $event)
	{
		$request = $event->getRequest();
		$page = $this->findPage($request);
		if ($page) {

			$forceShow = false;
			$showWorking = false;

			//if($app['setup']) {
			//	die('Site is not yet configured, go to admin.php and configure your site.');
			//}

			// check if we have a valid backend-user logged in
			$validUser = !!\User::getUser();
			if($validUser) {
				/*
				// check for inline-admin
				$adminNamespace = new \Zend\Session\Container('Curry\Controller\Backend');
				if($app['liveEdit'] && !$request->getParam('curry_force_show')) {
					if($request->query->has('curry_inline_admin'))
						$adminNamespace->inlineAdmin = (bool)$request->query->get('curry_inline_admin');
					if($adminNamespace->inlineAdmin) {
						$options['inlineAdmin'] = true;
						$forceShow = true;
						$showWorking = true;
						\Curry_InlineAdmin::$active = true;
					}
				}
				*/

				// show working revision? (default is published)
				if($request->query->get('curry_show_working')) {
					$forceShow = true;
					$showWorking = true;
				}

				// show inactive pages?
				if($request->query->get('curry_force_show')) {
					$forceShow = true;
				}

				if($showWorking)
					\Page::setRevisionType(\Page::WORKING_REVISION);
			}

			// Show maintenance page?
			if($this->app['maintenance.enabled'] && !$forceShow) {
				$this->app->logger->debug("Maintenance enabled");

				$message = 'Page is down for maintenance, please check back later.';
				if($this->app['maintenance.message'])
					$message = $this->app['maintenance.message'];

				$request->attributes->set('message', $message);
				$request->attributes->set('page', $this->app['maintenance.page']);
				$request->attributes->set('_controller', new Maintenance($this->app));
				return;
			}

			// Force domain redirect
			// @todo force scheme?
			if($this->app['maintenance.enabled'] && !$forceShow) {
				$base = URL::getDefaultBaseUrl();
				$baseHost = strtolower($base['host']);
				$httpHost = strtolower($request->server->get('HTTP_HOST'));
				if ($httpHost !== $baseHost) {
					$target = url($request->getRequestUri())->getAbsolute();
					$event->setResponse(RedirectResponse::create($target));
					$event->stopPropagation();
				}
			}

			// Follow page redirects...
			while($page && $page->getRedirectMethod()) {
				switch($page->getRedirectMethod()) {
					case \PagePeer::REDIRECT_METHOD_CLONE:
						if($page->getRedirectUrl() !== null) {
							$event->setResponse(Response::create(file_get_contents($page->getRedirectUrl())));
							return;
						}
						$redirectPage = $page->getActualRedirectPage();
						if ($redirectPage && $redirectPage !== $page) {
							$page = $redirectPage;
						} else {
							break 2;
						}
						break;

					default:
						$code = ($page->getRedirectMethod() == \PagePeer::REDIRECT_METHOD_PERMANENT ? 301 : 302);
						// @todo should this append query string? it used to...
						$event->setResponse(RedirectResponse::create($page->getFinalUrl(), $code));
						return;
				}
			}

			$request->attributes->set('page', $page);
			$request->attributes->set('_controller', new Page($this->app));
		}
	}

	/**
	 * @param Request $request
	 * @return \Page|null
	 * @throws \Exception
	 */
	public function findPage(Request $request)
	{
		$requestUri = $request->getPathInfo();

		// remove base path
		$baseUrl = URL::getDefaultBaseUrl();
		$basePath = $baseUrl['path'];
		if (strpos($requestUri, $basePath) === 0)
			$requestUri = substr($requestUri, strlen($basePath));

		// add trailing slash if missing
		if(substr($requestUri,-1) != '/')
			$requestUri .= '/';

		// use domain mapping to restrict page to a certain page-branch
		$rootPage = null;
		if($this->app['domainMapping.enabled']){
			$currentDomain = strtolower($request->server->get('HTTP_HOST'));
			foreach ($this->app['domainMapping.domains'] as $domain) {
				if(strtolower($domain->domain) === $currentDomain
					|| ($domain->include_www && strtolower('www.'.$domain->domain) === $currentDomain)){
					$rootPage = $domain->base_page;
					break;
				}
			}
			if(!$rootPage && $this->app['domainMapping.default'])
				$rootPage = $this->app['domainMapping.default'];
			if($rootPage)
				$rootPage = \PageQuery::create()->findPk($rootPage);
		}

		// attempt to find page using url
		if($this->app['pageCache']) {
			$pages = array();
			$allPages = \Page::getCachedPages();
			foreach($allPages as $page) {
				if($page->getUrl() == $requestUri) {
					if(!$rootPage || $rootPage->isAncestorOf($page) || $rootPage->getPageId() == $page->getPageId())
						$pages[] = $page;
				}
			}
		} else {
			$pages = \PageQuery::create()
				->filterByUrl($requestUri)
				->_if($rootPage)
				->branchOf($rootPage)
				->_endif()
				->joinWith('Page.ActivePageRevision apr', \Criteria::LEFT_JOIN)
				->find();
		}

		if(count($pages) > 1)
			throw new \Exception('URL refers to multiple pages: ' . $requestUri);
		else if(count($pages) == 1)
			return $pages[0];
		return null;
	}

	/**
	 * Cached map of Page URL to Model.
	 *
	 * @var string[]
	 */
	protected static $urlToModel = null;

	/**
	 * Find model from URL.
	 *
	 * @param string $url
	 * @return string|null
	 */
	protected static function findPageModel($url)
	{
		if(self::$urlToModel === null) {
			$cacheName = __CLASS__ . '_' . 'UrlToModel';
			if((self::$urlToModel = App::getInstance()->cache->load($cacheName)) === false) {
				self::$urlToModel = \PageQuery::create()
					->filterByModelRoute(null, \Criteria::ISNOTNULL)
					->find()
					->toKeyValue('Url', 'ModelRoute');
				App::getInstance()->cache->save(self::$urlToModel, $cacheName);
			}
		}
		return isset(self::$urlToModel[$url]) ? self::$urlToModel[$url] : null;
	}

	/**
	 * Handler for reverse-routing.
	 *
	 * @param string $path
	 * @param string|array $query
	 */
	public function reverseRoute(&$path, &$query)
	{
		// remove matching base path
		$baseUrl = URL::getDefaultBaseUrl();
		$basePath = $baseUrl['path'];
		$basePathRemoved = false;
		if (StringHelper::startsWith($path, $basePath) && $path !== '/') {
			$path = substr($path, strlen($basePath));
			$basePathRemoved = true;
		}
		//\Curry_Route_ModelRoute::reverse($path, $query);
		// re-add base path if it was removed
		if ($basePathRemoved) {
			$path = $basePath . $path;
		}
	}

	/**
	 * Perform routing.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request)
	{
		$p = explode('?', $request->getUri(), 2);
		$parts = explode("/", $p[0]);
		$query = isset($p[1]) ? "?".$p[1] : '';

		$url = "";
		while(count($parts)) {
			$model = self::findPageModel($url ? $url : '/');
			if($model) {
				$slug = array_shift($parts);
				$remaining = join("/", $parts);
				$params = self::getParamFromSlug($model, $slug);
				if($params) {
					// add params to request
					foreach($params as $name => $value)
						$request->setParam('get', $name, $value);
					$query .= (strlen($query) ? '&' : '?') . http_build_query($params);
					// rebuild uri
					$request->setUri($url.$remaining.$query);
					// continue routing
					return true;
				}
				// put slug back and continue searching
				array_unshift($parts, $slug);
			}
			$url .= array_shift($parts) . "/";
		}

		return null;
	}

	/**
	 * From an internal URL, create the public URL.
	 *
	 * @param string $path
	 * @param array|string $query
	 */
	public static function reverse(&$path, &$query)
	{
		$parts = explode("/", $path);
		$url = "";
		$newpath = array();
		while(count($parts)) {
			// find model for current url
			$model = self::findPageModel($url ? $url : '/');
			if($model) {
				$env = array();
				parse_str($query, $env);
				$slug = self::reverseModelSlug($model, $env);
				if($slug !== null) {
					$newpath[] = $slug;
					$query = http_build_query($env, null, '&');
				}
			}
			// build next url
			while(count($parts)) {
				$part = array_shift($parts);
				$newpath[] = $part;
				if($part) {
					$url .= $part . "/";
					break;
				}
			}
		}
		$path = join('/', $newpath);
	}

	/**
	 * From model and variables, attempt to find a slug.
	 *
	 * @param string $model
	 * @param array $env
	 * @return string|null The slug if found, otherwise null.
	 */
	protected static function reverseModelSlug($model, &$env)
	{
		// Find primary-key values
		$primaryKeyColumns = \PropelQuery::from($model)
			->getTableMap()
			->getPrimaryKeys();
		$pk = array();
		foreach($primaryKeyColumns as $primaryKeyColumn) {
			$name = strtolower($primaryKeyColumn->getName());
			if(!isset($env[$name]))
				return null;
			$pk[] = $env[$name];
		}
		// Find model object from primary-key
		$modelObject = \PropelQuery::from($model)
			->findPk(count($pk) == 1 ? $pk[0] : $pk);
		if(!$modelObject)
			return null;
		// Unset environment variables
		foreach($primaryKeyColumns as $primaryKeyColumn) {
			$name = strtolower($primaryKeyColumn->getName());
			unset($env[$name]);
		}
		return $modelObject->getSlug();
	}

	/**
	 * From model and slug, attempt to find parameters.
	 *
	 * @param string $model
	 * @param string $slug
	 * @return array|null
	 */
	protected static function getParamFromSlug($model, $slug)
	{
		if(in_array('Curry_IRoutable', class_implements($model)))
			return call_user_func(array($model, "getParamFromSlug"), $slug);
		$modelObject = \PropelQuery::from($model)
			->findOneBySlug($slug);
		if($modelObject) {
			$param = array();
			$tableMap = \PropelQuery::from($model)
				->getTableMap();
			foreach($tableMap->getPrimaryKeys() as $primaryKeyColumn) {
				$name = strtolower($primaryKeyColumn->getName());
				$phpName = $primaryKeyColumn->getPhpName();
				$value = $modelObject->{'get'.$phpName}();
				$param[$name] = $value;
			}
			return $param;
		}
		return null;
	}
}
