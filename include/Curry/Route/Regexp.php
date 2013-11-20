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
 * Uses (perl) regular expression to match URL.
 * 
 * When matched the request will be routed to $target, and named capture
 * groups will be added as parameters.
 * 
 * Example:
 * 	 Regexp: '@^/product/(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2})$@'
 *   Target: '/show-product/'
 *   URL: '/product/2012-11-26'
 * 
 * Will be routed to:
 *   '/show-product/?year=2012&month=11&day=26'
 * 
 * @package Curry\Route
 */
class Curry_Route_Regexp implements Curry_IRoute {
	/**
	 * Regular expression to match against URL.
	 *
	 * @var string
	 */
	protected $regexp;
	
	/**
	 * Target to route request to after successful match.
	 *
	 * @var string
	 */
	protected $target;
	
	/**
	 * Create new regular expression route.
	 *
	 * @param string $regexp Regular expression ta match against URL.
	 * @param string $target Target to route request to after successful match.
	 */
	public function __construct($regexp, $target)
	{
		$this->regexp = $regexp;
		$this->target = $target;
	}
	
	/**
	 * Perform routing.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request)
	{
		if(preg_match($this->regexp, $request->getUri(), $m)) {
			foreach($m as $k => $v) {
				if(is_string($k))
					$request->setParam('get', $k, $v);
			}
			$request->setUri($this->target);
			return true;
		}
		return false;
	}
}
