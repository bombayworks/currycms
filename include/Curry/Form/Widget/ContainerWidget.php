<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class ContainerWidget extends AbstractWidget {
	public function render(Entity $entity) {
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
		return $this->renderContainer($entity, $normal, $hidden);
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
			$markup = Entity::html('fieldset', $this->attributes, $markup);
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
		$markup = ($entity->isLabelOutside() ? $entity->renderLabel() : '').
			$entity->render().
			$entity->renderDescription().
			$entity->renderErrors();
		return Entity::html('div', $attr, $markup);
	}

	public function isLabelOutside()
	{
		return false;
	}
}