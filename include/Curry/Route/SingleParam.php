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
 * Routes everything below a page and convert remainder of URL to parameter.
 * 
 * Example:
 *   Base: '/product/'
 *   Target: '/show-product/'
 *   Param: 'date'
 *   URL: '/product/2012-11-26'
 * 
 * Will be routed to:
 *   '/show-product/?date=2012-11-26'
 * 
 * @todo Implement support for reverse-routing.
 * @package Curry\Route
 */
class Curry_Route_SingleParam implements Curry_IRoute {
	/**
	 * The base URL. URLs beginning with this will be routed.
	 *
	 * @var string
	 */
	protected $base;
	
	/**
	 * The target page to rewrite matched URLs to.
	 *
	 * @var string
	 */
	protected $target;
	
	/**
	 * Name of parameter to store remaining part of URL after successful match.
	 *
	 * @var string
	 */
	protected $param;
	
	/**
	 * Creates a new route.
	 *
	 * @param string $base
	 * @param string $target
	 * @param string $param
	 */
	public function __construct($base, $target, $param)
	{
		$this->base = $base;
		$this->target = $target;
		$this->param = $param;
	}
	
	/**
	 * Perform routing.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request)
	{
		$uri = $request->getUri();
		$qpos = strpos($uri, '?');
		if($qpos !== false)
			$uri = substr($uri, 0, $qpos);
			
		if(substr($uri, 0, strlen($this->base)) == $this->base && strlen($uri) > strlen($this->base)) {
			$remaining = substr($uri, strlen($this->base));
			
			// remove trailing slash
			if (substr($remaining, -1) == '/')
				$remaining = substr($remaining, 0, -1);
				
			$request->setParam('get', $this->param, $remaining);
			$request->setUri($this->target);
			return true;
		}
		return false;
	}
}
