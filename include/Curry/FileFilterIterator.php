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
 * FilterIterator for DirectoryIterator to filter filenames using a regular expression.
 * 
 * @package Curry
 *
 */
class Curry_FileFilterIterator extends FilterIterator {
	/**
	 * Regular expression used to match filename.
	 *
	 * @var string
	 */
	private $regexp;
	
	/**
	 * Construct a filter and apply it to passed iterator.
	 *
	 * @param Iterator $it	Should be an instance of DirectoryIterator.
	 * @param string $regexp	Regular expression to match
	 */
	public function __construct($it, $regexp)
	{
		parent::__construct($it);
		$this->regexp = $regexp;
	}
	
	/**
	 * Matches the current element of the iterator using the regular expression.
	 *
	 * @return bool
	 */
	public function accept()
	{
		$file = $this->current();
		if(!$file->isFile())
			return false;
			
		return preg_match($this->regexp, $file->getFilename());
	}
}
