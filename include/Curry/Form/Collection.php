<?php

namespace Curry\Form;

class Collection extends Container {
	protected $defaultWidget = '\\Curry\\Form\\CollectionWidget';

	public $entity;

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
		$entities = array();
		foreach((array)$data as $k => $v) {
			$entities[$k] = clone $this->entity;
			$entities[$k]->setParent($this);
			$entities[$k]->setInitial($v);
		}
		$entities[] = $entity = clone $this->entity;
		$entity->setParent($this);
		$this->children = $entities;
	}

	public function getContainerClass()
	{
		return parent::getContainerClass().' form-collection';
	}

	function populate($data)
	{
		$unchanged = 0;
		$entities = array();
		foreach((array)$data as $k => $v) {
			$entities[$k] = clone $this->entity;
			$entities[$k]->setParent($this);
			$entities[$k]->populate($v);
			if (!$entities[$k]->hasChanged())
				++$unchanged;
			else
				$unchanged = 0;
		}
		if (!$unchanged) {
			$entities[] = $entity = clone $this->entity;
			$entity->setParent($this);
		}
		$this->children = $entities;
	}
}