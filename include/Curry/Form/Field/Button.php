<?php

namespace Curry\Form\Field;

class Button extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\Button';

	public function hasChanged()
	{
		return false;
	}

	public function isClicked()
	{
		return $this->isPopulated && $this->getValue() !== null;
	}
}