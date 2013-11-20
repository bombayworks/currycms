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
 * Filebrowser element.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_Filebrowser extends Zend_Form_Element_Text
{
	/**
	 * Specify filebrowser options.
	 *
	 * @param array $options
	 */
	public function setFilebrowserOptions($options)
	{
		$this->setAttrib('data-filebrowser', json_encode($options));
	}
	
	/**
	 * Specify finder options.
	 *
	 * @param array $options
	 */
	public function setFinderOptions($options)
	{
		$this->setAttrib('data-finder', json_encode($options));
	}
	
	/**
	 * Specify dialog options.
	 *
	 * @param array $options
	 */
	public function setDialogOptions($options)
	{
		$this->setAttrib('data-dialog', json_encode($options));
	}
	
	/**
	 * Override attributes to append filebrowser class.
	 *
	 * @return array
	 */
	public function getAttribs()
	{
		$attribs = parent::getAttribs();
		$class = isset($attribs['class']) ? array($attribs['class']) : array();
		$class[] = 'filebrowser';
		$attribs['class'] = join(" ", $class);
		return $attribs;
	}
}
