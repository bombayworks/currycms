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
namespace Curry\Util;

/**
 * Helper class to only execute costly functions when used, and optionally cache the result.
 * 
 * This is useful if you want to provide variables to Twig templates, that
 * are costly and you are not sure if they are going to be used in the
 * template.
 * 
 * Example:
 * <code>
 * $od = new Curry\Util\OnDemand('rand', 1, 10);
 * echo $od->get(); // outputs a number between 1-10
 * echo $od->get(); // outputs the same number
 * </code>
 * 
 * @package Curry\Util
 */
class OnDemand {
	/**
	 * Callback to the costly function.
	 *
	 * @var callback
	 */
	protected $callback;
	
	/**
	 * Additional parameters passed to callback function.
	 *
	 * @var array
	 */
	protected $params;
	
	/**
	 * The actual cached value.
	 *
	 * @var mixed
	 */
	protected $value;
	
	/**
	 * Should we cache the return value of the callback function?
	 *
	 * @var bool
	 */
	protected $cacheValue = true;
	
	/**
	 * Do we have a cached value?
	 *
	 * @var bool
	 */
	protected $hasCachedValue = false;
	
	/**
	 * Constructor
	 *
	 * @todo document the optional arguments once phpdocumentor supports it.
	 * @param callback $callback
	 */
	public function __construct($callback /*, ...*/)
	{
		$this->callback = $callback;
		$this->params = array_slice(func_get_args(), 1);
	}
	
	/**
	 * Specify if caching of the callback-return value should be enabled.
	 *
	 * @param bool $value
	 */
	public function setCacheValue($value)
	{
		$this->cacheValue = $value;
	}
	
	/**
	 * Is caching of the callback return value enabled?
	 *
	 * @return bool
	 */
	public function getCacheValue()
	{
		return $this->cacheValue;
	}
	
	/**
	 * Get the value of the costly function.
	 *
	 * @return mixed
	 */
	public function get()
	{
		if($this->cacheValue) {
			if($this->hasCachedValue)
				return $this->value;
			$this->hasCachedValue = true;
			$this->value = call_user_func_array($this->callback, $this->params);
			return $this->value;
		}
		return call_user_func_array($this->callback, $this->params);
	}
	
	/**
	 * Allow casting in twig templates.
	 *
	 * @return mixed
	 */
	public function toTwig()
	{
		return $this->get();
	}
}
