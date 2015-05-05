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

/**
 * Class to read TAR archives. Supports both compressed (gz, bz2) and uncompressed archives.
 *
 * @package Curry\Archive
 */
class Reader
{
	/**
	 * Filename to archive.
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * File pointer to archive.
	 *
	 * @var resource
	 */
	protected $file;

	/**
	 * Archive compression type.
	 *
	 * @var string
	 */
	protected $compression;

	/**
	 * Cached parameters.
	 *
	 * @var array
	 */
	protected $parameters;

	/**
	 * Internal variable to keep track of position from where to read next entry.
	 *
	 * @var int|null
	 */
	protected $nextPos = null;

	/**
	 * Parameters used to decide what functions to use for file operations.
	 *
	 * @var array
	 */
	protected static $compressionParameters = array(
		Archive::COMPRESSION_NONE => array(
			'open' => 'fopen',
			'read' => 'fread',
			'write' => 'fwrite',
			'close' => 'fclose',
			'seek' => 'fseek',
			'tell' => 'ftell',
			'open_read' => 'rb',
			'open_write' => 'wb',
			'open_readwrite' => 'r+b',
		),
		Archive::COMPRESSION_GZ => array(
			'open' => 'gzopen',
			'read' => 'gzread',
			'write' => 'gzwrite',
			'close' => 'gzclose',
			'seek' => 'gzseek',
			'tell' => 'gztell',
			'open_read' => 'rb',
			'open_write' => 'wb9',
			'open_readwrite' => 'r+b',
			'extension' => 'zlib',
		),
		Archive::COMPRESSION_BZ2 => array(
			'open' => 'bzopen',
			'read' => 'bzread',
			'write' => 'bzwrite',
			'close' => 'bzclose',
			'seek' => '',
			'tell' => '',
			'open_read' => 'r',
			'open_write' => 'w',
			'open_readwrite' => '',
			'extension' => 'bz2',
		),
	);

	/**
	 * Create reader using filename and compression type.
	 *
	 * @param string $filename
	 * @param string $compression
	 */
	public function __construct($filename, $compression)
	{
		$this->filename = $filename;
		$this->compression = $compression;

		if (!array_key_exists($this->compression, self::$compressionParameters))
			throw new \Exception('Unknown compression type');
		$this->parameters = self::$compressionParameters[$this->compression];

		// make sure required extension is loaded
		if (isset($this->parameters['extension']) && !extension_loaded($this->parameters['extension']))
			throw new \Exception("The extension '" . $this->parameters['extension'] . "' is not loaded.");

		$this->open();
	}

	/**
	 * Make sure to close files on destruction.
	 *
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Internal function to call for file operations.
	 *
	 * @return mixed
	 */
	protected function call( /* name, ...parameters */)
	{
		$args = func_get_args();
		$funcName = array_shift($args);
		$func = $this->parameters[$funcName];
		if (!$func)
			throw new \Exception("$funcName not supported using {$this->compression}");
		return call_user_func_array($func, $args);
	}

	/**
	 * Extract entry data from position and return as string
	 *
	 * @param int $position Offset into archive.
	 * @param int $size Number of bytes to extract.
	 * @return string The extracted data.
	 */
	public function extractFromPosition($position, $size)
	{
		$this->call('seek', $this->file, $position);
		$contents = $this->call('read', $this->file, $size);
		return $contents;
	}

	/**
	 * Extract entry data from position and write to file resource.
	 *
	 * @param resource $file File resource to write extracted data to.
	 * @param int $position Offset into archive.
	 * @param int $size How much data to read, in bytes.
	 * @param int $blockSize Specifies the buffer size, that is how much data to fetch on each read (in bytes).
	 */
	public function extractFromPositionToFile($file, $position, $size, $blockSize = 8192)
	{
		$this->call('seek', $this->file, $position);
		for (; $size > 0; $size -= $blockSize) {
			$len = min($size, $blockSize);
			fwrite($file, $this->call('read', $this->file, $len), $len);
		}
	}

	/**
	 * Open file.
	 */
	protected function open()
	{
		$this->file = $this->call('open', $this->filename, $this->parameters['open_read']);
		if (!$this->file)
			throw new \Exception('Unable to open in read mode \'' . $this->filename . '\'');
	}

	/**
	 * Close file.
	 */
	public function close()
	{
		if ($this->file) {
			$this->call('close', $this->file);
			$this->file = null;
		}
	}

	/**
	 * Read a block of specified size.
	 *
	 * @param int $length Bytes to read.
	 * @return string
	 */
	public function readBlock($length = 512)
	{
		if (!$this->file)
			throw new \Exception('Invalid file descriptor');

		return $this->call('read', $this->file, $length);
	}

	/**
	 * Skip $num of 512-byte blocks.
	 *
	 * @param int $num
	 */
	protected function jumpBlock($num = 1)
	{
		if (!$this->file)
			throw new \Exception('Invalid file descriptor');

		$seek = $this->parameters['seek'];
		if ($seek)
			$this->call('seek', $this->file, $num * 512, SEEK_CUR);
		else {
			while ($num--)
				$this->readBlock();
		}
	}

	/**
	 * Read an entry from the archive and return a Curry_Archive_FileInfo.
	 * Null will be returned on read-errors or EOF.
	 *
	 * @param array $options
	 * @return FileInfo|null
	 */
	protected function readEntry($options)
	{
		if ($this->nextPos) {
			$position = $this->call('tell', $this->file);
			if ($position !== $this->nextPos)
				$this->call('seek', $this->file, $this->nextPos);
			$this->nextPos = null;
		}

		while (strlen($data = $this->readBlock()) != 0) {
			$header = $this->readHeader($data);

			if (!$header)
				return null;

			if ($header['filename'] == '')
				continue;

			$fileOptions = Archive::getPathOptions($header['filename'], $options);

			$position = $this->call('tell', $this->file);
			$this->nextPos = $position + ceil($header['size'] / 512) * 512;
			if ($fileOptions['skip']) {
				$this->call('seek', $this->file, $this->nextPos);
				continue;
			}

			$type = FileInfo::TYPE_FILE;
			if ($header['typeflag'] == '5')
				$type = FileInfo::TYPE_DIR;
			else if ($header['typeflag'] == '2')
				$type = FileInfo::TYPE_LINK;

			return new FileInfo($header['filename'], $type, $header['size'], $header['mtime'], $header['link'], $header['mode'], $header['uid'], $header['gid'], $this, $position, $fileOptions);
		}
		return null;
	}

	/**
	 * Read header block.
	 *
	 * @param string $data
	 * @return array|null
	 */
	public function readHeader(&$data)
	{
		if (strlen($data) != 512)
			throw new \Exception('Invalid block size: ' . strlen($data));

		// calculate checksum
		$checksum = 0;
		for ($i = 0; $i < 512; ++$i)
			$checksum += ($i >= 148 && $i < 156) ? 32 : ord(substr($data, $i, 1));

		$header = array();
		$unpacked = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix", $data);

		// verify checksum
		$header['checksum'] = octdec(trim($unpacked['checksum']));
		if ($header['checksum'] != $checksum) {
			// Look for last block (empty block)
			if (($checksum == 256) && ($header['checksum'] == 0))
				return null;

			throw new \Exception('Invalid checksum for file "' . $unpacked['filename'] . '" : ' . $checksum . ' calculated, ' . $header['checksum'] . ' expected');
		}

		$header['filename'] = $unpacked['filename'];
		$header['mode'] = octdec(trim($unpacked['mode']));
		$header['uid'] = octdec(trim($unpacked['uid']));
		$header['gid'] = octdec(trim($unpacked['gid']));
		$header['size'] = octdec(trim($unpacked['size']));
		$header['mtime'] = octdec(trim($unpacked['mtime']));
		$header['typeflag'] = $unpacked['typeflag'];
		$header['link'] = trim($unpacked['link']);

		if ($unpacked['magic'] == 'ustar' && !empty($unpacked['prefix']))
			$header['filename'] = $unpacked['prefix'] . DIRECTORY_SEPARATOR . $header['filename'];

		if ($header['typeflag'] == '5')
			$header['size'] = 0;

		if ($header['typeflag'] == 'L') {
			// read "long header"
			$filename = '';
			for ($size = $header['size']; $size > 0; $size -= 512) {
				$d = $this->readBlock();
				$filename .= $d;
				$data .= $d;
			}
			$d = $this->readBlock();
			$data .= $d;
			$header = $this->readHeader($d);
			if ($header)
				$header['filename'] = trim($filename);
			return $header;
		}

		return $header;
	}
}
