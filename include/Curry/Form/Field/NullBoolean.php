<?php

namespace Curry\Form\Field;

class NullBoolean extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\NullBooleanSelect';

	public function clean($value)
	{
		switch(strtolower($value)) {
			case 'yes':
			case 'true':
			case '1':
				return true;
			case 'no':
			case 'false':
			case '0':
				return false;
			default:
				return null;
		}
	}
}