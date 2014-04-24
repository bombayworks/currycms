<?php

namespace Curry\Form\Field;

class MultipleChoice extends Choice {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\SelectMultiple';

	public function isArray()
	{
		return true;
	}
}
