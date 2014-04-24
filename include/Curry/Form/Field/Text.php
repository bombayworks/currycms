<?php

namespace Curry\Form\Field;

class Text extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\TextInput';
	public $initial = '';
}