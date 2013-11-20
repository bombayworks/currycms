<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */

/**
 * Form element Date/Time decorator.
 *
 * @package Curry\Form
 */
class Curry_Form_Decorator_DateTime extends Zend_Form_Decorator_Abstract
{
	/**
	 * Render element.
	 *
	 * @param string $content
	 * @return string
	 */
	public function render($content)
	{
		$element = $this->getElement();
		if (!$element instanceof Curry_Form_Element_DateTime)
			return $content;
 
		$view = $element->getView();
		if (!$view instanceof Zend_View_Interface)
			return $content;
 
		$date = $element->getDate();
		$time = $element->getTime();
		$name = $element->getFullyQualifiedName();
 
		$dateParams = array(
			'class' => 'date datepicker',
			'size'	  => 10,
			'maxlength' => 10,
		);
		$timeParams = array(
			'class' => 'time',
			'size'	  => 8,
			'maxlength' => 8,
		);
 
		$markup = $view->formText($name . '[date]', $date, $dateParams)
			. $view->formText($name . '[time]', $time, $timeParams);
 
		switch ($this->getPlacement()) {
			case self::PREPEND:
				return $markup . $this->getSeparator() . $content;
			case self::APPEND:
			default:
				return $content . $this->getSeparator() . $markup;
		}
	}
}