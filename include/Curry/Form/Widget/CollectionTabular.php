<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class CollectionTabular extends CollectionWidget {
	public function renderContainer(Entity $entity, $normal, $hidden)
	{
		$markup = '';
		foreach($entity as $subEntity) {
			$markup .= '<thead><tr>';
			foreach($subEntity as $columnEntity) {
				$markup .= '<th>'.$columnEntity->renderLabel().'</th>';
			}
			$markup .= '</tr></thead>';
			break;
		}
		$markup .= Entity::html('tbody', array(), join("\n", $normal));
		return Entity::html('table', array(), $markup);
	}

	/*
	public function render(Entity $entity)
	{
		return $entity->renderLabel().
			$entity->renderDescription().
			$entity->renderErrors().
			$entity->renderWidget($this);
	}
	*/

	public function renderNormal(Entity $entity) {
		$markup = "";
		foreach($entity as $columnEntity) {
			$attr = array('class' => $columnEntity->getContainerClass());
			$markup .= Entity::html('td', $attr, $columnEntity->render());
		}
		$attr = $this->attributes + array('class' => $entity->getContainerClass());
		return Entity::html('tr', $attr, $markup);
	}
}