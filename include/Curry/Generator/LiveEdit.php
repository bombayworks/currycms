<?php
namespace Curry\Generator;

use Curry\App;
use Curry\Module\PageModuleWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LiveEdit implements EventSubscriberInterface {
	/**
	 * Path to shared jQuery library.
	 *
	 */
	const JQUERY_JS = '//code.jquery.com/jquery-2.1.1.min.js';
	/**
	 * @var App
	 */
	protected $app;

	public function __construct(App $app) {
		$this->app = $app;
	}

	public static function getSubscribedEvents()
	{
		return array(
			GeneratorEvents::POST_MODULE => array('postModule', -100),
			GeneratorEvents::RENDER => array('render', -100),
		);
	}

	public function isEnabled()
	{
		$user = \User::getUser();
		return $user && $this->app->request->cookies->get('curry_liveedit');
	}

	public function postModule(PostModuleEvent $event) {
		if ($this->isEnabled()) {
			$content = $this->adminModule($event->getContent(), $event->getModuleWrapper());
			$event->setContent($content);
		}
	}

	public function render(RenderEvent $event) {
		if ($this->isEnabled()) {
			$this->adminPanel();

			$moduleContent = $event->getContent();
			$excluded = $this->app['backend.placeholderExclude'];
			$placeholders = array();
			$tpl = $event->getTemplate();
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
			$event->setContent($moduleContent);
		} else {
			// Add button to toggle live edit
			$htmlHead = $this->app->generator->getHtmlHead();
			$htmlHead->addInlineScript(<<<JS
document.addEventListener('DOMContentLoaded', function() {
	var el = document.createElement('a');
	el.id = 'curry-enable-live-edit';
	el.href = window.location.href;
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
	el.onclick = function() {
		document.cookie = 'curry_liveedit=1; path=/';
	};
	document.body.appendChild(el);
});
JS
			);
		}
	}

	/**
	 * Build admin panel
	 */
	protected function adminPanel()
	{
		$user = \User::getUser();
		$page = $this->app->page;
		$pageRevision = $this->app->pageRevision;
		$commands = array();

		$url = url('/admin/curry_backend_page/', array('view'=>'PageProperties', 'page_id'=>$pageRevision->getPageId() ));
		if($user->hasPagePermission($page, \PageAccessPeer::PERM_PROPERTIES))
			$commands[] = array('Name' => 'Page properties', 'Url' => $url, 'Class' => 'iframe');

		$url = url('/admin/curry_backend_page/', array('view'=>'NewPage', 'page_id' => $pageRevision->getPageId()));
		if($user->hasPagePermission($page, \PageAccessPeer::PERM_CREATE_PAGE))
			$commands[] = array('Name' => 'New page', 'Url' => $url, 'Class' => 'iframe');

		$url = url('/admin/curry_backend_page/', array('view'=>'PageRevisions', 'page_id'=>$pageRevision->getPageId()));
		if($user->hasPagePermission($page, \PageAccessPeer::PERM_REVISIONS))
			$commands[] = array('Name' => 'Page revisions', 'Url' => $url, 'Class' => 'iframe');

		if($pageRevision->allowEdit()) {
			$commands[] = array('Name' => 'Show all modules', 'Url' => '#', 'Class' => 'toggle-show-all-modules');
		} else {
			$url = '';
			$commands[] = array('Name' => 'Create working revision (TODO)', 'Url' => $url, 'Class' => 'iframe');
		}

		$view = \Curry_Backend_Page::getPageView($page);
		$url = url('admin.php?module=Curry_Backend_Page', array('view'=>$view,'page_id'=>$pageRevision->getPageId()));
		$commands[] = array('Name' => 'Backend', 'Url' => $url, 'Class' => 'curry-admin-backend');

		$url = url('', $_GET);
		$commands[] = array('Name' => 'Exit Live Edit', 'Url' => $url, 'Class' => 'curry-admin-logout');

		$tpl = \Curry_Twig_Template::loadTemplateString($this->getAdminPanelTpl());
		$content = $tpl->render(array(
				'commands' => $commands,
			));

		$htmlHead = $this->app->generator->getHtmlHead();
		$htmlHead->addScript(self::JQUERY_JS);
		$htmlHead->addInlineScript('window.inlineAdminContent = '.json_encode($content).';');
		$htmlHead->addScript('shared/backend/common/js/inline-admin.js');
		$htmlHead->addStyleSheet('shared/backend/'.$this->app['backend.theme'].'/css/inline-admin.css');
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
		$user = \User::getUser();
		$page = $this->app->page;
		$pageRevision = $this->app->pageRevision;
		$commands = array();

		$url = url('/admin/curry_backend_page/', array('view'=>'NewModule', 'page_id'=>$pageRevision->getPageId(), 'target'=> $target));
		if($user->hasPagePermission($page, \PageAccessPeer::PERM_CREATE_MODULE))
			$commands['add'] = array('Name' => 'Add content', 'Url' => $url, 'Class' => 'iframe');

		if (!count($commands))
			return $content;

		$tpl = \Curry_Twig_Template::loadTemplateString($this->getAdminBlockTpl());
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
	 * @param PageModuleWrapper $pageModuleWrapper
	 * @return string
	 */
	protected function adminModule($content, PageModuleWrapper $pageModuleWrapper)
	{
		$user = \User::getUser();
		$pageId = $pageModuleWrapper->getPageRevision()->getPageId();
		$page = $pageModuleWrapper->getPageRevision()->getPage();
		$pageModuleId = $pageModuleWrapper->getPageModuleId();
		$commands = array();

		$templatePermission = $user->hasAccess('Curry_Backend_Template');
		$contentPermission = $user->hasPagePermission($page, \PageAccessPeer::PERM_CONTENT);
		$modulePermission = $user->hasPagePermission($page, \PageAccessPeer::PERM_MODULES);
		$createPermission = $user->hasPagePermission($page, \PageAccessPeer::PERM_CREATE_MODULE);

		if (!$user->hasModuleAccess($pageModuleWrapper))
			return $content;

		if ($contentPermission || $modulePermission) {
			$url = url('/admin/curry_backend_page/', array('view'=>'Module', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			if($user->hasPagePermission($page, \PageAccessPeer::PERM_CONTENT))
				$commands['edit'] = array('Name' => 'Edit', 'Url' => $url, 'Class' => 'iframe');
		}

		if ($pageModuleWrapper->getTemplate() && $templatePermission) {
			$url = url('/admin/curry_backend_template/', array('view'=>'Edit', 'file'=>$pageModuleWrapper->getTemplate()));
			$commands['template'] = array('Name' => 'Edit template', 'Url' => $url, 'Class' => 'iframe');
		}

		if ($modulePermission) {
			$url = url('/admin/curry_backend_page/', array('view'=>'ModuleProperties', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			$commands['properties'] = array('Name' => 'Properties', 'Url' => $url, 'Class' => 'iframe');
		}

		if($createPermission && (($contentPermission && !$pageModuleWrapper->isInherited()) || $modulePermission)) {
			$url = url('/admin/curry_backend_page/', array('view'=>'DeleteModule', 'page_id'=>$pageId, 'page_module_id'=>$pageModuleId));
			$commands['delete'] = array('Name' => 'Delete', 'Url' => $url, 'Class' => 'iframe');
		}

		$module = $pageModuleWrapper->createObject();
		$commands = $module->getInlineCommands($commands);

		if (!count($commands))
			return $content;

		$tpl = \Curry_Twig_Template::loadTemplateString($this->getAdminModuleTpl());
		return $tpl->render(array(
				'Id' => $pageModuleId,
				'Name' => $pageModuleWrapper->getName(),
				'ClassName' => $pageModuleWrapper->getClassName(),
				'Content' => $content,
				'commands' => $commands,
			));
	}

	public function getAdminPanelTpl() {
		return <<<TPL
<div id="curry-admin-panel">
	<h2>Administrate</h2>
	<ul>
		{% for command in commands %}
		<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
		{% endfor %}
	</ul>
</div>
TPL;
	}


	public function getAdminBlockTpl() {
		return <<<TPL
<div id="block-{{Id}}-open" class="block-open" style="display: none">
	<div class="block-control">
		<h2>{{Target}}</h2>
		<ul>
			{% for command in commands %}
			<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
			{% endfor %}
		</ul>
	</div>
</div>
{{Content|raw}}
<div id="block-{{Id}}-close" class="block-close"></div>
TPL;
	}


	public function getAdminModuleTpl() {
		return <<<TPL
<div id="module-{{Id}}-open" class="module-open" style="display: none" data-module="{{ {id: Id, page_id: curry.page.PageId}|json_encode }}">
	<div class="module-control">
		<h2 title="{{ClassName}}"><span class="close">&times;</span> {{Name}}</h2>
		<ul class="commands">
			{% for command in commands %}
			<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
			{% endfor %}
		</ul>
	</div>
</div>
{{Content|raw}}
<div id="module-{{Id}}-close" class="module-close"></div>
TPL;
	}

	public function getAdminItemStartTpl() {
		return <<<TPL
<div id="module-{{Id}}-open" class="module-open" style="display: none">
	<div class="module-control">
		<h2 title="{{ClassName}}">{{Name}}</h2>
		<ul class="commands">
			<li><a href="{{Url}}" class="iframe">Edit</a></li>
		</ul>
	</div>
</div>
TPL;
	}

	public function getAdminItemEndTpl() {
		return <<<TPL
<div id="module-{{Id}}-close" class="module-close"></div>
TPL;
	}
}