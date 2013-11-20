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
 * Routes everything below a page and converts slugs to parameters.
 * 
 * Example:
 *   Base: '/product/'
 *   Target: '/show-product/'
 *   URL: '/product/year/2012/month/11/day/26/'
 * 
 * Will be routed to:
 *   '/show-product/?year=2012&month=11&day=26'
 * 
 * @todo Implement support for reverse-routing.
 * 
 * @package Curry\Route
 */
class Curry_Route_Params implements Curry_IRoute {
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
	 * Creates a new route.
	 *
	 * @param string $base Base url, all URLs below this will be rewritten.
	 * @param string|null $target The target page to rewrite matched URLs to, if null this will be the same as $base.
	 */
	public function __construct($base, $target = null)
	{
		$this->base = $base;
		$this->target = $target !== null ? $target : $base;
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
		if(substr($uri, 0, strlen($this->base)) == $this->base) {
			$remaining = substr($uri, strlen($this->base));
			$name = null;
			foreach(explode('/', $remaining) as $param) {
				if($name) {
					$request->setParam('get', $name, $param);
					$name = null;
				} else {
					$name = $param;
				}
			}
			$request->setUri($this->target);
			return true;
		}
		return false;
	}
}
