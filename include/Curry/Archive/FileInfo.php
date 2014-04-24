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

/**
 * Describes an entry in an archive. Compatible with SplFileInfo.
 *
 * @package Curry\Archive
 */
class FileInfo
{
	/**
	 * Entry is a file.
	 */
	const TYPE_FILE = 'file';

	/**
	 * Entry is a symlink.
	 */
	const TYPE_LINK = 'link';

	/**
	 * Entry is a directory.
	 */
	const TYPE_DIR = 'dir';

	/**
	 * Filename.
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * Entry type. Refers to one of the TYPE_ constants.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Size of file in bytes. Only applies to TYPE_FILE.
	 *
	 * @var integer
	 */
	protected $size;

	/**
	 * Modified time (unix timestamp).
	 *
	 * @var int
	 */
	protected $mtime;

	/**
	 * Link target path. Only applies to TYPE_LINK.
	 *
	 * @var string
	 */
	protected $linkTarget;

	/**
	 * Entry unix permissions.
	 *
	 * @var int
	 */
	protected $perms;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected $uid;

	/**
	 * Group ID.
	 *
	 * @var int
	 */
	protected $gid;

	/**
	 * Options used when extracting.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Object used to read data from.
	 *
	 * @var Reader
	 */
	protected $sourceReader = null;

	/**
	 * Byte offset in $sourceReader from where to extract file.
	 *
	 * @var int
	 */
	protected $sourcePosition = null;

	/**
	 * Create archive file entry.
	 *
	 * @param string $filename
	 * @param string $type
	 * @param int $size
	 * @param int $mtime
	 * @param string $linkTarget
	 * @param int $perms
	 * @param int $uid
	 * @param int $gid
	 * @param Reader $reader
	 * @param int $position
	 * @param array $options
	 */
	public function __construct($filename, $type, $size, $mtime, $linkTarget, $perms, $uid, $gid, Reader $reader = null, $position = null, $options = array())
	{
		$this->filename = $filename;
		$this->type = $type;
		$this->size = $size;
		$this->mtime = $mtime;
		$this->linkTarget = $linkTarget;
		$this->perms = $perms;
		$this->uid = $uid;
		$this->gid = $gid;
		$this->sourceReader = $reader;
		$this->sourcePosition = $position;
		$this->options = $options;
	}

	/**
	 * Get name of this entry, without path.
	 *
	 * @return string
	 */
	public function getFilename()
	{
		return basename($this->filename);
	}

	/**
	 * Get full name of this entry, including path.
	 *
	 * @return string
	 */
	public function getPathname()
	{
		return $this->filename;
	}

	/**
	 * Get name of this entry, excluding path and possibly extension ($suffix).
	 *
	 * @param string $suffix
	 * @return string
	 */
	public function getBasename($suffix = null)
	{
		return basename($this->filename, $suffix);
	}

	/**
	 * Get extension (the part after the last .) from filename.
	 *
	 * @return string
	 */
	public function getExtension()
	{
		return pathinfo($this->filename, PATHINFO_EXTENSION);
	}

	/**
	 * Get parent path of this entry.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return dirname($this->filename);
	}

	/**
	 * Get extraction target. Depending on extraction options this may not be the same as filename.
	 *
	 * @return string
	 */
	public function getTarget()
	{
		return $this->options['target'] ? $this->options['target'] : $this->filename;
	}

	/**
	 * Type of entry.
	 *
	 * @return string One of TYPE_DIR, TYPE_FILE, TYPE_LINK.
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Size of entry in bytes. Only applies to TYPE_FILE.
	 *
	 * @return int
	 */
	public function getSize()
	{
		return $this->size;
	}

	/**
	 * Get modified time (unix timestamp)
	 *
	 * @return int
	 */
	public function getMTime()
	{
		return $this->mtime;
	}

	/**
	 * Get link target destination. Only applies to TYPE_LINK.
	 *
	 * @return string
	 */
	public function getLinkTarget()
	{
		return $this->linkTarget;
	}

	/**
	 * Get unix permissions.
	 *
	 * @return int
	 */
	public function getPerms()
	{
		return $this->perms;
	}

	/**
	 * Get OwnerID.
	 *
	 * @return int
	 */
	public function getOwner()
	{
		return $this->uid;
	}

	/**
	 * Get GroupID.
	 *
	 * @return int
	 */
	public function getGroup()
	{
		return $this->gid;
	}

	/**
	 * Get extraction options.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * Check if this has type equal to TYPE_FILE.
	 *
	 * @return bool
	 */
	public function isFile()
	{
		return $this->type == self::TYPE_FILE;
	}

	/**
	 * Check if this has type equal to TYPE_LINK.
	 *
	 * @return bool
	 */
	public function isLink()
	{
		return $this->type == self::TYPE_LINK;
	}

	/**
	 * Check if this has type equal to TYPE_DIR.
	 *
	 * @return bool
	 */
	public function isDir()
	{
		return $this->type == self::TYPE_DIR;
	}

	/**
	 * Extract entry to target destination. Only applies to TYPE_FILE.
	 *
	 * @param string|null $target Destination where to extract file. If null, the archive filename will be used.
	 */
	public function extract($target = null)
	{
		if (!$this->isFile())
			throw new Exception('Cannot extract non-file');

		if (!($this->sourceReader && $this->sourcePosition))
			throw new Exception('Unable to extract file: ' . $this->filename);

		if ($target === null)
			$target = $this->getTarget();

		$file = @fopen($target, "wb");
		if (!$file)
			throw new Exception("Error while opening '$target' in write binary mode");

		if ($this->size)
			$this->sourceReader->extractFromPositionToFile($file, $this->sourcePosition, $this->size);

		fclose($file);
	}

	/**
	 * Get the contents of the file and return as string.
	 *
	 * @return string
	 */
	public function getContents()
	{
		if (!$this->isFile())
			throw new Exception('Cannot extract non-file');
		if (!$this->size)
			return '';
		if (!($this->sourceReader && $this->sourcePosition))
			throw new Exception('Unable to extract file: ' . $this->filename);
		return $this->sourceReader->extractFromPosition($this->sourcePosition, $this->size);
	}

	/**
	 * Set Archive Reader and byte offset for extraction.
	 *
	 * @param Reader $reader
	 * @param int $position
	 */
	public function setSource(Reader $reader, $position)
	{
		$this->sourceReader = $reader;
		$this->sourcePosition = $position;
	}

	/**
	 * String representation will return the filename.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->filename;
	}
}
