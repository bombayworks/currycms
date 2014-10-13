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
	\Curry\App::getInstance()->logger->debug($value);
}

/**
 * Global helper function for logging messages or objects, with level set to notice (aka info).
 *
 * @param mixed $value
 */
function trace_notice($value)
{
	\Curry\App::getInstance()->logger->notice($value);
}

/**
 * Global helper function for logging messages or objects, with level set to warning.
 *
 * @param mixed $value
 */
function trace_warning($value)
{
	\Curry\App::getInstance()->logger->warning($value);
}

/**
 * Global helper function for logging messages or objects, with level set to error.
 *
 * @param mixed $value
 */
function trace_error($value)
{
	\Curry\App::getInstance()->logger->error($value);
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
	
	public static function init($config, $startTime = null, $initAutoloader = null)
	{

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
			$file = \Curry\App::getInstance()->config->curry->configPath;
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
			$file = \Curry\App::getInstance()->config->curry->configPath;
		}
		$writer = new \Zend\Config\Writer\PhpArray();
		$writer->toFile($file, $config);
		if(extension_loaded('apc')) {
			if(function_exists('apc_delete_file'))
				@apc_delete_file(\Curry\App::getInstance()->config->curry->configPath);
			else
				@apc_clear_cache();
		}
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
			$mail->addTo(\Curry\App::getInstance()->config->curry->adminEmail);
			$mail->setSubject('Error on '.\Curry\App::getInstance()->config->curry->name);
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
			\Curry\App::getInstance()->logger->info('Sent error notification');
		}
		catch(Exception $e) {
			\Curry\App::getInstance()->logger->error('Failed to send error notification');
		}
	}
}
