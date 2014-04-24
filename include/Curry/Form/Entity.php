<?php

namespace Curry\Form;

abstract class Entity extends \Curry\Configurable {
	// COMPONENTS
	// Label
	// Widget "the element"
	// Description
	// Errors

	protected static $classMap = array(
		'form' => '\\Curry\\Form\\Form',
		'collection' => '\\Curry\\Form\\Collection',
		'text' => '\\Curry\\Form\\Field\\Text',
		'statictext' => '\\Curry\\Form\\Field\\StaticText',
		'boolean' => '\\Curry\\Form\\Field\\Boolean',
		'nullboolean' => '\\Curry\\Form\\Field\\NullBoolean',
		'choice' => '\\Curry\\Form\\Field\\Choice',
		'multiplechoice' => '\\Curry\\Form\\Field\\MultipleChoice',
		'button' => '\\Curry\\Form\\Field\\Button',
		'submit' => '\\Curry\\Form\\Field\\Submit',
	);

	public static function createEntity($spec)
	{
		if ($spec instanceof Entity) {
			return $spec;
		} else if (is_string($spec)) {
			if (array_key_exists(strtolower($spec), self::$classMap))
				$spec = self::$classMap[strtolower($spec)];
			return new $spec;
		} else if (is_array($spec) && isset($spec['type'])) {
			$type = $spec['type'];
			unset($spec['type']);
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
	 * @var Widget
	 */
	public $widget;

	/**
	 * @var string
	 */
	public $label;

	/**
	 * @var string
	 */
	public $description; // aka help-text

	/**
	 * @var array
	 */
	public $errors = array();

	/**
	 * @var array
	 */
	public $attributes;

	/**
	 * @var Entity
	 */
	public $parent = null;

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
		$widget = $this->getWidget();
		return $widget ? $widget->needsMultiPart() : null;
	}

	/**
	 * @return boolean
	 */
	public function isHidden()
	{
		$widget = $this->getWidget();
		return $widget ? $widget->isHidden() : null;
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
		return 'id-'.strtolower(trim(strtr($this->getFullName(), '[]', '--'), '-'));
	}

	public function getEntityName($entity)
	{
		throw new \Exception('getEntityName() not supported for this entity.');
	}

	public function isValid($data = null)
	{
		if ($data !== null)
			$this->populate($data);
		return count($this->getAllErrors());
	}

	abstract public function setInitial($data);
	abstract public function getInitial();
	abstract public function getValue();
	abstract public function getRawValue();
	abstract public function hasChanged();
	abstract public function getContainerClass();

	abstract function populate($data);

	/**
	 * @param \Curry\Form\Entity $parent
	 */
	public function setParent($parent)
	{
		$this->parent = $parent;
	}

	/**
	 * @return \Curry\Form\Entity
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * @param array $attributes
	 */
	public function setAttributes($attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
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
	 * @param string $label
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
		return $this->label !== null ? $this->label : ucfirst($this->getName());
	}

	/**
	 * @param \Curry\Form\Widget\AbstractWidget $widget
	 */
	public function setWidget($widget)
	{
		/**
		 * TODO: fix class names
		 */
		if (is_string($widget)) {
			$type = '\\Curry\\Form\\Widget\\'.ucfirst($widget);
			$widget = new $type;
		} else if (is_array($widget)) {
			$type = '\\Curry\\Form\\Widget\\'.ucfirst($widget['type']);
			unset($widget['type']);
			$widget = new $type($widget);
		}
		if (!($widget instanceof Widget\AbstractWidget)) {
			throw new \Exception('Unknown widget type '.print_r($widget, true));
		}
		$this->widget = $widget;
	}

	/**
	 * @return \Curry\Form\Widget\AbstractWidget
	 */
	public function getWidget()
	{
		if (!$this->widget) {
			$this->widget = new $this->defaultWidget;
		}
		return $this->widget;
	}

	public function render(Widget\AbstractWidget $widget = null)
	{
		if ($widget === null)
			$widget = $this->getWidget();
		return $widget->render($this);
	}

	public function renderRow(Widget\AbstractWidget $widget = null)
	{
		if (!$widget && $this->getParent())
			$widget = $this->getParent()->getWidget();
		if (!$widget)
			throw new \Exception('Unable to render row - widget not set.');
		return $widget->renderNormal($this);
	}

	public function renderLabel()
	{
		$label = $this->getLabel();
		if (!$label)
			return '';
		return self::html('label', array('for' => $this->getId()), htmlspecialchars($label));
	}

	public function renderDescription()
	{
		$description = $this->getDescription();
		if (!$description)
			return '';
		return self::html('p', array('class' => 'form-description'), htmlspecialchars($description));
	}

	public function renderErrors()
	{
		if (!count($this->errors))
			return '';
		return self::html('ul', array('class' => 'form-errors'), '<li>'.\join("</li><li>",\array_map('htmlspecialchars', $this->errors)).'</li>');
	}

	public static function html($tagName, array $attributes = array(), $content = '')
	{
		$tag = "<$tagName";
		foreach($attributes as $k => $v) {
			if (is_bool($v)) {
				if ($v) {
					$tag .= ' '.$k;
				}
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