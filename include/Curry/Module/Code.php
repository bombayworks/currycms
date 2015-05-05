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
namespace Curry\Module;
use Curry\Twig\Template;

/**
 * Executes PHP code and sends output to the front-end.
 * 
 * Can be used both with and without a template. When used with
 * a template, the following variables are available:
 * 
 * * content (string): The output of the PHP code.
 * 
 * @package Curry\Module
 */
class Code extends AbstractModule {
	/**
	 * PHP code to execute.
	 *
	 * @var string
	 */
	protected $code = "echo 'Hello world!';";
	
	/** {@inheritdoc} */
	public function showFront(Template $template = null)
	{
		$twig = $this->toTwig();
		return $template ? $template->render($twig) : $twig['content'];
	}
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		ob_start();
		eval($this->code);
		return array('content' => ob_get_clean());
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new \Curry_Form_SubForm(array(
			'elements' => array(
				'code' => array('textarea', array(
					'label' => 'Code',
					'value' => $this->code,
					'rows' => 10,
					'cols' => 30,
					'wrap' => 'off',
					'spellcheck' => 'false',
				)),
			)
		));
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->code = $values['code'];
	}
}
