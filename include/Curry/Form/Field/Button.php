<?php

namespace Curry\Form\Field;

class Button extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\Button';

	public function isClicked()
	{
		return $this->isPopulated && $this->getValue() !== null;
	}
}