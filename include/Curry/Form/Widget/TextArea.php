<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class TextArea extends AbstractWidget {
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'id' => $entity->getId(),
				'name' => $entity->getFullName(),
				'required' => $entity->getRequired(),
			);
		return Entity::html('textarea', $attr, htmlspecialchars($entity->getValue()));
	}
}