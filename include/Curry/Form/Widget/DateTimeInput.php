<?php

namespace Curry\Form\Widget;

class DateTimeInput extends Input {
	protected $type = 'datetime-local';

	protected function getValue($entity)
	{
		$value = $entity->getValue();
		return $value instanceof \DateTime ? $value->format('Y-m-d\TH:i:s') : $value;
	}
}