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
 * This is a dummy element that allows you to insert raw html inside the dd-tag of the element. The value
 * of the element will be used as raw html. This element will always pass validation.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_RawHtml extends Zend_Form_Element_Hidden
{
	/**
	 * Initialize path to curry decorators.
	 */
	public function init()
	{
		$this->addPrefixPath('Curry_Form_Decorator', 'Curry/Form/Decorator/', 'decorator');
	}
	
	/**
	 * This elemente is always valid.
	 *
	 * @param mixed $value
	 * @param mixed $context
	 * @return bool
	 */
	public function isValid($value, $context = null)
	{
		return true;
	}
	
	/**
	 * Override default decorators.
	 */
	public function loadDefaultDecorators()
	{
		if ($this->loadDefaultDecoratorsIsDisabled()) {
			return;
		}

		$decorators = $this->getDecorators();
		if (empty($decorators)) {
			$this->addDecorator('RawHtml')
				->addDecorator('HtmlTag', array('tag' => 'dd'))
				->addDecorator('Label', array('tag' => 'dt'));
		}
		$this->getDecorators();
	}
}
