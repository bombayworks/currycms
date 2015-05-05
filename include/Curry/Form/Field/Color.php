<?php

namespace Curry\Form\Field;

class Color extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\ColorInput';
	protected $initial = '#000000';

	public function clean($value)
	{
		return preg_match('/^#[0-9a-zA-Z]{6}$/', $value) ? $value : null;
	}
}