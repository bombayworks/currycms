<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class Form extends ContainerWidget {
	public function render(Entity $entity)
	{
		$attr = $this->attributes + array(
				'class' => 'form',
				'enctype' => $entity->isMultiPart() ? 'multipart/form-data' : null,
				'action' => $entity->getAction(),
				'method' => $entity->getMethod()
			);
		return Entity::html('form', $attr, $this->renderBody($entity));
	}

	public function renderBody(Entity $entity)
	{
		$title = $entity->getLabel() !== '' ? Entity::html('label', array(), htmlspecialchars($entity->getLabel())) : '';
		return $title.
			$entity->renderDescription().
			$entity->renderErrors().
			$this->renderChildren($entity);
	}
}