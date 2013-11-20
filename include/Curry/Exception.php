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
 * Base exception for Curry.
 * 
 * @package Curry\Exception
 */
class Curry_Exception extends Exception {
	/**
	 * Construct new exception.
	 *
	 * @param string $message
	 * @param int $code
	 */
	public function __construct($message, $code = 0/*, Exception $previous = null*/)
	{
		parent::__construct($message, $code/*, $previous*/);
	}
}
