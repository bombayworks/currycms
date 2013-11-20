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
 * This overrides the Zend Label decorator do add some extra info in the dt-tag.
 * 
 * @package Curry\Form
 */
class Curry_Form_Decorator_Label extends Zend_Form_Decorator_Label
{
	public function render($content)
	{
		$class = (string)$this->getTagClass();
		$element = $this->getElement();

		// add element-type class
		$elementType = strtolower(get_class($element));
		$last = strrpos($elementType, '_');
		if($last !== false)
			$elementType = substr($elementType, $last + 1);
		$class .= " element-type-" . $elementType;

		// add element-has-errors and element-required classes
		if ($element instanceof Zend_Form_Element) {
			if ($element->hasErrors())
				$class .= ' element-has-errors';
			if ($element->isRequired())
				$class .= ' element-required';
		}
		$this->setTagClass(trim($class));

		return parent::render($content);
	}

}