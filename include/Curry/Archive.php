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
 * Read/write Tar archives.
 * 
 * @package Curry\Archive
 */
class Curry_Archive implements IteratorAggregate {
	/**
	 * Do not use compression.
	 */
	const COMPRESSION_NONE = '';
	
	/**
	 * Use gzip compression.
	 */
	const COMPRESSION_GZ = 'gz';
	
	/**
	 * Use bzip2 compression.
	 */
	const COMPRESSION_BZ2 = 'bz2';
	
	/**
	 * Never overwrite existing files.
	 */
	const OVERWRITE_NEVER = 'never';
	
	/**
	 * Always overwrite existing files.
	 */
	const OVERWRITE_ALWAYS = 'always';
	
	/**
	 * Overwrite existing files only if the file is newer.
	 */
	const OVERWRITE_NEWER = 'newer';
	
	/**
	 * Overwrite existing files only if the file is larger.
	 */
	const OVERWRITE_LARGER = 'larger';
	
	/**
	 * Default options used when adding/extracting files.
	 *
	 * @var array
	 */
	public static $defaultOptions = array(
		'skip' => false,
		'target' => null,
		'overwrite' => self::OVERWRITE_NEVER,
		'chmod' => false,
		'chown' => false,
		'chgrp' => false,
		'mtime' => true,
		'callback' => null,
	);
	
	/**
	 * Archive filename.
	 *
	 * @var string
	 */
	protected $filename;
	
	/**
	 * Compression used for the archive.
	 *
	 * @var string
	 */
	protected $compression;
	
	/**
	 * Options used when iterating/extracting.
	 *
	 * @var array
	 */
	protected $options = array();
	
	/**
	 * Files to be added to the archive.
	 *
	 * @var array
	 */
	protected $add = array();
	
	/**
	 * Files to remove from the archive.
	 *
	 * @var array
	 */
	protected $remove = array();
	
	/**
	 * Clear content in existing archive.
	 *
	 * @var bool
	 */
	protected $clear = false;
	
	/**
	 * Create new or open existing archive.
	 *
	 * @param string $filename
	 * @param string $compression
	 */
	public function __construct($filename, $compression = null)
	{
		$this->filename = $filename;
		$this->compression = $compression;
		
		if($this->compression === null) {
			// Find compression method from file
			if ($filename && @file_exists($filename)) {
				$fp = @fopen($filename, "rb");
				if ($fp) {
					$data = fread($fp, 2);
					fclose($fp);
					if ($data == "\37\213")
						$this->compression = self::COMPRESSION_GZ;
					else if($data == "BZ")
						$this->compression = self::COMPRESSION_BZ2;
				}
			}
			// Find compression method from filename
			if($this->compression === null) {
				$p = strrpos($filename, '.');
				$ext = ($p === false ? '' : substr($filename, $p + 1));
				if (in_array($ext, array('gz','tgz')))
					$this->compression = self::COMPRESSION_GZ;
				else if (in_array($ext, array('bz','bz2','tbz','tb2')))
					$this->compression = self::COMPRESSION_BZ2;
				else
					$this->compression = self::COMPRESSION_NONE;
			}
		}
	}
	
	/**
	 * Get the filename of this archive.
	 *
	 * @return string
	 */
	public function getFilename()
	{
		return $this->filename;
	}
	
	/**
	 * The compression used for this archive.
	 *
	 * @return string
	 */
	public function getCompression()
	{
		return $this->compression;
	}
	
	/**
	 * Specify options used when iterating over the object or when extracting.
	 *
	 * @param array $options
	 */
	public function setOptions($options)
	{
		$this->options = $options;
	}
	
	/**
	 * Allows iteration using foreach()
	 *
	 * @return Curry_Archive_Iterator
	 */
	public function getIterator()
	{
		return new Curry_Archive_Iterator($this, $this->options);
	}
	
	/**
	 * Extract archive contents.
	 *
	 * @param array $options
	 */
	public function extract($options = null)
	{
		clearstatcache();
		$it = new Curry_Archive_Iterator($this, $options === null ? $this->options : $options);
		foreach($it as $entry) {
		  	$o = $entry->getOptions();
		  	$target = $entry->getTarget();

			if ($o['callback'] !== null) {
				if (call_user_func($o['callback'], $entry, $o) === false)
					continue;
			}
		  		
		  	$mode = null;
		  	if($o['chmod'] !== false)
	  			$mode = $o['chmod'] === true ? $entry->getPerms() : $o['chmod'];
	  		
	  		$mtime = null;
		  	if($o['mtime'] !== false)
	  			$mtime = $o['mtime'] === true ? $entry->getMTime() : $o['mtime'];
	  		
	  		$type = null;
	  		if(file_exists($target)) {
	  			$type = Curry_Archive_FileInfo::TYPE_FILE;
	  			if(is_link($target))
	  				$type = Curry_Archive_FileInfo::TYPE_LINK;
	  			else if(is_dir($target))
	  				$type = Curry_Archive_FileInfo::TYPE_DIR;
	  		}
	  		
	  		if($type) { // file already exists
	  			if($entry->isDir()) {
	  				if($type !== Curry_Archive_FileInfo::TYPE_DIR)
		  				throw new Exception("Directory '$target' already exists as a '$type'.");
	  				else
	  					continue;
	  			} else if($entry->isLink()) {
	  				if($type !== Curry_Archive_FileInfo::TYPE_LINK)
		  				throw new Exception("Link '$target' already exists as a '$type'.");
	  				else if($entry->getLinkTarget() == readlink($target))
	  					continue;
	  				else
	  					throw new Exception("Link '$target' already exists with a different target.");
	  			} else if($entry->isFile()) {
	  				if($type !== Curry_Archive_FileInfo::TYPE_FILE)
		  				throw new Exception("File '$target' already exists as a '$type'.");
	  				else {
						$allowed = array(self::OVERWRITE_NEVER, self::OVERWRITE_ALWAYS, self::OVERWRITE_NEWER, self::OVERWRITE_LARGER);
						if($o['overwrite'] === true)
							$o['overwrite'] = self::OVERWRITE_ALWAYS;
						else if($o['overwrite'] === false)
							$o['overwrite'] = self::OVERWRITE_NEVER;
						if (!in_array($o['overwrite'], $allowed)) {
							if (!is_callable($o['overwrite'])) {
								throw new Exception('Overwrite option when extracting should be one of the '.
									__CLASS__.'::OVERWRITE_* constants or a callable function.');
							}
							if (call_user_func($o['overwrite'], $entry, $o) !== true) {
								continue;
							}
						} else if($o['overwrite'] == self::OVERWRITE_NEVER) {
	  						continue;
						} else if($o['overwrite'] == self::OVERWRITE_NEWER) {
	  						if($entry->getMTime() <= filemtime($target))
	  							continue;
	  					} else if($o['overwrite'] == self::OVERWRITE_LARGER) {
	  						if($entry->getSize() <= filesize($target))
	  							continue;
	  					}
	  				}
	  			}
	  		}
	  		
		  	if($entry->isFile()) {
		  		$dir = dirname($target);
				if($dir && !is_dir($dir))
		  			mkdir($dir, 0777, true);
		  		$entry->extract($target);
		  		if($mode !== null)
		  			chmod($target, $mode);
		  	} else if($entry->isDir()) {
		  		mkdir($target, $mode !== null ? $mode : 0777, true);
		  	} else if($entry->isLink()) {
		  		if(!symlink($entry->getLinkTarget(), $target))
		  			exec('ln -s ' . escapeshellarg($entry->getLinkTarget()) . ' ' . escapeshellarg($target));
		  		$mtime = null; // do not modify mtime for symlinks
		  	}
		  	
		  	if($mtime !== null)
		  		touch($target, $mtime);
		}
	}
	
	/**
	 * Extract content and return as string.
	 *
	 * @param string $path
	 * @return string
	 */
	public function extractString($path)
	{
		return $this->getFile($path)->getContents();
	}
	
	/**
	 * Get file entry from archive.
	 *
	 * @param string $path
	 * @return Curry_Archive_FileInfo
	 */
	public function getFile($path)
	{
		foreach($this as $entry) {
			if($entry->getPathname() === $path)
				return $entry;
		}
		throw new Exception("File '$path' not found in archive.");
	}
	
	/**
	 * Remove file from archive.
	 *
	 * @param string $filename
	 */
	public function remove($filename)
	{
		$this->remove[$filename] = substr($filename, -1) == '/';
	}
	
	/**
	 * Add file or directory to archive.
	 *
	 * @param string $filename
	 * @param string $target
	 * @param array $options
	 * @param bool $recursive
	 */
	public function add($filename, $target = '', array $options = array(), $recursive = true)
	{
		if(!file_exists($filename))
			throw new Exception("File not found '$filename'.");
			
		if($target === '')
			$target = basename($filename) . (is_dir($filename) ? '/' : '');
		
		if(is_dir($filename)) {
			array_unshift($options, array('path' => '', 'target' => $target));
			$base = realpath($filename) . DIRECTORY_SEPARATOR;
			$dit = $recursive ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base), RecursiveIteratorIterator::SELF_FIRST) : new DirectoryIterator($base);
			foreach($dit as $entry) {
				if($dit->isDot())
					continue;
				$archiveName = substr($entry->getPathname(), strlen($base));
				$this->addFile($entry, $archiveName, $options);
			}
		} else {
			$archiveName = basename($filename);
			$path = $target[strlen($target)-1] !== '/' ? $archiveName : '';
			array_unshift($options, array('path' => $path, 'target' => $target));
			$this->addFile(new SplFileInfo($filename), $archiveName, $options);
		}
	}
	
	/**
	 * Add entry using content from string.
	 *
	 * @param string $path
	 * @param string $content
	 */
	public function addString($path, $content)
	{
		$this->add[$path] = $content;
	}
	
	/**
	 * Create empty directory in archive.
	 *
	 * @param string $path
	 */
	public function addEmptyDir($path)
	{
		$this->add[$path] = null;
	}
	
	/**
	 * Add file from SplFileInfo object.
	 *
	 * @param SplFileInfo $entry
	 * @param string $archiveName
	 * @param array $options
	 */
	protected function addFile(SplFileInfo $entry, $archiveName, $options)
	{
		$o = self::getPathOptions($archiveName, $options);
		if($o['skip'])
			return;
		if($o['target'])
			$archiveName = $o['target'];
		$this->add[$archiveName] = $entry;
	}
	
	/**
	 * Clear archive contents.
	 */
	public function clear()
	{
		$this->add = array();
		$this->remove = array();
		$this->clear = true;
	}
	
	/**
	 * Save archive to file.
	 *
	 * @param string $target	Defaults to the current archive if not specified.
	 */
	public function save($target = '')
	{
		clearstatcache();
		
		if($target === '')
			$target = $this->filename;
			
		$reader = null;
		$writer = null;
		$tempfile = null;
		
		if(!$this->clear && file_exists($this->filename)) {
			// we need to preserve file contents!
			if($target == $this->filename) {
				$tempfile = uniqid('tar').'.tar';
				$target = $tempfile;
			}
			$reader = new Curry_Archive_Reader($this->filename, $this->compression);
			$writer = new Curry_Archive_Writer($target, $this->compression);
		} else {
			$writer = new Curry_Archive_Writer($target, $this->compression);
		}
		
		$this->buildArchive($writer, $reader);
		if($reader)
			$reader->close();
		$writer->close();
		
		if($tempfile !== null)
			rename($tempfile, $this->filename);
		
		$this->add = array();
		$this->remove = array();
		$this->clear = false;
	}
	
	/**
	 * Stream content to browser.
	 */
	public function stream()
	{
		$writer = new Curry_Archive_StreamWriter($this->filename, $this->compression);
		$reader = null;
		if(!$this->clear && file_exists($this->filename))
			$reader = new Curry_Archive_Reader($this->filename, $this->compression);
		$this->buildArchive($writer, $reader);
	}
	
	/**
	 * Build archive.
	 *
	 * @param Curry_Archive_Writer $writer
	 * @param Curry_Archive_Reader|null $reader
	 */
	protected function buildArchive(Curry_Archive_Writer $writer, Curry_Archive_Reader $reader = null)
	{
		if($reader) {
			while (strlen($data = $reader->readBlock()) != 0) {
				$header = $reader->readHeader($data);
				if(!$header)
					break;
				
				if ($header['filename'] == '')
					continue;
				
				$keep = true;
				foreach($this->remove as $remove => $v)	 {
					if(($v && substr($header['filename'], 0, strlen($remove)) == $remove) || $header['filename'] == $remove) {
						$keep = false;
						break;
					}
				}
				
				if(array_key_exists($header['filename'], $this->add)) {
					// replace this entry with the one in $add
					$writer->writeEntry($header['filename'], $this->add[$header['filename']]);
					unset($this->add[$header['filename']]);
					$keep = false;
				}
				
				$blockSize = 8192;
				if($keep) {
					$writer->writeBlock($data);
					if($header['size']) {
						for ($size = ceil($header['size']/512)*512; $size > 0; $size -= $blockSize) {
							$len = min($size, $blockSize);
							$writer->writeBlock($reader->readBlock($len), $len);
						}
					}
				} else {
					for ($size = ceil($header['size']/512)*512; $size > 0; $size -= $blockSize)
						$reader->readBlock(min($size, $blockSize));
				}
			}
		}
		
		// add files in $this->add
		ksort($this->add);
		foreach($this->add as $path => $entry)
			$writer->writeEntry($path, $entry);
		
		$writer->writeFooter();
	}
	
	/**
	 * Get options for a specific path.
	 *
	 * @param string $path
	 * @param array $options
	 * @return array
	 */
	public static function getPathOptions($path, $options)
	{
		$basename = basename($path);
		$dirname = dirname($path);
		$pathOptions = Curry_Archive::$defaultOptions;
		foreach($options as $o) {
			$p = isset($o['path']) ? $o['path'] : '';
			if($p === $path || $p === '' || (Curry_String::endsWith($p, '/') && Curry_String::startsWith($path, $p))) {
				if(isset($o['pattern'])) {
					$subject = $basename;
					if(isset($o['pattern_subject'])) {
						if($o['pattern_subject'] == 'path')
							$subject = $path;
						else if($o['pattern_subject'] == 'basename')
							$subject = $basename;
						else if($o['pattern_subject'] == 'dirname')
							$subject = $dirname;
						else
							throw new Exception("'Unknown pattern subject '".$o['pattern_subject']."'");
					}
					$func = isset($o['pattern_function']) ? $o['pattern_function'] : 'fnmatch';
					if (!$func($o['pattern'], $subject))
						continue;
				}
				$pathOptions = $o + $pathOptions;
				if(isset($o['target']))
					$pathOptions['target'] = $o['target'] . substr($path, strlen($p));
			}
		}
		$pathOptions['path'] = $path;
		return $pathOptions;
	}
	
	/**
	 * Get MIME-type for compression.
	 *
	 * @param string $compression
	 * @return string
	 */
	public static function getCompressionMimeType($compression)
	{
		if($compression == self::COMPRESSION_NONE)
			return 'application/x-tar';
		else if($compression == self::COMPRESSION_GZ)
			return 'application/x-tgz';
		else if($compression == self::COMPRESSION_BZ2)
			return 'application/x-bzip-compressed-tar';
		throw new Exception('Unknown compression type');
	}
}
