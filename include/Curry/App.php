<?php
namespace {
	use Curry\URL;

	/**
	 * Global helper function for getting language variables. Alias for Curry_Language::get().
	 *
	 * @param string $variableName
	 * @param string|Language|null $language
	 * @return string|null
	 */
	function L($variableName, $language = null)
	{
		return \Curry_Language::get($variableName, $language);
	}

	/**
	 * Global helper function for creating URLs.
	 *
	 * This is a helper function for the Curry\URL-class. The first parameter
	 * specifies the URL, if empty the current url will be used.
	 *
	 * The second parameter is an array of query-string variables to be added
	 * to the URL. You can specify key=>value pairs, or if you specify a value 'foo'
	 * (ie numerical key) the corresponding $_GET['foo'] value will be used.
	 *
	 * @param string $url	URL path
	 * @param array $vars	Additional query-string variables
	 * @return URL
	 */
	function url($url = "", array $vars = array())
	{
		$url = new \Curry\URL($url);
		$url->add($vars);
		return $url;
	}

	/**
	 * Check if this request is a post request.
	 *
	 * @param string|null $variable If set, also require this variable to be set.
	 * @return bool
	 */
	function isPost($variable = null)
	{
		return ($_SERVER['REQUEST_METHOD'] == 'POST') && ($variable === null || isset($_POST[$variable]));
	}

	/**
	 * Check if this request is an ajax (XmlHttpRequest) request.
	 *
	 * @return bool
	 */
	function isAjax()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
	}
}

namespace Curry {
	use Composer\Autoload\ClassLoader;
	use Curry\Controller\Backend;
	use Curry\Controller\FileNotFound;
	use Curry\Controller\Frontend;
	use Curry\Controller\StaticContent;
	use Curry\Util\Html;
	use Curry\Util\PathHelper;
	use Monolog\Handler\BufferHandler;
	use Monolog\Handler\FingersCrossedHandler;
	use Monolog\Handler\NullHandler;
	use Monolog\Logger;
	use Monolog\Processor\IntrospectionProcessor;
	use Symfony\Component\EventDispatcher\EventDispatcher;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\RequestStack;
	use Symfony\Component\HttpFoundation\Response;
	use Symfony\Component\HttpKernel\Controller\ControllerResolver;
	use Symfony\Component\HttpKernel\HttpKernel;
	use Symfony\Component\HttpKernel\HttpKernelInterface;
	use Symfony\Component\HttpKernel\TerminableInterface;
	use Exception;
	use Curry\Util\Helper;
	use Curry\Util\ArrayHelper;
	use Whoops\Exception\Inspector;
	use Whoops\Handler\CallbackHandler;
	use Whoops\Handler\Handler;
	use Whoops\Handler\PlainTextHandler;
	use Whoops\Handler\PrettyPageHandler;
	use Whoops\Run;
	use Zend\Config\Config;

	/**
	 * Class App
	 *
	 * @property \Symfony\Component\HttpFoundation\Request $request
	 * @property Logger $logger
	 * @property \Page $page
	 * @property \PageRevision $pageRevision
	 * @property \Curry\Generator\AbstractGenerator $generator
	 * @property EventDispatcher $dispatcher
	 *
	 * @package Curry
	 */
	class App extends ServiceContainer implements HttpKernelInterface, TerminableInterface {
		/**
		 * The CurryCms version.
		 */
		const VERSION = '2.0.0-dev';

		/**
		 * Current migration version number. This is used to decide if project migration is needed.
		 */
		const MIGRATION_VERSION = 1;

		/**
		 * @var App;
		 */
		protected static $instance;

		public static function create($config) {
			$config = self::getConfig($config);
			$applicationClass = $config['applicationClass'];
			$app = new $applicationClass($config);
			if (!self::$instance) {
				self::$instance = $app;
			}
			return $app;
		}

		public static function getInstance() {
			if (!isset(self::$instance)) {
				throw new Exception('No application created.');
			}
			return self::$instance;
		}

		/**
		 * Get execution time since CurryCms was first initialized.
		 *
		 * @return float
		 */
		public function getExecutionTime() {
			return microtime(true) - $this['startTime'];
		}

		public function boot() {
			// Create services
			$this->singleton('logger', array($this, 'getLogger'));
			$this->singleton('cache', array($this, 'getCache'));
			$this->singleton('index', array($this, 'getIndex'));
			$this->singleton('autoloader', array($this, 'getAutoloader'));

			// some more
			$app = $this;
			$this->singleton('dispatcher', function () use ($app) {
					return new EventDispatcher();
				});
			$this->singleton('resolver', function () use ($app) {
					return new ControllerResolver($app->logger);
				});
			$this->singleton('kernel', function () use ($app) {
					return new HttpKernel($app->dispatcher, $app->resolver, $app->requestStack);
				});
			$this->singleton('requestStack', function () use ($app) {
					return new RequestStack();
				});
			$this->singleton('backend', function () use ($app) {
					return new Backend($app);
				});
			$this->singleton('whoopsHandler', function() use ($app) {
					if (PHP_SAPI === 'cli') {
						return new PlainTextHandler();
					} else if ($this['developmentMode']) {
						return new PrettyPageHandler;
					} else {
						return new CallbackHandler(array($app, 'showException'));
					}
				});
			$this->singleton('whoops', function() use ($app) {
					$whoops = new \Whoops\Run;
					$whoops->pushHandler($app->whoopsHandler);
					// Send error mail
					if ($app['errorNotification']) {
						$whoops->pushHandler(array($app, 'sendErrorNotification'));
					}
					// Add error to log
					$whoops->pushHandler(function(\Exception $e) use ($app) {
						$app->logger->error($e->getMessage(), array('exception' => $e));
						return Handler::DONE;
					});
					return $whoops;
				});
			$this->whoops->register();

			if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
				$this->logger->warning('Magic quotes gpc is enabled, please disable!');
			}

			// TODO: remove this!
			$this->globals = (object) array(
				'ProjectName' => $this['name'],
				'BaseUrl' => $this['baseUrl'],
				'DevelopmentMode' => $this['developmentMode'],
			);

			// Try to set utf-8 locale
			$arguments = (array)$this['locale'];
			array_unshift($arguments, LC_ALL);
			$locale = call_user_func_array('setlocale', $arguments);
			$this->logger->debug($locale ? 'Set default locale to '.$locale : 'Unable to set default locale');

			// Set default umask
			if ($this['umask'] !== false) {
				umask($this['umask']);
			}

			self::initErrorHandling();
			self::initPropel();

			URL::setDefaultBaseUrl($this['baseUrl']);
			URL::setDefaultSecret($this['secret']);

			if ($this['autoPublish']) {
				$this->autoPublish();
			}

			if ($this['sharedController']) {
				$this->logger->notice('Using php routing for curry shared folder');
				$this->dispatcher->addSubscriber(new StaticContent('/shared/', $app['basePath'].'/shared'));
			}

			if ($app['backend.basePath'])
				$this->dispatcher->addSubscriber($app->backend);
			if (class_exists('Page'))
				$this->dispatcher->addSubscriber(new Frontend($this));
			$this->dispatcher->addSubscriber(new FileNotFound($this));

			$this->dispatcher->addSubscriber(new Generator\ModuleProfiler($app->logger));
			$this->dispatcher->addSubscriber(new Generator\ModuleCacher($app->cache));
			$this->dispatcher->addSubscriber(new Generator\ModuleHtmlHead());
			$this->dispatcher->addSubscriber(new Generator\LiveEdit($this));
		}

		public function run(Request $request = null) {
			if (!$request) {
				$request = Request::createFromGlobals();
			}
			$this->boot();
			$response = $this->handle($request);
			$response->prepare($request);
			$response->send();
			$this->terminate($request, $response);
		}

		/**
		 * {@inheritdoc}
		 */
		public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true) {
			$startTime = microtime(true);
			$this->logger->debug(($type === HttpKernelInterface::MASTER_REQUEST ? 'Master-Request: ' : 'Sub-Request').$request->getMethod().' '.$request->getRequestUri());

			$previous = isset($this->request) ? $this->request : null;
			$this->request = $request;
			$response = $this->kernel->handle($request, $type, $catch);
			$this->request = $previous;

			if ($this['developmentMode']) {
				$queryCount = Util\Propel::getQueryCount();
				$this->logger->debug("Response completed", array(
					'time' => microtime(true) - $startTime,
					'mem' => Helper::humanReadableBytes(memory_get_peak_usage()),
					'sql' => $queryCount !== null ? $queryCount : 'n/a',
				));
			}

			return $response;
		}

		/**
		 * Get a configuration object with the default configuration-options.
		 *
		 * @return Config
		 */
		public function getDefaultConfiguration() {
			return new Config(self::getConfig($this['configPath'], false));
		}

		/**
		 * Open configuration for changes.
		 *
		 * @param string|null $file
		 * @return Config
		 */
		public function openConfiguration($file = null) {
			if ($file === null) {
				$file = $this['configPath'];
			}
			return new Config($file ? require($file) : array(), true);
		}

		/**
		 * Write configuration.
		 *
		 * @param Config $config
		 * @param string|null $file
		 */
		public function writeConfiguration(Config $config, $file = null) {
			if ($file === null) {
				$file = $this['configPath'];
			}
			$writer = new \Zend\Config\Writer\PhpArray();
			$writer->toFile($file, $config);

			// clear apc cache entry
			if (function_exists('apc_delete_file')) {
				@apc_delete_file($file);
			} else if (function_exists('apc_clear_cache')) {
				@apc_clear_cache();
			}
		}

		//////////////////////////////////////

		/**
		 * Load configuration.
		 *
		 * @param string|array|null $config
		 * @param bool $loadUserConfig
		 * @return array
		 */
		protected static function getConfig($config, $loadUserConfig = true) {
			$userConfig = array();
			$configPath = null;
			$projectPath = null;

			if (is_string($config)) {
				// Load configuration from file
				$configPath = realpath($config);
				if (!$configPath) {
					throw new Exception('Configuration file not found: ' . $config);
				}
				$projectPath = PathHelper::path(true, dirname($configPath), '..');
				if ($loadUserConfig) {
					$userConfig = require($configPath);
				}
			} else if (is_array($config)) {
				// Configuration provided by array
				if ($loadUserConfig) {
					$userConfig = $config;
				}
			} else if ($config === null || $config === false) {
				// Skip configuration
			} else {
				throw new Exception('Unknown configuration format.');
			}

			// Attempt to find project path
			if (!$projectPath) {
				$projectPath = PathHelper::path(true, getcwd(), '..', 'cms');
			}
			if (!$projectPath) {
				$projectPath = PathHelper::path(true, getcwd(), 'cms');
			}

			// default config
			$config = array(
				'startTime' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true),
				'name' => "untitled",
				'baseUrl' => '/',
				'adminEmail' => "info@example.com",
				'divertOutMailToAdmin' => false,
				'statistics' => false,
				'applicationClass' => 'Curry\App',
				'defaultGeneratorClass' => 'Curry\Generator\HtmlGenerator',
				'forceDomain' => false,
				'revisioning' => false,
				'umask' => 0002,
				'locale' => array('en_US.UTF-8', 'en_US.UTF8', 'UTF-8', 'UTF8'),
				'liveEdit' => true,
				'secret' => 'SECRET',
				'errorNotification' => false,
				'basePath' => PathHelper::path(true, dirname(__FILE__), '..', '..'),
				'projectPath' => $projectPath,
				'wwwPath' => getcwd(),
				'configPath' => $configPath,
				'cache' => array('method' => 'auto'),
				'mail' => array('method' => 'sendmail'),
				'log' => array(),
				'maintenance' => array('enabled' => false),
				'defaultEditor' => 'tinyMCE',
				'migrationVersion' => self::MIGRATION_VERSION,
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
			);

			if ($loadUserConfig) {
				ArrayHelper::extend($config, $userConfig);
			}

			// Fix base url
			$config['baseUrl'] = url($config['baseUrl'])->getAbsolute();

			if (!$config['projectPath']) {
				throw new Exception('Project path could not be found, please use a configuration file to specify the path');
			}

			$secondaryConfig = array(
				'vendorPath' => PathHelper::path($config['basePath'], 'vendor'),
				'tempPath' => self::getTempDir($config['projectPath']),
				'trashPath' => PathHelper::path($config['projectPath'], 'data', 'trash'),
				'autoBackup' => $config['developmentMode'] ? 0 : 86400,
				'errorReporting' => $config['developmentMode'] ? -1 : false,
				'propel' => array(
					'conf' => PathHelper::path($config['projectPath'], 'config', 'propel.php'),
					'projectClassPath' => PathHelper::path($config['projectPath'], 'propel', 'build', 'classes'),
				),
				'template' => array(
					'root' => PathHelper::path($config['projectPath'], 'templates'),
					'options' => array(
						'debug' => (bool) $config['developmentMode'],
						'cache' => PathHelper::path($config['projectPath'], 'data', 'cache', 'templates'),
						'base_template_class' => 'Curry_Twig_Template',
					),
				),
				'backend' => array(
					'basePath' => 'admin/',
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
				'sharedController' => !file_exists($config['wwwPath'].'/shared')
			);
			$config = ArrayHelper::extend($secondaryConfig, $config);
			return $config;
		}

		public function sendMail(Mail $mail)
		{
			static $initialized = false;
			if(!$initialized) {
				$this->initMail();
				$initialized = true;
			}

			if ($this['divertOutMailToAdmin']) {
				$subject = '(dev) ' . $mail->getSubject();
				$mail->clearSubject();
				$mail->setSubject($subject);
				$mail->clearRecipients();
				$mail->addTo($this['adminEmail']);
			}
		}

		protected function initMail()
		{
			if (isset($this['mail.from.email']))
				\Zend_Mail::setDefaultFrom($this['mail.from.email'], $this['mail.from.name']);
			if (isset($this['mail.replyto.email']))
				\Zend_Mail::setDefaultReplyTo($this['mail.replyto.email'], $this['mail.replyto.name']);
			// Create transport
			switch(strtolower($this['mail.method'])) {
				case 'smtp':
					$transport = new \Zend_Mail_Transport_Smtp($this['mail.host'], (array)$this['mail.options']);
					\Zend_Mail::setDefaultTransport($transport);
					break;
				case 'sendmail':
				default:
					$transport = new \Zend_Mail_Transport_Sendmail((array)$this['mail.options']);
					\Zend_Mail::setDefaultTransport($transport);
					break;
			}
		}

		/**
		 * Initialize error-handling.
		 */
		protected function initErrorHandling() {
			$level = $this['errorReporting'];
			if ($level !== false) {
				error_reporting($level);
			}
			ini_set('display_errors', $this['developmentMode']);
		}

		/**
		 * Initializes Propel.
		 */
		protected function initPropel() {
			if (!file_exists($this['propel.conf'])) {
				$this->logger->notice("Propel configuration missing, skipping propel initialization.");
				return;
			}

			// Use Composer autoloader instead of the built-in propel autoloader
			\Propel::configure($this['propel.conf']);
			$config = \Propel::getConfiguration(\PropelConfiguration::TYPE_OBJECT);
			$classmap = array();
			$projectClassPath = $this['propel.projectClassPath'];
			foreach ($config['classmap'] as $className => $file) {
				$classmap[$className] = $projectClassPath . DIRECTORY_SEPARATOR . $file;
			}

			$level = error_reporting(error_reporting() & ~E_USER_WARNING);
			\Propel::initialize();
			\PropelAutoloader::getInstance()->unregister();
			$this->autoloader->addClassMap($classmap);
			error_reporting($level);

			// Initialize debugging/logging
			if ($this['propel.debug']) {
				\Propel::getConnection()->useDebug(true);
				if ($this['propel.logging']) {
					\Propel::setLogger($this->logger);
				}
			}
		}

		/**
		 * Initialize logging.
		 */
		protected function getLogger() {
			$logger = new Logger('currycms');

			foreach ($this['log'] as $log) {
				if (isset($log['enabled']) && !$log['enabled'])
					continue;
				$clazz = new \ReflectionClass($log['type']);
				$arguments = isset($log['arguments']) ? $log['arguments'] : array();
				$handler = $clazz->newInstanceArgs($arguments);
				if (isset($log['buffer']) && $log['buffer']) {
					$handler = new BufferHandler($handler);
				}
				if (isset($log['fingersCrossed']) && $log['fingersCrossed']) {
					$handler = new FingersCrossedHandler($handler);
				}
				$logger->pushHandler($handler);
			}

			if (!count($logger->getHandlers())) {
				$logger->pushHandler(new NullHandler());
			}

			if ($this['developmentMode'])
				$logger->pushProcessor(new IntrospectionProcessor(Logger::WARNING));

			return $logger;
		}

		/**
		 * Initializes zend cache.
		 */
		protected function getCache() {
			$uniqueId = substr(
				md5(
					$this['name'] .
					':' . $this['projectPath'] .
					':' . $this['basePath']
				), 0, 6
			);
			$frontendOptions = array(
				'automatic_serialization' => true,
				'cache_id_prefix' => $uniqueId,
			);
			$backend = "File";
			$backendOptions = array(
				'file_name_prefix' => $uniqueId,
			);

			if ($this['cache.logging']) {
				$frontendOptions['logging'] = $this['cache.logging'];
				$frontendOptions['logger'] = $this->logger;
			}

			switch ($this['cache.method']) {
				case 'auto':
					if (extension_loaded('memcache')) {
						$backend = 'Memcached';
						$backendOptions = array();
					} else if (extension_loaded('apc')) {
						$backend = 'Apc';
						$backendOptions = array();
					} else if (extension_loaded('xcache')) {
						$backend = 'Xcache';
						$backendOptions = array();
					} else {
						$backend = 'File';
						$backendOptions = array(
							'cache_dir' => $this['tempPath'],
							'file_name_prefix' => $uniqueId
						);
					}
					$this->logger->info('Using ' . $backend . ' as caching backend');
					break;

				case 'file':
					$backendOptions = $this['cache.options'];
					break;

				case 'memcached':
					$backend = 'Memcached';
					$backendOptions = $this['cache.options'];
					break;

				case 'apc':
					$backend = 'Apc';
					$backendOptions = $this['cache.options'];
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
		 * @return \Zend_Search_Lucene_Interface
		 */
		protected function getIndex() {
			\Zend_Search_Lucene_Analysis_Analyzer::setDefault(
				new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive()
			);

			\Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('utf-8');

			$path = PathHelper::path($this['projectPath'], 'data', 'searchindex');
			return \Zend_Search_Lucene::open($path);
		}

		/**
		 * Return composer autoloader instance.
		 *
		 * @return ClassLoader
		 */
		protected function getAutoloader() {
			foreach (spl_autoload_functions() as $callback) {
				if (is_array($callback) && is_object($callback[0]) && $callback[0] instanceof ClassLoader) {
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
		protected static function getTempDir($projectPath) {
			$dir = PathHelper::path($projectPath, 'data', 'temp');
			if (function_exists('sys_get_temp_dir')) { // prefer system temp dir if it exists
				$d = sys_get_temp_dir();
				if (is_writable($d)) {
					$dir = $d;
				}
			}
			return $dir;
		}

		/**
		 * Prints uncatched exceptions.
		 */
		public function showException(\Exception $e, Inspector $inspector, Run $run)
		{
			echo '<h1>Sorry, something went wrong</h1>'.
				'<p>Please try again later, or contact the administrator.</p>';
			return Handler::QUIT;
		}

		/**
		 * Send error notification email.
		 *
		 * @param Exception $e
		 */
		public function sendErrorNotification(Exception $e, Inspector $inspector, Run $run) {
			try {
				// Create form to recreate error
				$method = strtoupper($_SERVER['REQUEST_METHOD']);
				$hidden = Html::createHiddenFields($method == 'POST' ? $_POST : $_GET);
				$action = url(URL::getRequestUri())->getAbsolute();
				$form = '<form action="' . $action . '" method="' . $method . '">' . $hidden . '<button type="submit">Execute</button></form>';
				// Compose mail
				$content = '<html><body>' .
					'<h1>' . get_class($e) . '</h1>' .
					'<h2>' . htmlspecialchars($e->getMessage()) . '</h2>' .
					'<p><strong>Method:</strong> ' . $method . '<br/>' .
					'<strong>URL:</strong> ' . $action . '<br/>' .
					'<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '(' . $e->getLine() . ')</p>' .
					'<h2>Recreate</h2>' .
					$form .
					'<h2>Trace</h2>' .
					'<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>' .
					'<h2>Variables</h2>' .
					'<h3>$_GET</h3>' .
					'<pre>' . htmlspecialchars(print_r($_GET, true)) . '</pre>' .
					'<h3>$_POST</h3>' .
					'<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>' .
					'<h3>$_SERVER</h3>' .
					'<pre>' . htmlspecialchars(print_r($_SERVER, true)) . '</pre>' .
					'</body></html>';
				// Create and send mail
				$mail = new Mail();
				$mail->addTo($this['adminEmail']);
				$mail->setSubject('Error on ' . $this['name']);
				$mail->setBodyHtml($content);
				$mail->send();
				$this->logger->info('Sent error notification');
			} catch (Exception $e) {
				$this->logger->error('Failed to send error notification');
			}
			return Handler::DONE;
		}

		/**
		 * {@inheritdoc}
		 */
		public function terminate(Request $request, Response $response) {
			$this->kernel->terminate($request, $response);
		}

		/**
		 * Check if a migration of the project is required.
		 *
		 * @return bool
		 */
		public function requireMigration() {
			return $this['migrationVersion'] < self::MIGRATION_VERSION;
		}

		/**
		 * Do automatic publishing of pages.
		 * @todo rewrite this so it stores the time of next publish in the cache instead of using ttl
		 */
		public function autoPublish()
		{
			$cacheName = strtr(__CLASS__, '\\', '_') . '_' . 'AutoPublish';
			if(($nextPublish = $this->cache->load($cacheName)) === false) {
				$this->logger->notice('Doing auto-publish');
				/** @var \PropelObjectCollection|\PageRevision[] $revisions */
				$revisions = \PageRevisionQuery::create()
					->filterByPublishDate(time(), \Criteria::LESS_EQUAL)
					->orderByPublishDate()
					->find();
				$nextPublish = time() + 86400;
				foreach($revisions as $revision) {
					if($revision->getPublishDate('U') <= time()) {
						// publish revision
						$page = $revision->getPage();
						$this->logger->notice('Publishing page: ' . $page->getUrl());
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
				$this->logger->info('Next publish is in '.($nextPublish - time()) . ' seconds.');
				$this->cache->save(true, $cacheName, array(), $nextPublish - time());
			}
		}
	}
}