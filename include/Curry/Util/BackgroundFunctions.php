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

namespace Curry\Util;

/**
 * Class BackgroundFunctions
 * @package Curry\Util
 */
class BackgroundFunctions {
	/**
	 * Array of background functions to be executed on shutdown.
	 *
	 * @var array
	 */
	protected static $functions = null;

	/**
	 * Register a function for background execution on shutdown.
	 * Output is not possible in the callback function.
	 *
	 * @param callback $callback
	 * @param mixed $parameters,... [optional] Optional parameters passed to the callback function.
	 */
	public static function register($callback)
	{
		if (self::$functions === null) {
			// Replace output-buffering with custom function
			while(ob_get_level())
				ob_end_clean();
			ob_start(function($buffer) {
					header("Connection: close", true);
					header("Content-Encoding: none", true);
					header("Content-Length: ".strlen($buffer), true);
					return $buffer;
				});
			register_shutdown_function(array(__CLASS__, 'execute'));
			self::$functions = array();
		}
		self::$functions[] = func_get_args();
	}

	/**
	 * Remove a previously registered function (using registerBackgroundFunction) from being executed.
	 *
	 * @param $callback
	 * @return bool
	 */
	public static function unregister($callback)
	{
		if (self::$functions === null)
			return false;
		$status = false;
		foreach(self::$functions as $k => $args) {
			$cb = array_shift($args);
			if ($cb === $callback) {
				unset(self::$functions[$k]);
				$status = true;
			}
		}
		return $status;
	}

	/**
	 * Execute registered callback functions.
	 *
	 * This function will be called automatically if there are background
	 * functions registered and is not supposed to be called manually.
	 */
	public static function execute()
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
		foreach(self::$functions as $args) {
			$callback = array_shift($args);
			call_user_func_array($callback, $args);
		}
	}
}
