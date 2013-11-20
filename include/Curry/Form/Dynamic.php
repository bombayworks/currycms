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
 * Allows removal of subform, can be used together with Curry_Form_MultiForm.
 * 
 * @see Curry_Form_MultiForm
 * 
 * @package Curry\Form
 */
class Curry_Form_Dynamic extends Curry_Form_SubForm
{
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
			$this->addDecorator('FormElements')
				 ->addDecorator('HtmlTag', array('tag' => 'dl'))
				 ->addDecorator('Fieldset')
				 ->addDecorator(array('LiTag' => 'HtmlTag'), array('tag' => 'li'));
		}
		
		$this->setLegend($this->getLegend().' <a href="#" onclick="'.htmlspecialchars("$(this).closest('li').remove(); return false;").'"><img src="shared/images/icons/delete.png" alt="Delete" title="Delete"/></a>');
		$this->getDecorator('Fieldset')->setOption('escape', false);
	}
}
