<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class ContainerWidget extends AbstractWidget {
	protected $separateHidden = true;

	public function render(Entity $entity) {
		$normal = array();
		$hidden = array();
		foreach($entity as $name => $field) {
			if ($field->isHidden()) {
				$hidden[$name] = $this->renderHidden($field);
			} else {
				$normal[$name] = $this->renderNormal($field);
			}
		}
		$markup = $this->renderContainer($entity, $normal, $hidden);
		// wrap in form-tag if no parent
		return $entity->getParent() ? $markup : Entity::html('form', array('action' => $entity->getAction(), 'method' => $entity->getMethod()), $markup);
	}

	public function renderContainer(Entity $entity, $normal, $hidden)
	{
		$legend = $entity->getLabel() ? Entity::html($entity->getParent() ? 'legend' : 'h2', array(), htmlspecialchars($entity->getLabel())) : '';
		$markup = $legend.
			$entity->renderDescription().
			$entity->renderErrors().
			join("\n", $hidden).
			join("\n", $normal);
		if ($entity->getParent())
			$markup = Entity::html('fieldset', array(), $markup);
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
		$attr = array('class' => $entity->getContainerClass());
		$markup = $entity->renderLabel().
			$entity->render().
			$entity->renderDescription().
			$entity->renderErrors();
		return Entity::html('div', $attr, $markup);
	}
}