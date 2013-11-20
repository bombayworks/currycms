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
 * Datepicker element.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Date extends Zend_Form_Element_Text
{
	/**
	 * Specifies options for datepicker.
	 * 
	 * @link http://api.jqueryui.com/datepicker/
	 *
	 * @param array $options
	 */
	public function setDatePickerOptions($options)
	{
		$this->setAttrib('data-datepicker', json_encode($options));
	}

	/**
	 * Override attributes to append datepicker class.
	 *
	 * @return array
	 */
	public function getAttribs()
	{
		$attribs = parent::getAttribs();
		$class = isset($attribs['class']) ? array($attribs['class']) : array();
		$class[] = 'datepicker';
		$attribs['class'] = join(" ", $class);
		return $attribs;
	}
}
