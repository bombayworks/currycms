<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class FormTable extends ContainerWidget {
	public function renderContainer(Entity $entity, $normal, $hidden) {
		$caption = $entity->getLabel() ? Entity::html('caption', array(), htmlspecialchars($entity->getLabel())) : '';
		return $entity->renderDescription().
		$entity->renderErrors().
		join("\n", $hidden).
		Entity::html('table', array(), $caption.Entity::html('tbody', array(), join("\n", $normal)));
	}

	public function renderNormal(Entity $entity)
	{
		$attr = array('class' => $entity->getContainerClass());
		return Entity::html('tr', $attr,
			Entity::html('td', array(), $entity->renderLabel()).
			Entity::html('td', array(), $entity->render().$entity->renderDescription().$entity->renderErrors())
		);
	}
}