<?php

namespace Curry\Form;

class Form extends Container {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\Form';

	/**
	 * @var string
	 */
	protected $action = null;

	/**
	 * @var string
	 */
	protected $method = 'POST';

	public function getContainerClass()
	{
		return parent::getContainerClass().' form-formset';
	}

	/**
	 * @param string $action
	 */
	public function setAction($action)
	{
		$this->action = $action;
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @param string $method
	 */
	public function setMethod($method)
	{
		$this->method = $method;
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}

	public function setFields($value)
	{
		foreach($this as $k => $value) {
			unset($this->$k);
		}
		return $this->addFields($value);
	}

	public function addField($name, $field)
	{
		// TODO: do not allow the following characters [. ]
		$this->$name = self::createEntity($field);
		return $this;
	}

	public function addFields($fields)
	{
		foreach($fields as $name => $field) {
			$this->addField($name, $field);
		}
		return $this;
	}
}