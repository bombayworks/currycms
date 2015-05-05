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
 * Article module to store HTML content.
 * 
 * Can be used both with and without a template. When used with
 * a template, the following variables are available:
 * 
 * * content (string): The HTML content of the article.
 *
 * @package Curry\Module
 */
class Article extends AbstractModule {
	/**
	 * Default editor.
	 */
	const DEFAULT_EDITOR = '';
	
	/**
	 * Textarea instead of editor.
	 */
	const PLAIN = 'plain';
	
	/**
	 * Codemirror editor.
	 * 
	 * @link http://codemirror.net/
	 */
	const CODEMIRROR = 'codemirror';
	
	/**
	 * TinyMCE editor.
	 * 
	 * @link http://www.tinymce.com/
	 */
	const TINYMCE = 'tinymce';
	
	/**
	 * Editor used to edit the contents of the module.
	 *
	 * @var string
	 */
	protected $editor = self::DEFAULT_EDITOR;
	
	/**
	 * The (HTML) contents of the module.
	 *
	 * @var string
	 */
	protected $content = "<p><br /></p>";
	
	/**
	 * Allow twig-syntax in the content? If true, the content will be passed through twig.
	 *
	 * @var bool
	 */
	protected $allowTemplateSyntax = false;
	
	/** {@inheritdoc} */
	public function showFront(Template $template = null)
	{
		$twig = $this->toTwig();
		return $template ? $template->render($twig) : $twig['content'];
	}
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		$content = $this->content;
		if($this->allowTemplateSyntax) {
			$tpl = $this->app->loadTemplateString($content);
			$content = $tpl->render(array());
		}
		
		return array('content' => $content);
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$editor = $this->editor;
		if(!$editor)
			$editor = strtolower($this->app['defaultEditor']);
			
		$elementType = 'textarea';
		if($editor == self::CODEMIRROR)
			$elementType = 'codeMirror';
		else if($editor == self::TINYMCE)
			$elementType = 'tinyMCE';

		$form = new \Curry_Form_SubForm(array(
		    'elements' => array(
		    	'content' => array($elementType, array(
		    		'label' => 'Content',
		    		'value' => $this->content,
		    	)),
			),
		));

		$subform = new \Curry_Form_SubForm(array(
			'legend' => 'Advanced',
			'class' => 'advanced',
		    'elements' => array(
		    	'editor' => array('select', array(
		    		'multiOptions' => array(
		    			self::DEFAULT_EDITOR => 'Default',
		    			self::CODEMIRROR => 'CodeMirror',
		    			self::TINYMCE => 'TinyMCE',
		    			self::PLAIN => 'Plain',
		    		),
		    		'value' => $this->editor,
		    	)),
		    	'switch' => array('submit', array(
		    		'label' => 'Switch'
		    	)),
			    'allow_template_syntax' => array('checkbox', array(
		    		'label' => 'Allow template syntax',
		    		'value' => $this->allowTemplateSyntax,
		    		'description' => 'Allows insertion of global variables, e.g. {{curry.page.Name}}.'
			    )),
			),
		));

		$subform->addDisplayGroup(array('editor','switch'), 'editor_switch', array(
			'class' => 'horizontal-group',
			'legend' => 'Editor'
		));

		$form->addSubForm($subform, 'advanced', 0);

		if($this->editor == self::PLAIN ) {
			$form->content->setAttrib('wrap', 'off');
			$form->content->setAttrib('spellcheck', 'false');
		}

		return $form;
	}

	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);

		if($form->advanced->switch->isChecked()) {
			$this->editor = $values['advanced']['editor'];
		} else {
			$this->content = $values['content'];
			$this->allowTemplateSyntax = (bool)$values['advanced']['allow_template_syntax'];
		}
	}
}
