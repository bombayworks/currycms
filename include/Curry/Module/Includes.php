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

use Curry\Generator\HtmlGenerator;
use Curry\Twig\Template;
use Curry\Util\PathHelper;

/**
 * Module to handle javascript and stylesheet includes.
 * 
 * @package Curry\Module
 */
class Includes extends AbstractModule {
	/**
	 * List of script files.
	 *
	 * @var array
	 */
	protected $script = array();
	
	/**
	 * List of stylesheet files
	 *
	 * @var array
	 */
	protected $stylesheet = array();
	
	/**
	 * Inline script.
	 *
	 * @var string
	 */
	protected $inlineScript = '';
	
	/**
	 * This module doesnt support a template.
	 *
	 * @return bool
	 */
	public static function hasTemplate()
	{
		return false;
	}
	
	/** {@inheritdoc} */
	public function showFront(Template $template = null)
	{
		$pageGenerator = $this->app->generator;
		if(!($pageGenerator instanceof HtmlGenerator))
			throw new \Exception('Includes module only works on pages with PageGenerator set to Curry\Generator\HtmlGenerator.');
		$head = $pageGenerator->getHtmlHead();
		
		// Stylesheets
		foreach($this->stylesheet as $stylesheet) {
			if($stylesheet['condition'])
				$head->beginConditional($stylesheet['condition']);
			$head->addStylesheet($stylesheet['source'], $stylesheet['media']);
			if($stylesheet['condition'])
				$head->endConditional();
		}
		
		// Scripts
		foreach($this->script as $script) {
			if($script['condition'])
				$head->beginConditional($script['condition']);
			$attr = array();
			if(count($script['async']))
				$attr['async'] = 'async';
			if(count($script['defer']))
				$attr['defer'] = 'defer';
			$head->addScript($script['source'], $script['type'], $attr);
			if($script['condition'])
				$head->endConditional();
		}
		
		// Inline script
		if($this->inlineScript)
			$head->addInlineScript($this->inlineScript);
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new \Curry_Form_SubForm(array(
			'elements' => array()
		));
		
		$scriptForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Script',
			'elements' => array(
				'source' => array('filebrowser', array(
					'label' => 'Source',
					'required' => true,
					'description' => 'May be a local or external file.',
				)),
				'type' => array('text', array(
					'label' => 'Type',
					'required' => true,
					'value' => 'text/javascript',
					'description' => 'The type attribute of the script tag.'
				)),
				'condition' => array('text', array(
					'label' => 'Condition',
					'value' => '',
					'description' => 'Wrap the tag in a conditional comment, example: lt IE 8. Leave blank to disable.',
				)),
				'async' => array('multiCheckbox', array(
					'label' => 'Async',
					'multiOptions' => array('1' => 'Async'),
					'value' => false,
					'description' => "Load the script asyncronously using the HTML5 async attribute.",
				)),
				'defer' => array('multiCheckbox', array(
					'label' => 'Defer',
					'multiOptions' => array('1' => 'Defer'),
					'value' => false,
					'description' => "Defer execution of script until after the HTML has been loaded.",
				)),
			),
		));
		$scriptForm->addDisplayGroup(array('type','condition','async','defer'), 'options', array('Legend' => 'Options', 'class' => 'horizontal-group'));
		
		$form->addSubForm(new \Curry_Form_MultiForm(array(
			'legend' => 'Script includes',
			'cloneTarget' => $scriptForm,
			'defaults' => $this->script,
		)), 'script');
		
		$stylesheetForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Stylesheet',
			'elements' => array(
				'source' => array('filebrowser', array(
					'label' => 'Source',
					'required' => true,
				)),
				'media' => array('text', array(
					'label' => 'Media',
					'required' => true,
					'value' => 'all',
				)),
				'condition' => array('text', array(
					'label' => 'Condition',
					'value' => '',
				)),
			),
		));
		
		$form->addSubForm(new \Curry_Form_MultiForm(array(
			'legend' => 'Stylesheet includes',
			'cloneTarget' => $stylesheetForm,
			'defaults' => $this->stylesheet,
		)), 'stylesheet');
		
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Custom inline javascript',
			'class' => $this->inlineScript ? '' : 'advanced',
			'elements' => array(
				'source' => array('codeMirror', array(
					'codeMirrorOptions' => array(
						'mode' => array(
							'name' => 'javascript',
						),
					),
					'label' => 'Source',
					'value' => $this->inlineScript,
					'wrap' => 'off',
					'rows' => 15,
					'cols' => 35,
				)),
			),
		)), 'inline_script');
		
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->script = (array)$values['script'];
		$this->stylesheet = (array)$values['stylesheet'];
		$this->inlineScript = $values['inline_script']['source'];
	}
}
