<?php

namespace Curry\Form;

class Container extends Entity implements \IteratorAggregate, \Countable {
	/**
	 * @var array
	 */
	public $children = array();

	public function renderLabel()
	{
		return '';
	}

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
		return new \ArrayIterator($this->children);
	}

	public function count()
	{
		return \count($this->children);
	}

	public function __get($name)
	{
		return isset($this->children[$name]) ? $this->children[$name] : null;
	}
	public function __set($name, $value)
	{
		$this->children[$name] = $value;
	}
	public function __isset($name)
	{
		return isset($this->children[$name]);
	}
	public function __unset($name)
	{
		unset($this->children[$name]);
	}

	public function __clone()
	{
		parent::__clone();
		foreach($this->children as $k => $v) {
			$this->children[$k] = clone $v;
			$this->children[$k]->setParent($this);
		}
	}
}