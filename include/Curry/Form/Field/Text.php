<?php

namespace Curry\Form\Field;

class Text extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\TextInput';
	protected $initial = '';

	public function clean($value)
	{
		return (string)$value;
	}
}