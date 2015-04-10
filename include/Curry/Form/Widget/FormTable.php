<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class FormTable extends Form {

	public function renderBody(Entity $entity)
	{
		$title = $entity->getLabel() !== '' ? Entity::html('label', array(), htmlspecialchars($entity->getLabel())) : '';
		return $title.
			$entity->renderDescription().
			$entity->renderErrors().
			$this->renderChildren($entity, true).
			Entity::html('table', array(), Entity::html('tbody', array(), $this->renderChildren($entity, false)));
	}

	public function renderNormal(Entity $entity)
	{
		$attr = array('class' => $entity->getWrapperClass());
		return Entity::html('tr', $attr,
			Entity::html('td', array(), ($entity->isLabelOutside() ? $entity->renderLabel() : '&nbsp;')).
			Entity::html('td', array(), $entity->render().$entity->renderDescription().$entity->renderErrors())
		);
	}
}