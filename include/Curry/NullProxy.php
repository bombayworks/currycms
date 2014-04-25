<?php

namespace Curry;

class NullProxy implements \ArrayAccess {
	public function __get($name) { return $this; }
	public function __set($name, $value) { }
	public function __isset($name) { return false; }
	public function __unset($name) { }

	public function __call($name, $arguments) { return $this; }
	public function __invoke() { return $this; }

	public function offsetGet($name) { return $this; }
	public function offsetSet($name, $value) { }
	public function offsetExists($name) { return false; }
	public function offsetUnset($name) { }
}
