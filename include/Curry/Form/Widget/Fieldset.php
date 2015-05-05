<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class Fieldset extends ContainerWidget {

	public function render(Entity $entity)
	{
		$legend = $entity->getLabel() !== '' ? Entity::html('legend', array(), htmlspecialchars($entity->getLabel())) : '';
		$markup = $legend.
			$entity->renderDescription().
			$entity->renderErrors().
			$this->renderChildren($entity);
		return Entity::html('fieldset', $this->attributes, $markup);
	}

	public function isLabelOutside()
	{
		return false;
	}
}