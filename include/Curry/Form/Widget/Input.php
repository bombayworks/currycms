<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class Input extends AbstractWidget {
	protected $type = 'text';
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'type' => $this->type,
				'id' => $entity->getId(),
				'required' => $entity->getRequired(),
				'name' => $entity->getFullName(),
				'value' => $entity->getValue(),
			);
		return Entity::html('input', $attr);
	}
}