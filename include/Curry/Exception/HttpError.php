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
 * Fatal exception with HTTP error code.
 *
 * @package Curry\Exception
 */
class Curry_Exception_HttpError extends Curry_Exception {
	/**
	 * HTTP error code.
	 *
	 * @var int
	 */
	protected $statusCode;
	
	/**
	 * Constructor
	 *
	 * @param int $statusCode
	 * @param string $message
	 */
	public function __construct($statusCode, $message)
	{
		parent::__construct($message);
		$this->statusCode = $statusCode;
	}
	
	/**
	 * Get HTTP status/error code.
	 *
	 * @return int
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}
}