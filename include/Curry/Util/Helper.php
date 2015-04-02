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
use Curry\App;

/**
 * Static utility class.
 *
 * @package Curry\Util
 */
class Helper {
	
	/**
	 * Get a string from the error code provided by $_FILES[x]['error']
	 *
	 * @param integer $code
	 * @return string
	 */
	public static function uploadCodeToMessage($code)
	{
		switch ($code) {
			case UPLOAD_ERR_INI_SIZE:
				return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
			case UPLOAD_ERR_FORM_SIZE:
				return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
			case UPLOAD_ERR_PARTIAL:
				return "The uploaded file was only partially uploaded";
			case UPLOAD_ERR_NO_FILE:
				return "No file was uploaded";
			case UPLOAD_ERR_NO_TMP_DIR:
				return "Missing a temporary folder";
			case UPLOAD_ERR_CANT_WRITE:
				return "Failed to write file to disk";
			case UPLOAD_ERR_EXTENSION:
				return "File upload stopped by extension";

			default:
				return "Unknown upload error";
		}
	}
	
	/**
	 * Get human readable file size, quick and dirty.
	 * 
	 * @todo Improve i18n support.
	 *
	 * @param int $bytes
	 * @param string $format
	 * @param int|null $decimal_places
	 * @return string Human readable string with file size.
	 */
	public static function humanReadableBytes($bytes, $format = "en", $decimal_places = null){
		
		switch ($format) {
			case "sv":
				$dec_separator = ",";
				$thousands_separator = " ";
				break;
			default:
			case "en":
				$dec_separator = ".";
				$thousands_separator = ",";
				break;
		}
		
		$b = (int)$bytes;
		$s = array('B', 'kB', 'MB', 'GB', 'TB');

		if ($b <= 0)
			return "0 ".$s[0];

		$con = 1024;
		$e = (int)(log($b,$con));
		$e = min($e, count($s)-1);
		
		$v = $b/pow($con,$e);
		if($decimal_places === null)
			$decimal_places = max(0, 2 - (int)log($v, 10));
		
		return number_format($v, (!$e?0:$decimal_places), $dec_separator, $thousands_separator).' '.$s[$e];
	}
	
	/**
	 * Get number of bytes from human readable string.
	 * 
	 * This is the reverse of humanReadableBytes.
	 *
	 * @param string $size
	 * @return int
	 */
	public static function computerReadableBytes($size) {
		$size = preg_replace('/[^tgmk0-9.-]/i', '', $size);
		$last = strtolower($size[strlen($size)-1]);
		switch($last) {
			case 't':
				$size *= 1024;
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}
		return intval($size);
	}
	
	/**
	 * Get property from object.
	 * 
	 * Tries to read a property from an object, in the following order:
	 * * Array access $object[$name]
	 * * Object property $object->$name
	 * * Object magic property $object->$name through __get
	 * * Method $object->$name()
	 * * Get method $object->get$name()
	 * * Propel virtual columns $object->getVirtualColumn($name)
	 * * Magic method $object->get$name() __call
	 * * Otherwise, return null.
	 *
	 * @param mixed $object
	 * @param mixed $name
	 * @return mixed
	 */
	public static function getProperty($object, $name)
	{
		// Array
		if ((is_array($object) || is_object($object) && $object instanceof \ArrayAccess) && isset($object[$name]))
			return $object[$name];
		if(is_object($object)) {
			// Object property
			if (property_exists($object, $name))
				return $object->$name;
			if (method_exists($object, '__get') && isset($object->$name))
				return $object->$name;
			// Method
			if (method_exists($object, $name))
				return $object->{$name}();
			if (method_exists($object, 'get'.$name))
				return $object->{'get'.$name}();
			// Propel virtual columns
			if($object instanceof \BaseObject && $object->hasVirtualColumn($name))
				return $object->getVirtualColumn($name);
			// Attempt to call function (__call)
			if(method_exists($object, '__call')) {
				try {
					return $object->$name();
				}
				catch (\Exception $e) { App::getInstance()->logger->error($e->getMessage()); }
			}
		}
		return null;
	}


	/**
	 * Get end-of-line characters used in string.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function getStringEol($value)
	{
		if(strpos($value, "\r\n") !== false)
			return "\r\n";
		if(strpos($value, "\r") !== false)
			return "\r";
		if(strpos($value, "\n") !== false)
			return "\n";
		return PHP_EOL;
	}

	public static function getUniqueId($length = 16)
	{
		$chars = array_merge(range('a', 'z'), range('A', 'Z'), range('0','9'));
		$uid = "";
		while($length--) {
			$r = mt_rand(0, count($chars) - 1);
			$uid .= $chars{$r};
		}
		return $uid;
	}

	/**
	 * Get CPU time (using getrusage) in milliseconds.
	 *
	 * @param string $type
	 * @param int $who
	 * @return mixed
	 */
	public static function getCpuTime($type = 'u', $who = 0)
	{
		$d = getrusage($who);
		return (double)$d['ru_'.$type.'time.tv_sec'] + ($d['ru_'.$type.'time.tv_usec'] / 1000000.0);
	}

	/**
	 * Cast provided value to string.
	 *
	 * Note: Explicitly call __toString() of objects to allow exceptions.
	 *
	 * @param $value
	 * @return string
	 */
	public static function stringify($value)
	{
		if (is_string($value))
			return $value;
		if (is_object($value) && method_exists($value, '__toString'))
			return $value->__toString();
		return (string)$value;
	}
}
