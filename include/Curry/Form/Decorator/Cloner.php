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
 * This will add a button to clone a subform.
 * 
 * @package Curry\Form
 */
class Curry_Form_Decorator_Cloner extends Zend_Form_Decorator_Abstract
{
	/** {@inheritdoc} */
	public function render($content)
	{
		$element = $this->getElement();
		if (!method_exists($element, 'getCloneTarget')) {
			return $content;
		}

		$view = $element->getView();
		if (null === $view) {
			return $content;
		}

		$placement = $this->getPlacement();
		$separator = $this->getSeparator();

		$cloneTarget = $element->getCloneTarget();
		if(!$cloneTarget)
			return $content;
		
		$form = clone $cloneTarget;

		$name = md5(microtime(true));
		$belongTo = $element->getElementsBelongTo()."[".$name."]";
		
		$form->setName($name);
		$form->setElementsBelongTo( $belongTo );
		$form->setView($view);
		$cloneHtml = addcslashes($form->render(), "'\"\n\r\t\\");
		
		$nextIndex = $element->getNextIndex();
		$javascript = "var nextIndex = $.data(this, 'nextIndex') ? $.data(this, 'nextIndex') : $nextIndex; $.data(this, 'nextIndex', nextIndex + 1); var cont = '$cloneHtml'; cont = cont.replace(/$name/g, ''+nextIndex); $(this).closest('li').before(cont).prev().trigger('curry-init'); return false;";
		
		$markup = '<li class="nosort"><button type="button" class="btn" onclick="'.htmlspecialchars($javascript).'">Add</button></li>';
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
