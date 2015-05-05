<?php

namespace Curry\Form;

class Collection extends Container {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\CollectionWidget';
	protected $entity = null;

	/**
	 * @param mixed $entity
	 */
	public function setEntity($entity)
	{
		$initial = $this->getInitial();
		$rawValue = $this->getRawValue();
		$this->entity = self::createEntity($entity);
		$this->setInitial($initial);
		$this->populate($rawValue);
	}

	/**
	 * @return mixed
	 */
	public function getEntity()
	{
		return $this->entity;
	}

	public function setInitial($data)
	{
		$children = $this->children;
		foreach((array)$data as $k => $v) {
			if (!$this->hasField($k)) {
				$this->addField($k, clone $this->entity);
			} else {
				unset($children[$k]);
			}
			$this->getField($k)->setInitial($v);
		}
		// Deleted entries
		foreach($children as $k => $child) {
			$this->removeField($k);
		}
		$this->addExtra();
	}

	public function getWrapperClass()
	{
		return parent::getWrapperClass().' form-collection';
	}

	public function populate($data)
	{
		$children = $this->children;
		$unchanged = 0;
		foreach((array)$data as $k => $v) {
			if (!$this->hasField($k)) {
				$this->addField($k, clone $this->entity);
			} else {
				unset($children[$k]);
			}
			$field = $this->getField($k);
			$field->populate($v);
			if (!$field->hasChanged()) {
				++$unchanged;
			} else {
				$unchanged = 0;
			}
		}

		// Deleted entries
		foreach($children as $k => $child) {
			$this->removeField($k);
		}

		if (!$unchanged) {
			$this->addExtra();
		}
	}

	public function addExtra($name = null)
	{
		// Add extra
		$entity = clone $this->entity;
		if ($name === null) {
			$name = $this->getNextId();
		}
		$this->addField($name, $entity);
	}

	public function getNextId()
	{
		return count($this->children) ? max(array_keys($this->children)) + 1 : 0;
	}
}