<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class File extends Input {
	protected $type = 'file';

	public function needsMultiPart()
	{
		return true;
	}

	public function render(Entity $entity) {
		$attr = $this->attributes + array(
				'type' => $this->type,
				'id' => $entity->getId(),
				'required' => $entity->getRequired(),
				'name' => $entity->getFullName(),
				'multiple' => $entity->isArray(),
			);
		return Entity::html('input', $attr);
	}
}