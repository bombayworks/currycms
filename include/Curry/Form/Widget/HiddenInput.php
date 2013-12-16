<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class HiddenInput extends Input {
	protected $type = 'hidden';
	public function render(Entity $entity) {
		$value = $entity->getValue();
		$attr = $this->attributes + array(
				'type' => $this->type,
				'id' => $entity->getId(),
				'name' => $entity->getFullName(),
			);
		if ($entity->isArray()) {
			$output = "";
			foreach($value as $v) { // TODO: does not preserve keys, is this ok?
				$output .= Entity::html('input', $attr + array('value' => $v));
			}
			return $output;
		}
		return Entity::html('input', $attr + array('value' => $value));
	}

	public function isHidden() {
		return true;
	}
}