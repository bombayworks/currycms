<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class Form extends ContainerWidget {
	public function render(Entity $entity)
	{
		$attr = $this->attributes + array(
				'enctype' => $entity->isMultiPart() ? 'multipart/form-data' : null,
				'action' => $entity->getAction(),
				'method' => $entity->getMethod()
			);
		$markup = parent::render($entity);
		return $entity->getParent() ?
			$markup :
			Entity::html('form', $attr, $markup);
	}

}