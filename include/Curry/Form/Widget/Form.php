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
		$title = $entity->getLabel() !== '' ? Entity::html('label', array(), htmlspecialchars($entity->getLabel())) : '';
		$markup = $title.
			$entity->renderDescription().
			$entity->renderErrors().
			$this->renderChildren($entity);
		return Entity::html('form', $attr, $markup);
	}
}