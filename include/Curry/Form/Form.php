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
}