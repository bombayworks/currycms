<?php

namespace Curry\Form\Field;

class Number extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\NumberInput';
	protected $initial = 0;

	public function clean($value)
	{
		return 0 + $value;
	}
}