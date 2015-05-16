<?php

namespace Curry\Form\Widget;

use Curry\Configurable;
use Curry\Form\Entity;

/**
 * Class Widget
 *
 * Widget
 * Full (label, widget, description, errors)
 * Row (wrapper)
 *
 * @package Curry\Form
 */
abstract class AbstractWidget extends Configurable {
	protected static $classMap = array(
		// Widgets
		'button' => '\\Curry\\Form\\Widget\\Button',
		'checkbox' => '\\Curry\\Form\\Widget\\CheckboxInput',
		'checkboxselectmultiple' => '\\Curry\\Form\\Widget\\CheckboxSelectMultiple',
		'collectiontabular' => '\\Curry\\Form\\Widget\\CollectionTabular',
		'collection' => '\\Curry\\Form\\Widget\\CollectionWidget',
		'collectionwidget' => '\\Curry\\Form\\Widget\\CollectionWidget',
		'color' => '\\Curry\\Form\\Widget\\ColorInput',
		'colorinput' => '\\Curry\\Form\\Widget\\ColorInput',
		'container' => '\\Curry\\Form\\Widget\\ContainerWidget',
		'containerwidget' => '\\Curry\\Form\\Widget\\ContainerWidget',
		'date' => '\\Curry\\Form\\Widget\\DateInput',
		'dateinput' => '\\Curry\\Form\\Widget\\DateInput',
		'datetime' => '\\Curry\\Form\\Widget\\DateTimeInput',
		'datetimeinput' => '\\Curry\\Form\\Widget\\DateTimeInput',
		'fieldset' => '\\Curry\\Form\\Widget\\Fieldset',
		'file' => '\\Curry\\Form\\Widget\\File',
		'form' => '\\Curry\\Form\\Widget\\Form',
		'formtable' => '\\Curry\\Form\\Widget\\FormTable',
		'hidden' => '\\Curry\\Form\\Widget\\HiddenInput',
		'hiddeninput' => '\\Curry\\Form\\Widget\\HiddenInput',
		'input' => '\\Curry\\Form\\Widget\\Input',
		'nullbooleanselect' => '\\Curry\\Form\\Widget\\NullBooleanSelect',
		'number' => '\\Curry\\Form\\Widget\\NumberInput',
		'radioselect' => '\\Curry\\Form\\Widget\\RadioSelect',
		'select' => '\\Curry\\Form\\Widget\\Select',
		'selectmultiple' => '\\Curry\\Form\\Widget\\SelectMultiple',
		'statictext' => '\\Curry\\Form\\Widget\\StaticText',
		'submitbutton' => '\\Curry\\Form\\Widget\\SubmitButton',
		'textarea' => '\\Curry\\Form\\Widget\\TextArea',
		'text' => '\\Curry\\Form\\Widget\\TextInput',
		'textinput' => '\\Curry\\Form\\Widget\\TextInput',
		'time' => '\\Curry\\Form\\Widget\\TimeInput',
		'timeinput' => '\\Curry\\Form\\Widget\\TimeInput',
		'zendform' => '\\Curry\\Form\\Widget\\ZendForm',
	);

	public static function create($spec)
	{
		if ($spec instanceof AbstractWidget) {
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
			throw new \Exception('Unknown widget type: '.print_r($spec, true));
		}
	}

	protected $attributes = array();

	protected function setOptionFallback($name, $value)
	{
		$this->attributes[$name] = $value;
	}

	protected function getOptionFallback($name)
	{
		return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
	}

	abstract public function render(Entity $entity);

	public function isHidden()
	{
		return false;
	}

	public function isLabelOutside()
	{
		return true;
	}

	public function needsMultiPart()
	{
		return false;
	}
}