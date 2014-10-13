<?php

namespace Curry\Form\Field;

class Date extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\DateInput';

	public function clean($value)
	{
		if ($value === '')
			return null;
		// Normalize time to 00:00:00
		$dt = new \DateTime($value);
		$dt->setTime(0,0,0);
		return $dt;
	}
}