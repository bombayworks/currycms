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
 * Decorate element with page-selector.
 *
 * @package Curry\Form
 */
class Curry_Form_Decorator_InternalSelect extends Zend_Form_Decorator_Abstract
{
	/**
	 * Default separator: empty string
	 *
	 * @var string
	 */
	protected $_separator = '';
	
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
		$attr = array(
			'onchange'=>'$(this).prev().val(this.value.split("|")[1]); this.selectedIndex = 0;'
		);
		
		$options = array('' => 'Select page...');
		foreach(PageQuery::create()->orderByBranch()->find() as $page) {
			if(Curry_Backend_Page::isTemplatePage($page))
				continue;
			$options[$page->getPageId().'|'.$page->getUrl()] = str_repeat(Curry_Core::SELECT_TREE_PREFIX, $page->getLevel()) . $page->getName();
		}
		$options = Curry_Html::createSelectOptions($options, '');
		$markup = Curry_Html::createTag('select', $attr, $options);
		
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
