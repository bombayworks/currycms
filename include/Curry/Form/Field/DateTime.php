<?php

namespace Curry\Form\Field;

class DateTime extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\DateTimeInput';

	public function clean($value)
	{
		return $value == '' ? null : new \DateTime($value);
	}
}