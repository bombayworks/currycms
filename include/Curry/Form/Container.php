<?php

namespace Curry\Form;

class Container extends Entity implements \IteratorAggregate, \Countable {
	/**
	 * @var array
	 */
	protected $children = array();

	public function getInitial()
	{
		return array_map(function($child) { return $child->getInitial(); }, $this->children);
	}

	public function getRawValue()
	{
		return array_map(function($child) { return $child->getRawValue(); }, $this->children);
	}

	public function getValue()
	{
		return array_map(function($child) { return $child->getValue(); }, $this->children);
	}

	public function setInitial($data)
	{
		foreach($this->children as $name => $child) {
			if (isset($data[$name])) {
				$child->setInitial($data[$name]);
			}
		}
	}

	public function populate($data)
	{
		foreach($this->children as $name => $child) {
			$child->populate(isset($data[$name]) ? $data[$name] : null);
		}
	}

	public function hasChanged()
	{
		foreach($this->children as $child) {
			if ($child->hasChanged())
				return true;
		}
		return false;
	}

	public function getChangedFields()
	{
		$changed = array();
		foreach($this->children as $name => $child) {
			if ($child->hasChanged())
				$changed[] = $name;
		}
		return $changed;
	}

	public function isMultiPart()
	{
		foreach($this->children as $child) {
			if ($child->isMultiPart())
				return true;
		}
		return false;
	}

	public function getContainerClass()
	{
		return 'form-container';
	}

	public function getName()
	{
		return $this->parent ? parent::getName() : null;
	}

	public function getEntityName($entity)
	{
		$pos = array_search($entity, $this->children, true);
		if ($pos === false)
			throw new \Exception('Field not found');
		return $pos;
	}

	public function getIterator()
	{
		$index = 1;
		$names = array();
		$orders = array();
		$positions = array();
		foreach($this->children as $name => $child) {
			$order = $child->getOrder();
			if ($order === null)
				$order = $index;
			$names[] = $name;
			$orders[] = $order;
			$positions[] = $index++;
		}

		$sorted = array();
		array_multisort($orders, $positions, $names);
		foreach($names as $name) {
			$sorted[$name] = $this->children[$name];
		}
		return new \ArrayIterator($sorted);
	}

	public function count()
	{
		return \count($this->children);
	}

	public function __get($name)
	{
		return isset($this->children[$name]) ? $this->children[$name] : null;
	}
	public function __set($name, Entity $value)
	{
		if ($value->parent) {
			$otherName = $value->getName();
			unset($value->parent->$otherName);
		}
		if (isset($this->$name)) {
			unset($this->$name);
		}
		$this->children[$name] = $value;
		$value->parent = $this;
	}
	public function __isset($name)
	{
		return isset($this->children[$name]);
	}
	public function __unset($name)
	{
		if (!isset($this->children[$name]))
			throw new \Exception('Field "'.$name.'" not found');
		$this->children[$name]->parent = null;
		unset($this->children[$name]);
	}

	public function __clone()
	{
		parent::__clone();
		$children = $this->children;
		$this->children = array();
		foreach($children as $name => $child) {
			$this->$name = clone $child;
		}
	}
}