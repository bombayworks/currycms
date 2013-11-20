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
 * Creates a CodeMirror editor.
 * 
 * @package Curry\Element
 */
class Curry_Form_Element_CodeMirror extends Zend_Form_Element_Textarea
{
	/**
	 * Specifies codemirror options.
	 * 
	 * @link http://codemirror.net/doc/manual.html#config
	 *
	 * @param array $options
	 */
	public function setCodeMirrorOptions($options)
	{
		$this->setAttrib('data-codemirror', json_encode($options));
	}
	
	/**
	 * Override attributes to append codemirror class.
	 *
	 * @return array
	 */
	public function getAttribs()
	{
		$attribs = parent::getAttribs();
		$class = isset($attribs['class']) ? array($attribs['class']) : array();
		$class[] = 'codemirror';
		$attribs['class'] = join(" ", $class);
		return $attribs;
	}
}
