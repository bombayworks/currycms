<?php

namespace Curry\Form\Widget;

class DateInput extends Input {
	protected $type = 'date';

	protected function getValue($entity)
	{
		$value = $entity->getValue();
		return $value instanceof \DateTime ? $value->format('Y-m-d') : $value;
	}
}