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
 * Override the Submit element to create a &lt;button type="submit"&gt; instead of &lt;input type="submit"&gt;
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Submit extends Zend_Form_Element_Submit
{
	/**
	 * View helper.
	 *
	 * @var string
	 */
	public $helper = 'formButton';
	
	/**
	 * Set type attribute to submit.
	 */
	public function init()
	{
		$this->setAttrib('type', 'submit');
		if (!isset($this->class)) {
			$primary = array('Save', 'Insert', 'Go', 'Execute');
			$this->setAttrib('class', in_array($this->getLabel(), $primary) ? 'btn btn-primary' : 'btn');
		}
	}
}
