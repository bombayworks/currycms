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
 * Page Generator for HTML documents.
 * 
 * Adds support for inline admin and HTML-head.
 *
 * @package Curry
 */
class Curry_PageGenerator_Html extends Curry_PageGenerator {
	/**
	 * Is Inline Admin enabled?
	 *
	 * @var bool
	 */
	protected $inlineAdmin = false;
	
	/**
	 * Object to modify HTML-head
	 *
	 * @var Curry_HtmlHead
	 */
	protected $htmlHead;
	
	/**
	 * Constructor
	 *
	 * @param PageRevision $pageRevision
	 * @param Curry_Request $request
	 */
	public function __construct(PageRevision $pageRevision, Curry_Request $request)
	{
		$this->htmlHead = new Curry_HtmlHead();
		parent::__construct($pageRevision, $request);
	}

	/**
	 * Content type is text/html.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		return "text/html";
	}
	
	/**
	 * Get an Curry_HtmlHead object to modify the &lt;head&gt; section of the html-page.
	 *
	 * @return Curry_HtmlHead
	 */
	public function getHtmlHead()
	{
		return $this->htmlHead;
	}
	
	/** {@inheritdoc} */
	public function generate(array $options = array())
	{
		$this->inlineAdmin = isset($options['inlineAdmin']) && (bool)$options['inlineAdmin'];
		return parent::generate($options);
	}
	
	/** {@inheritdoc} */
	protected function insertModule(Curry_PageModuleWrapper $pageModuleWrapper)
	{
		$this->htmlHead->clearBacklog();
		if($this->inlineAdmin && $this->pageRevision->allowEdit())
			return $this->adminModule(parent::insertModule($pageModuleWrapper), $pageModuleWrapper);
		return parent::insertModule($pageModuleWrapper);
	}
	
	/** {@inheritdoc} */
	protected function saveModuleCache($cacheName, $lifetime)
	{
		$this->moduleCache['head'] = $this->htmlHead->getBacklog();
		parent::saveModuleCache($cacheName, $lifetime);
	}
	
	/** {@inheritdoc} */
	protected function insertCachedModule($cache)
	{
		if(is_array($cache['head'])) {
			$this->htmlHead->replay($cache['head']);
		}
		parent::insertCachedModule($cache);
	}
	
	/** {@inheritdoc} */
	protected function postGeneration()
	{
		parent::postGeneration();
		
		if($this->inlineAdmin)
			$this->adminPanel();
		else if (Curry_Core::$config->curry->liveEdit && User::getUser()) {
			// Add button to toggle live edit
			$url = json_encode(url('', $_GET)->add(array('curry_inline_admin'=>1))->getAbsolute());
			$htmlHead = $this->getHtmlHead();
			$htmlHead->addInlineScript(<<<JS
document.addEventListener('DOMContentLoaded', function() {
	var el = document.createElement('a');
	el.id = 'curry-enable-live-edit';
	el.href = $url;
	el.textContent = 'Live edit';
	el.style.display = 'block';
	el.style.position = 'fixed';
	el.style.right = '5px';
	el.style.top = '5px';
	el.style.backgroundColor = 'black';
	el.style.color = 'white';
	el.style.padding = '5px';
	el.style.zIndex = 1000;
	el.style.textDecoration = 'none';
	document.body.appendChild(el);
});
JS
);
		}
		
		$appVars = Curry_Application::getInstance()->getGlobalVariables();
		$appVars->HtmlHead = $this->htmlHead->getContent();
	}

	public function renderTemplate($template, $moduleContent)
	{
		if ($this->inlineAdmin) {
			$excluded = Curry_Core::$config->curry->backend->placeholderExclude->toArray();
			$placeholders = array();
			$tpl = $template;
			while($tpl) {
				$placeholders = array_merge($placeholders, $tpl->getPlaceholders());
				$tpl = $tpl->getParent(array());
			}
			foreach($placeholders as $id => $placeholder) {
				if (in_array($placeholder, $excluded))
					continue;
				if (!array_key_exists($placeholder, $moduleContent))
					$moduleContent[$placeholder] = '';
				$moduleContent[$placeholder] = $this->adminBlock($moduleContent[$placeholder], $placeholder, $id);
			}
		}
		return parent::renderTemplate($template, $moduleContent);
	}

	/**
	 * Build admin panel
	 */
	protected function adminPanel()
	{
		$user = User::getUser();
		$page = $this->getPage();
		$commands = array();
		
		$url = url('admin.php?module=Curry_Backend_Page', array("view"=>"PageProperties", "page_id"=>$this->pageRevision->getPageId() ));
		if($user->hasPagePermission($page, PageAccessPeer::PERM_PROPERTIES))
			$commands[] = array('Name' => 'Page properties', 'Url' => $url, 'Class' => 'iframe');
		
		$url = url('admin.php?module=Curry_Backend_Page', array('module'=>'Curry_Backend_Page', 'view'=>'NewPage', 'page_id' => $this->pageRevision->getPageId()));
		if($user->hasPagePermission($page, PageAccessPeer::PERM_CREATE_PAGE))
			$commands[] = array('Name' => 'New page', 'Url' => $url, 'Class' => 'iframe');

		$url = url('admin.php?module=Curry_Backend_Page', array('module'=>'Curry_Backend_Page', 'view'=>'PageRevisions', "page_id"=>$this->pageRevision->getPageId()));
		if($user->hasPagePermission($page, PageAccessPeer::PERM_REVISIONS))
			$commands[] = array('Name' => 'Page revisions', 'Url' => $url, 'Class' => 'iframe');

		if($this->pageRevision->allowEdit()) {
			$commands[] = array('Name' => 'Show all modules', 'Url' => '#', 'Class' => 'toggle-show-all-modules');
		} else {
			$url = '';
			$commands[] = array('Name' => 'Create working revision (TODO)', 'Url' => $url, 'Class' => 'iframe');
		}

		$view = Curry_Backend_Page::getPageView($page);
		$url = url('admin.php?module=Curry_Backend_Page', array('view'=>$view,'page_id'=>$this->pageRevision->getPageId()));
		$commands[] = array('Name' => 'Backend', 'Url' => $url, 'Class' => 'curry-admin-backend');
		
		$url = (string)url('', $_GET)->add(array('curry_inline_admin'=>0));
		$commands[] = array('Name' => 'Exit Live Edit', 'Url' => $url, 'Class' => 'curry-admin-logout');
		
		$tpl = Curry_Twig_Template::loadTemplateString(Curry_InlineAdmin::getAdminPanelTpl());
		$content = $tpl->render(array(
			'commands' => $commands,
		));
		
		$htmlHead = $this->getHtmlHead();
		$htmlHead->addScript(Curry_Backend::JQUERY_JS);
		$htmlHead->addInlineScript('window.inlineAdminContent = '.json_encode($content).';');
		$htmlHead->addScript("shared/backend/common/js/inline-admin.js");
		$htmlHead->addStyleSheet("shared/backend/".Curry_Core::$config->curry->backend->theme."/css/inline-admin.css");
	}
	
	/**
	 * Wrap placeholder with inline admin controls.
	 *
	 * @param string $content
	 * @param string $target
	 * @param integer $id
	 * @return string
	 */
	protected function adminBlock($content, $target, $id)
	{
		$user = User::getUser();
		$page = $this->getPage();
		$commands = array();

		$url = url('admin.php', array('module' => 'Curry_Backend_Page', 'view'=>'NewModule', 'page_id'=>$this->pageRevision->getPageId(), 'target'=> $target));
		if($user->hasPagePermission($page, PageAccessPeer::PERM_CREATE_MODULE))
			$commands['add'] = array('Name' => 'Add content', 'Url' => $url, 'Class' => 'iframe');

		if (!count($commands))
			return $content;
		
		$tpl = Curry_Twig_Template::loadTemplateString(Curry_InlineAdmin::getAdminBlockTpl());
		return $tpl->render(array(
			'Id' => $id,
			'Target' => $target,
			'Content' => $content,
			'commands' => $commands,
		));
	}

	/**
	 * Wrap module with inline admin controls.
	 *
	 * @param string $content
	 * @param Curry_PageModuleWrapper $pageModuleWrapper
	 * @return string
	 */
	protected function adminModule($content, Curry_PageModuleWrapper $pageModuleWrapper)
	{
		$user = User::getUser();
		$pageId = $pageModuleWrapper->getPageRevision()->getPageId();
		$page = $pageModuleWrapper->getPageRevision()->getPage();
		$pageModuleId = $pageModuleWrapper->getPageModuleId();
		$commands = array();

		$templatePermission = $user->hasAccess('Curry_Backend_Template');
		$contentPermission = $user->hasPagePermission($page, PageAccessPeer::PERM_CONTENT);
		$modulePermission = $user->hasPagePermission($page, PageAccessPeer::PERM_MODULES);
		$createPermission = $user->hasPagePermission($page, PageAccessPeer::PERM_CREATE_MODULE);

		if (!$user->hasModuleAccess($pageModuleWrapper))
			return $content;

		if ($contentPermission || $modulePermission) {
			$url = url('admin.php', array('module'=>'Curry_Backend_Page', 'view'=>'Module', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			if($user->hasPagePermission($page, PageAccessPeer::PERM_CONTENT))
				$commands['edit'] = array('Name' => 'Edit', 'Url' => $url, 'Class' => 'iframe');
		}

		if ($pageModuleWrapper->getTemplate() && $templatePermission) {
			$url = url('admin.php', array('module'=>'Curry_Backend_Template', 'view'=>'Edit', 'file'=>$pageModuleWrapper->getTemplate()));
			$commands['template'] = array('Name' => 'Edit template', 'Url' => $url, 'Class' => 'iframe');
		}

		if ($modulePermission) {
			$url = url('admin.php', array('module'=>'Curry_Backend_Page', 'view'=>'ModuleProperties', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			$commands['properties'] = array('Name' => 'Properties', 'Url' => $url, 'Class' => 'iframe');
		}
		
		if($createPermission && (($contentPermission && !$pageModuleWrapper->isInherited()) || $modulePermission)) {
			$url = url('admin.php', array('module'=>'Curry_Backend_Page', 'view'=>'DeleteModule', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			$commands['delete'] = array('Name' => 'Delete', 'Url' => $url, 'Class' => 'iframe');
		}
		
		$module = $pageModuleWrapper->createObject();
		$module->setPageGenerator($this);
		$commands = $module->getInlineCommands($commands);

		if (!count($commands))
			return $content;
		
		$tpl = Curry_Twig_Template::loadTemplateString(Curry_InlineAdmin::getAdminModuleTpl());
		return $tpl->render(array(
			'Id' => $pageModuleId,
			'Name' => $pageModuleWrapper->getName(),
			'ClassName' => $pageModuleWrapper->getClassName(),
			'Content' => $content,
			'commands' => $commands,
		));
	}
}
