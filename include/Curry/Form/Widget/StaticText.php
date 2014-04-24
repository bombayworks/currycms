<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class StaticText extends AbstractWidget {
	public function render(Entity $entity) {
		return Entity::html('span', array(), htmlspecialchars($entity->getInitial()));
	}
}