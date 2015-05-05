<?php

namespace Curry\Form\Field;

class DateTime extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\DateTimeInput';

	public function hasChanged()
	{
		return $this->getValue() != $this->getInitial();
	}

	public function clean($value)
	{
		return $value == '' ? null : new \DateTime($value);
	}
}