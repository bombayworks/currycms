<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class Select extends AbstractWidget {
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'id' => $entity->getId(),
				'name' => $entity->getFullName(),
			);
		return Entity::html('select', $attr, $this->buildChoices($entity->getChoices(), (array)$entity->getValue(), $entity->getDisabledChoices()));
	}

	protected function buildChoices($choices, array $selected, array $disabled) {
		$options = " ";
		foreach($choices as $k => $v) {
			if (is_array($v)) {
				$options .= Entity::html('optgroup', array('label' => $k), $this->buildChoices($v, $selected, $disabled));
			} else {
				$attr = array(
					'value' => $k,
					'selected' => in_array($k, $selected),
					'disabled' => in_array($k, $disabled),
				);
				$options .= Entity::html('option', $attr, htmlspecialchars($v));
			}
		}
		return $options;
	}
}