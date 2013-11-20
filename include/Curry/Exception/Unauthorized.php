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
 * Thrown when trying to do an unauthorized operation.
 * 
 * Returns 401 HTTP status code.
 * 
 * @package Curry\Exception
 */
class Curry_Exception_Unauthorized extends Curry_Exception_HttpError {
	/**
	 * Constructor
	 *
	 * @param string $message
	 */
	public function __construct($message = 'Unauthorized')
	{
		parent::__construct(401, $message);
	}
}