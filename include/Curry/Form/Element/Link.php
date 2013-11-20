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
 * Element to select a link.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Link extends Zend_Form_Element_Text
{
	/**
	 * Add curry decorator path.
	 */
	public function init()
	{
		$this->addPrefixPath('Curry_Form_Decorator', 'Curry/Form/Decorator/', 'decorator');
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
				->addDecorator('InternalSelect')
				->addDecorator('Errors')
				->addDecorator('Description', array('tag' => 'p', 'class' => 'description'))
				->addDecorator('HtmlTag', array('tag' => 'dd'))
				->addDecorator('Label', array('tag' => 'dt'));
		}
		$this->getDecorators();
	}
}