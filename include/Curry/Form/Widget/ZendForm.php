<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class ZendForm extends Form {
	public function renderBody(Entity $entity)
	{
		$legend = $entity->getLabel() ? Entity::html($entity->getParent() ? 'legend' : 'h2', array(), htmlspecialchars($entity->getLabel())) : '';
		$markup = $legend.
			$entity->renderDescription().
			$entity->renderErrors().
			$this->renderChildren($entity, true).
			Entity::html('dl', array('class' => 'zend_form'), $this->renderChildren($entity, false));
		if ($entity->getParent())
			$markup = Entity::html('fieldset', array(), $markup);
		return $markup;
	}

	public function renderNormal(Entity $entity)
	{
		$attr = array('id' => $entity->getId().'-label', 'class' => $entity->getWrapperClass());
		return Entity::html('dt', $attr, $entity->isLabelOutside() ? $entity->renderLabel() : '').
		Entity::html('dd', array('id' => $entity->getId().'-element'), $entity->render().
			$entity->renderDescription().
			$entity->renderErrors());
	}
}