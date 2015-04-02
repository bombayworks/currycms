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
use Curry\Backend\AbstractBackend;
use Curry\Exception;
use Curry\Util\ClassEnumerator;
use Curry\Util\Helper;
use Curry\View;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Main class for the backend.
 *
 * @package Curry\Controller\Backend
 */
class Backend extends AbstractBackend implements EventSubscriberInterface {
	public function initialize()
	{
		$app = App::getInstance();

		$cacheName = sha1(__CLASS__.'_backendClasses');
		$backendClasses = $app->cache->load($cacheName);
		if ($backendClasses === false) {
			$backendClasses = array();
			$classes = ClassEnumerator::findClasses(__DIR__.'/../Backend');
			foreach($classes as $className) {
				if (class_exists($className) && $className !== __CLASS__) {
					$r = new \ReflectionClass($className);
					if ($r->isSubclassOf('Curry\\Backend\\AbstractBackend') && !$r->isAbstract())
						$backendClasses[strtolower($r->getShortName())] = $className;
				}
			}
			$app->cache->save($backendClasses, $cacheName);
		}

		foreach($backendClasses as $viewName => $className) {
			$this->addView($viewName, new $className($this->app));
		}
	}

	public function url($parameters = null)
	{
		/**
		 * @todo: make this configurable
		 */
		return '/admin/';
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
		if (preg_match('#^'.preg_quote($this->url(), '#').'(.*)$#', $request->getPathInfo(), $m)) {
			$view = $this->findView($m[1]);
			if ($view) {
				$request->attributes->set('view', $view);
				$request->attributes->set('_controller', array($this, 'index'));
			}
		}
	}

	public function index(Request $request, View $view)
	{
		$response = $view->show($request);
		if (!$response instanceof Response)
			$response = new Response(Helper::stringify($response));
		return $response;
	}

	public function show(Request $request)
	{
		foreach($this->views() as $name => $viewAndRoute) {
			list($view, $route) = $viewAndRoute;
			$this->addMainContent('<a href="'.$view->url().'">'.$name.'</a><br/>');
		}
		return $this->render();
	}
}
