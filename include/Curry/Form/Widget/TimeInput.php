<?php

namespace Curry\Form\Widget;

class TimeInput extends Input {
	protected $type = 'time';

	protected function getValue($entity)
	{
		$value = $entity->getValue();
		return $value instanceof \DateTime ? $value->format('H:i:00') : $value;
	}
}