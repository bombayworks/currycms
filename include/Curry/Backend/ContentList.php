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
use Curry\Backend\AbstractBackend;
use Curry\Module\PageModuleWrapper;
use Curry\Util\ArrayHelper;
use Curry\Util\Html;

/**
 * Page content list view.
 * Manage module content for a specific PageRevision.
 *
 * @package Curry\Backend
 */
class Curry_Backend_ContentList extends Curry_ModelView_List {
	/**
	 * @var PageRevision
	 */
	protected $pageRevision;

	/**
	 * @var string
	 */
	protected $langcode = "";

	/**
	 * @var array
	 */
	protected $pagePermission = array();

	public function __construct(Curry_Backend_Page $backend, PageRevision $pageRevision, $langcode = "")
	{
		$this->pageRevision = $pageRevision;
		$this->langcode = $langcode;
		parent::__construct('PageModule', array(
			'title' => 'Content',
			'maxPerPage' => 0,
			'columns' => array(
				'name' => array(
					'sortable' => false,
					'action' => 'edit',
					'callback' => function($wrapper) {
						return $wrapper->getName();
					}
				),
				'target' => array(
					'sortable' => false,
					'callback' => function($wrapper) {
						return $wrapper->getTarget();
					}
				),
				'type' => array(
					'sortable' => false,
					'escape' => false,
					'callback' => function($wrapper) {
						return Html::tag('span', array('title' => $wrapper->getClassName()),
							basename(str_replace('_', '/', $wrapper->getClassName()))
						);
					}
				),
			),
			'addDefaultActions' => false,
			'actions' => array(
				'edit' => array(
					'single' => true,
					'class' => 'inline',
					'action' => array($this, 'showEdit'),
				),
			),
			'sortable' => array($this, 'sortItems'),
		));

		$user = User::getUser();
		$this->pagePermission = $backend->getPagePermission($pageRevision->getPage());

		if ($user->hasAccess('Curry_Backend_Template')) {
			$this->addColumn('template', array(
				'sortable' => false,
				'escape' => false,
				'callback' => function($wrapper) {
					if (!$wrapper->getTemplate())
						return 'None';
					$templateUrl = url('', array('module' => 'Curry_Backend_Template', 'view' => 'Edit', 'file' => $wrapper->getTemplate()));
					return Html::tag('a', array(
						'href' => $templateUrl,
						'title' => $wrapper->getTemplate(),
					), basename($wrapper->getTemplate()));
				}
			));
		}
		if ($this->pagePermission[PageAccessPeer::PERM_MODULES]) {
			$this->addColumn('info', array(
				'sortable' => false,
				'escape' => false,
				'callback' => array($this, 'getInfo'),
				'order' => 1,
			));
			$this->addAction('properties', array(
				'single' => true,
				'class' => 'dialog',
				'action' => array($this, 'showProperties'),
			));
			$this->addAction('inheritance', array(
				'label' => 'Inheritance details',
				'general' => true,
				'class' => 'dialog',
				'action' => array($this, 'showInheritance'),
			));
		}
		if ($this->pagePermission[PageAccessPeer::PERM_CREATE_MODULE]) {
			$this->addAction('new', array(
				'general' => true,
				'label' => 'New content',
				'class' => 'dialog',
				'href' => (string)url('', array('module'=>'Curry_Backend_Page','page_id'=>$this->pageRevision->getPageId(),'view'=>'NewModule')),
			));
			$this->addAction('delete', array(
				'single' => true,
				'class' => 'inline modelview-delete',
				'action' => array($this, 'showDelete'),
			));
		}
	}

	protected function getPageInheritance($page, $wrappers)
	{
		$ret = Html::tag('a', array(
				'href' => url('', array('module','view','page_id'=>$page->getPageId())),
				'title' => $page->getUrl(),
			), $page->getName());

		// Module sorting
		$sorted = ModuleSortorderQuery::create()
			->filterByPageRevisionId($page->getWorkingPageRevisionId())
			->count() > 0;
		if ($sorted)
			$ret .= ' <i class="icon-reorder" title="Sorted"></i>';

		// Modules
		$moduleDatas = ModuleDataQuery::create()
			->filterByPageRevisionId($page->getWorkingPageRevisionId())
			->filterByPageModuleId(array_keys($wrappers))
			->find();
		$list = array();
		foreach($moduleDatas as $moduleData) {
			$id = $moduleData->getPageModuleId();
			$wrapper = $wrappers[$id];
			if (!isset($list[$id]))
				$list[$id] = $wrapper->getName().' ';
			if ($moduleData->getLangcode())
				$list[$id] .= ' ('.$moduleData->getLangcode().' ';
			if ($moduleData->getData() !== null)
				$list[$id] .= '<i class="icon-picture" title="Content"></i>';
			if ($moduleData->getTemplate() !== null)
				$list[$id] .= '<i class="icon-file-alt" title="Template"></i>';
			if ($moduleData->getEnabled() !== null)
				$list[$id] .= '<i class="icon-lightbulb" title="Module '.($moduleData->getEnabled()?'enabled':'disabled').'"></i>';
			if ($moduleData->getLangcode())
				$list[$id] .= ')';
		}
		if (count($list))
			$ret .= '<br/>'.join('<br/>', $list);

		// Subpages
		$subPages = PageQuery::create()
			->useWorkingPageRevisionQuery('', Criteria::INNER_JOIN)
			->filterByBasePage($page)
			->endUse()
			->find();
		$list = array();
		foreach($subPages as $subPage) {
			$list[] .= $this->getPageInheritance($subPage, $wrappers);
		}
		if (count($list))
			$ret .= '<ul>'.join('', $list).'</ul>';

		return '<li>'.$ret.'</li>';
	}

	public function showInheritance($selection, $backend)
	{
		$backend->addMainContent('<ul>'.$this->getPageInheritance($this->pageRevision->getPage(), $this->pageRevision->getPageModuleWrappers()).'</ul>');
	}

	public function sortItems($params)
	{
		ModuleSortorderQuery::create()
			->filterByPageRevision($this->pageRevision)
			->delete();

		$wrappers = $this->pageRevision->getPageModuleWrappers();
		$unsortedIds = ArrayHelper::objectsToArray($wrappers, false, 'getPageModuleId');
		$wrapperById = ArrayHelper::objectsToArray($wrappers, 'getPageModuleId');

		// Get primary keys
		$items = $_POST['item'];
		if (!is_array($items))
			throw new Exception('Expected array POST variable `item`.');
		$sortedIds = array();
		foreach($items as $item) {
			$pk = json_decode($item, true);
			if ($pk === null)
				throw new Exception('Invalid primary key for item: '.$item);
			if (!array_key_exists($pk, $wrapperById))
				throw new Exception('Module not found when sorting');
			$sortedIds[] = $pk;
		}

		if ($sortedIds !== $unsortedIds) {
			foreach($wrappers as $wrapper) {
				$sortorder = $wrapper->getSortorder(true);
				$sortorder->insertAtBottom();
				$sortorder->save();
			}
			$pks = array();
			foreach($sortedIds as $id) {
				$pks[] = array($id, $this->pageRevision->getPageRevisionId());
			}
			Curry_Propel::sortableReorder($pks, 'ModuleSortorder');
		}

		$this->pageRevision->setUpdatedAt(time());
		$this->pageRevision->save();
	}

	public function getSelfSelection($params)
	{
		$pk = isset($params['item']) ? json_decode($params['item'], true) : null;
		$pageModule = $pk ? PropelQuery::from($this->getModelClass())->findPk($pk) : null;
		if (!$pageModule)
			return null;
		return new PageModuleWrapper($pageModule, $this->pageRevision, $this->langcode);
	}

	public function getInfo(PageModuleWrapper $wrapper)
	{
		$icons = '';
		$pageModule = $wrapper->getPageModule();
		if ($wrapper->isInherited()) {
			$pageUrl = url('', array('module','view','page_id' => $pageModule->getPageId()));
			$icons .= '<a href="'.$pageUrl.'" title="Inherited from '.$pageModule->getPage().'"><i class="icon-double-angle-up"></i></a>';
		} else {
			$icons .= '<i class="icon-double-angle-down" title="Content is inherited to subpages"></i>';
		}

		$langContent = ModuleDataQuery::create()
			->filterByPageModule($wrapper->getPageModule())
			->filterByPageRevision($wrapper->getPageRevision())
			->filterByLangcode("", Criteria::NOT_EQUAL)
			->select('Langcode')
			->find()
			->getArrayCopy();

		if (!$wrapper->getEnabled())
			$icons .= ' <i class="icon-eye-close"></i>';
		if ($wrapper->hasData())
			$icons .= ' <i class="icon-picture" title="Page content"></i>';
		if (count($langContent))
			$icons .= ' <i class="icon-flag" title="Language specific content: '.htmlspecialchars(join(', ', $langContent)).'"></i>';
		return $icons;
	}

	protected function addQueryColumns()
	{
	}

	protected function getItemKey($obj)
	{
		return json_encode($obj->getPageModuleId());
	}

	protected function find($query, $params)
	{
		$all = $this->pageRevision->getPageModuleWrappers($this->langcode);
		$user = User::getUser();

		if ($user->hasPagePermission($this->pageRevision->getPage(), PageAccessPeer::PERM_MODULES))
			return $all;

		$wrappers = array();
		foreach($all as $wrapper) {
			if ($user->hasModuleAccess($wrapper))
				$wrappers[] = $wrapper;
		}
		return $wrappers;
	}

	protected function getResult($items, $rows)
	{
		return array(
			"page" => 1,
			"total" => count($items),
			"rows" => $rows
		);
	}

	public function showDelete(PageModuleWrapper $wrapper, $backend)
	{
		if ($wrapper->isInherited() && !$this->pagePermission[PageAccessPeer::PERM_MODULES]) {
			$backend->addMessage('You do not have permission to delete inherited modules.', AbstractBackend::MSG_ERROR);
			return;
		}
		if (!$this->pagePermission[PageAccessPeer::PERM_CREATE_MODULE]) {
			$backend->addMessage('You do not have permission to delete modules.', AbstractBackend::MSG_ERROR);
			return;
		}
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'elements' => array(
				'delete' => array('submit', array(
					'label' => 'Delete',
				)),
			),
		));
		if (isPost() && $form->isValid($_POST)) {
			$pageModule = $wrapper->getPageModule();
			$pk = $pageModule->getPrimaryKey();
			$revisionModule = RevisionModuleQuery::create()
				->findPk(array($pageModule->getPageModuleId(), $wrapper->getOriginPage()->getWorkingPageRevisionId()));
			if (!$revisionModule)
				throw new Exception('Unable to find RevisionModule to delete.');
			$revisionModule->delete();
			$backend->addMessage('The module has been deleted!', AbstractBackend::MSG_SUCCESS);
			$backend->createModelUpdateEvent('PageModule', $pk, 'delete');
			$backend->addBodyClass('live-edit-close');
		} else {
			$msg = 'Do you really want to delete this module?';
			$originPage = $wrapper->getOriginPage();
			$dependencies = $originPage->getDependantPages();
			if ($wrapper->isInherited()) {
				$backend->addMessage('This module is inherited and will be removed from '.$originPage.
					'. It will also be removed from the following subpages: '.
					join(", ", $dependencies), AbstractBackend::MSG_WARNING);
			} else if(count($dependencies)) {
				$backend->addMessage('This module is inherited to subpages and will '.
					'also be removed from the following subpages: '.
					join(", ", $dependencies), AbstractBackend::MSG_WARNING);
			}
			$backend->addMessage($msg);
			$backend->addMainContent($form);
		}
	}

	public function showProperties(PageModuleWrapper $wrapper, $backend)
	{
		$form = Curry_Backend_PageHelper::getModulePropertiesForm($wrapper);
		if (isPost('pid_moduleproperties') && $form->isValid($_POST)) {
			$values = $form->getValues();
			Curry_Backend_PageHelper::saveModuleProperties($wrapper, $values);
			$wrapper->reload();
			$form = Curry_Backend_PageHelper::getModulePropertiesForm($wrapper);
			$backend->createModelUpdateEvent('PageModule', $wrapper->getPageModuleId(), 'update');
			if (isAjax())
				return;
			else
				$backend->addBodyClass('live-edit-close');
		}
		$backend->addMainContent($form);
	}

	public function showEdit(PageModuleWrapper $wrapper, $backend)
	{
		$form = $this->getModuleForm($wrapper);
		if(!$form) {
			$backend->addMessage('This module doesn\'t have a backend.');
			return;
		}

		if(isPost('pid_editmodule') && $form->isValid($_POST)) {
			$modified = false;
			$moduleForm = $form->getSubForm('module'.$wrapper->getPageModuleId());
			if($moduleForm) {
				$module = $wrapper->createObject();
				if($module->saveBack($moduleForm) !== false)
					$modified = $module->saveModule();
				$backend->createModelUpdateEvent('PageModule', $wrapper->getPageModuleId(), 'update');
				$backend->addBodyClass('live-edit-close');
			}

			if($form->delete && $form->delete->isChecked()) {
				$wrapper->getModuleData()->delete();
				$modified = true;
			} else if($form->create && $form->create->isChecked() && !$wrapper->hasData()) {
				$wrapper->createData();
				$modified = true;
			}

			if($modified) {
				$wrapper->reload();
				$pageRevision = $wrapper->getPageRevision();
				$pageRevision->setUpdatedAt(time());
				$pageRevision->save();
				if ($pageRevision->getPage()->getActivePageRevisionId() == $pageRevision->getPageRevisionId())
					Curry_Backend_Page::updateIndex($pageRevision->getPage());
			}

			$form = $this->getModuleForm($wrapper);
		}

		if (!$wrapper->hasData()) {
			$moduleData = $wrapper->getDataSource();
			if ($moduleData) {
				$page = $moduleData->getPageRevision()->getPage();
				$backend->addMainContent('<p>Content is inherited from <strong title="'.$page->getUrl().'">'.
					htmlspecialchars($page->getName()).'</strong> page.</p>');
			} else {
				$backend->addMainContent('<p>No content has been set for this page.</p>');
			}
		}
		$backend->addMainContent($form);
	}

	/**
	 * Edit module form.
	 *
	 * @param PageModuleWrapper $wrapper
	 * @return Curry_Form|null
	 */
	protected function getModuleForm(PageModuleWrapper $wrapper)
	{
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'elements' => array(
				'pid_editmodule' => array('hidden'),
			)
		));

		if($wrapper->hasData()) {
			$subform = $wrapper->createObject()->showBack();
			if($subform == null) {
				return null;
			}

			if(!($subform instanceof Curry_Form_SubForm))
				throw new Exception($wrapper->getClassName().'::showBack() did not return an instance of Curry_Form_SubForm.');
			if(!$subform->getLegend())
				$subform->setLegend($wrapper->getName() . ' ('.$wrapper->getClassName().')');
			if(!($subform instanceof Curry_Form_MultiForm))
				$subform->setDecorators(array('FormElements'));
			$form->addSubForm($subform, 'module'.$wrapper->getPageModuleId());

			$buttons = array('save');
			$form->addElement('submit', 'save', array('label'=>'Save'));
			if($wrapper->isDeletable()) {
				$form->addElement('submit', 'delete', array('label'=>'Remove content'));
				$buttons[] = 'delete';
			}
			$form->addDisplayGroup($buttons, 'dg1', array('class' => 'horizontal-group'));
		} else {
			$form->addElement('submit', 'create', array('label' => $wrapper->isInherited() ? 'Override content' : 'Create content'));
		}

		return $form;
	}
}
