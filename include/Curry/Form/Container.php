<?php

namespace Curry\Form;

class Container extends Entity implements \IteratorAggregate, \Countable {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\ContainerWidget';

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
		$data = array_intersect_key($data, $this->children);
		foreach($data as $name => $value) {
			$this->children[$name]->setInitial($value);
		}
	}

	public function setValue($data)
	{
		$data = array_intersect_key($data, $this->children);
		foreach($data as $name => $value) {
			$this->children[$name]->setValue($value);
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

	public function getWrapperClass()
	{
		return trim('form-entity form-container '.parent::getWrapperClass());
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
		return new \ArrayIterator($this->getFields(true));
	}

	public function count()
	{
		return \count($this->children);
	}

	public function hasField($name)
	{
		return isset($this->children[$name]);
	}

	public function getFields($ordered = false)
	{
		if (!$ordered)
			return $this->children;

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
		return $sorted;
	}

	public function getField($name)
	{
		return isset($this->children[$name]) ? $this->children[$name] : null;
	}

	public function addField($name, $field)
	{
		// TODO: do not allow the following characters [. ]
		$field = self::create($field);
		if ($field->parent) {
			$field->parent->removeField($field->getName());
		}
		if (isset($this->children[$name])) {
			$this->removeField($name);
		}
		$this->children[$name] = $field;
		$field->parent = $this;
		return $this;
	}

	public function removeField($name)
	{
		if (!isset($this->children[$name]))
			throw new \Exception('Field "'.$name.'" not found');
		$this->children[$name]->parent = null;
		unset($this->children[$name]);
		return $this;
	}

	public function setFields($value)
	{
		foreach($this->getFields() as $k => $v) {
			$this->removeField($k);
		}
		return $this->addFields($value);
	}

	public function addFields($fields)
	{
		foreach($fields as $name => $field) {
			$this->addField($name, $field);
		}
		return $this;
	}

	public function __get($name)
	{
		return $this->getField($name);
	}
	public function __set($name, $value)
	{
		$this->addField($name, $value);
	}
	public function __isset($name)
	{
		return $this->hasField($name);
	}
	public function __unset($name)
	{
		$this->removeField($name);
	}

	public function __clone()
	{
		parent::__clone();
		$children = $this->children;
		$this->children = array();
		foreach($children as $name => $child) {
			$this->addField($name, clone $child);
		}
	}
}