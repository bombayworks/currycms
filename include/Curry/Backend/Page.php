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
 * Manage pages.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_Page extends Curry_Backend
{
	/** {@inheritdoc} */
	public static function getGroup()
	{
		return "Content";
	}

	public static function getName()
	{
		return "Pages";
	}

	public static function getNotifications()
	{
		return PageQuery::create()
			->filterByWorkingPageRevisionId(null, Criteria::ISNOTNULL)
			->where('Page.WorkingPageRevisionId != Page.ActivePageRevisionId')
			->count();
	}

	public static function updateIndex(Page $page)
	{
		if (Curry_Core::$config->curry->autoUpdateIndex)
			Curry_Application::registerBackgroundFunction(array(__CLASS__, 'doUpdateIndex'), $page);
	}

	public static function doUpdateIndex(Page $page)
	{
		// Re-index related pages
		$pages = $page->getDependantPages();
		$pages[] = $page;
		foreach($pages as $page) {
			Curry_Backend_Indexer::updateItem($page);
		}
	}

	public static function getTemplatePage()
	{
		$templatePage = Curry_Core::$config->curry->backend->templatePage;
		return $templatePage ? PageQuery::create()->findPk($templatePage) : null;
	}

	public static function isTemplatePage(Page $page)
	{
		$templatePage = self::getTemplatePage();
		return $templatePage && ($templatePage === $page || $page->isDescendantOf($templatePage));
	}
	
	/**
	 * Execute actions.
	 */
	public function preShow()
	{
		parent::preShow();
		Page::setRevisionType(Page::WORKING_REVISION);
	}

	/**
	 * Delete page
	 */
	public function showDeletePage()
	{
		if (!isPost())
			throw new Exception('Expected POST request');

		$page = self::getPage(PageAccessPeer::PERM_CREATE_PAGE);
		if (!$page)
			throw new Exception('You dont have access to delete this page');

		$dependantPages = $page->getDependantPages();
		if(count($dependantPages)) {
			$pageNames = join(", ", Curry_Array::objectsToArray($dependantPages, null, 'getName'));
			$this->addMessage("Unable to delete page, other pages depend on this page (".$pageNames.").", self::MSG_ERROR);
		} elseif($page->countChildren()) {
			$this->addMessage("Unable to delete page, page has subpages.", self::MSG_ERROR);
		} else {
			$name = $page->getName();
			$page->delete();
			$this->addMessage("Page '$name' was deleted.", self::MSG_SUCCESS);
		}
	}

	/**
	 * Publish single page revision
	 */
	public function showPublishPage()
	{
		if (!isPost())
			throw new Exception('Expected POST request');
		if (isset($_GET['page_revision_id'])) {
			$pageRevision = PageRevisionQuery::create()->findPk($_GET['page_revision_id']);
			$page = $pageRevision->getPage();
		} else if (isset($_GET['page_id'])) {
			$page = PageQuery::create()->findPk($_GET['page_id']);
			$pageRevision = $page ? $page->getWorkingPageRevision() : null;
		}
		if($page && $pageRevision) {
			if(!self::getPagePermission($page, PageAccessPeer::PERM_PUBLISH))
				throw new Exception('You do not have permission to publish this page.');
			$this->publishRevision($pageRevision);
			$this->addMessage('Page was published successfully!', self::MSG_SUCCESS);
		}

		$this->addPageMenu($page);
	}
	
	/**
	 * Get page from specified pageId while at the same time verifying permissions.
	 *
	 * @param string $permission
	 * @param int|null $pageId
	 * @return Page
	 */
	public function getPage($permission, $pageId = null)
	{
		if($pageId === null)
			$pageId = $_GET['page_id'];
		
		$page = PageQuery::create()->findPk($pageId);
		if(!$page) {
			$this->addPageMenu();
			throw new Exception('Page not found');
		}
		
		if($permission !== false && !self::getPagePermission($page, $permission)) {
			$this->addPageMenu($page);
			throw new Exception('You do not have permission to access this page.');
		}
		
		return $page;
	}
	
	/**
	 * Check if the logged in user has specified permission to page.
	 *
	 * @param Page $page
	 * @param string|null $permission
	 * @return array|bool
	 */
	public static function getPagePermission(Page $page, $permission = null)
	{
		$user = User::getUser();
		return $user ? $user->hasPagePermission($page, $permission) : false;
	}
	
	/**
	 * Show page tree.
	 */
	public function showMenu()
	{
		$access = array();
		foreach(PageQuery::create()->find() as $page) {
			if($this->getPagePermission($page, PageAccessPeer::PERM_VISIBLE))
				$access[] = $page->getPageId();
		}
		$query = PageQuery::create()
			->filterByPageId($access);
		$tree = new Curry_Tree_Propel($query, array(
			'ajaxUrl' => (string)url('', array('module', 'view'=>'Menu', 'page_id', 'json'=>1)),
			'minExpandLevel' => 2,
			'autoFocus' => false,
			'selectMode' => 1, // single
			'dndCallback' => array(__CLASS__, 'movePage'),
			'nodeCallback' => array($this, 'getPageTreeNode'),
		));
		// Override tree cookies to force tree selection
		$cookieId = $tree->getOption('cookieId');
		setcookie($cookieId."-focus", isset($_GET['page_id']) ? $_GET['page_id'] : null);
		setcookie($cookieId."-select", isset($_GET['page_id']) ? $_GET['page_id'] : null);

		$this->addMenuContent($tree);
	}
	
	/**
	 * Get page tree node properties.
	 *
	 * @param Page $page
	 * @param Curry_Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function getPageTreeNode($page, Curry_Tree $tree, $depth = 0)
	{
		$p = $tree->objectToJson($page, $tree, $depth);

		if($page->getWorkingPageRevisionId() && $page->getWorkingPageRevisionId() !== $page->getActivePageRevisionId()) {
			$p['title'] .= '*';
			$p['addClass'] = 'page-unpublished';
		}

		$p['expand'] = true;
		$p['href'] = (string)url('', array('module', 'view' => self::getPageView($page), 'page_id' => $page->getPageId()));
		
		// Mark active node
		if(isset($_GET['page_id']) && $_GET['page_id'] == $page->getPageId())
			$p['activate'] = $p['focus'] = $p['select'] = true;
		
		// Icon
		$p['iconClass'] = 'no-icon';
		if(self::isTemplatePage($page)) {
			if ($page === self::getTemplatePage())
				$p['title'] .= ' <span class="icon-columns"></span>';
		} else {
			$icon = "";
			if(!$page->getEnabled())
				$icon .= '<span class="icon-lock" title="Inactive"></span>';
			if(!$page->getVisible())
				$icon .= '<span class="icon-eye-close" title="Do not show in menu"></span>';
			if($page->getRedirectMethod())
				$icon .= '<span class="icon-link" title="Redirect"></span>';
			if ($icon)
				$p['title'] .= " $icon";
		}
		return $p;
	}
	
	/**
	 * Callback when moving pages in tree.
	 *
	 * @param array $params
	 * @return array|bool
	 */
	public static function movePage($params)
	{
		$page = PageQuery::create()->findPk($params['source']);
		$target = PageQuery::create()->findPk($params['target']);
		$mode = $params['mode'];
		if($page && $target) {
			trace('Moving '.$page->getName().' '.$mode.' '.$target->getName());

			// Remember if we have a custom url
			$isCustomUrl = $page->isCustomUrl();

			// Move page
			if($mode == 'over')
				$page->moveToFirstChildOf($target);
			else if($mode == 'before')
				$page->moveToPrevSiblingOf($target);
			else // $mode == 'after'
				$page->moveToNextSiblingOf($target);
			
			// Update URL
			if(!$isCustomUrl) {
				$page->setUrlRecurse($page->getExpectedUrl());
				$page->save();
			}
			
			return array('success' => true);
		}
		return false;
	}
	
	/** {@inheritdoc} */
	public function showMain()
	{
		$root = PageQuery::create()->findRoot();
		if ($root && self::getPagePermission($root, PageAccessPeer::PERM_CREATE_PAGE))
			$this->addDialogCommand("New page", url('', array('module', 'view'=>'NewPage')), 'icon-plus-sign', 'Create new page');
		$this->addCommand("Publish", url('', array('module', 'view'=>'Publish')), 'icon-ok-sign');
		$this->addPageMenu();
	}
	
	/**
	 * Show list of unpublished pages and prepare for publish.
	 */
	public function showPublish()
	{
		$options = array();
		$disabled = array();
		$enabled = array();
		foreach(PageQuery::create()->orderByBranch()->find() as $page) {
			$options[$page->getPageId()] = str_repeat(Curry_Core::SELECT_TREE_PREFIX, $page->getLevel()).$page->getName();
			if(!$page->getWorkingPageRevisionId() || $page->getWorkingPageRevisionId() == $page->getActivePageRevisionId())
				$disabled[] = $page->getPageId();
			else
				$enabled[$page->getPageId()] = 1;
		}

		if (!count($enabled)) {
			$this->addPageMenu();
			$this->addMessage('There are no pages to publish!', self::MSG_SUCCESS);
			return;
		}
		
		$form = new Curry_Form(array(
			'action' => url('', array('view')),
			'method' => 'post',
			'elements' => array(
				'force' => array('hidden', array('value'=>0)),
				'page_ids' => array('multiCheckbox', array(
					'label' => 'Unpublished pages',
					'required' => true,
					'multiOptions' => $options,
					'disable' => $disabled,
				)),
				'publish' => array('submit', array(
					'label' => 'Publish',
				)),
			),
		));
		
		if(isPost() && $form->isValid($_POST)) {
			$hasUnpublishedDependencies = false;
			$pageIds = $form->page_ids->getValue();
			foreach($pageIds as $pageId) {
				$page = PageQuery::create()->findPk($pageId);
				if(!self::getPagePermission($page, PageAccessPeer::PERM_PUBLISH))
					throw new Exception('You do not have permission to publish page '.$page->getName().'.');
				$dependencies = $page->getPageRevision()->getInheritanceChain();
				foreach($dependencies as $depRevision) {
					$dep = $depRevision->getPage();
					if(array_key_exists($dep->getPageId(), $enabled) && !in_array($dep->getPageId(), $pageIds)) {
						$hasUnpublishedDependencies = true;
						$this->addMessage('Publishing '.$page->getName().' while not publishing base page '.$dep->getName().' not recommended, use force publish to override.', self::MSG_WARNING);
					}
				}
			}
			$force = $form->force->getValue();
			if(!$hasUnpublishedDependencies || $force) {
				foreach($pageIds as $pageId) {
					$page = PageQuery::create()->findPk($pageId);
					$this->publishRevision($page->getWorkingPageRevision());
					$this->addMessage('Publishing page '.$page->getName(), self::MSG_SUCCESS);
				}
			} else if($hasUnpublishedDependencies) {
				$form->addElement('checkbox', 'force', array('order'=>0, 'label' => 'Force publish'));
			}
		}
		
		$this->addMainContent($form);
		$this->addPageMenu();
	}
	
	/**
	 * Show form to create new page.
	 */
	protected function showNewPage()
	{
		$parent = isset($_GET['page_id']) ? PageQuery::create()->findPk($_GET['page_id']) : null;
		$parentOrRoot = $parent ? $parent : PageQuery::create()->findRoot();
		if (!self::getPagePermission($parentOrRoot, PageAccessPeer::PERM_CREATE_PAGE))
			throw new Exception('You dont have access to create new pages');
		$advanced = $parentOrRoot ? self::getPagePermission($parentOrRoot, PageAccessPeer::PERM_MODULES) : false;
		$form = Curry_Backend_PageHelper::getNewPageForm($parent ? $parent->getPageId() : null, $advanced);
		if (isPost('pid_newpage') && $form->isValid($_POST)) {
			$values = $form->getValues();
			
			$basePage = null;
			if($values['base_page_id'])
				$basePage = PageQuery::create()->findPk($values['base_page_id']);
			
			$subpage = Curry_Backend_PageHelper::saveNewPage($values);
			$subpage->createDefaultRevisions($basePage);
			$subpage->save();
			
			// Redirect to properties of new page
			self::redirect(url('', array('module', 'view' => 'PageProperties', 'page_id' => $subpage->getPageId())));
		} else {
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Show base page image preview.
	 */
	public function showBasePreview()
	{
		$page = PageQuery::create()->findPk($_GET['page_id']);
		if ($page && file_exists($page->getImage())) {
			url($page->getImage())->redirect();
			exit;
		}
		
		url('shared/backend/common/images/no-page-image.png')->redirect();
	}
	
	/**
	 * Get name of current/default view for page.
	 *
	 * @param Page|null $page
	 * @return string
	 */
	public static function getPageView(Page $page = null)
	{
		$access = array();
		if($page) {
			$permissions = self::getPagePermission($page, null);
			if($permissions[PageAccessPeer::PERM_PROPERTIES])
				$access[] = 'PageProperties';
			if($permissions[PageAccessPeer::PERM_CONTENT])
				$access[] = 'Content';
			if($permissions[PageAccessPeer::PERM_META])
				$access[] = 'PageMetadata';
			if($permissions[PageAccessPeer::PERM_REVISIONS])
				$access[] = 'PageRevisions';
			if($permissions[PageAccessPeer::PERM_PERMISSIONS])
				$access[] = 'PagePermissions';
			if($permissions[PageAccessPeer::PERM_MODULES])
				$access[] = 'CopyPaste';
		}
			
		if(isset($_GET['view']) && in_array($_GET['view'], $access))
			return $_GET['view'];
			
		$ses = new Zend_Session_Namespace(__CLASS__);
		if(isset($ses->pageView) && in_array($ses->pageView, $access))
			return $ses->pageView;
		
		return count($access) ? $access[0] : '';
	}
	
	/**
	 * Add menu items for page.
	 *
	 * @param Page|null $page
	 * @param bool $menuItems
	 * @param string|null $defaultView
	 */
	public function addPageMenu($page = null, $menuItems = true, $defaultView = null)
	{
		if(!$page && isset($_GET['page_id']))
			$page = PageQuery::create()->findPk($_GET['page_id']);
			
		if(!$defaultView)
			$defaultView = self::getPageView($page);
		
		$this->addTrace("Pages", url('', array("module", "view"=>"Main")));
		$this->showMenu();

		if(!$page)
			return;
		
		// Trace
		$pages = array_reverse($page->getPath()->getArrayCopy());
		foreach($pages as $p)
			$this->addTrace($p->getName(), url('', array("module", "view" => $defaultView, "page_id"=>$p->getPageId())));
		
		// Menu items
		if($menuItems) {
			$permissions = self::getPagePermission($page, null);
			if($permissions[PageAccessPeer::PERM_PROPERTIES])
				$this->addMenuItem("Properties", url('', array("module", "view"=>"PageProperties","page_id"=>$page->getPageId())));
			if($permissions[PageAccessPeer::PERM_CONTENT])
				$this->addMenuItem("Content", url('', array("module", "view"=>"Content", "page_id"=>$page->getPageId())));
			if($permissions[PageAccessPeer::PERM_META])
				$this->addMenuItem("Metadata", url('', array("module", "view"=>"PageMetadata", "page_id"=>$page->getPageId())));
			if($permissions[PageAccessPeer::PERM_REVISIONS])
				$this->addMenuItem("Revisions", url('', array("module", "view"=>"PageRevisions","page_id"=>$page->getPageId())));
			if($permissions[PageAccessPeer::PERM_PERMISSIONS])
				$this->addMenuItem("Permissions", url('', array("module", "view"=>"PagePermissions","page_id"=>$page->getPageId())));
			if($permissions[PageAccessPeer::PERM_MODULES])
				$this->addMenuItem("Copy / Paste", url('', array("module", "view"=>"CopyPaste", "page_id"=>$page->getPageId())));
			
			$pageRevision = $page->getWorkingPageRevision();
			$published = $pageRevision ? $page->getActivePageRevisionId() == $pageRevision->getPageRevisionId() : false;
			
			// Show
			$url = url($page->getUrl(), array('curry_force_show' => 'true'));
			$this->addCommand('Show', $url, 'icon-search', array('title' => 'Show the published revision'));
			
			if($pageRevision && !$published) {
				// Preview
				$url = url($page->getUrl(), array('curry_show_working' => 'true'));
				$this->addCommand('Preview', $url, 'icon-wrench', array('title' => 'Preview the working revision'));
				
				// Publish
				if(self::getPagePermission($page, PageAccessPeer::PERM_PUBLISH)) {
					$url = url('', array('module', 'view'=>'PublishPage', 'page_revision_id'=>$pageRevision->getPageRevisionId()));
					$this->addCommand('Publish', $url, 'icon-ok-sign', array(
						'class' => 'postback',
						'title' => 'Publish working revision',
					));
				}
			}
			
			// Create
			if ($permissions[PageAccessPeer::PERM_CREATE_PAGE]) {
				$url = url('', array('module', 'view' => 'NewPage', 'page_id'));
				$this->addDialogCommand('New page', $url, 'icon-plus-sign', 'Create new subpage');
			}

			// New revision
			if ($permissions[PageAccessPeer::PERM_REVISIONS]) {
				$copyRevisionId = $pageRevision ? $pageRevision->getPageRevisionId() : $page->getActivePageRevisionId();
				$url = url('', array('module', 'page_id' => $page->getPageId(), 'copy_revision_id' => $copyRevisionId, 'view' => 'CreateRevision'));
				$this->addDialogCommand('New revision', $url, 'icon-copy');
			}
			
			// Live edit
			if(Curry_Core::$config->curry->liveEdit)
				$this->addCommand('Live edit', url($page->getUrl(), array('curry_inline_admin'=>'true')), 'icon-cogs');

			// Delete
			if($permissions[PageAccessPeer::PERM_CREATE_PAGE] && !$page->countChildren()) {
				$url = url('', array('module','view'=>'DeletePage', 'page_id'=>$page->getPageId()));
				$attr = array(
					'class' => 'postback btn-danger',
					'onclick' => "return confirm('Do you really want to delete this page? You cannot undo this.');",
				);
				$this->addCommand('Delete', $url, 'icon-trash', $attr);
			}
		}
	}
	
	/**
	 * Show metadata form.
	 */
	public function showPageMetadata()
	{
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'PageMetadata';
		
		$page = self::getPage(PageAccessPeer::PERM_META);
		$pageRevision = $page->getPageRevision();
		
		$form = Curry_Backend_PageHelper::getMetadataForm($pageRevision);
		if(isPost('pid_metadata') && $form->isValid($_POST)) {
			Curry_Backend_PageHelper::savePageMetadata($pageRevision, $form->getValues());
			$form = Curry_Backend_PageHelper::getMetadataForm($pageRevision);
			PagePeer::changePage();
			$this->addMessage('Page metadata was saved.', self::MSG_SUCCESS);
		}
		
		$this->addPageMenu($page);
		$this->addMainContent($form);
		
		if(self::getPagePermission(PageQuery::create()->findRoot(), PageAccessPeer::PERM_META)) {
			$url = url('', array('module', 'view' => 'NewMetadata'));
			$this->addDialogCommand('Add new field', $url, 'icon-plus');
		}
		
		$url = url('', array('module' => 'Curry_Backend_Database', 'view' => 'Table', 'table' => 'Metadata'));
		$this->addCommand('Edit fields', $url, 'icon-table');
	}
	
	/**
	 * Create new metadata for page.
	 *
	 */
	public function showNewMetadata()
	{
		if(!self::getPagePermission(PageQuery::create()->findRoot(), PageAccessPeer::PERM_META)) {
			$this->addMessage('You do not have permission to access this page.', self::MSG_ERROR);
			return;
		}
		
		$form = Curry_Backend_PageHelper::getNewMetadataForm();
		if(isPost('pid_newmetadata') && $form->isValid($_POST)) {
			Curry_Backend_PageHelper::saveNewMetadata($form->getValues());
			Curry_Application::returnPartial('');
		}
		$this->addMainContent($form);
	}
	
	/**
	 * Show revisions for page.
	 *
	 */
	public function showPageRevisions()
	{
		$page = self::getPage(PageAccessPeer::PERM_REVISIONS);
		
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'PageRevisions';
		
		$this->addPageMenu($page);
		$query = PageRevisionQuery::create()->filterByPage($page);
		$list = new Curry_ModelView_List($query, array(
			'columns' => array(
				'template' => false,
				'description' => array(
					'escape' => false,
					'callback' => function($pageRevision) {
						$page = $pageRevision->getPage();
						$icon = '';
						if($page->getActivePageRevision() === $pageRevision)
							$icon .= ' <i class="icon-bolt" title="Active revision" />';
						if($page->getWorkingPageRevision() === $pageRevision)
							$icon .= ' <i class="icon-wrench" title="Working revision"></i>';
						return htmlspecialchars($pageRevision->getDescription()) . $icon;
					},
				)
			),
			'actions' => array(
				'edit' => false,
				'new' => array(
					'href' => (string)url('', array("module","page_id"=>$page->getPageId(), "view"=>"CreateRevision")),
				),
				'make_working' => array(
					'label' => 'Switch to working',
					'single' => true,
					'class' => 'post',
					'action' => function($revision, $backend) use($page) {
						if (isPost()) {
							$page->setWorkingPageRevision($revision);
							$page->save();
							$backend->createModelUpdateEvent('Page', $page->getPrimaryKey(), 'update');
							$backend->createModelUpdateEvent('PageRevision', $revision->getPrimaryKey(), 'update');
						}
					},
				),
			),
		));
		if (self::getPagePermission($page, PageAccessPeer::PERM_PUBLISH)) {
			$list->addAction('publish_date', array(
				'label' => 'Set publish date',
				'single' => true,
				'class' => 'inline',
				'href' => (string)url('', array('module', 'view' => 'SetPublishDate')),
			));
			$list->addAction('publish', array(
				'single' => true,
				'class' => 'post',
				'action' => function($revision, Curry_Backend_Page $backend) use($page) {
					if (isPost()) {
						$backend->publishRevision($revision);
						$backend->createModelUpdateEvent('Page', $page->getPrimaryKey(), 'update');
						$backend->createModelUpdateEvent('PageRevision', $revision->getPrimaryKey(), 'update');
					}
				},
			));
		}
		$list->show($this);
	}

	/**
	 * Publish page revision.
	 *
	 * @param PageRevision $pageRevision
	 */
	public function publishRevision(PageRevision $pageRevision)
	{
		$page = $pageRevision->getPage();
		
		$page->setActivePageRevision($pageRevision);
		$page->setWorkingPageRevision(null);
		$page->save();
		
		$pageRevision->setPublishedDate(time());
		$pageRevision->keepUpdateDateUnchanged();
		$pageRevision->save();

		self::updateIndex($page);
	}
	
	/**
	 * Set publish date for revision.
	 */
	public function showSetPublishDate()
	{
		$pageRevision = PageRevisionQuery::create()->findPk(json_decode($_GET['item']));
		if(!$pageRevision)
			throw new Exception('PageRevision not found');
		if(!self::getPagePermission($pageRevision->getPage(), PageAccessPeer::PERM_PUBLISH))
			throw new Exception('You do not have permission to publish this page.');
			
		if(!Curry_Core::$config->curry->autoPublish)
			$this->addMessage('Auto publishing is not enabled, for this functionality to work you must enable it in <a href="'.url('', array('module'=>'Curry_Backend_System')).'">System</a>.', self::MSG_WARNING, false);
			
		$form = Curry_Backend_PageHelper::getPublishDateForm($pageRevision);
		if(isPost('pid_setpublishdate') && $form->isValid($_POST)) {
			$values = $form->getValues();
			if($values['publish_date'] == '' && $values['publish_time'] == '')
				$pageRevision->setPublishDate(null);
			else
				$pageRevision->setPublishDate($values['publish_date'].' '.$values['publish_time']);
			$pageRevision->keepUpdateDateUnchanged();
			$pageRevision->save();
			$this->createModelUpdateEvent('PageRevision', $pageRevision->getPrimaryKey(), 'update');
		}
		
		$this->addMainContent($form);
	}
	
	/**
	 * Create new revision.
	 */
	public function showCreateRevision()
	{
		$page = self::getPage(PageAccessPeer::PERM_REVISIONS);
		$form = Curry_Backend_PageHelper::getNewRevisionForm($page, $_GET['copy_revision_id']);
		if (isPost('pid_createrevision') && $form->isValid($_POST)) {
			Curry_Backend_PageHelper::saveNewRevision($page, $form->getValues());
			self::redirect(url('', array('module', 'view' => self::getPageView($page), 'page_id' => $page->getPageId())));
		} else {
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Copy/paste pages.
	 */
	public function showCopyPaste()
	{
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'CopyPaste';
		
		$page = self::getPage(PageAccessPeer::PERM_MODULES);
		
		$copyForm = new Curry_Form(array(
			'action' => url('', array('view','page_id')),
			'method' => 'post',
			'elements' => array(
				'copy_code' => array('textarea', array(
					'label' => 'Page Code',
					'value' => json_encode(Curry_Backend_PageSyncHelper::getPageCode($page)),
					'onclick' => 'select()',
					'rows' => 3,
				)),
			),
		));
		
		$form = new Curry_Form(array(
			'action' => url('', array('view','page_id')),
			'method' => 'post',
			'elements' => array(
				'code' => array('textarea', array(
					'label' => 'Paste code',
					'onclick' => 'select()',
					'rows' => 3,
				)),
				'subpages' => array('checkbox', array(
					'label' => 'Subpages',
					'value' => true,
				)),
				'submit' => array('submit', array(
					'label' => 'Paste',
				))
			),
		));
		
		if(isPost() && $form->isValid($_POST)) {
			$pageData = json_decode($form->code->getValue(), true);
			$subpages = (bool)$form->subpages->getValue();
			if($pageData && $pageData['name']) {
				unset($pageData['name']);
				$pages = Curry_Backend_PageSyncHelper::restorePages($page, $pageData, $subpages);
				$this->addMessage('Pasted '.count($pages).' page(s) successfully.', self::MSG_SUCCESS);
			}
		}

		$this->addPageMenu($page);
		$this->addMainContent($copyForm);
		$this->addMainContent( $form );
	}
	
	/**
	 * Show page properties form.
	 */
	public function showPageProperties()
	{
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'PageProperties';
		
		$page = self::getPage(PageAccessPeer::PERM_PROPERTIES);
		$form = Curry_Backend_PageHelper::getPagePropertiesForm($page);
		if (isPost('pid_properties') && $form->isValid($_POST)) {
			$values = $form->getValues();
			Curry_Backend_PageHelper::savePageProperties($page, $values);
			$form = Curry_Backend_PageHelper::getPagePropertiesForm($page);
			Curry_Admin::getInstance()->addBodyClass('live-edit-close');
			self::updateIndex($page);
		}
		
		$this->addPageMenu($page);
		$this->addMainContent($form);
	}

	/**
	 * Show page permissions.
	 */
	public function showPagePermissions()
	{
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'PagePermissions';
		
		$page = self::getPage(PageAccessPeer::PERM_PERMISSIONS);
		if(isPost('pid_permissions'))
			Curry_Backend_PageHelper::savePagePermission($page, $_POST);
		
		$this->addPageMenu($page);
		$this->addMainContent(Curry_Backend_PageHelper::getPagePermissionForm($page));
	}
	
	/**
	 * Show edit content form.
	 */
	public function showContent()
	{
		$ses = new Zend_Session_Namespace(__CLASS__);
		$ses->pageView = 'Content';
		
		$page = self::getPage(PageAccessPeer::PERM_CONTENT);
		$pageRevision = $page->getWorkingPageRevision();
		if(!$pageRevision) {
			$this->addPageMenu($page);
			$this->addMessage('To edit this page you need to create a new working revision.', self::MSG_WARNING);
			return;
		}

		if(empty($_GET['action'])) {
			$this->addPageMenu($page);
		}

		$langcode = null;
		if ($this->getPagePermission($page, PageAccessPeer::PERM_MODULES)) {
			if (!empty($_GET['langcode']))
				$langcode = $_GET['langcode'];
			if (empty($_GET['action'])) {
				$langform = $this->getLanguageForm($langcode);
				$this->addMainContent($langform);
			}
		}

		$list = new Curry_Backend_ContentList($this, $pageRevision, $langcode);
		$list->show($this);
	}
	
	/**
	 * Get language selection form.
	 *
	 * @param string $langcode
	 * @return Curry_Form
	 */
	private function getLanguageForm($langcode)
	{
		$form = new Curry_Form(array(
			'method' => 'get',
			'action' => url(''),
			'id' => 'language-selector',
			'elements' => array(
				'langcode' => array('select', array(
					'label' => 'Language',
					'multiOptions' => array('' => 'None') + LanguageQuery::create()->find()->toKeyValue('Langcode','Name'),
					'onchange' => 'this.form.submit();',
					'value' => $langcode,
					'description' => 'Change this if you want to set language specific content.',
				)),
			)
		));
		
		$preserve = array('module', 'view', 'page_module_id', 'page_id');
		foreach($preserve as $var) {
			if(isset($_GET[$var]))
				$form->addElement('hidden', $var, array('value' => $_GET[$var]));
		}
		
		return $form;
	}

	/**
	 * Create new module form.
	 */
	public function showNewModule()
	{
		$page = PageQuery::create()->findPk($_GET['page_id']);
		if(!$page)
			throw new Exception('Page not found');
		$pageRevision = $page->getWorkingPageRevision();
		
		try {
			$moduleClass = isset($_POST['module_class']) ? $_POST['module_class'] : '';
			$target = isset($_GET['target']) ? $_GET['target'] : '';
			$form = Curry_Backend_PageHelper::getNewModuleForm($pageRevision, $moduleClass, $target);
			if(isPost('pid_newmodule') && $form->isValid($_POST)) {
				$pageModule = Curry_Backend_PageHelper::saveNewModule($pageRevision, $form->getValues());
				if ($page->getActivePageRevisionId() == $pageRevision->getPageRevisionId())
					self::updateIndex($page);
				$this->createModelUpdateEvent('PageModule', $pageModule->getPrimaryKey(), 'insert');
				$url = url('', array('module','view' => 'Module', 'page_id' => $pageModule->getPageId(),'page_module_id' => $pageModule->getPageModuleId()));
				$this->redirect($url, false);
			}
			$this->addMainContent($form);
		}
		catch (Exception $e) {
			$this->addMainContent($e->getMessage());
		}
	}

	/**
	 * Generate TinyMCE link list.
	 */
	public function showTinyMceList()
	{
		$pages = array();
		foreach(PageQuery::create()->orderByBranch()->find() as $page) {
			if (self::isTemplatePage($page))
				continue;
			$pages[] = array(
				str_repeat(Curry_Core::SELECT_TREE_PREFIX, $page->getLevel()) . $page->getName(),
				$page->getUrl(),
			);
		}
		Curry_Application::returnPartial('var tinyMCELinkList = ' . json_encode($pages).';', "text/plain");
	}

	/**
	 * Swap position of modules.
	 *
	 * @todo Fix ugly $_POST hack.
	 *
	 * @throws Exception
	 */
	public function showSwapModules()
	{
		$page = self::getPage(PageAccessPeer::PERM_CONTENT);
		$pageRevision = $page->getPageRevision();

		$a = (int)$_POST['a'];
		$b = (int)$_POST['b'];
		$modules = Curry_Array::objectsToArray($pageRevision->getModules(), null, 'getPageModuleId');
		$aIndex = array_search($a, $modules);
		$bIndex = array_search($b, $modules);
		if ($aIndex === false || $bIndex === false)
			throw new Exception('Unable to find modules to swap.');
		$modules[$aIndex] = $b;
		$modules[$bIndex] = $a;

		$list = new Curry_Backend_ContentList($this, $pageRevision);
		$_POST['item'] = array_map('json_encode', $modules);
		$list->sortItems(array());
	}

	/**
	 * Module properties.
	 */
	public function showModuleProperties()
	{
		url('', array('module','page_id','view'=>'Content','action'=>'properties','item'=>json_encode($_GET['page_module_id'])))->redirect();
	}
	
	/**
	 * Delete module.
	 */
	public function showDeleteModule()
	{
		url('', array('module','page_id','view'=>'Content','action'=>'delete','item'=>json_encode($_GET['page_module_id'])))->redirect();
	}

	/**
	 * Show module.
	 */
	public function showModule()
	{
		url('', array('module','page_id','view'=>'Content','action'=>'edit','item'=>json_encode($_GET['page_module_id'])))->redirect();
	}
}
