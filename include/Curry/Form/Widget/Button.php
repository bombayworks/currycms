<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class Button extends AbstractWidget {
	protected $type = 'button';
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'id' => $entity->getId(),
				'type' => $this->type,
				'name' => htmlspecialchars($entity->getFullName()),
			);
		return Entity::html('button', $attr, htmlspecialchars($entity->getLabel()));
	}
}