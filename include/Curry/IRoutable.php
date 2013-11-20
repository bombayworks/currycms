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
 * Interface for routable objects.
 * 
 * @package Curry\Route
 */
interface Curry_IRoutable {
	/**
	 * Return parameters from slug.
	 *
	 * @param array|null $slug
	 */
	public static function getParamFromSlug($slug);
	
	/**
	 * Get slug for object.
	 * 
	 * @return string
	 */
	public function getSlug();
}
