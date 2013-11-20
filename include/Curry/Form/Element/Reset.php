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
 * Override Reset element to create a &lt;button type="reset"&gt; instead of a &lt;input type="reset"&gt;
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Reset extends Zend_Form_Element_Reset
{
	/**
	 * View helper.
	 *
	 * @var string
	 */
	public $helper = 'formButton';
	
	/**
	 * Set type attribute to reset.
	 */
	public function init()
	{
		$this->setAttrib('type', 'reset');
	}
}
