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
 * Contains information about an HTTP request.
 * 
 * @package Curry
 */
class Curry_Request
{
	/**
	 * Request method.
	 *
	 * @var string
	 */
	protected $method;
	
	/**
	 * Requested URI.
	 *
	 * @var string
	 */
	protected $uri;
	
	/**
	 * GET/POST variables.
	 *
	 * @var array
	 */
	protected $params = array();
	
	/**
	 * Constructor
	 *
	 * @param string $method
	 * @param string $uri
	 */
	public function __construct($method, $uri)
	{
		$this->method = $method;
		$this->uri = $uri;
	}
	
	/**
	 * Add parameter source.
	 * 
	 * Example:
	 * <code>
	 * $request = new Curry_Request('POST', $_SERVER['REQUEST_URI']);
	 * $request->addParamSource('get', $_GET);
	 * </code>
	 *
	 * @param string $name
	 * @param array $content
	 */
	public function addParamSource($name, array $content)
	{
		$this->params[$name] = $content;
	}
	
	/**
	 * Check if this request uses POST method.
	 *
	 * @return bool
	 */
	public function isPost()
	{
		return $this->method == "POST";
	}
	
	/**
	 * Get the request method.
	 *
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}
	
	/**
	 * Set the request method for this request.
	 *
	 * @param string $method
	 */
	public function setMethod($method)
	{
		$this->method = $method;
	}
	
	/**
	 * Get the URI for this request.
	 *
	 * @return string
	 */
	public function getUri()
	{
		return $this->uri;
	}
	
	/**
	 * Set the URI for this request.
	 *
	 * @param string $uri
	 */
	public function setUri($uri)
	{
		$this->uri = $uri;
	}
	
	/**
	 * Get the URI as a Curry_URL object.
	 *
	 * @return Curry_URL
	 */
	public function getUrl()
	{
		return url($this->uri);
	}
	
	/**
	 * Check if the specified parameter exists. This will check all parameter sources.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasParam($name)
	{
		foreach($this->params as $p) {
			if(isset($p[$name]))
				return true;
		}
		return false;
	}
	
	/**
	 * Get parameter value, using default value if parameter doesn't exist.
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getParam($name, $default = '')
	{
		foreach($this->params as $p) {
			if(is_array($name)) {
				foreach($name as $n) {
					if(isset($p[$n]))
						return $p[$n];
				}
			} elseif(isset($p[$name]))
				return $p[$name];
		}
		return $default;
	}
	
	/**
	 * Set parameter value.
	 *
	 * @param string $type Name of parameter source.
	 * @param string $name
	 * @param string $value
	 */
	public function setParam($type, $name, $value)
	{
		$this->params[$type][$name] = $value;
	}
	
	/**
	 * Provide property style access to parameter sources.
	 *
	 * @param string $name
	 * @return array|null
	 */
	public function __get($name)
	{
		if(isset($this->params[$name]))
			return $this->params[$name];
		return null;
	}
	
	/**
	 * Check if parameter source exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->params[$name]);
	}
}
