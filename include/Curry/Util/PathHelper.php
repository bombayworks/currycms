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

use Curry\Util\StringHelper;

/**
 * Static utility class.
 *
 * @package Curry\Util
 */
class PathHelper {

	/**
	 * Create path from arguments, automatically joining using the correct directory separator.
	 *
	 * @param bool $realpath optional, canonize path using realpath()
	 * @param string $path,... unlimited optional number of paths to join
	 * @return string
	 */
	public static function path(/* [bool realpath], ... */) {
		$args = func_get_args();
		$realpath = false;
		if (is_bool($args[0])) {
			$realpath = array_shift($args);
		}
		$path = join(DIRECTORY_SEPARATOR, $args);
		return $realpath ? realpath($path) : $path;
	}

	/**
	 * From a file extension, find a suitable font-awesome icon.
	 *
	 * @link http://fontawesome.io
	 *
	 * @param string $extension
	 * @return string
	 */
	public static function getIconFromExtension($extension) {
		switch (strtolower($extension)) {
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
	public static function getFilePermissions($pathname) {
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
	 * Convert path (absolute or relative) to an absolute path. Also works
	 * for non-existing paths, as opposed to php's native realpath().
	 *
	 * @param string $path	Path to convert to absolute path.
	 * @param string|null $cwd	Directory to use as base for relative paths, null means current working directory.
	 * @return string	The absolute path.
	 */
	public static function getAbsolute($path, $cwd = null) {
		if ($cwd === null) {
			$cwd = getcwd();
		}
		$path = ($path{0} == '/' ? $path : $cwd . '/' . $path);
		$parts = array();
		foreach (explode('/', $path) as $part) {
			if ($part == '..' && count($parts) && $parts[count($parts) - 1] != '..') {
				array_pop($parts);
			} else if ($part == '.' || $part == '') {
				continue;
			} else {
				array_push($parts, $part);
			}
		}
		return "/" . join("/", $parts);
	}

	/**
	 * Create a relative path from one path to another.
	 *
	 * @param string $from
	 * @param string $to
	 * @return string
	 */
	public static function getRelative($from, $to) {
		$from = self::getAbsolute($from);
		$to = self::getAbsolute($to);
		$relative = "";
		while ($from && $from !== $to && !StringHelper::startsWith($to, $from . '/')) {
			$relative .= '../';
			$from = dirname($from);
			if ($from == '/') {
				break;
			}
		}
		if ($from !== $to) {
			$relative .= substr($to, strlen($from) + 1) . '/';
		}
		return $relative;
	}
}