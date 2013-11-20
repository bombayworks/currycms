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
 * Class to write archive directly to output. This is provided
 * because using Curry_Archive_Writer directly on php://output
 * doesn't work work with compression.
 * 
 * @package Curry\Archive
 */
class Curry_Archive_StreamWriter extends Curry_Archive_Writer {
	/**
	 * Cyclic redundancy check.
	 *
	 * @var null|string
	 */
	protected $crc = null;
	
	/**
	 * Data to perform CRC on.
	 *
	 * @var string
	 */
	protected $crcCache = "";
	
	/**
	 * Stream zlib filter.
	 *
	 * @var resource|null
	 */
	protected $filter = null;
	
	/**
	 * Keeps track of file-pointer offset. Used to implement tell().
	 *
	 * @var int
	 */
	protected $position = 0;
	
	/**
	 * Construct output stream writer.
	 *
	 * @param string $filename
	 * @param string $compression
	 */
	public function __construct($filename, $compression)
	{
		parent::__construct($filename, $compression);
		// Override callbacks
		$this->parameters['read'] = '';
		$this->parameters['seek'] = '';
		$this->parameters['tell'] = array($this, 'tell');
		$this->parameters['write'] = array($this, 'write');
	}
	
	/**
	 * Open file for output.
	 */
	public function open()
	{
		$this->position = 0;
		$this->file = fopen('php://output', 'wb');
		if (!$this->file)
			throw new Exception('Unable to open in write mode \'' . $this->filename . '\'');

		if($this->compression == Curry_Archive::COMPRESSION_GZ) {
			// write gzip header
			fwrite($this->file, "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\x03");
			$params = 9;//array('level' => 6, 'window' => 15, 'memory' => 9);
			$this->filter = stream_filter_append($this->file, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
			$this->crc = 0;
		} else if($this->compression == Curry_Archive::COMPRESSION_BZ2) {
			throw new Exception('Bzip2 direct output not implemented.');
		}
	}

	/**
	 * Close file.
	 */
	public function close()
	{
		$this->flushCrc();
		fflush($this->file);
		
		if($this->filter)
			stream_filter_remove($this->filter);
		
		if($this->compression == Curry_Archive::COMPRESSION_GZ) {
			// write crc and size
			fwrite($this->file, pack('V', $this->crc));
    		fwrite($this->file, pack('V', $this->position));
		}
		
    	parent::close();
	}
	
	/**
	 * Write data to stream.
	 *
	 * @param resource $fp
	 * @param string $data
	 * @param int|null $len
	 */
	public function write($fp, $data, $len = null)
	{
		if($len === null)
			$len = strlen($data);
		else if(strlen($data) !== $len)
			$data = substr($data, 0, $len);
			
		if(!$len)
			return;

		// update crc
		if($this->crc !== null) {
			$this->crcCache .= $data;
			if(strlen($this->crcCache) > 1048576)
				$this->flushCrc();
		}
		
		$this->position += $len;
		fwrite($this->file, $data);
	}
	
	/**
	 * Make sure all data has passed through crc.
	 */
	public function flushCrc()
	{
		if($this->crc !== null && strlen($this->crcCache)) {
			$this->crc = self::crc32_combine($this->crc, crc32($this->crcCache), strlen($this->crcCache));
			$this->crcCache = "";
		}
	}
	
	/**
	 * Returns the current position of the file writer pointer.
	 *
	 * @param resource $file
	 * @return int
	 */
	public function tell($file)
	{
		return $this->position;
	}
	
	/**
	 * Combine two crc32 checksums.
	 *
	 * @param int $crc1
	 * @param int $crc2
	 * @param int $len2
	 * @return int
	 */
	protected static function crc32_combine($crc1, $crc2, $len2)
	{
		$odd[0] = 0xedb88320;
		$row = 1;
		
		for($n = 1; $n < 32; ++$n) {
			$odd[$n] = $row;
			$row <<= 1;
		}
		
		$even = array();
		self::gf2_matrix_square($even, $odd);
		self::gf2_matrix_square($odd, $even);
		
		do {
			// apply zeros operator for this bit of len2
			self::gf2_matrix_square($even, $odd);
			if ($len2 & 1)
				$crc1 = self::gf2_matrix_times($even, $crc1);
			$len2 >>= 1;
			
			// if no more bits set, then done
			if ($len2 == 0)
				break;
			
			// another iteration of the loop with odd and even swapped
			self::gf2_matrix_square($odd, $even);
			if ($len2 & 1)
				$crc1 = self::gf2_matrix_times($odd, $crc1);
			$len2 >>= 1;
		
		} while($len2 != 0);
		
		$crc1 ^= $crc2;
		return $crc1;
	}
	
	/**
	 * Used by crc32_combine.
	 *
	 * @param array $square
	 * @param array $mat
	 */
	protected static function gf2_matrix_square(&$square, &$mat)
	{
		for ($n = 0; $n < 32; ++$n)
			$square[$n] = self::gf2_matrix_times($mat, $mat[$n]);
	}
	
	/**
	 * Used by crc32_combine.
	 *
	 * @param array $mat
	 * @param int $vec
	 * @return int
	 */
	protected static function gf2_matrix_times($mat, $vec)
	{
		$sum = 0;
		$i = 0;
		while($vec) {
			if($vec & 1)
				$sum ^= $mat[$i];
			$vec = ($vec >> 1) & 0x7FFFFFFF;
			$i++;
		}
		return $sum;
	}
}
