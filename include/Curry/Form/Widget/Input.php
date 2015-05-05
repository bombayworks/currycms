<?php

namespace Curry\Form\Widget;

use Curry\Form\Entity;

class Input extends AbstractWidget {
	/**
	 * @var string
	 */
	protected $type = 'text';

	/**
	 * @var array
	 */
	protected $datalist = array();

	/**
	 * @param $value string
	 */
	public function setInputType($value)
	{
		$this->attributes['type'] = $value;
	}

	/**
	 * @return string
	 */
	public function getInputType()
	{
		return isset($this->attributes['type']) ? $this->attributes['type'] : $this->type;
	}

	/**
	 * @param $entity
	 * @return string
	 */
	protected function getValue($entity)
	{
		return $entity->getValue();
	}

	public function render(Entity $entity) {
		$datalist = '';
		if (count($this->datalist)) {
			foreach($this->datalist as $value) {
				$datalist .= Entity::html('option', array('value' => $value));
			}
			$datalist = Entity::html('datalist', array('id' => $entity->getId().'-datalist'), $datalist);
		}
		$attr = $this->attributes + array(
				'type' => $this->type,
				'id' => $entity->getId(),
				'required' => $entity->getRequired(),
				'name' => $entity->getFullName(),
				'value' => $this->getValue($entity),
				'list' => $datalist ? $entity->getId().'-datalist' : null,
			);
		return $datalist.Entity::html('input', $attr);
	}

	/**
	 * @return array
	 */
	public function getDatalist()
	{
		return $this->datalist;
	}

	/**
	 * @param array $datalist
	 */
	public function setDatalist($datalist)
	{
		$this->datalist = $datalist;
	}
}