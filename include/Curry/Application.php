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

/**
 * Main class for the frontend.
 * 
 * The purpose of this class is to handle the current request, use routes to find
 * what page is being requested and then output the page to the client.
 * 
 * @package Curry
 */
class Curry_Application {
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
	 * Singleton instance for this class.
	 *
	 * @var Curry_Application
	 */
	protected static $instance;

	/**
	 * Array of background functions to be executed on shutdown.
	 *
	 * @var array
	 */
	protected static $backgroundFunctions = null;
	
	/**
	 * Get global singleton instance.
	 *
	 * @return Curry_Application
	 */
	public static function getInstance()
	{
		if(!isset(self::$instance)) {
			$applicationClass = Curry_Core::$config->curry->applicationClass;
			self::$instance = new $applicationClass;
			Curry_Core::triggerHook('Curry_Application::init');
		}
		return self::$instance;
	}
	
	/**
	 * Initializes the application. Sets up default routes.
	 */
	public function __construct()
	{
		if(Curry_Core::$config->curry->pageCache && class_exists('Page')) {
			Page::getCachedPages();
		}

		$this->addRoute(new Curry_Route_ModelRoute());
		$this->addRoute(new Curry_Route_Page());
		Curry_URL::setReverseRouteCallback(array($this, 'reverseRoute'));
	}

	public function getGlobalVariables()
	{
		if ($this->globalVariables === null) {
			$this->globalVariables = (object)array(
				'ProjectName' => Curry_Core::$config->curry->name,
				'BaseUrl' => Curry_Core::$config->curry->baseUrl,
				'DevelopmentMode' => Curry_Core::$config->curry->developmentMode,
			);
		}
		return $this->globalVariables;
	}
	
	/**
	 * Add a route to the top of the routing list.
	 *
	 * @param Curry_IRoute $route
	 */
	public function addRoute(Curry_IRoute $route)
	{
		array_unshift($this->routes, $route);
	}
	
	/**
	 * Handler for reverse-routing.
	 *
	 * @param string $path
	 * @param array $env
	 */
	public function reverseRoute(&$path, array &$env)
	{
		// remove matching base path
		$baseUrl = Curry_URL::getBaseUrl();
		$basePath = $baseUrl['path'];
		$basePathRemoved = false;
		if (Curry_String::startsWith($path, $basePath) && $path !== '/') {
			$path = substr($path, strlen($basePath));
			$basePathRemoved = true;
		}
		Curry_Route_ModelRoute::reverse($path, $env);
		// re-add base path if it was removed
		if ($basePathRemoved) {
			$path = $basePath . $path;
		}
	}
	
	/**
	 * Find Page from a request object using routes.
	 *
	 * @param Curry_Request $request
	 * @return Page
	 */
	public function findPage(Curry_Request $request)
	{
		// use routes to find
		$loop = true;
		while($loop) {
			$loop = false;
			foreach($this->routes as $route) {
				$ret = $route->route($request);
				if($ret === true) {
					$loop = true;
					break;
				}
				else if($ret instanceof Page)
					return $ret;
			}
		}
		
		throw new Exception('Page not found');
	}
	
	/**
	 * Handle page redirection.
	 *
	 * @param Page $page
	 * @param Curry_Request $r
	 * @return Page
	 */
	public function redirectPage(Page $page, Curry_Request $r)
	{
		while($page && $page->getRedirectMethod()) {
			switch($page->getRedirectMethod()) {
				case PagePeer::REDIRECT_METHOD_CLONE:
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
					$code = ($page->getRedirectMethod() == PagePeer::REDIRECT_METHOD_PERMANENT ? 301 : 302);
					url($page->getFinalUrl(), $r->get)->redirect($code);
					break;
			}
		}
		
		return $page;
	}

	protected function getRequestUri()
	{
		// separate path and query-string
		$requestUri = explode('?', rawurldecode(Curry_URL::getRequestUri()), 2);

		// remove matching base path
		$baseUrl = Curry_URL::getBaseUrl();
		$basePath = $baseUrl['path'];
		if ($basePath === $requestUri[0]) {
			$requestUri[0] = '/';
		} else if (Curry_String::startsWith($requestUri[0], $basePath)) {
			$requestUri[0] = substr($requestUri[0], strlen($basePath));
		}

		return join('?', $requestUri);
	}

	protected function createRequest()
	{
		$request = new Curry_Request($_SERVER['REQUEST_METHOD'], $this->getRequestUri());
		$request->addParamSource('cookie', $_COOKIE);
		$request->addParamSource('post', $_POST);
		$request->addParamSource('get', $_GET);
		$request->addParamSource('env', $_ENV);
		return $request;
	}
	
	/**
	 * Initialize the request and handle it. This is the main method of this class.
	 */
	public function run()
	{
		$request = $this->createRequest();
		$this->handle($request);
	}

	/**
	 * Do automatic publishing of pages.
	 */
	public function autoPublish()
	{
		$cacheName = __CLASS__ . '_' . 'AutoPublish';
		if(($nextPublish = Curry_Core::$cache->load($cacheName)) === false) {
			trace_notice('Doing auto-publish');
			$revisions = PageRevisionQuery::create()
				->filterByPublishDate(null, Criteria::ISNOTNULL)
				->orderByPublishDate()
				->find();
			$nextPublish = time() + 86400;
			foreach($revisions as $revision) {
				if($revision->getPublishDate('U') <= time()) {
					// publish revision
					$page = $revision->getPage();
					trace_notice('Publishing page: ' . $page->getUrl());
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
			trace_notice('Next publish is in '.($nextPublish - time()) . ' seconds.');
			Curry_Core::$cache->save(true, $cacheName, array(), $nextPublish - time());
		}
	}
	
	/**
	 * Change the active language.
	 *
	 * @param string|Language $language
	 */
	public function setLanguage($language)
	{
		$locale = Curry_Language::setLanguage($language);
		$language = Curry_Language::getLanguage();
		if($language)
			trace_notice('Current language is now '.$language->getName().' (with locale '.$locale.')');
	}
	
	/**
	 * Handle the specified request.
	 *
	 * @param Curry_Request $request
	 */
	public function handle(Curry_Request $request)
	{
		trace_notice('Starting request at '.$request->getUri());
		
		if(Curry_Core::$config->curry->autoPublish)
			$this->autoPublish();
		
		$page = null;
		$vars = array('curry' => array());
		$options = array();
		$forceShow = false;
		$showWorking = false;

		if(Curry_Core::$config->curry->setup) {
			die('Site is not yet configured, go to admin.php and configure your site.');
		}
		
		// check if we have a valid backend-user logged in
		$validUser = !!User::getUser();
		if($validUser) {
			
			// check for inline-admin
			$adminNamespace = new Zend_Session_Namespace('Curry_Admin');
			if(Curry_Core::$config->curry->liveEdit) {
				if($request->hasParam('curry_inline_admin'))
					$adminNamespace->inlineAdmin = $request->getParam('curry_inline_admin') ? true : false;
				if($adminNamespace->inlineAdmin) {
					$options['inlineAdmin'] = true;
					$forceShow = true;
					$showWorking = true;
					Curry_InlineAdmin::$active = true;
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
				Page::setRevisionType(Page::WORKING_REVISION);
		}
		
		// Maintenance enabled?
		if(Curry_Core::$config->curry->maintenance->enabled && !$forceShow) {
			Curry_Core::log("Maintenance enabled");
			
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 3600');
			
			$message = 'Page is down for maintenance, please check back later.';
			if(Curry_Core::$config->curry->maintenance->message)
				$message = Curry_Core::$config->curry->maintenance->message;
			
			$page = Curry_Core::$config->curry->maintenance->page;
			if($page !== null)
				$page = PageQuery::create()->findPk((int)$page);
			if(!$page)
				die($message);
				
			$vars['curry']['MaintenanceMessage'] = $message;
		}
		
		// Check force domain?
		if(Curry_Core::$config->curry->forceDomain && !$forceShow) {
			$uri = $request->getUri();
			$url = parse_url(Curry_Core::$config->curry->baseUrl);
			if(strcasecmp($_SERVER['HTTP_HOST'], $url['host']) !== 0) {
				$location = substr(Curry_Core::$config->curry->baseUrl, 0, -1) . $uri;
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
			$cacheName = __CLASS__ . '_Page_' . md5($request->getUri());
			if(($cache = Curry_Core::$cache->load($cacheName)) !== false) {
				trace_notice('Using cached page content');
				foreach($cache['headers'] as $header)
					header($header);
				echo $cache['content'];
				Curry_Core::triggerHook('Curry_Application::render', $cache['page_id'], $cache['page_revision_id'], microtime(true) - $time, 0);
				return;
			}
		}
			
		// attempt to find the requested page
		if(!$page) {
			try {
				$page = $this->findPage($request);
				$page = $this->redirectPage($page, $request);
			}
			catch(Exception $e) {
				Curry_Core::log('Error when trying to find page: ' . $e->getMessage(), Zend_Log::ERR);
				$page = null;
			}
			// make sure page is enabled
			if(($page instanceof Page) && !$forceShow && !$page->getEnabled()) {
				Curry_Core::log('Page is not accessible', Zend_Log::ERR);
				$page = null;
			}
		}
		
		// Page was not found, attempt to find 404 page
		if(!$page) {
			header("HTTP/1.1 404 Not Found");
			if(Curry_Core::$config->curry->errorPage->notFound) {
				$page = PageQuery::create()->findPk(Curry_Core::$config->curry->errorPage->notFound);
				if(!$page || !$page->getEnabled())
					throw new Exception('Page not found, additionally the page-not-found page could not be found.');
			} else {
				die('Page not found');
			}
		}
		
		// Set language
		$language = $page->getInheritedProperty('Language');
		$fallbackLanguage = Curry_Core::$config->curry->fallbackLanguage;
		if($language) {
			$this->setLanguage($language);
		} else if($fallbackLanguage) {
			trace_warning('Using fallback language');
			$this->setLanguage($fallbackLanguage);
		} else {
			trace_warning('Language not set for page');
		}
		
		// Attempt to render page
		try {
			$this->render($page->getPageRevision(), $request, $vars, $options);
		}
		catch(Curry_Exception_Unauthorized $e) {
			Curry_Core::log($e->getMessage(), Zend_Log::ERR);
			if(!headers_sent())
				header("HTTP/1.1 " . $e->getStatusCode() . " " . $e->getMessage());
			
			if(Curry_Core::$config->curry->errorPage->unauthorized) {
				Curry_Core::log('Showing unauthorized page', Zend_Log::NOTICE);
				$page = PageQuery::create()->findPk(Curry_Core::$config->curry->errorPage->unauthorized);
				if(!$page)
					throw new Exception('Unauthorized page not found');
					
				try {
					$vars = array('curry' => array('error' => array(
						'Message' => $e->getMessage(),
						'Trace' => $e->getTraceAsString()
					)));
					$options = array();
					$this->render($page->getPageRevision(), $request, $vars, $options);
				}
				catch(Exception $e2) {
					Curry_Core::log('An error occured while trying to generate the unauthorized page: ' . $e2->getMessage(), Zend_Log::ERR);
					throw $e;
				}
			} else {
				throw $e;
			}
		}
		catch(Curry_Exception_HttpError $e) {
			Curry_Core::log($e->getMessage(), Zend_Log::ERR);
			if(!headers_sent())
				header("HTTP/1.1 ".$e->getStatusCode()." ".$e->getMessage());
		}
		catch(Exception $e) {
			Curry_Core::log($e->getMessage(), Zend_Log::ERR);
			if(!headers_sent())
				header("HTTP/1.1 500 Internal server error");
			
			if(Curry_Core::$config->curry->errorNotification)
				Curry_Core::sendErrorNotification($e);
			
			if(Curry_Core::$config->curry->errorPage->error) {
				
				Curry_Core::log('Trying to show error page', Zend_Log::NOTICE);
				$page = PageQuery::create()->findPk(Curry_Core::$config->curry->errorPage->error);
				if(!$page)
					throw new Exception('Error page not found');
					
				try {
					$vars = array('curry' => array('error' => array(
						'Message' => $e->getMessage(),
						'Trace' => $e->getTraceAsString()
					)));
					$options = array();
					$this->render($page->getPageRevision(), $request, $vars, $options);
				}
				catch(Exception $e2) {
					Curry_Core::log('An error occured, additionally an error occured while trying to generate the error page: ' . $e2->getMessage(), Zend_Log::ERR);
					throw $e;
				}
			} else {
				throw $e;
			}
		}
	}
	
	/**
	 * Render the specified page revision.
	 *
	 * @param PageRevision $pageRevision
	 * @param Curry_Request $request
	 * @param array $vars
	 * @param array $options
	 */
	protected function render(PageRevision $pageRevision, Curry_Request $request, array $vars, array $options)
	{
		Curry_Core::log('Showing page ' . $pageRevision->getPage()->getName() . ' (PageRevisionId: '.$pageRevision->getPageRevisionId().')', Zend_Log::NOTICE);
		
		$time = microtime(true);
		$queries = Curry_Propel::getQueryCount();
		
		$cacheName = __CLASS__ . '_Page_' . md5($request->getUri());
		$cacheLifetime = $pageRevision->getPage()->getCacheLifetime();
		$doCache = $request->getMethod() === 'GET' && $cacheLifetime !== 0;
		
		if($doCache)
			ob_start();
			
		$generator = self::createPageGenerator($pageRevision, $request);
		$generator->display($vars, $options);
		
		if($doCache) {
			$cache = array(
				'page_id' => $pageRevision->getPageId(),
				'page_revision_id' => $pageRevision->getPageRevisionId(),
				'headers' => headers_list(),
				'content' => ob_get_flush(),
			);
			Curry_Core::$cache->save($cache, $cacheName, array(), $cacheLifetime < 0 ? false : $cacheLifetime);
		}
		
		if(Curry_Core::$config->curry->updateTranslationStrings)
			Curry_Language::updateLanguageStrings();
		
		$time = microtime(true) - $time;
		$queries = $queries !== null ? Curry_Propel::getQueryCount() - $queries : null;
		Curry_Core::triggerHook('Curry_Application::render', $pageRevision->getPageId(), $pageRevision->getPageRevisionId(), $time, $queries);
	}
	
	/**
	 * Create a PageGenerator instance for the specified PageRevision.
	 *
	 * @param PageRevision $pageRevision
	 * @param Curry_Request $request
	 * @return Curry_PageGenerator
	 */
	public static function createPageGenerator(PageRevision $pageRevision, Curry_Request $request)
	{
		$generatorClass = $pageRevision->getPage()->getInheritedProperty('Generator', Curry_Core::$config->curry->defaultGeneratorClass);
		return new $generatorClass($pageRevision, $request);
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
		$charset = $charset ? $charset : Curry_Core::$config->curry->outputEncoding;
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
		header("Content-Disposition: attachment; filename=".Curry_String::escapeQuotedString($filename));
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
			throw new Curry_Exception('Data is of unknown type.');
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
		header('Content-Disposition: attachment; filename='.Curry_String::escapeQuotedString($filename));
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
