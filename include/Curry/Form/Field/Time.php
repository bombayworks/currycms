<?php

namespace Curry\Form\Field;

class Time extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\TimeInput';

	public function clean($value)
	{
		if ($value === '')
			return null;
		// Normalize date to current day
		$dt = new \DateTime($value);
		$now = new \DateTime();
		$dt->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
		return $dt;
	}
}