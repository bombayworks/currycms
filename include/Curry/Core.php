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
 * Global helper function for logging messages or objects.
 *
 * @param mixed $value
 */
function trace($value)
{
	Curry_Core::logger()->debug($value);
}

/**
 * Global helper function for logging messages or objects, with level set to notice (aka info).
 *
 * @param mixed $value
 */
function trace_notice($value)
{
	Curry_Core::logger()->notice($value);
}

/**
 * Global helper function for logging messages or objects, with level set to warning.
 *
 * @param mixed $value
 */
function trace_warning($value)
{
	Curry_Core::logger()->warning($value);
}

/**
 * Global helper function for logging messages or objects, with level set to error.
 *
 * @param mixed $value
 */
function trace_error($value)
{
	Curry_Core::logger()->error($value);
}

/**
 * Global helper function for getting language variables. Alias for Curry_Language::get().
 *
 * @param string $variableName
 * @param string|Language|null $language
 * @return string|null
 */
function L($variableName, $language = null)
{
	return Curry_Language::get($variableName, $language);
}

/**
 * Global helper function for creating URLs.
 * 
 * This is a helper function for the Curry_URL-class. The first parameter
 * specifies the URL, if empty the current url will be used.
 * 
 * The second parameter is an array of query-string variables to be added
 * to the URL. You can specify key=>value pairs, or if you specify a value 'foo'
 * (ie numerical key) the corresponding $_GET['foo'] value will be used.
 *
 * @param string $url	URL path
 * @param array $vars	Additional query-string variables
 * @return Curry_URL
 */
function url($url = "", array $vars = array())
{
	$url = new Curry_URL($url);
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

/**
 * CurryCms main initialization and configuration class.
 * 
 * @package Curry
 */
class Curry_Core {
	/**
	 * The CurryCms version.
	 */
	const VERSION = '1.1.0-alpha1';
	
	/**
	 * Current migration version number. This is used to decide if project migration is needed.
	 */
	const MIGRATION_VERSION = 1;
	
	/**
	 * Custom logging level used to log tables to firebug.
	 */
	const LOG_TABLE = 8;
	
	/**
	 * String used to prefix tree-structures in select elements.
	 */
	const SELECT_TREE_PREFIX = "\xC2\xA0\xC2\xA0\xC2\xA0"; // utf-8 version of \xA0 or &nbsp;
	
	/**
	 * Global configuration object
	 * 
	 * @var \Zend\Config\Config
	 */
	public static $config;
	
	/**
	 *  Global cache object.
	 * 
	 * @var Zend_Cache_Core
	 */
	public static $cache;
	
	/**
	 * Convert php-errors to exceptions.
	 * 
	 * @var boolean
	 */
	public static $throwExceptionsOnError = true;
	
	/**
	 * Lucene search index.
	 * 
	 * @var Zend_Search_Lucene_Interface
	 */
	private static $index;
	
	/**
	 * Optional logger instance.
	 * 
	 * @var \Monolog\Logger
	 */
	private static $logger;

	/**
	 * Starting time when CurryCms was initialized.
	 * 
	 * @var float
	 */
	private static $startTime;
	
	/**
	 * Array of callbacks bound to hooks.
	 * 
	 * @var array|null
	 */
	private static $hooks;

	/**
	 * @var \Composer\Autoload\ClassLoader
	 */
	protected static $autoloader = null;
	
	/**
	 * Initializes CurryCms using the specified configuration.
	 * 
	 * @param string|array|null $config Path to configuration file, array with configuration options or null for no configuration.
	 * @param float|null $startTime Time when initialization was started (use microtime(true)), if not specified the current time will be used.
	 * @param bool|null $initAutoloader Attempt to initialize vendor/autoload.php
	 */
	public static function init($config, $startTime = null, $initAutoloader = null)
	{
		self::$startTime = $startTime === null ? microtime(true) : $startTime;
		
		if (get_magic_quotes_gpc())
			throw new Exception('magic quotes gpc is enabled, please disable!');

		// Initialize autoloader?
		if ($initAutoloader === null) {
			$initAutoloader = spl_autoload_functions();
			$initAutoloader = $initAutoloader === false || !count($initAutoloader);
		}
		if($initAutoloader) {
			$autoload = dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
			if(!file_exists($autoload))
				throw new Exception('curry/vendor/autoload.php not found, make sure composer dependencies have been installed.');
			self::$autoloader = require_once $autoload;
		}
		
		// load configuration
		self::$config = new \Zend\Config\Config(self::getConfig($config));
		
		// add project path to autoloader
		if($initAutoloader) {
			$projectInclude = Curry_Util::path(self::$config->curry->projectPath, 'include');
			self::$autoloader->add('', $projectInclude);
			set_include_path($projectInclude.PATH_SEPARATOR.get_include_path());
		}
		
		// trigger hook
		self::triggerHook('Curry_Core::preInit');
		
		// try to use utf-8 locale
		setlocale(LC_ALL, 'en_US.UTF-8', 'en_US.UTF8', 'UTF-8', 'UTF8');
		
		// umask
		if(self::$config->curry->umask)
			umask(self::$config->curry->umask);
		
		// init
		self::initErrorHandling();
		self::initLogging();
		self::initPropel();
		self::initCache();
		self::initEncoding();

		Curry_URL::setDefaultBaseUrl(self::$config->curry->baseUrl);
		Curry_URL::setDefaultSecret(self::$config->curry->secret);
		
		self::triggerHook('Curry_Core::postInit');
		register_shutdown_function(array(__CLASS__,'shutdown'));
	}

	/**
	 * Return composer autoloader instance.
	 *
	 * @return \Composer\Autoload\ClassLoader
	 */
	public static function getAutoloader()
	{
		if(self::$autoloader)
			return self::$autoloader;
		foreach(spl_autoload_functions() as $callback) {
			if (is_array($callback) && is_object($callback[0]) && $callback[0] instanceof \Composer\Autoload\ClassLoader) {
				self::$autoloader = $callback[0];
				return self::$autoloader;
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
	private static function getTempPath($projectPath)
	{
		$dir = Curry_Util::path($projectPath, 'data', 'temp');
		if(function_exists('sys_get_temp_dir')) { // prefer system temp dir if it exists
			$d = sys_get_temp_dir();
			if(is_writable($d))
				$dir = $d;
		}
		// TODO: do we need to create this dir?
		return $dir;
	}
	
	/**
	 * Get a configuration object with the default configuration-options.
	 *
	 * @return \Zend\Config\Config
	 */
	public static function getDefaultConfiguration()
	{
		return new \Zend\Config\Config(self::getConfig(self::$config->curry->configPath, false));
	}

	/**
	 * Open configuration for changes.
	 *
	 * @param string|null $file
	 * @return \Zend\Config\Config
	 */
	public static function openConfiguration($file = null)
	{
		if ($file === null) {
			$file = Curry_Core::$config->curry->configPath;
		}
		return new \Zend\Config\Config($file ? require($file) : array(), true);
	}

	/**
	 * Write configuration.
	 *
	 * @param \Zend\Config\Config $config
	 * @param string|null $file
	 */
	public static function writeConfiguration(\Zend\Config\Config $config, $file = null)
	{
		if ($file === null) {
			$file = Curry_Core::$config->curry->configPath;
		}
		$writer = new \Zend\Config\Writer\PhpArray();
		$writer->toFile($file, $config);
		if(extension_loaded('apc')) {
			if(function_exists('apc_delete_file'))
				@apc_delete_file(Curry_Core::$config->curry->configPath);
			else
				@apc_clear_cache();
		}
	}
	
	/**
	 * Load configuration.
	 *
	 * @param string|array|null $config
	 * @param bool $loadUserConfig
	 * @return array
	 */
	private static function getConfig($config, $loadUserConfig = true)
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
			'curry' => array(
				'name' => "untitled",
				'baseUrl' => '/',
				'adminEmail' => "info@example.com",
				'divertOutMailToAdmin' => false,
				
				'statistics' => false,
				'applicationClass' => 'Curry_Application',
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
				'tempPath' => self::getTempPath($config['curry']['projectPath']),
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
	 * Register a callback to be executed for a specific hook.
	 *
	 * @param string $name
	 * @param callback $callback
	 */
	public static function registerHook($name, $callback)
	{
		$hooks = require(Curry_Core::$config->curry->hooksPath);
		if(!array_key_exists($name, $hooks))
			$hooks[$name] = array();
		if(!in_array($callback, $hooks[$name], true))
			$hooks[$name][] = $callback;
		self::$hooks = $hooks;

		file_put_contents(Curry_Core::$config->curry->hooksPath, '<?php return '.var_export(self::$hooks));
	}
	
	/**
	 * Unregister a callback so that it is not executed for a specific hook.
	 *
	 * @param string $name
	 * @param callback $callback
	 */
	public static function unregisterHook($name, $callback)
	{
		$hooks = require(Curry_Core::$config->curry->hooksPath);
		if(array_key_exists($name, $hooks)) {
			foreach($hooks[$name] as $key => $hook)
				if($hook === $callback)
					unset($hooks[$name][$key]);
		}
		self::$hooks = $hooks;
		
		file_put_contents(Curry_Core::$config->curry->hooksPath, '<?php return '.var_export(self::$hooks));
	}
	
	/**
	 * Trigger all callbacks registered for a specific hook.
	 *
	 * @param string $name
	 * @param mixed $param,... Optional parameters passed when calling the callbacks.
	 */
	public static function triggerHook($name/*, ... */)
	{
		if(!self::$hooks) {
			if (file_exists(Curry_Core::$config->curry->hooksPath)) {
				self::$hooks = require(Curry_Core::$config->curry->hooksPath);
			} else {
				self::$hooks = array();
			}
		}
		
		$args = func_get_args();
		array_shift($args);
		if(array_key_exists($name, self::$hooks)) {
			foreach(self::$hooks[$name] as $hook) {
				call_user_func_array($hook, $args);
			}
		}
	}
	
	/**
	 * Initialize error-handling.
	 */
	private static function initErrorHandling()
	{
		$level = self::$config->curry->errorReporting;
		if ($level !== false)
			error_reporting($level);
		ini_set('display_errors', self::$config->curry->developmentMode);
		set_error_handler(array(__CLASS__, "errorHandler"));
		set_exception_handler(array(__CLASS__, "showException"));
	}
	
	/**
	 * Initialize logging.
	 */
	private static function initLogging()
	{
		$log = self::$config->curry->log;
		self::$logger = new \Monolog\Logger('currycms');
		switch ($log->method) {
			case 'firebug':
				ob_start();
				self::$logger->pushHandler(new \Monolog\Handler\FirePHPHandler());
				break;
				
			case 'file':
				self::$logger->pushHandler(new \Monolog\Handler\StreamHandler($log->file));
				break;
			
			case 'none':
			default:
				return;
		}
		
		Curry_Core::logger()->debug("Logging initialized");
	}
	
	/**
	 * Check if there is a logger instance.
	 *
	 * @return bool
	 */
	public static function hasLogger()
	{
		return self::$logger !== null;
	}

	/**
	 * Return logger instance.
	 *
	 * @return \Monolog\Logger
	 */
	public static function logger()
	{
		return self::$logger;
	}

	/**
	 * Create log message with the specified level.
	 *
	 * @param mixed $message String or object to be logged.
	 * @param int $level One of the log level constants.
	 */
	public static function log($message, $level = null)
	{
		if(self::$logger) {
			if($level === null)
				$level = \Monolog\Logger::DEBUG;
			try {
				self::$logger->log($level, $message);
			}
			catch(Exception $e) {

			}
		}
	}
	
	/**
	 * Initializes Propel.
	 */
	private static function initPropel()
	{
		if(!file_exists(self::$config->curry->propel->conf)) {
			Curry_Core::logger()->notice("Propel configuration missing, skipping propel initialization.");
			return;
		}

		// Use Composer autoloader instead of the built-in propel autoloader
		Propel::configure(self::$config->curry->propel->conf);
		$config = Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT);
		$classmap = array();
		$projectClassPath = self::$config->curry->propel->projectClassPath;
		foreach($config['classmap'] as $className => $file) {
			$classmap[$className] = $projectClassPath . DIRECTORY_SEPARATOR . $file;
		}

		$level = error_reporting(error_reporting() & ~E_USER_WARNING);
		Propel::initialize();
		PropelAutoloader::getInstance()->unregister();
		self::getAutoloader()->addClassMap($classmap);
		error_reporting($level);
		
		// Initialize debugging/logging
		if(self::$config->curry->propel->debug) {
			Propel::getConnection()->useDebug(true);
			if(self::$logger && self::$config->curry->propel->logging)
				Propel::setLogger(self::$logger);
		}
	}
	
	/**
	 * Initializes zend cache.
	 */
	private static function initCache()
	{
		$cache = self::$config->curry->cache;
		
		$uniqueId = substr(md5(self::$config->curry->name.
			':'.self::$config->curry->projectPath.
			':'.self::$config->curry->basePath), 0, 6);
		$frontendOptions = array(
			'automatic_serialization' => true,
			'cache_id_prefix' => $uniqueId,
		);
		$backend = "File";
		$backendOptions = array(
			'file_name_prefix' => $uniqueId,
		);
		
		if($cache->logging && self::$logger) {
			$frontendOptions['logging'] = $cache->logging;
			$frontendOptions['logger'] = self::$logger;
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
						'cache_dir' => self::$config->curry->tempPath,
						'file_name_prefix' => $uniqueId
					);
				}
				Curry_Core::logger()->info('Using '.$backend.' as caching backend');
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
				Curry_Core::logger()->info("Caching is not enabled");
		}
		
		self::$cache = Zend_Cache::factory('Core', $backend, $frontendOptions, $backendOptions);
	}
	
	/**
	 * Initializes encoding.
	 * 
	 * @todo: what to do with $_REQUEST ?
	 */
	private static function initEncoding()
	{
		$internal = self::$config->curry->internalEncoding;
		$output = self::$config->curry->outputEncoding;
		
		if(function_exists('mb_internal_encoding'))
			mb_internal_encoding($internal);
		
		// assume input is in the same encoding as our output by default
		$postEncoding = $output;
		$getEncoding = $output;
		
		if (isAjax()) {
			// ajax requests are always in utf-8
			$postEncoding = $getEncoding = 'utf-8';
		} else if(isset($_SERVER['CONTENT_TYPE'])) {
			// check if there is a charset in the content type header
			$charset = strstr($_SERVER['CONTENT_TYPE'], "charset=");
			if ($charset !== false) {
				$charset = strtolower(trim(substr($charset, 8)));
				if ($charset)
					$postEncoding = $charset;
			}
		}
		
		if($getEncoding != $internal)
			array_walk_recursive($_GET, create_function('&$value', 'if(is_string($value)) $value = iconv("'.$getEncoding.'", "'.$internal.'//TRANSLIT", $value);'));
		if($postEncoding != $internal)
			array_walk_recursive($_POST, create_function('&$value', 'if(is_string($value)) $value = iconv("'.$postEncoding.'", "'.$internal.'//TRANSLIT", $value);'));
	}
	
	/**
	 * Custom error handling function. Will convert regular php-errors to Exceptions.
	 * 
	 * @param int $type
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 */
	public static function errorHandler($type, $message, $file, $line)
	{
		if(self::$throwExceptionsOnError && ($type & error_reporting())) {
			throw new ErrorException($message, $type, 0, $file, $line);
		}
	}
	
	/**
	 * Get execution time since CurryCms was first initialized.
	 *
	 * @return float
	 */
	public static function getExecutionTime()
	{
		return microtime(true) - self::$startTime;
	}

	/**
	 * Print exception error.
	 *
	 * @param Exception $e
	 * @param bool|null $sendNotification
	 */
	public static function showException(Exception $e, $sendNotification = null)
	{
		// Set error headers and remove content in output buffer
		if (!headers_sent()) {
			@header("HTTP/1.0 500 Internal Server Error");
			@header("Status: 500 Internal Server Error");
		}
		@ob_end_clean();

		// Show error description
		$debug = self::$config && self::$config->curry->developmentMode;
		if($debug) {
			echo '<h1>'.get_class($e).'</h1>';
			echo '<p>'.htmlspecialchars(basename($e->getFile())).'('.$e->getLine().'): ';
			echo htmlspecialchars($e->getMessage()).'</p>';
			echo '<h2>Trace</h2>';
			echo '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
		} else {
			echo '<h1>Internal server error, please try again later.</h1>';
		}

		// Send error notification
		if ($sendNotification === null) {
			$sendNotification = self::$config && self::$config->curry->errorNotification;
		}
		if ($sendNotification) {
			self::sendErrorNotification($e);
		}
		exit;
	}

	/**
	 * Send error notification email.
	 *
	 * @param Exception $e
	 */
	public static function sendErrorNotification(Exception $e)
	{
		try {
			// Create form to recreate error
			$method = strtoupper($_SERVER['REQUEST_METHOD']);
			$hidden = Curry_Html::createHiddenFields($method == 'POST' ? $_POST : $_GET);
			$action = url(Curry_URL::getRequestUri())->getAbsolute();
			$form = '<form action="'.$action.'" method="'.$method.'">'.$hidden.'<button type="submit">Execute</button></form>';

			// Create mail
			$mail = new Curry_Mail();
			$mail->addTo(Curry_Core::$config->curry->adminEmail);
			$mail->setSubject('Error on '.Curry_Core::$config->curry->name);
			$mail->setBodyHtml('<html><body>'.
				'<h1>'.get_class($e).'</h1>'.
				'<h2>'.htmlspecialchars($e->getMessage()).'</h2>'.
				'<p><strong>Method:</strong> '.$method.'<br/>'.
				'<strong>URL:</strong> '.$action.'<br/>'.
				'<strong>File:</strong> '.htmlspecialchars($e->getFile()).'('.$e->getLine().')</p>'.
				'<h2>Recreate</h2>'.
				$form.
				'<h2>Trace</h2>'.
				'<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>'.
				'<h2>Variables</h2>'.
				'<h3>$_GET</h3>'.
				'<pre>'.htmlspecialchars(print_r($_GET, true)).'</pre>'.
				'<h3>$_POST</h3>'.
				'<pre>'.htmlspecialchars(print_r($_POST, true)).'</pre>'.
				'<h3>$_SERVER</h3>'.
				'<pre>'.htmlspecialchars(print_r($_SERVER, true)).'</pre>'.
				'</body></html>'
			);
			$mail->send();
			Curry_Core::logger()->info('Sent error notification');
		}
		catch(Exception $e) {
			Curry_Core::logger()->error('Failed to send error notification');
		}
	}
	
	/**
	 * Shutdown function to execute at the end of the request. This function
	 * is called automatically so there is no need to call it explicitly.
	 */
	public static function shutdown()
	{
		$error = error_get_last();
		if($error !== null && $error['type'] == E_ERROR) {
			$e = new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);
			self::showException($e);
		}
		if(self::$logger) {
			self::$throwExceptionsOnError = false;
			
			$queryCount = Curry_Propel::getQueryCount();
			$generationTime = self::getExecutionTime();
			Curry_Core::logger()->debug("Generation time: ".round($generationTime, 3)."s");
			Curry_Core::logger()->debug("Peak memory usage: ".Curry_Util::humanReadableBytes(memory_get_peak_usage()));
			Curry_Core::logger()->debug("SQL query count: ".($queryCount !== null ? $queryCount : 'n/a'));
		}
	}

	/**
	 * Open the lucene search index and return it.
	 *
	 * @param bool $forceCreation
	 * @return Zend_Search_Lucene_Interface
	 */
	public static function getSearchIndex($forceCreation = false)
	{
		if(!self::$index || $forceCreation) {
			if(self::$index) {
				self::$index->commit();
				self::$index = null;
			}
			
			Zend_Search_Lucene_Analysis_Analyzer::setDefault(
				new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
			
			Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding(self::$config->curry->internalEncoding);
		
			$path = Curry_Util::path(self::$config->curry->projectPath, 'data', 'searchindex');
			self::$index = $forceCreation ? Zend_Search_Lucene::create($path) : Zend_Search_Lucene::open($path);
		}
		return self::$index;
	}
	
	/**
	 * Check if a migration of the project is required.
	 *
	 * @return bool
	 */
	public static function requireMigration()
	{
		return self::$config->curry->migrationVersion < Curry_Core::MIGRATION_VERSION;
	}
}
