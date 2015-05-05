<?php

namespace Curry;

abstract class Configurable {
	protected $prioritizedOptions = array();

	public function __construct($options = null)
	{
		if ($options !== null)
			$this->setOptions($options);
	}

	public function getOption($name)
	{
		$method = 'get' . ucfirst($name);
		if (method_exists($this, $method)) {
			return $this->$method();
		} else {
			return $this->getOptionFallback($name);
		}
	}

	public function setOption($name, $value)
	{
		$method = 'set' . ucfirst($name);
		if (method_exists($this, $method)) {
			$this->$method($value);
		} else {
			$this->setOptionFallback($name, $value);
		}
		return $this;
	}

	public function setOptions(array $options)
	{
		foreach(array_intersect_key($options, $this->prioritizedOptions) as $name => $value) {
			$this->setOption($name, $value);
			unset($options[$name]);
		}
		foreach($options as $name => $value) {
			$this->setOption($name, $value);
		}
		return $this;
	}

	protected function getOptionFallback($name)
	{
		throw new \Exception('Unable to get unknown option "'.$name.'".');
	}

	protected function setOptionFallback($name, $value)
	{
		throw new \Exception('Unable to set unknown option "'.$name.'".');
	}
}
