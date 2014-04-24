<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class RadioSelect extends AbstractWidget {
	public function render(Entity $entity) {
		$values = (array)$entity->getValue();
		return $this->buildChoices($entity->getFullName(), $entity->getId(), $entity->getChoices(), $values, $entity->getDisabledChoices());
	}

	protected function buildChoices($name, $id, $choices, $selected, $disabled) {
		$output = " ";
		foreach($choices as $k => $v) {
			if (is_array($v)) {
				$output .= Entity::html('div', array(), Entity::html('span', array(), $k).$this->buildChoices($name, $id, $v, $selected, $disabled));
			} else {
				$attr = array(
					'type' => 'radio',
					'value' => $k,
					'checked' => in_array($k, $selected),
					'disabled' => in_array($k, $disabled),
					'id' => $id.'-'.$k,
					'name' => $name,
				);
				$output .= Entity::html('label', array('for' => $attr['id']), Entity::html('input', $attr).' '.htmlspecialchars($v).' ');
			}
		}
		return $output;
	}
}