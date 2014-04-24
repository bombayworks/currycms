<?php

namespace Curry\Form\Field;

class StaticText extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\StaticText';
	public $initial = '';

	public function getId()
	{
		return false;
	}

	public function hasChanged()
	{
		return false;
	}

	public function populate($data)
	{
	}
}