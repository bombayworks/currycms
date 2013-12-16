<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class NullBooleanSelect extends Select {
	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'id' => $entity->getId(),
				'name' => $entity->getFullName(),
			);
		return Entity::html('select', $attr, $this->buildChoices($this->getChoices(), (array)$entity->getValue(), $this->getDisabledChoices()));
	}

	public function getChoices()
	{
		return array(
			'' => 'Not set',
			'true' => 'True',
			'false' => 'False',
		);
	}

	public function getDisabledChoices()
	{
		return array();
	}
}