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
 * Class used to specify how to cache front end modules.
 * 
 * This object should be used by Curry_Module::getCacheProperties()
 * to specify how to cache the module.
 * 
 * @see Curry_Module::getCacheProperties()
 * @package Curry
 */
class Curry_CacheProperties {
	/**
	 * Parameters that together creates the unique cache-entry.
	 *
	 * @var array
	 */
	private $params;
	
	/**
	 * Lifetime (in seconds) of the cache, or false for infinite.
	 *
	 * @var int|bool
	 */
	private $lifetime;
	
	/**
	 * Specifies caching properties.
	 *
	 * @param array $params			Parameters that together creates the unique cache-entry.
	 * @param bool|int $lifetime	Cache lifetime in seconds, or false for infinite.
	 */
	public function __construct(array $params = array(), $lifetime = false)
	{
		$this->params = $params;
		$this->lifetime = $lifetime;
	}
	
	/**
	 * Cache lifetime in seconds, or false for infinite.
	 *
	 * @return int|bool
	 */
	public function getLifetime()
	{
		return $this->lifetime;
	}
	
	/**
	 * Parameters that together creates the unique cache-entry.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}
}
