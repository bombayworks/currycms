<?php

namespace Curry\Form\Field;

class Boolean extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\CheckboxInput';
	public $initial = false;

	public function clean($value)
	{
		return (bool)$value;
	}
}