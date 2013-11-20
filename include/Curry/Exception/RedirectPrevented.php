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
 * Thrown by Curry_URL when setPreventRedirect() has been enabled.
 * 
 * @see Curry_URL::setPreventRedirect()
 * 
 * @package Curry\Exception
 */
class Curry_Exception_RedirectPrevented extends Curry_Exception {
	/**
	 * URL of prevented redirection.
	 *
	 * @var string
	 */
	protected $url;
	
	/**
	 * Constructor
	 *
	 * @param string $url
	 */
	public function __construct($url)
	{
		parent::__construct("A URL redirection was prevented.");
		$this->url = $url;
	}
	
	/**
	 * URL of prevented redirection.
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}
}
