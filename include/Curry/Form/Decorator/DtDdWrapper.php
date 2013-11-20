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
 * Override the default Zend DtDdWrapper, we want some information on the element in the dt-tag.
 * 
 * @package Curry\Form
 */
class Curry_Form_Decorator_DtDdWrapper extends Zend_Form_Decorator_DtDdWrapper
{
	/**
	 * Get class names, and append some custom classes.
	 *
	 * @return string
	 */
	public function getAutoClass()
	{
		$element = $this->getElement();
		
		$clazz = array();
		
		// add element-type class
		$elementType = strtolower(get_class($element));
		$last = strrpos($elementType, '_');
		if($last !== false)
			$elementType = substr($elementType, $last + 1);
		$clazz[] = "element-type-" . $elementType;
		
		// add element-has-errors and element-required classes
		if ($element instanceof Zend_Form_Element) {
			if ($element->hasErrors())
				$clazz[] = 'element-has-errors';
			if ($element->isRequired())
				$clazz[] = 'element-required';
		}

		return join(" ", $clazz);
	}
	
	/**
	 * Render decorator.
	 *
	 * Renders as the following:
	 * 
	 * <code>
	 * <dt id="$elementName-label"></dt>
	 * <dd id="elementName-element">$content</dd>
	 * </code>
	 *
	 * @param  string $content
	 * @return string
	 */
	public function render($content)
	{
		$elementName = $this->getElement()->getName();

		return '<dt id="' . $elementName . '-label" class="'.htmlspecialchars($this->getAutoClass()).'">&nbsp;</dt>' .
			   '<dd id="' . $elementName . '-element">' . $content . '</dd>';
	}
}