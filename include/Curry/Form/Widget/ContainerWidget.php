<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class ContainerWidget extends AbstractWidget {
	public function render(Entity $entity)
	{
		return Entity::html('div', $this->attributes, $this->renderChildren($entity));
	}

	public function renderChildren(Entity $entity)
	{
		if (!$entity instanceof \Curry\Form\Container)
			throw new \Exception('ContainerWidget requires entities to be a subclass of \\Curry\\Form\\Container');
		$normal = array();
		$hidden = array();
		foreach($entity as $name => $field) {
			if ($field->isHidden()) {
				$hidden[$name] = $this->renderHidden($field);
			} else {
				$normal[$name] = $this->renderNormal($field);
			}
		}
		return join("\n", $hidden).join("", $normal);
	}

	public function renderChild(Entity $entity)
	{
		return $entity->isHidden() ? $this->renderHidden($entity) : $this->renderNormal($entity);
	}

	public function renderHidden(Entity $entity)
	{
		return $entity->render();
	}

	public function renderNormal(Entity $entity)
	{
		$attr = array('class' => $entity->getWrapperClass());
		$markup = ($entity->isLabelOutside() ? $entity->renderLabel() : '').
			$entity->render().
			$entity->renderDescription().
			$entity->renderErrors();
		return Entity::html('div', $attr, $markup);
	}
}