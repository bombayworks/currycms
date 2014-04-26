<?php

namespace Curry;

use Curry\Controller\Frontend;
use Symfony\Component\EventDispatcher\EventDispatcher;
use \Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use \Symfony\Component\HttpKernel\HttpKernelInterface;
use \Symfony\Component\HttpKernel\TerminableInterface;
use \Exception;
use \Curry_Util;
use \Curry_Array;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class App
 *
 * @property \Symfony\Component\HttpFoundation\Request $request
 *
 * @package Curry
 */
class App extends ServiceContainer implements HttpKernelInterface, TerminableInterface {
	/**
	 * @var \Curry\App;
	 */
	protected static $instance;

	/**
	 * Convert php-errors to exceptions.
	 *
	 * @var boolean
	 */
	public $throwExceptionsOnError = true;

	public static function create($config)
	{
		$config = self::getConfig($config);
		$applicationClass = $config['curry']['applicationClass'];
		$app = new $applicationClass($config);
		if (!self::$instance)
			self::$instance = $app;
		return $app;
	}

	public function __construct($config)
	{
		// Load config
		foreach($config as $k => $v)
			$this[$k] = $v;
		$this->config = new \Zend\Config\Config($config);
	}

	public static function getInstance()
	{
		if(!isset(self::$instance)) {
			throw new Exception('No application created.');
		}
		return self::$instance;
	}

	public function boot()
	{
		if (get_magic_quotes_gpc())
			throw new Exception('Magic quotes gpc is enabled, please disable!');

		// Create services
		$this->singleton('logger', array($this, 'getLogger'));
		$this->singleton('cache', array($this, 'getCache'));
		$this->singleton('index', array($this, 'getIndex'));
		$this->singleton('autoloader', array($this, 'getAutoloader'));

		// some more
		$app = $this;
		$this->singleton('dispatcher', function () use ($app) {
			$dispatcher = new EventDispatcher();



			/*
			$urlMatcher = new LazyUrlMatcher(function () use ($app) {
				return $app['url_matcher'];
			});

			$dispatcher->addSubscriber(new LocaleListener($app, $urlMatcher, $app['request_stack']));
			if (isset($app['exception_handler'])) {
				$dispatcher->addSubscriber($app['exception_handler']);
			}
			$dispatcher->addSubscriber(new ResponseListener($app['charset']));
			$dispatcher->addSubscriber(new MiddlewareListener($app));
			$dispatcher->addSubscriber(new ConverterListener($app['routes'], $app['callback_resolver']));
			$dispatcher->addSubscriber(new StringToResponseListener());
			*/

			return $dispatcher;
		});
		$this->singleton('requestContext', function () use ($app) {
			$context = new RequestContext();

			//$context->setHttpPort($app['request.http_port']);
			//$context->setHttpsPort($app['request.https_port']);

			return $context;
		});
		$this->singleton('routes', function () use ($app) {
			return new RouteCollection();
		});
		$this->singleton('urlMatcher', function () use ($app) {
			return new UrlMatcher($app->routes, $app->requestContext);
		});
		$this->singleton('resolver', function () use ($app) {
			return new ControllerResolver($app->logger);
		});
		$this->singleton('kernel', function () use ($app) {
			return new HttpKernel($app->dispatcher, $app->resolver, $app->requestStack);
		});
		$this->singleton('requestStack', function () use ($app) {
			if (class_exists('Symfony\Component\HttpFoundation\RequestStack')) {
				return new RequestStack();
			}
			return null;
		});

		// TODO: remove this!
		$this->globals = (object)array(
			'ProjectName' => $this->config->curry->name,
			'BaseUrl' => $this->config->curry->baseUrl,
			'DevelopmentMode' => $this->config->curry->developmentMode,
		);

		// Try to set utf-8 locale
		setlocale(LC_ALL, 'en_US.UTF-8', 'en_US.UTF8', 'UTF-8', 'UTF8');

		// umask
		if($this->config->curry->umask)
			umask($this->config->curry->umask);

		self::initErrorHandling();
		self::initPropel();

		\Curry_URL::setDefaultBaseUrl($this->config->curry->baseUrl);
		\Curry_URL::setDefaultSecret($this->config->curry->secret);

		register_shutdown_function(array($this, 'shutdown'));

		//$frontend = new Frontend();
		//$this->routes->add('curry', new Route('/start/', array('_controller' => array($frontend, 'index'))));
		$this->dispatcher->addSubscriber(new Frontend());
	}

	public function run(Request $request = null)
	{
		if (!$request)
			$request = Request::createFromGlobals();
		$this->boot();
		$response = $this->handle($request);
		$response->send();
		$this->terminate($request, $response);
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
	{
		$current = isset($this->request) ? $this->request : null;
		$this->request = $request;

		if ($this->routes->count())
			$this->dispatcher->addSubscriber(new RouterListener($this->urlMatcher, $this->requestContext, $this->logger, $this->requestStack));

		$response = $this->kernel->handle($request, $type, $catch);
		$this->request = $current;

		return $response;
	}

	//////////////////////////////////////

	/**
	 * Load configuration.
	 *
	 * @param string|array|null $config
	 * @param bool $loadUserConfig
	 * @return array
	 */
	protected static function getConfig($config, $loadUserConfig = true)
	{
		$userConfig = array();
		$configPath = null;
		$projectPath = null;

		if(is_string($config)) {
			// Load configuration from file
			$configPath = realpath($config);
			if(!$configPath)
				throw new Exception('Configuration file not found: '.$config);
			$projectPath = Curry_Util::path(true, dirname($configPath), '..');
			if($loadUserConfig)
				$userConfig = require($configPath);
		} else if(is_array($config)) {
			// Configuration provided by array
			if($loadUserConfig)
				$userConfig = $config;
		} else if($config === null || $config === false) {
			// Skip configuration
		} else {
			throw new Exception('Unknown configuration format.');
		}

		// Attempt to find project path
		if(!$projectPath)
			$projectPath = Curry_Util::path(true, getcwd(), '..', 'cms');
		if(!$projectPath)
			$projectPath = Curry_Util::path(true, getcwd(), 'cms');

		// default config
		$config = array(
			'startTime' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true),
			'curry' => array(
				'name' => "untitled",
				'baseUrl' => '/',
				'adminEmail' => "info@example.com",
				'divertOutMailToAdmin' => false,

				'statistics' => false,
				'applicationClass' => 'Curry\App',
				'defaultGeneratorClass' => 'Curry_PageGenerator_Html',
				'forceDomain' => false,
				'revisioning' => false,
				'umask' => 0002,
				'liveEdit' => true,
				'secret' => 'SECRET',
				'errorNotification' => false,
				'errorReporting' => E_ALL ^ E_NOTICE,

				'generator' => 'Curry_PageGenerator_Html',
				'internalEncoding' => 'utf-8',
				'outputEncoding' => 'utf-8',

				'basePath' => Curry_Util::path(true, dirname(__FILE__), '..', '..'),
				'projectPath' => $projectPath,
				'wwwPath' => getcwd(),
				'configPath' => $configPath,

				'cache' => array('method' => 'auto'),
				'mail' => array('method' => 'sendmail'),
				'log' => array('method'=>'none'),
				'maintenance' => array('enabled'=>false),

				'defaultEditor' => 'tinyMCE',
				'migrationVersion' => \Curry_Core::MIGRATION_VERSION,
				'pageCache' => true,
				'autoPublish' => false,
				'developmentMode' => false,
				'autoUpdateIndex' => false,

				'password' => array(
					'algorithm' => PASSWORD_BCRYPT,
					'options' => array(
						'cost' => 10, //value between 4 to 31
					),
				),

				'debug' => array(
					'moduleTimeLimit' => 0.5,
					'moduleCpuLimit' => 0.25,
					'moduleMemoryLimit' => 5*1024*1024,
					'moduleSqlLimit' => 8,
				),
			),
		);

		if($loadUserConfig)
			Curry_Array::extend($config, $userConfig);

		// Fix base url
		$config['curry']['baseUrl'] = url($config['curry']['baseUrl'])->getAbsolute();

		if (!$config['curry']['projectPath'])
			throw new Exception('Project path could not be found, please use a configuration file to specify the path');

		$secondaryConfig = array(
			'curry' => array(
				'vendorPath' => Curry_Util::path($config['curry']['basePath'], 'vendor'),
				'tempPath' => self::getTempDir($config['curry']['projectPath']),
				'trashPath' => Curry_Util::path($config['curry']['projectPath'], 'data', 'trash'),
				'hooksPath' => Curry_Util::path($config['curry']['projectPath'], 'config', 'hooks.php'),
				'autoBackup' => $config['curry']['developmentMode'] ? 0 : 86400,
				'propel' => array(
					'conf' => Curry_Util::path($config['curry']['projectPath'], 'config', 'propel.php'),
					'projectClassPath' => Curry_Util::path($config['curry']['projectPath'], 'propel', 'build', 'classes'),
				),
				'template' => array(
					'root' => Curry_Util::path($config['curry']['projectPath'], 'templates'),
					'options' => array(
						'debug' => (bool)$config['curry']['developmentMode'],
						'cache' => Curry_Util::path($config['curry']['projectPath'], 'data', 'cache', 'templates'),
						'base_template_class' => 'Curry_Twig_Template',
					),
				),
				'backend' => array(
					'placeholderExclude' => array(),
					'theme' => 'vindaloo',
					'loginCookieExpire' => 31536000,
					'loginTokenExpire' => 31536000,
				),
				'mail' => array(
					'options' => array(),
				),
				'domainMapping' => array(
					'enabled' => false,
					'domains' => array(),
				),
			),
		);
		$config = Curry_Array::extend($secondaryConfig, $config);
		return $config;
	}

	/**
	 * Initialize error-handling.
	 */
	protected function initErrorHandling()
	{
		$level = $this->config->curry->errorReporting;
		if ($level !== false)
			error_reporting($level);
		ini_set('display_errors', $this->config->curry->developmentMode);
		set_error_handler(array($this, "errorHandler"));
		//set_exception_handler(array(__CLASS__, "showException"));
	}

	/**
	 * Initializes Propel.
	 */
	protected function initPropel()
	{
		if(!file_exists($this->config->curry->propel->conf)) {
			$this->logger->notice("Propel configuration missing, skipping propel initialization.");
			return;
		}

		// Use Composer autoloader instead of the built-in propel autoloader
		\Propel::configure($this->config->curry->propel->conf);
		$config = \Propel::getConfiguration(\PropelConfiguration::TYPE_OBJECT);
		$classmap = array();
		$projectClassPath = $this->config->curry->propel->projectClassPath;
		foreach($config['classmap'] as $className => $file) {
			$classmap[$className] = $projectClassPath . DIRECTORY_SEPARATOR . $file;
		}

		$level = error_reporting(error_reporting() & ~E_USER_WARNING);
		\Propel::initialize();
		\PropelAutoloader::getInstance()->unregister();
		$this->autoloader->addClassMap($classmap);
		error_reporting($level);

		// Initialize debugging/logging
		if($this->config->curry->propel->debug) {
			\Propel::getConnection()->useDebug(true);
			if($this->config->curry->propel->logging)
				\Propel::setLogger($this->logger);
		}
	}

	/**
	 * Initialize logging.
	 */
	protected function getLogger()
	{
		$log = $this->config->curry->log;
		$logger = new \Monolog\Logger('currycms');
		switch ($log->method) {
			case 'firebug':
				ob_start();
				$logger->pushHandler(new \Monolog\Handler\FirePHPHandler());
				break;

			case 'file':
				$logger->pushHandler(new \Monolog\Handler\StreamHandler($log->file));
				break;

			case 'none':
			default:
				return new \Curry\NullProxy();
		}
		
		$logger->debug("Logging initialized");
		return $logger;
	}

	/**
	 * Initializes zend cache.
	 */
	protected function getCache()
	{
		$cache = $this->config->curry->cache;

		$uniqueId = substr(md5($this->config->curry->name.
			':'.$this->config->curry->projectPath.
			':'.$this->config->curry->basePath), 0, 6);
		$frontendOptions = array(
			'automatic_serialization' => true,
			'cache_id_prefix' => $uniqueId,
		);
		$backend = "File";
		$backendOptions = array(
			'file_name_prefix' => $uniqueId,
		);

		if($cache->logging) {
			$frontendOptions['logging'] = $cache->logging;
			$frontendOptions['logger'] = $this->logger;
		}

		switch ($cache->method) {
			case 'auto':
				if (extension_loaded('memcache')) {
					$backend = 'Memcached';
					$backendOptions = array();
				}
				else if(extension_loaded('apc')) {
					$backend = 'Apc';
					$backendOptions = array();
				}
				else if (extension_loaded('xcache')) {
					$backend = 'Xcache';
					$backendOptions = array();
				}
				else {
					$backend = 'File';
					$backendOptions = array(
						'cache_dir' => $this->config->curry->tempPath,
						'file_name_prefix' => $uniqueId
					);
				}
				$this->logger->info('Using '.$backend.' as caching backend');
				break;

			case 'file':
				if($cache->options)
					$backendOptions = $cache->options->toArray();
				break;

			case 'memcached':
				$backend = 'Memcached';
				if($cache->options)
					$backendOptions = $cache->options->toArray();
				break;

			case 'apc':
				$backend = 'Apc';
				if($cache->options)
					$backendOptions = $cache->options->toArray();
				break;

			case 'none':
			default:
				$backend = 'Black Hole';
				$frontendOptions['caching'] = false;
				$this->logger->info("Caching is not enabled");
		}

		return \Zend_Cache::factory('Core', $backend, $frontendOptions, $backendOptions, false, false, true);
	}

	/**
	 * Open the lucene search index and return it.
	 *
	 * @return Zend_Search_Lucene_Interface
	 */
	protected function getIndex()
	{
		\Zend_Search_Lucene_Analysis_Analyzer::setDefault(
			new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());

		\Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding($this->config->curry->internalEncoding);

		$path = Curry_Util::path($this->config->curry->projectPath, 'data', 'searchindex');
		return \Zend_Search_Lucene::open($path);
	}

	/**
	 * Return composer autoloader instance.
	 *
	 * @return \Composer\Autoload\ClassLoader
	 */
	protected function getAutoloader()
	{
		foreach(spl_autoload_functions() as $callback) {
			if (is_array($callback) && is_object($callback[0]) && $callback[0] instanceof \Composer\Autoload\ClassLoader) {
				return $callback[0];
			}
		}
		throw new Exception('Autoloader not found.');
	}

	/**
	 * Get temporary path, uses sys_get_temp_dir() and makes sure the path is writable,
	 * if not it uses a fallback in the project directory.
	 *
	 * @param string $projectPath
	 * @return string
	 */
	protected static function getTempDir($projectPath)
	{
		$dir = Curry_Util::path($projectPath, 'data', 'temp');
		if(function_exists('sys_get_temp_dir')) { // prefer system temp dir if it exists
			$d = sys_get_temp_dir();
			if(is_writable($d))
				$dir = $d;
		}
		return $dir;
	}

	/**
	 * Custom error handling function. Will convert regular php-errors to Exceptions.
	 *
	 * @param int $type
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 */
	public function errorHandler($type, $message, $file, $line)
	{
		if($this->throwExceptionsOnError && ($type & error_reporting())) {
			throw new \ErrorException($message, 0, $type, $file, $line);
		}
	}

	/**
	 * Shutdown function to execute at the end of the request. This function
	 * is called automatically so there is no need to call it explicitly.
	 */
	public function shutdown()
	{
		$this->throwExceptionsOnError = false;

		$error = error_get_last();
		if($error !== null && $error['type'] == E_ERROR) {
			$e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
			//self::showException($e);
		}

		if ($this['debug']) {
			$queryCount = Curry_Propel::getQueryCount();
			$generationTime = self::getExecutionTime();
			$this->logger->debug("Generation time: ".round($generationTime, 3)."s");
			$this->logger->debug("Peak memory usage: ".Curry_Util::humanReadableBytes(memory_get_peak_usage()));
			$this->logger->debug("SQL query count: ".($queryCount !== null ? $queryCount : 'n/a'));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function terminate(Request $request, Response $response)
	{
		$this->kernel->terminate($request, $response);
	}
}
