<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class CheckboxInput extends Input {
	protected $type = 'checkbox';
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'type' => $this->type,
				'id' => $entity->getId(),
				'required' => $entity->getRequired(),
				'name' => $entity->getFullName(),
				//'value' => $entity->getValue(),
				'checked' => (bool)$entity->getValue(),
			);
		return Entity::html('input', $attr);
	}
}