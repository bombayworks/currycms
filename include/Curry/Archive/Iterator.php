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
 * Iterator classed used to iterate over the entries inside an archive.
 *
 * @package Curry\Archive
 */
class Iterator extends Reader implements \Iterator
{
	/**
	 * Current entry.
	 *
	 * @var FileInfo
	 */
	protected $currentEntry = null;

	/**
	 * Options passed to Curry_Archive_FileInfo.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Create iterator instance.
	 *
	 * @param Archive $archive
	 * @param array $options
	 */
	public function __construct(Archive $archive, $options = array())
	{
		parent::__construct($archive->getFilename(), $archive->getCompression());
		$this->options = $options;
	}

	/**
	 * Reset iterator to point to the first element.
	 */
	public function rewind()
	{
		$this->nextPos = 0;
		$this->currentEntry = $this->readEntry($this->options);
	}

	/**
	 * Get the current element
	 *
	 * @return FileInfo|null
	 */
	public function current()
	{
		return $this->currentEntry;
	}

	/**
	 * The pathname of the current entry
	 *
	 * @return string
	 */
	public function key()
	{
		return $this->currentEntry ? $this->currentEntry->getPathname() : null;
	}

	/**
	 * Move iterator to the next entry.
	 */
	public function next()
	{
		$this->currentEntry = $this->readEntry($this->options);
	}

	/**
	 * Check if we have reached the end of the archive.
	 *
	 * @return bool
	 */
	public function valid()
	{
		return $this->currentEntry !== null;
	}
}
