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
use Curry\Util\Flash;

/**
 * Use a flash to act as a input-field.
 * 
 * @package Curry\Form
 *
 */
class Curry_Form_Decorator_Flashtool extends Zend_Form_Decorator_Abstract
{
	/** {@inheritdoc} */
	public function render($content)
	{
		$element = $this->getElement();

		$view = $element->getView();
		if (null === $view) {
			return $content;
		}

		$placement = $this->getPlacement();
		$separator = $this->getSeparator();
		
		$flashvars = array(
			'value' => $element->getValue(),
			'elementId' => $element->getId(),
		);
		
		$flashvars = array_merge($flashvars, $element->getAttrib('flashvars'));
		
		$markup = Flash::embed(Flash::SWFOBJECT_DYNAMIC, $element->getAttrib('source'), $element->getAttrib('width'),
			$element->getAttrib('height'), $element->getAttrib('version'), array_merge($element->getAttrib('options'), array('flashvars' => $flashvars, 'target' => $element->getId().'flashTool')))
			. '<div id="'.$element->getId().'flashTool'.'"></div>';
		
		switch ($placement) {
			case 'PREPEND':
				$content = $markup . $separator .  $content;
				break;
			case 'APPEND':
			default:
				$content = $content . $separator . $markup;
		}
		
		return $content;
	}
}