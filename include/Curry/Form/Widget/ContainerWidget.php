<?php

namespace Curry\Form\Widget;

use Curry\Form\Container;
use Curry\Form\Entity;

class ContainerWidget extends AbstractWidget {
	public function render(Entity $entity)
	{
		return Entity::html('div', $this->attributes, $this->renderChildren($entity));
	}

	public function renderChildren(Container $entity, $hidden = null)
	{
		$markup = "";
		foreach($entity as $name => $field) {
			if ($hidden === null || $field->isHidden() === $hidden) {
				$markup .= $this->renderChild($field);
			}
		}
		return $markup;
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