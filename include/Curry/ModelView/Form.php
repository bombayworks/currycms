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
namespace Curry\ModelView;

use Curry\App;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * @package Curry\ModelView
 */
class Form extends AbstractBackend {
	protected $modelForm;
	protected $preRender = null;
	protected $preSave = null;
	protected $postSave = null;

	public function __construct($modelOrModelForm, $options = array())
	{
		if (is_string($modelOrModelForm)) {
			$this->modelForm = new \Curry\Form\ModelForm($modelOrModelForm, $options);
		} else if ($modelOrModelForm instanceof \Curry\Form\ModelForm) {
			$this->modelForm = $modelOrModelForm;
		} else {
			throw new Exception('Expected string or Curry\Form\ModelForm');
		}
		parent::__construct(App::getInstance());
	}

	public function setPostSave($postSave)
	{
		$this->postSave = $postSave;
	}

	public function setPreRender($preRender)
	{
		$this->preRender = $preRender;
	}

	public function setPreSave($preSave)
	{
		$this->preSave = $preSave;
	}

	protected function triggerCallback($callback, $item, $form)
	{
		if ($callback !== null) {
			return call_user_func($callback, $item, $form);
		}
	}

	public function getModelClass()
	{
		return $this->modelForm->getModelClass();
	}

	public function show(Request $request)
	{
		$modelClass = $this->modelForm->getModelClass();
		$item = $this->getSelection();
		if(!isset($item) || !($item instanceof $modelClass)) {
			$item = new $modelClass;
			$relatedItem = $this->parent instanceof AbstractBackend ? $this->parent->getSelection() : null;
			if($relatedItem) {
				$relations = \PropelQuery::from($modelClass)->getTableMap()->getRelations();
				foreach($relations as $relation) {
					if($relation->getRightTable()->getPhpName() == get_class($relatedItem) &&
						in_array($relation->getType(), array(\RelationMap::MANY_TO_ONE))) {
						$item->{'set'.$relation->getName()}($relatedItem);
					}
				}
			}
		}

		$form = clone $this->modelForm;
		$buttons = array('save');
		$form->addField('save', array('type' => 'submit', 'label' => 'Save', 'class' => 'btn btn-primary'));
		if(!$item->isNew() && ($this->parent instanceof ListView) && $this->parent->hasAction('delete')) {
			$form->addField('delete', array(
				'type' => 'submit',
				'label' => 'Delete',
				'class' => 'btn btn-danger',
				'onclick' => "return confirm('Do you really want to delete this item? This cannot be undone.');",
			));
			$buttons[] = 'delete';
		}
		//$form->addDisplayGroup($buttons, 'save_group', array('class' => 'horizontal-group'));
		$form->fillForm($item);

		if($request->isMethod('POST') && $form->isValid($_POST)) {
			if($form->delete && $form->delete->isChecked()) {
				//$this->createModelUpdateEvent($modelClass, $item->getPrimaryKey(), 'delete');
				$item->delete();

				if ($item instanceof \Curry_ISearchable)
					\Curry_Backend_Indexer::removeItem($item);

				$this->addMainContent('<p>The item has been deleted.</p>');
				return parent::render();
			}

			$form->fillModel($item);
			$this->triggerCallback($this->preSave, $item, $form);
			$item->save();
			$this->triggerCallback($this->postSave, $item, $form);
			$form->fillForm($item);

			//$this->createModelUpdateEvent($modelClass, $item->getPrimaryKey(), 'update');
			if ($item instanceof \Curry_ISearchable)
				\Curry_Backend_Indexer::updateItem($item);

			if ($request->isXmlHttpRequest())
				return \Symfony\Component\HttpFoundation\Response::create('');
		}

		$this->triggerCallback($this->preRender, $item, $form);
		$this->addMainContent($form);

		return parent::render();
	}
}
