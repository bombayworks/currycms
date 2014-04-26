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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
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
	 * List of routes.
	 *
	 * @var array
	 */
	protected $routes = array();

	/**
	 * @var object
	 */
	protected $globalVariables = null;
	
	/**
	 * Array of background functions to be executed on shutdown.
	 *
	 * @var array
	 */
	protected static $backgroundFunctions = null;

	/**
	 * Initializes the application. Sets up default routes.
	 */
	public function __construct()
	{
		if(\Curry\App::getInstance()->config->curry->pageCache && class_exists('\\Page')) {
			\Page::getCachedPages();
		}
		\Curry_URL::setReverseRouteCallback(array($this, 'reverseRoute'));
	}

	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::REQUEST => 'onKernelRequest',
			//KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
		);
	}

	public function onKernelRequest(GetResponseEvent $event)
	{
		$request = $event->getRequest();
		$page = $this->findPage($request);
		if ($page) {
			$request->attributes->set('_page', $page);
			$request->attributes->set('_controller', array($this, 'index'));
		}
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
		$baseUrl = \Curry_URL::getDefaultBaseUrl();
		$basePath = $baseUrl['path'];
		$basePathRemoved = false;
		if (\Curry_String::startsWith($path, $basePath) && $path !== '/') {
			$path = substr($path, strlen($basePath));
			$basePathRemoved = true;
		}
		\Curry_Route_ModelRoute::reverse($path, $query);
		// re-add base path if it was removed
		if ($basePathRemoved) {
			$path = $basePath . $path;
		}
	}
	
	public function findPage(Request $request)
	{
		$requestUri = $request->getPathInfo();

		// remove base path
		$baseUrl = \Curry_URL::getDefaultBaseUrl();
		$basePath = $baseUrl['path'];
		if (strpos($requestUri, $basePath) === 0)
			$requestUri = substr($requestUri, strlen($basePath));

		// add trailing slash if missing
		if($requestUri && substr($requestUri,-1) != '/')
			$requestUri .= '/';

		// use domain mapping to restrict page to a certain page-branch
		$rootPage = null;
		if(\Curry\App::getInstance()->config->curry->domainMapping->enabled){
			$currentDomain = strtolower($_SERVER['HTTP_HOST']);
			foreach (\Curry\App::getInstance()->config->curry->domainMapping->domains as $domain) {
				if(strtolower($domain->domain) === $currentDomain
					|| ($domain->include_www && strtolower('www.'.$domain->domain) === $currentDomain)){
					$rootPage = $domain->base_page;
					break;
				}
			}
			if(!$rootPage && \Curry\App::getInstance()->config->curry->domainMapping->default)
				$rootPage = \Curry\App::getInstance()->config->curry->domainMapping->default;
			if($rootPage)
				$rootPage = \PageQuery::create()->findPk($rootPage);
		}

		// attempt to find page using url
		if(\Curry\App::getInstance()->config->curry->pageCache) {
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
	 * Handle page redirection.
	 *
	 * @param \Page $page
	 * @param \Curry_Request $r
	 * @return \Page
	 */
	public function redirectPage(\Page $page, \Curry_Request $r)
	{
		while($page && $page->getRedirectMethod()) {
			switch($page->getRedirectMethod()) {
				case \PagePeer::REDIRECT_METHOD_CLONE:
					if($page->getRedirectUrl() !== null) {
						readfile($page->getRedirectUrl());
						exit;
					}
					$redirectPage = $page->getActualRedirectPage();
					if ($redirectPage && $redirectPage !== $page) {
						$page = $redirectPage;
					} else {
						return $page;
					}
					break;
					
				default:
					$code = ($page->getRedirectMethod() == \PagePeer::REDIRECT_METHOD_PERMANENT ? 301 : 302);
					url($page->getFinalUrl(), $r->get)->redirect($code);
					break;
			}
		}
		
		return $page;
	}

	/**
	 * Do automatic publishing of pages.
	 */
	public function autoPublish()
	{
		$cacheName = strtr(__CLASS__, '\\', '_') . '_' . 'AutoPublish';
		if(($nextPublish = \Curry\App::getInstance()->cache->load($cacheName)) === false) {
			\Curry\App::getInstance()->logger->notice('Doing auto-publish');
			$revisions = \PageRevisionQuery::create()
				->filterByPublishDate(null, \Criteria::ISNOTNULL)
				->orderByPublishDate()
				->find();
			$nextPublish = time() + 86400;
			foreach($revisions as $revision) {
				if($revision->getPublishDate('U') <= time()) {
					// publish revision
					$page = $revision->getPage();
					\Curry\App::getInstance()->logger->notice('Publishing page: ' . $page->getUrl());
					$page->setActivePageRevision($revision);
					$revision->setPublishedDate(time());
					$revision->setPublishDate(null);
					$page->save();
					$revision->save();
				} else {
					$nextPublish = $revision->getPublishDate('U');
					break;
				}
			}
			$revisions->clearIterator();
			\Curry\App::getInstance()->logger->info('Next publish is in '.($nextPublish - time()) . ' seconds.');
			\Curry\App::getInstance()->cache->save(true, $cacheName, array(), $nextPublish - time());
		}
	}
	
	/**
	 * Change the active language.
	 *
	 * @param string|Language $language
	 */
	public function setLanguage($language)
	{
		$locale = \Curry_Language::setLanguage($language);
		$language = \Curry_Language::getLanguage();
		if($language)
			\Curry\App::getInstance()->logger->info('Current language is now '.$language->getName().' (with locale '.$locale.')');
	}
	
	public function index()
	{
		$app = \Curry\App::getInstance();
		$request = $app->request;
		$page = $request->attributes->get('_page');
		$pageRevision = $page->getPageRevision();

		$app->logger->info('Starting request at '.$request->getUri());

		/*
		
		if($app->config->curry->autoPublish)
			$this->autoPublish();
		
		$page = null;
		$vars = array('curry' => array());
		$options = array();
		$forceShow = false;
		$showWorking = false;

		if($app->config->curry->setup) {
			die('Site is not yet configured, go to admin.php and configure your site.');
		}
		
		// check if we have a valid backend-user logged in
		$validUser = null;//!!\User::getUser();
		if($validUser) {
			
			// check for inline-admin
			$adminNamespace = new \Zend\Session\Container('Curry_Admin');
			if($app->config->curry->liveEdit && !$request->getParam('curry_force_show')) {
				if($request->hasParam('curry_inline_admin'))
					$adminNamespace->inlineAdmin = $request->getParam('curry_inline_admin') ? true : false;
				if($adminNamespace->inlineAdmin) {
					$options['inlineAdmin'] = true;
					$forceShow = true;
					$showWorking = true;
					\Curry_InlineAdmin::$active = true;
				}
			}

			// show working revision? (default is published)
			if($request->getParam('curry_show_working')) {
				$forceShow = true;
				$showWorking = true;
			}

			// show inactive pages?
			if($request->getParam('curry_force_show'))
				$forceShow = true;
				
			if($showWorking)
				\Page::setRevisionType(Page::WORKING_REVISION);
		}
		
		// Maintenance enabled?
		if($app->config->curry->maintenance->enabled && !$forceShow) {
			$app->logger->debug("Maintenance enabled");
			
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 3600');
			
			$message = 'Page is down for maintenance, please check back later.';
			if($app->config->curry->maintenance->message)
				$message = $app->config->curry->maintenance->message;
			
			$page = $app->config->curry->maintenance->page;
			if($page !== null)
				$page = \PageQuery::create()->findPk((int)$page);
			if(!$page)
				die($message);
				
			$vars['curry']['MaintenanceMessage'] = $message;
		}
		
		// Check force domain?
		if($app->config->curry->forceDomain && !$forceShow) {
			$uri = $request->getUri();
			$url = parse_url($app->config->curry->baseUrl);
			if(strcasecmp($_SERVER['HTTP_HOST'], $url['host']) !== 0) {
				$location = substr($app->config->curry->baseUrl, 0, -1) . $uri;
				header("Location: " . $location, true, 301);
				exit;
			}
		}
		
		// Parameters to show a single module
		if($request->getParam('curry_show_page_module_id'))
			$options['pageModuleId'] = $request->getParam('curry_show_page_module_id');
		if(isAjax() && $request->getParam('curry_ajax_page_module_id'))
			$options['pageModuleId'] = $request->getParam('curry_ajax_page_module_id');
		
		// Attempt to find cached page
		if($request->getMethod() === 'GET') {
			$time = microtime(true);
			$cacheName = strtr(__CLASS__, '\\', '_') . '_Page_' . md5($request->getUri());
			if(($cache = $app->cache->load($cacheName)) !== false) {
				$app->logger->info('Using cached page content');
				foreach($cache['headers'] as $header)
					header($header);
				echo $cache['content'];
				//Curry_Core::triggerHook('Curry\Controller\Frontend::render', $cache['page_id'], $cache['page_revision_id'], microtime(true) - $time, 0);
				return;
			}
		}
			
		// attempt to find the requested page
		if(!$page) {
			try {
				$page = $this->findPage($request);
				$page = $this->redirectPage($page, $request);
			}
			catch(\Exception $e) {
				$app->logger->notice('Error when trying to find page: ' . $e->getMessage());
				$page = null;
			}
			// make sure page is enabled
			if(($page instanceof \Page) && !$forceShow && !$page->getEnabled()) {
				$app->logger->notice('Page is not accessible');
				$page = null;
			}
		}
		
		// Page was not found, attempt to find 404 page
		if(!$page) {
			header("HTTP/1.1 404 Not Found");
			if($app->config->curry->errorPage->notFound) {
				$page = PageQuery::create()->findPk($app->config->curry->errorPage->notFound);
				if(!$page || !$page->getEnabled())
					throw new \Exception('Page not found, additionally the page-not-found page could not be found.');
			} else {
				die('Page not found');
			}
		}
		*/
		$vars = array();
		$options = array();
		
		// Set language
		$language = $page->getInheritedProperty('Language');
		$fallbackLanguage = $app->config->curry->fallbackLanguage;
		if($language) {
			$this->setLanguage($language);
		} else if($fallbackLanguage) {
			$app->logger->info('Using fallback language');
			$this->setLanguage($fallbackLanguage);
		} else {
			$app->logger->notice('Language not set for page');
		}
		
		// Attempt to render page
		$app->logger->notice('Showing page ' . $page->getName() . ' (PageRevisionId: '.$pageRevision->getPageRevisionId().')');

		$generatorClass = $pageRevision->getPage()->getInheritedProperty('Generator', $app->config->curry->defaultGeneratorClass);
		$generator = new $generatorClass($pageRevision, $request);

		$app->page = $page;
		$app->pageRevision = $pageRevision;
		$app->generator = $generator;

		return $generator->render($vars, $options);
	}

	/**
	 * Register a function for background execution on shutdown.
	 * Output is not possible in the callback function.
	 *
	 * @param callback $callback
	 * @param mixed $parameters,... [optional] Optional parameters passed to the callback function.
	 */
	public static function registerBackgroundFunction($callback)
	{
		if (self::$backgroundFunctions === null) {
			// Replace output-buffering with custom function
			while(ob_get_level())
				ob_end_clean();
			ob_start(function($buffer) {
				header("Connection: close", true);
				header("Content-Encoding: none", true);
				header("Content-Length: ".strlen($buffer), true);
				return $buffer;
			});
			register_shutdown_function(array(__CLASS__, 'executeBackgroundFunctions'));
			self::$backgroundFunctions = array();
		}
		self::$backgroundFunctions[] = func_get_args();
	}

	/**
	 * Remove a previously registered function (using registerBackgroundFunction) from being executed.
	 *
	 * @param $callback
	 * @return bool
	 */
	public static function unregisterBackgroundFunction($callback)
	{
		if (self::$backgroundFunctions === null)
			return false;
		$status = false;
		foreach(self::$backgroundFunctions as $k => $args) {
			$cb = array_shift($args);
			if ($cb === $callback) {
				unset(self::$backgroundFunctions[$k]);
				$status = true;
			}
		}
		return $status;
	}

	/**
	 * Execute registered callback functions.
	 * This function will be called automatically if there are background
	 * functions registered and is not supposed to be called manually.
	 */
	public static function executeBackgroundFunctions()
	{
		// Send browser response, and continue running script in background
		ignore_user_abort(true);
		set_time_limit(60);
		ob_end_flush();
		flush();
		if (session_id())
			session_write_close();
		if (function_exists('fastcgi_finish_request'))
			fastcgi_finish_request();

		// Execute registered functions
		foreach(self::$backgroundFunctions as $args) {
			$callback = array_shift($args);
			call_user_func_array($callback, $args);
		}
	}
	
	/**
	 * Return json-data to browser and exit. Will set content-type header and encode the data.
	 *
	 * @param mixed $content	Data to encode with json_encode. Note that this must be utf-8 encoded.
	 * @param string $jsonp		To send the response using a JSONP callback, set this to a string with the name of the callback-function.
	 * @param string $contentType	The content-type header to send, charset will be appended.
	 * @param bool $exit		Terminate the script after sending the data.
	 */
	public static function returnJson($content, $jsonp = "", $contentType = "application/json", $exit = true)
	{
		header("Content-type: $contentType; charset=utf-8");
		if(!is_string($content))
			$content = json_encode($content);
		echo $jsonp ? "$jsonp($content);" : $content;
		if($exit)
			exit;
	}
	
	/**
	 * Return partial html-content to browser and exit. Will set content-type header and return the content.
	 *
	 * @param string $content		Content to send to browser.
	 * @param string $contentType	The content-type header to send, charset will be appended.
	 * @param string $charset		Charset to send with content-type, if not set this will default to the outputEncoding.
	 * @param bool $exit			Terminate the script after sending the data.
	 */
	public static function returnPartial($content, $contentType = 'text/html', $charset = null, $exit = true)
	{
		$charset = $charset ? $charset : \Curry\App::getInstance()->config->curry->outputEncoding;
		header("Content-type: $contentType; charset=$charset");
		echo (string)$content;
		if($exit)
			exit;
	}
	
	/**
	 * Return data as file attachment.
	 *
	 * @param resource|string $data	A string or resource containing the data to send.
	 * @param string $contentType	The content-type header to send.
	 * @param string $filename		Filename to send to browser.
	 * @param bool $exit			Terminate the script after sending the data.
	 */
	public static function returnData($data, $contentType = 'application/octet-stream', $filename = 'file.dat', $exit = true)
	{
		header('Content-Description: File Transfer');
		header('Content-Transfer-Encoding: binary');
		header("Content-Disposition: attachment; filename=".\Curry_String::escapeQuotedString($filename));
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header("Content-type: $contentType");
		if(is_string($data)) {
			header('Content-Length: ' . strlen($data));
			echo $data;
		} else if(is_resource($data) && (get_resource_type($data) === 'stream' || get_resource_type($data) === 'file')) {
			// save current
			$current = ftell($data);
		
			//Seek to the end
			fseek($data, 0, SEEK_END);
		
			//Get the size value
			$size = ftell($data) - $current;
		
			fseek($data, $current, SEEK_SET);
			header('Content-Length: ' . $size);
			fpassthru($data);
			if ($exit)
				fclose($data);
		} else
			throw new \Curry_Exception('Data is of unknown type.');
		if($exit)
			exit;
	}

	/**
	 * Return a file to browser and exit. Will set appropriate headers and return the content.
	 *
	 * @param string $file			Path to file
	 * @param string $contentType	The content-type header to send.
	 * @param string $filename		Filename to send to browser, uses the basename of $file if not specified.
	 * @param bool $exit			Terminate the script after sending the data.
	 * @param bool $disableOutputBuffering	Disable output buffering.
	 */
	public static function returnFile($file, $contentType = 'application/octet-stream', $filename = '', $exit = true, $disableOutputBuffering = true)
	{
		if(!$filename)
			$filename = basename($file);
		header('Content-Description: File Transfer');
		header('Content-Transfer-Encoding: binary');
		header('Content-Disposition: attachment; filename='.\Curry_String::escapeQuotedString($filename));
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-type: '.$contentType);
		header('Content-Length: '.filesize($file));
		
		if($disableOutputBuffering) {
			while(@ob_end_flush())
				;
		}
		
		readfile($file);
		
		if($exit)
			exit;
	}
}
