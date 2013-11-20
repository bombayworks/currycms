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
 * Use a flash as an input element.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Flashtool extends Zend_Form_Element_Hidden
{
	/**
	 * Set curry decorator path and set default attributes.
	 */
	public function init()
	{
		$this->addPrefixPath('Curry_Form_Decorator', 'Curry/Form/Decorator/', 'decorator')
			 ->setAttrib('soure', null)
			 ->setAttrib('width', $this->getAttrib('width') ? $this->getAttrib('width') : '100%')
			 ->setAttrib('height', $this->getAttrib('height') ? $this->getAttrib('height') : 250)
			 ->setAttrib('version', $this->getAttrib('version') ? $this->getAttrib('version') : '9.0.0')
			 ->setAttrib('options', $this->getAttrib('options') ? $this->getAttrib('options') : array())
			 ->setAttrib('flashvars', $this->getAttrib('flashvars') ? $this->getAttrib('flashvars') : array());
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
			$this->addDecorator('ViewHelper')
				->addDecorator('Flashtool')
				->addDecorator('Errors')
				->addDecorator('Description', array('tag' => 'p', 'class' => 'description'))
				->addDecorator('HtmlTag', array('tag' => 'dd'))
				->addDecorator('Label', array('tag' => 'dt'));
		}
		$this->getDecorators();
	}
}