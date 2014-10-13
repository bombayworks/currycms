<?php

namespace Curry\Form\Field;

class Field extends \Curry\Form\Entity {
	/**
	 * @var bool
	 */
	protected $required = false;

	/**
	 * @var mixed
	 */
	protected $initial; // default/initial/value

	/**
	 * @var mixed
	 */
	protected $rawValue;

	/**
	 * @var mixed
	 */
	protected $value;

	/**
	 * @var bool
	 */
	protected $isPopulated = false;

	public function getContainerClass()
	{
		$type = get_class($this);
		$pos = strrpos($type, '\\');
		if ($pos !== false)
			$type = substr($type, $pos + 1);
		return 'form-field'.
			' form-field-'.strtolower($type).
			($this->getRequired() ? ' form-required' : '').
			($this->hasErrors() ? ' form-errors' : '');
	}

	public function getRawValue()
	{
		return $this->rawValue;
	}

	public function hasChanged()
	{
		return $this->getValue() !== $this->getInitial();
	}

	public function clean($value)
	{
		return $value;
	}

	public function populate($data)
	{
		$this->rawValue = $data;
		$this->value = $this->clean($data);
		$this->isPopulated = true;
	}

	/**
	 * @param mixed $initial
	 */
	public function setInitial($initial)
	{
		$this->initial = $this->clean($initial);
	}

	/**
	 * @return mixed
	 */
	public function getInitial()
	{
		return $this->initial;
	}

	/**
	 * @param boolean $required
	 */
	public function setRequired($required)
	{
		$this->required = (bool)$required;
	}

	/**
	 * @return boolean
	 */
	public function getRequired()
	{
		return $this->required;
	}

	/**
	 * @param mixed $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->isPopulated ? $this->value : $this->initial;
	}
}