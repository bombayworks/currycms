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
 * Interface to create routes to pages.
 * 
 * @package Curry\Route
 */
interface Curry_IRoute {
	/**
	 * May return one of the following:
	 * * false: No routing performed.
	 * * true: Routing was performed, and the Curry_Request object was modified, continue routing.
	 * * Page: The final page to show.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request);
}
