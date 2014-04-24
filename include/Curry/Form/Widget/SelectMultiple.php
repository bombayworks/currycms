<?php

namespace Curry\Form\Widget;

use \Curry\Form\Entity;

class SelectMultiple extends Select {
	public function __construct()
	{
		$this->setOption('multiple', true);
	}
}