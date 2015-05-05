<?php

namespace Curry\Form\Field;

class Choice extends Field {
	protected $defaultWidget = '\\Curry\\Form\\Widget\\Select';

	/**
	 * @var array
	 */
	protected $choices = array();

	/**
	 * @var array
	 */
	protected $disabledChoices = array();

	/**
	 * @param array $choices
	 */
	public function setChoices($choices)
	{
		$this->choices = $choices;
	}

	/**
	 * @return array
	 */
	public function getChoices()
	{
		return $this->choices;
	}

	/**
	 * @param array $disabledChoices
	 */
	public function setDisabledChoices($disabledChoices)
	{
		$this->disabledChoices = $disabledChoices;
	}

	/**
	 * @return array
	 */
	public function getDisabledChoices()
	{
		return $this->disabledChoices;
	}
}