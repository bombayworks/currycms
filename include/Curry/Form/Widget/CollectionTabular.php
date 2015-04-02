<?php

namespace Curry\Form\Widget;

use Curry\Form\Container;
use Curry\Form\Entity;

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
		return join("\n", $hidden).Entity::html('table', $this->attributes, $markup);
	}

	public function renderNormal(Entity $entity) {
		$markup = "";
		foreach($entity as $columnEntity) {
			if (!$entity instanceof Container)
				throw new \Exception('CollectionTabular requires entities to be subclass of \\Curry\\Form\\Container');
			$attr = array('class' => $columnEntity->getWrapperClass());
			$markup .= Entity::html('td', $attr, $columnEntity->render());
		}
		$attr = array('class' => $entity->getWrapperClass());
		return Entity::html('tr', $attr, $markup);
	}

	public function isLabelOutside()
	{
		return true;
	}
}