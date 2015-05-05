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
namespace Curry\Archive;

use Exception;
use SplFileInfo;

/**
 * Class used to write an archive to a file.
 *
 * @package Curry\Archive
 */
class Writer extends Reader
{
	/**
	 * Construct an archive writer.
	 *
	 * @param string $filename
	 * @param string $compression
	 */
	public function __construct($filename, $compression)
	{
		parent::__construct($filename, $compression);
	}

	/**
	 * Open file
	 */
	protected function open()
	{
		$this->file = $this->call('open', $this->filename, $this->parameters['open_write']);
		if (!$this->file)
			throw new Exception('Unable to open in write mode \'' . $this->filename . '\'');
	}

	/**
	 * Write block to file.
	 *
	 * @param string $data
	 * @param int|null $len
	 * @return int Number of bytes written.
	 */
	public function writeBlock($data, $len = null)
	{
		if (!$this->file)
			throw new Exception('Invalid file descriptor');

		if ($len === null)
			return $this->call('write', $this->file, $data);
		else
			return $this->call('write', $this->file, $data, $len);
	}

	/**
	 * Write footer (two empty 512 byte blocks).
	 */
	public function writeFooter()
	{
		if (!$this->file)
			throw new Exception('Invalid file descriptor');
		$this->writeBlock(pack('a1024', ''));
	}

	/**
	 * Write an entry to archive, including header and content.
	 *
	 * @param string $archiveName
	 * @param null|string|SplFileInfo $entry
	 */
	public function writeEntry($archiveName, $entry)
	{
		if (strlen($archiveName) > 99)
			$data = self::createLongHeader($archiveName);
		else
			$data = '';

		$mode = 0600;
		$typeflag = '0';
		$size = 0;
		$uid = '';
		$gid = '';
		$mtime = time();
		$uname = '';
		$gname = '';
		$linkname = '';

		if ($entry === null) {
			$typeflag = '5';
		} else if (is_string($entry)) {
			$size = strlen($entry);
			$mode = 0700;
		} else if ($entry instanceof SplFileInfo) {
			$mode = $entry->getPerms();
			$mtime = $entry->getMTime();
			$uid = $entry->getOwner();
			$gid = $entry->getGroup();
			if ($entry->isLink()) {
				$typeflag = '2';
				$linkname = $entry->getLinkTarget();
			} else if ($entry->isDir()) {
				$typeflag = '5';
			} else {
				$size = $entry->getSize();
			}

			if (function_exists('posix_getpwuid')) {
				$userinfo = posix_getpwuid($uid);
				$groupinfo = posix_getgrgid($gid);
				$uname = $userinfo['name'];
				$gname = $groupinfo['name'];
			}
		} else
			throw new Exception('Invalid argument');

		$data .= self::createHeader($archiveName, $mode, $uid, $gid, $size, $mtime, $typeflag, $linkname, 'ustar ', ' ', $uname, $gname);
		if (strlen($data) % 512)
			throw new Exception('Invalid header size! ' . strlen($data));
		$this->writeBlock($data);

		if ($size) {
			$len = 0;
			if (is_string($entry)) {
				$this->writeBlock($entry);
				$len = strlen($entry);
			} else {
				$file = fopen($entry->getPathname(), "rb");
				while (($buffer = fread($file, 8192)) != '') {
					$len += strlen($buffer);
					$this->writeBlock($buffer);
				}
				fclose($file);
			}
			if ($len !== $size)
				throw new Exception('Invalid size when writing content');
			// add padding
			if ($len % 512)
				$this->writeBlock(pack('a' . (512 - ($len % 512)), ''));
		}
	}

	/**
	 * Create a long header, using two blocks.
	 *
	 * @param string $filename
	 * @return string
	 */
	protected static function createLongHeader($filename)
	{
		$data = self::createHeader('././@LongLink', 0, 0, 0, strlen($filename), 0, 'L');
		$contentLength = ceil(strlen($filename) / 512) * 512;
		$data .= pack('a' . $contentLength, $filename);
		return $data;
	}

	/**
	 * Creates a tar entry header.
	 *
	 * @param string $filename
	 * @param int $perms
	 * @param int $uid
	 * @param int $gid
	 * @param int $size
	 * @param int $mtime
	 * @param string $typeflag
	 * @param string $linkname
	 * @param string $magic
	 * @param string $version
	 * @param string $uname
	 * @param string $gname
	 * @param string $devmajor
	 * @param string $devminor
	 * @param string $prefix
	 * @return string
	 */
	protected static function createHeader($filename, $perms, $uid, $gid, $size, $mtime, $typeflag,
	                                       $linkname = '', $magic = '', $version = '', $uname = '', $gname = '', $devmajor = '', $devminor = '', $prefix = '')
	{
		$size = sprintf("%011o", $size);
		$uid = sprintf("%07o", $uid);
		$gid = sprintf("%07o", $gid);
		$perms = sprintf("%07o", $perms & 0777);
		$mtime = sprintf("%011o", $mtime);

		$data = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
			$filename, $perms, $uid, $gid, $size, $mtime, str_repeat(' ', 8),
			$typeflag, $linkname, $magic,
			$version, $uname, $gname,
			$devmajor, $devminor, $prefix, '');

		$checksum = 0;
		for ($i = 0; $i < 512; ++$i)
			$checksum += ord($data[$i]);

		$checksum = sprintf("%06o ", $checksum);
		return substr_replace($data, pack("a8", $checksum), 148, 8);
	}
}
