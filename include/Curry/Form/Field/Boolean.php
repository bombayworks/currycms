<?php

namespace Curry\Form\Field;

class Boolean extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\CheckboxInput';
	protected $initial = false;

	public function clean($value)
	{
		return (bool)$value;
	}
}