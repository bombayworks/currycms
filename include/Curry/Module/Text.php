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
 * Text module (single-line of text).
 * 
 * Can be used both with and without a template. Template variables:
 * 
 * * content (string): Text content.
 * 
 * @package Curry\Module
 */
class Curry_Module_Text extends Curry_Module {
	/**
	 * Text content.
	 *
	 * @var string
	 */
	protected $content = "Hello world!";

	/** {@inheritdoc} */
	public function showFront(Curry_Twig_Template $template = null)
	{
		return $template ? parent::showFront($template) : $this->content;
	}
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		return array('content' => $this->content);
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new Curry_Form_SubForm(array(
		    'elements' => array(
		    	'content' => array('text', array(
		    		'label' => 'Content',
		    		'value' => $this->content,
		    	)),
			),
		));
		
		return $form;
	}

	/** {@inheritdoc} */
	public function saveBack(Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->content = $values['content'];
	}
}
