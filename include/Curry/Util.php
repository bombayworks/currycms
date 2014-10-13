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
 * Static utility class.
 *
 * @package Curry
 */
class Curry_Util {
	
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
	 * @todo Unfinished and buggy, feel free to fix
	 * 
	 * @param string $size
	 * @param string $format
	 * @return int
	 */
	public static function computerReadableBytes($size, $format = "en"){
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

		$char = '';
		for ($i = 0; $i < strlen($size); $i++) {
			$char = $size[$i];
			if ($char < '0' || $char > '9')
				break;
		}
		
		$size = substr($size, 0, $i);
		
		switch ($char) {
			case 'K':
				$size *= 1024;
				break;
			case 'M':
				$size *= 1048576;
				break;
			case 'G':
				$size *= 1073741824;
				break;
			case 'T':
				$size *= 1099511627776;
				break;
		}
		
		return $size;
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
		if ((is_array($object) || is_object($object) && $object instanceof ArrayAccess) && isset($object[$name]))
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
			if($object instanceof BaseObject && $object->hasVirtualColumn($name))
				return $object->getVirtualColumn($name);
			// Attempt to call function (__call)
			if(method_exists($object, '__call')) {
				try {
					return $object->$name();
				}
				catch (Exception $e) { trace_error($e->getMessage()); }
			}
		}
		return null;
	}
	
	/**
	 * From a file extension, find a suitable famfamfam silk icon.
	 * 
	 * @link http://www.famfamfam.com/lab/icons/silk/
	 *
	 * @param string $extension
	 * @return string
	 */
	public static function getIconFromExtension($extension)
	{
		switch(strtolower($extension)) {
			case 'htm':
			case 'html':
			case 'xml':
				return 'icon-code';
			case 'zip':
			case 'rar':
			case 'tar':
			case 'gz':
				return 'icon-archive';
			case 'sql':
			case 'db':
			case 'xls':
			case 'xlsx':
				return 'icon-table';
			case 'fla':
			case 'swf':
			case 'ogv':
			case 'mp4':
			case 'flv':
			case 'avi':
				return 'icon-film';
			case 'js':
			case 'php':
				return 'icon-cog';
			case 'bmp':
			case 'gif':
			case 'jpg':
			case 'png':
				return 'icon-picture';
			case 'txt':
			case 'rtf':
			case 'doc':
			case 'docx':
			case 'pdf':
				return 'icon-file-text-alt';
			case 'url':
				return 'icon-world';
		}
		return 'icon-file-alt';
	}
	
	/**
	 * Get human-readable permissions for specified path.
	 *
	 * @param string $pathname
	 * @return string Classic notation, eg '-rw-rw-r--'
	 */
	public static function getFilePermissions($pathname)
	{
		$perms = fileperms($pathname);
		
		if (($perms & 0xC000) == 0xC000) {
			// Socket
			$info = 's';
		} elseif (($perms & 0xA000) == 0xA000) {
			// Symbolic Link
			$info = 'l';
		} elseif (($perms & 0x8000) == 0x8000) {
			// Regular
			$info = '-';
		} elseif (($perms & 0x6000) == 0x6000) {
			// Block special
			$info = 'b';
		} elseif (($perms & 0x4000) == 0x4000) {
			// Directory
			$info = 'd';
		} elseif (($perms & 0x2000) == 0x2000) {
			// Character special
			$info = 'c';
		} elseif (($perms & 0x1000) == 0x1000) {
			// FIFO pipe
			$info = 'p';
		} else {
			// Unknown
			$info = 'u';
		}
		
		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x') :
					(($perms & 0x0800) ? 'S' : '-'));
		
		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x') :
					(($perms & 0x0400) ? 'S' : '-'));
		
		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x') :
					(($perms & 0x0200) ? 'T' : '-'));
		return $info;
	}

	/**
	 * Create path from arguments, automatically joining using the correct directory separator.
	 *
	 * @param bool $realpath optional, canonize path using realpath()
	 * @param string $path,... unlimited optional number of paths to join
	 * @return string
	 */
	public static function path(/* [bool realpath], ... */)
	{
		$args = func_get_args();
		$realpath = false;
		if(is_bool($args[0]))
			$realpath = array_shift($args);
		$path = join(DIRECTORY_SEPARATOR, $args);
		return $realpath ? realpath($path) : $path;
	}
	
	/**
	 * Convert path (absolute or relative) to an absolute path. Also works
	 * for non-existing paths, as opposed to php's native realpath().
	 *
	 * @param string $path	Path to convert to absolute path.
	 * @param string|null $cwd	Directory to use as base for relative paths, null means current working directory.
	 * @return string	The absolute path.
	 */
	public static function getAbsolutePath($path, $cwd = null)
	{
		if($cwd === null)
			$cwd = getcwd();
		$path = ($path{0} == '/' ? $path : $cwd . '/' . $path);
		$parts = array();
		foreach(explode('/', $path) as $part) {
			if($part == '..' && count($parts) && $parts[count($parts)-1] != '..')
				array_pop($parts);
			else if($part == '.' || $part == '')
				continue;
			else
				array_push($parts, $part);
		}
		return "/".join("/", $parts);
	}
	
	/**
	 * Create a relative path from one path to another.
	 *
	 * @param string $from
	 * @param string $to
	 * @return string
	 */
	public static function getRelativePath($from, $to)
	{
		$from = self::getAbsolutePath($from);
		$to = self::getAbsolutePath($to);
		$relative = "";
		while($from && $from !== $to && !Curry_String::startsWith($to, $from.'/')) {
			$relative.= '../';
			$from = dirname($from);
			if($from == '/')
				break;
		}
		if($from !== $to)
			$relative .= substr($to, strlen($from) + 1) . '/';
		return $relative;
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
	 * This is a replacement function for php's built-in fputcsv(). This
	 * function should work better with excel and allows exported csv's to
	 * be opened directly.
	 *
	 * @param $fp
	 * @param $values
	 */
	public static function fputcsv($fp, $values)
	{
		$eol = "\r\n";
		$first = true;
		foreach($values as $value) {
			$value = utf8_decode($value);
			$value = str_replace(array('"', $eol), array('""', "\n"), $value);
			if (strpos($value, "\n") !== false || strpos($value, ";") !== false) {
				$value = '"'.$value.'"';
			}
			fwrite($fp, ($first?"":";").$value);
			$first = false;
		}
		fwrite($fp, $eol);
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
