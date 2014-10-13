<?php

namespace Curry;

class ServiceContainer implements \ArrayAccess {
	protected $configuration = array();
	protected $services = array();

	public function singleton($name, $value)
	{
		$this->services[$name] = function(ServiceContainer $app) use($value) {
			static $object = null;
			if ($object === null)
				$object = $app->_create($value);
			return $object;
		};
	}

	/**
	 * Internal function used by singleton to bypass php scoping issues.
	 *
	 * @param $value
	 * @return mixed
	 */
	public function _create($value)
	{
		return call_user_func($value, $this);
	}

	public function factory($name, $value)
	{
		$this->services[$name] = $value;
	}

	public function __get($name)
	{
		if (!isset($this->services[$name]))
			throw new \Exception("Service `$name` not registered");
		return $this->services[$name]($this);
	}

	public function __set($name, $value)
	{
		$this->services[$name] = function() use ($value) {
			return $value;
		};
	}

	public function __isset($name)
	{
		return isset($this->services[$name]);
	}

	public function __unset($name)
	{
		unset($this->services[$name]);
	}

	public function __call($name, $arguments)
	{
		if (!isset($this->services[$name]))
			throw new \Exception("Service `$name` not registered");

		array_unshift($arguments, $this);
		return call_user_func_array($this->services[$name], $arguments);
	}

	public function offsetGet($name)
	{
		return $this->configuration[$name];
	}

	public function offsetSet($name, $value)
	{
		$this->configuration[$name] = $value;
	}

	public function offsetExists($name)
	{
		return isset($this->configuration[$name]);
	}

	public function offsetUnset($name)
	{
		unset($this->configuration[$name]);
	}
}