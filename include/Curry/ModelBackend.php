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
 * Base class for "model backend modules".
 * 
 * @package Curry\Backend
 */
abstract class Curry_ModelBackend extends Curry_Backend {
	/**
	 * Array of allowed model classes.
	 *
	 * @var array
	 */
	protected $modelClasses;

	/**
	 * Get allowed model classes.
	 *
	 * @return array
	 */
	public function getModelClasses() {
		return $this->modelClasses;
	}
	
	/**
	 * Set allowed model classes.
	 *
	 * @param array $modelClasses
	 */
	public function setModelClasses($modelClasses) {
		$this->modelClasses = $modelClasses;
	}
	
	/**
	 * Get flexigrid for specified model.
	 *
	 * @param string $modelClass
	 * @param array $options
	 * @return Curry_Flexigrid
	 */
	public function getGrid($modelClass, $options = array()) {
		
		$options = array_merge(array('title' => $modelClass.'s', 'rp' => 100, 'rpOptions' => array(25,50,100,200, 500)), $options);
		$flexigrid = new Curry_Flexigrid_Propel($modelClass,
			url('', $_GET)->add(array('view'=>$modelClass.'Json')),
			$options);
		$editUrl = url('', $_GET)->add(array('module', 'view' => $modelClass));
		$flexigrid->addEditButton($editUrl);
		$flexigrid->addAddButton($editUrl);
		$flexigrid->addDeleteButton();
		if(in_array('translatable', array_keys(PropelQuery::from($modelClass)->getTableMap()->getBehaviors()))) {
			$langcode = LanguageQuery::create()->findOne()->getLangcode();
			$flexigrid->addLinkButton('View Translations', 'icon_flag', url('', $_GET)->add(array('translate' => true, 'langcode' => $langcode)));
		}
		return $flexigrid;
	}
	
	/**
	 * Show the HTML for the specified grid.
	 * 
	 * @todo Add support for i18n behavior.
	 *
	 * @param string $modelClass
	 */
	public function showGrid($modelClass) {
		if($_GET['translate']) {
			//$this->addMainContent(Project_Helpers_Form::getLangForm());
		}
		$this->addMainContent($this->getGrid($modelClass)->getHtml());
	}
	
	/**
	 * Show the JSON for the specified grid.
	 *
	 * @param string $modelClass
	 */
	public function showGridJson($modelClass) {
		$this->returnJson($this->getGrid($modelClass)->getJson());
	}
	
	/**
	 * Main routing function.
	 * 
	 * @see parent::show()
	 *
	 * @return string
	 */
	public function show()
	{
		try {
			$this->preShow();
			
			$func = 'show' . (isset($_GET['view']) ? $_GET['view'] : 'Main');
			if(method_exists($this, $func)) {
				$this->$func();
			}
			else if(in_array($_GET['view'], $this->modelClasses)) {
				$this->editModel($_GET['view']);
			}
			else if(in_array(substr($_GET['view'], 0, -1), $this->modelClasses) && substr($_GET['view'], -1) == 's') {
				$this->showGrid(substr($_GET['view'], 0, -1));
			}
			else if(in_array(substr($_GET['view'], 0, -4), $this->modelClasses) && substr($_GET['view'], -4) == 'Json') {
				$this->showGridJson(substr($_GET['view'], 0, -4));
			}
			else {
				throw new Exception('Invalid view');
			}
			$this->postShow();
		}
		catch (Exception $e) {
			if(!headers_sent())
				header("HTTP/1.0 500 Internal server error: ".str_replace("\n", "  ", $e->getMessage()));
			Curry_Core::log($e);
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
			$this->addMainContent("<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
		}
		return $this->render();
	}
	
	/**
	 * Show edit model form.
	 *
	 * @param string $modelClass
	 */
	public function editModel($modelClass) {
		$pks = PropelQuery::from($modelClass)->getTableMap()->getPrimaryKeys();
		$idParam = strtolower(reset($pks)->getName());
		$instance = isset($_GET[$idParam]) ? PropelQuery::from($modelClass)->findPk($_GET[$idParam]) : null;
		if(!$instance) {
			$instance = new $modelClass;
		}
		$form = $this->getEditForm($modelClass, $instance);
		if(isPost() && $form->isValid($_POST)) {
			try{
				$this->fillObjectFromForm($form, $instance);
				$instance->save();
				if(isAjax())
					$this->returnPartial('');
			}
			catch(PropelException $e) {
				$this->returnPartial(
					$form->render().
					'<p>'.$e->getMessage().'</p>'
				);
			}
		}
		if(isAjax())
			$this->returnPartial($form);
		else
			$this->addMainContent($form);
	}
	
	/**
	 * Update model instance with the values from provided form.
	 *
	 * @param Curry_Form_ModelForm $form
	 * @param mixed $instance
	 */
	public function fillObjectFromForm($form, $instance) {
		if(method_exists($form, 'fillModel')) {
			$form->fillModel($instance);
		}
	}
	
	/**
	 * Get form for model prefilled with values for specified instance.
	 *
	 * @param string $modelClass
	 * @param mixed $instance
	 * @param array $options
	 * @return Curry_Form_ModelForm
	 */
	public function getEditForm($modelClass, $instance = null, $options = array()) {
		$options = array_merge(array(
			'action' => (string)url('', $_GET),
			'method' => 'post',
			'class' => isAjax() ? 'dialog-form' : '',
		), $options);
		$form = new Curry_Form_ModelForm($modelClass, $options);
		if($instance !== null) {
			$form->fillForm($instance);
		}
		$form->addElement('submit', 'save', array(
			'label' => 'Save',
		));
		return $form;
	}
}
