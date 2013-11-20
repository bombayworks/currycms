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
 * TinyMCE editor element.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_TinyMCE extends Zend_Form_Element_Textarea
{
	/**
	 * Specify TinyMCE options.
	 * 
	 * @link http://www.tinymce.com/wiki.php/Configuration
	 *
	 * @param array $options
	 */
	public function setTinyMceOptions($options)
	{
		$this->setAttrib('data-tinymce', json_encode($options));
	}
	
	/**
	 * Override attributes to append tinymce class.
	 *
	 * @return array
	 */
	public function getAttribs()
	{
		$attribs = parent::getAttribs();
		$class = isset($attribs['class']) ? array($attribs['class']) : array();
		$class[] = 'tinymce';
		$attribs['class'] = join(" ", $class);
		return $attribs;
	}
}
