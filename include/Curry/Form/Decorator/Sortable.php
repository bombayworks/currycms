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
 * Make child elements sortable.
 * 
 * @package Curry\Form
 */
class Curry_Form_Decorator_Sortable extends Zend_Form_Decorator_Abstract
{
	/**
	 * Placement; default to surround content
	 * @var string
	 */
	protected $_placement = null;
	
	/** {@inheritdoc} */
	public function render($content)
	{
		$element = $this->getElement();

		$view = $element->getView();
		if (null === $view) {
			return $content;
		}

		$placement = $this->getPlacement();
		//$separator = $this->getSeparator();
		
		$id = "sortable-".md5(microtime(true));

		$javascript = <<<JS
// <![CDATA[
$(document).ready(function(){
	$.require('jquery-ui', function() {
		$("#$id").sortable({axis: 'y', items: '> li:not(.nosort)', containment: 'parent', cancel: ':input, button, select, option, img, .mceEditor', tolerance: 'pointer', delay: 200});
	});
});
// ]]>
JS;
		
		$open = '<ul id="'.$id.'">';
		$close = '</ul>'.'<script type="text/javascript">'.$javascript.'</script>';
		
		switch ($placement) {
			case 'PREPEND':
				$content = $open . $close .  $content;
				break;
			case 'APPEND':
				$content = $content . $open . $close;
				break;
			default:
				$content = $open . $content . $close;
		}
		
		return $content;
	}
}
