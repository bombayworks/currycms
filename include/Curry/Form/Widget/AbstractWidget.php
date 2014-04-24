<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

/**
 * Class Widget
 *
 * Widget
 * Full (label, widget, description, errors)
 * Row (wrapper)
 *
 * @package Curry\Form
 */
abstract class AbstractWidget extends \Curry\Configurable {
	protected $attributes = array();

	public function setOptionFallback($name, $value)
	{
		$this->attributes[$name] = $value;
	}

	abstract public function render(Entity $entity);

	public function isHidden() {
		return false;
	}

	public function needsMultiPart()
	{
		return false;
	}
}