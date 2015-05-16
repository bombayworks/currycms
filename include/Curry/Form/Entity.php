<?php

namespace Curry\Form;

use Curry\Configurable;
use Curry\Form\Widget\AbstractWidget;

abstract class Entity extends Configurable {
	protected static $classMap = array(
		'form' => '\\Curry\\Form\\Form',
		'container' => '\\Curry\\Form\\Container',
		'collection' => '\\Curry\\Form\\Collection',
		'text' => '\\Curry\\Form\\Field\\Text',
		'statictext' => '\\Curry\\Form\\Field\\StaticText',
		'boolean' => '\\Curry\\Form\\Field\\Boolean',
		'nullboolean' => '\\Curry\\Form\\Field\\NullBoolean',
		'choice' => '\\Curry\\Form\\Field\\Choice',
		'multiplechoice' => '\\Curry\\Form\\Field\\MultipleChoice',
		'button' => '\\Curry\\Form\\Field\\Button',
		'submit' => '\\Curry\\Form\\Field\\Submit',
		'file' => '\\Curry\\Form\\Field\\File',
		'date' => '\\Curry\\Form\\Field\\Date',
		'time' => '\\Curry\\Form\\Field\\Time',
		'datetime' => '\\Curry\\Form\\Field\\DateTime',
		'color' => '\\Curry\\Form\\Field\\Color',
		'number' => '\\Curry\\Form\\Field\\Number',
	);

	public static function create($spec)
	{
		if ($spec instanceof Entity) {
			return $spec;
		} else if (is_string($spec)) {
			if (array_key_exists(strtolower($spec), self::$classMap))
				$spec = self::$classMap[strtolower($spec)];
			return new $spec;
		} else if (is_array($spec)) {
			if (isset($spec['type'])) {
				$type = $spec['type'];
				unset($spec['type']);
			} else {
				$type = isset($spec['fields']) ? 'container' : 'text';
			}
			if (array_key_exists(strtolower($type), self::$classMap))
				$type = self::$classMap[strtolower($type)];
			return new $type($spec);
		} else {
			throw new \Exception('Unknown entity type: '.print_r($spec, true));
		}
	}

	/**
	 * @var string
	 */
	protected $defaultWidget = '\\Curry\\Form\\Widget\\HiddenInput';

	/**
	 * @var Widget\AbstractWidget
	 */
	protected $widget;

	/**
	 * @var string|null
	 */
	protected $label = null;

	/**
	 * @var string
	 */
	protected $description = '';

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * @var Entity
	 */
	protected $parent = null;

	/**
	 * @var int|null
	 */
	protected $order = null;

	/**
	 * @var string
	 */
	protected $wrapperClass = '';

	/**
	 * @param int|null $order
	 */
	public function setOrder($order)
	{
		$this->order = $order;
	}

	/**
	 * @return int|null
	 */
	public function getOrder()
	{
		return $this->order;
	}

	/**
	 * @return boolean
	 */
	public function isArray()
	{
		return false;
	}

	/**
	 * @return boolean
	 */
	public function isMultiPart()
	{
		return $this->getWidget()->needsMultiPart();
	}

	/**
	 * @return boolean
	 */
	public function isLabelOutside()
	{
		return $this->getWidget()->isLabelOutside();
	}

	/**
	 * @return boolean
	 */
	public function isHidden()
	{
		return $this->getWidget()->isHidden();
	}

	public function getName()
	{
		if ($this->parent) {
			return $this->parent->getEntityName($this);
		}
		throw new \Exception('getName() not available for orphan entity');
	}

	public function getFullName()
	{
		$name = $this->getName();
		$parentName = $this->parent ? $this->parent->getFullName() : null;
		if ($parentName !== null) {
			$name = $parentName."[".$name."]";
		}
		if ($this->isArray()) {
			$name .= '[]';
		}
		return $name;
	}

	public function setOptionFallback($name, $value)
	{
		$this->getWidget()->setOption($name, $value);
	}

	public function setWidgetOptions($options)
	{
		$this->getWidget()->setOptions($options);
	}

	public function getId()
	{
		$id = $this->getWidget()->getOption('id');
		return $id !== null ? $id : 'id-'.strtolower(trim(strtr($this->getFullName(), '[]', '--'), '-'));
	}

	public function getEntityName($entity)
	{
		throw new \Exception('getEntityName() not supported for this entity.');
	}

	public function isValid($data = null)
	{
		if ($data !== null)
			$this->populate($data);
		return count($this->getErrors()) === 0;
	}

	abstract public function setInitial($initial);
	abstract public function getInitial();
	abstract public function getValue();
	abstract public function setValue($value);
	abstract public function getRawValue();
	abstract public function hasChanged();
	abstract public function populate($data);

	public function setWrapperClass($wrapperClass)
	{
		$this->wrapperClass = $wrapperClass;
	}

	public function getWrapperClass()
	{
		return $this->wrapperClass;
	}

	/**
	 * @return \Curry\Form\Entity
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param array $errors
	 */
	public function setErrors($errors)
	{
		$this->errors = $errors;
	}

	/**
	 * @return array
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	public function addError($message)
	{
		$this->errors[] = $message;
	}

	public function hasErrors()
	{
		return count($this->errors) > 0;
	}

	/**
	 * @param string|null $label
	 */
	public function setLabel($label)
	{
		$this->label = $label;
	}

	/**
	 * @return string
	 */
	public function getLabel()
	{
		return $this->label !== null ? $this->label : ucfirst(strtr($this->getName(), "-_", "  "));
	}

	/**
	 * @param Entity $entity
	 * @return string|null
	 */
	public function getDefaultWidgetForEntity(Entity $entity)
	{
		return $this->parent ? $this->parent->getDefaultWidgetForEntity($entity) : null;
	}

	/**
	 * @param Widget\AbstractWidget $widget
	 */
	public function setWidget($widget)
	{
		$this->widget = AbstractWidget::create($widget);
	}

	/**
	 * @return Widget\AbstractWidget
	 */
	public function getWidget()
	{
		if (!$this->widget) {
			$widgetClass = $this->getDefaultWidgetForEntity($this);
			if (!$widgetClass)
				$widgetClass = $this->defaultWidget;
			$this->widget = new $widgetClass;
		}
		if (!$this->widget instanceof Widget\AbstractWidget) {
			throw new \Exception('Widget is not of type \Curry\Form\Widget\AbstractWidget');
		}
		return $this->widget;
	}

	public function __toString()
	{
		return $this->render();
	}

	public function render(Widget\AbstractWidget $widget = null)
	{
		if ($widget === null)
			$widget = $this->getWidget();
		return $widget->render($this);
	}

	public function renderLabel($attributes = array())
	{
		$label = $this->getLabel();
		if ($label === '')
			return '';
		return self::html('label', $attributes + array('for' => $this->getId()), htmlspecialchars($label));
	}

	public function renderDescription($attributes = array())
	{
		$description = $this->getDescription();
		if ($description === '')
			return '';
		return self::html('p', $attributes + array('class' => 'form-description'), htmlspecialchars($description));
	}

	public function renderErrors($attributes = array())
	{
		if (!count($this->errors))
			return '';
		$listItems = '<li>'.\join("</li><li>",\array_map('htmlspecialchars', $this->errors)).'</li>';
		return self::html('ul', $attributes + array('class' => 'form-errors'), $listItems);
	}

	public static function html($tagName, array $attributes = array(), $content = '')
	{
		$tag = "<$tagName";
		foreach($attributes as $k => $v) {
			if (is_bool($v)) {
				if ($v) {
					$tag .= ' '.$k;
				}
			} else if ($v === null) {
				continue;
			} else {
				$tag .= ' '.$k.'="'.htmlspecialchars((string)$v).'"';
			}
		}
		if ($content === null)
			return $tag.' />';
		if (!is_string($content))
			$content = (string)$content;
		return $tag.">$content</$tagName>";
	}

	public function __clone()
	{
		$this->parent = null;
		if ($this->widget)
			$this->widget = clone $this->widget;
	}
}