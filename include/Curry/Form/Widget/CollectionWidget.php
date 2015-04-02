<?php

namespace Curry\Form\Widget;

use Curry\Form\Collection;
use Curry\Form\Entity;

class CollectionWidget extends ContainerWidget {
	/**
	 * @var bool
	 */
	protected $cloneInfo = true;

	/**
	 * @return boolean
	 */
	public function getCloneInfo()
	{
		return $this->cloneInfo;
	}

	/**
	 * @param boolean $cloneInfo
	 */
	public function setCloneInfo($cloneInfo)
	{
		$this->cloneInfo = $cloneInfo;
	}

	protected function addCloneInfo(Entity $entity)
	{
		if (!$entity instanceof Collection)
			throw new \Exception('Expected \Curry\Form\Collection, got '.get_class($entity).' when rendering field');
		$name = uniqid('formhelper');
		$entity->addExtra($name);
		$cloneMarkup = $this->renderNormal($entity->$name);
		unset($entity->$name);

		$this->attributes['data-clone'] = $cloneMarkup;
		$this->attributes['data-clone-id'] = $name;
		$this->attributes['data-clone-next'] = $entity->getNextId();
	}

	public function render(Entity $entity)
	{
		if ($this->cloneInfo) {
			$this->addCloneInfo($entity);
		}
		return parent::render($entity);
	}
}