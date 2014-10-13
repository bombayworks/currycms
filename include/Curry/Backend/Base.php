<?php

namespace Curry\Backend;

use Curry\App;
use Symfony\Component\HttpFoundation\Request;

class Base extends \Curry\View {
	/**
	 * Path to shared jQuery library.
	 *
	 */
	const JQUERY_JS = 'shared/libs/jquery-ui-1.8.17/js/jquery-1.7.1.min.js';

	/**
	 * Success message.
	 */
	const MSG_SUCCESS = 'success';

	/**
	 * Notice message.
	 */
	const MSG_NOTICE = 'info';

	/**
	 * Debug message.
	 */
	const MSG_DEBUG = 'debug';

	/**
	 * Warning message.
	 */
	const MSG_WARNING = 'warning';

	/**
	 * Error message.
	 */
	const MSG_ERROR = 'error';

	/**
	 * Twig environment used for the backend.
	 *
	 * @var \Twig_Environment
	 */
	protected $twig;

	/**
	 * Object to modify HTML head section.
	 *
	 * @var \Curry_HtmlHead
	 */
	protected $htmlHead;

	/**
	 * JavaScript libraries.
	 *
	 * @var array
	 */
	protected $libraries = array();

	/**
	 * Classes to set on body-tag.
	 *
	 * @var string
	 */
	protected $bodyClass = "";

	/**
	 * Breadcrumb items.
	 *
	 * @var array
	 */
	protected $breadcrumbs = array();

	/**
	 * Command items.
	 *
	 * @var array
	 */
	protected $commands = array();

	/**
	 * Menu items.
	 *
	 * @var array
	 */
	protected $menuItems = array();

	/**
	 * Main content.
	 *
	 * @var string
	 */
	protected $mainContent = '';

	/**
	 * Menu content.
	 *
	 * @var string
	 */
	protected $menuContent = '';

	public function __construct()
	{
		$this->htmlHead = new \Curry_HtmlHead();
		$this->registerDefaultLibraries();
	}

	/**
	 * Override in subclasses to specify the name of the group the module belongs to.
	 *
	 * @return string
	 */
	public function getGroup()
	{
		return "Content";
	}

	/**
	 * Override in subclasses to get a number next to the module
	 *
	 * @return string|int
	 */
	public function getNotifications()
	{
		return 0;
	}

	/**
	 * Override in subclasses to get a mouse-over text
	 *
	 * @return string
	 */
	public function getMessage()
	{
		return "";
	}

	/**
	 * Override in subclasses to set the displayed name.
	 *
	 * @return string|null
	 */
	public function getName()
	{
		$rc = new \ReflectionClass($this);
		return $rc->getShortName();
	}

	/**
	 * Get permissions specific to this module.
	 *
	 * @return array
	 */
	public function getPermissions()
	{
		return array();
	}

	/**
	 * Check if the logged in user has access to the specified permission.
	 *
	 * @param string $permission
	 * @return bool
	 */
	public function hasPermission($permission)
	{
		$user = \User::getUser();
		$module = get_class($this);
		return $user->hasAccess($module.'/'.$permission);
	}

	/**
	 * Get the object used to modify the HTML head section for the backend.
	 *
	 * @return \Curry_HtmlHead
	 */
	public function getHtmlHead()
	{
		return $this->htmlHead;
	}

	/**
	 * @param string $bodyClass
	 */
	public function setBodyClass($bodyClass)
	{
		$this->bodyClass = $bodyClass;
	}

	/**
	 * @param string $bodyClass
	 */
	public function addBodyClass($bodyClass)
	{
		$this->bodyClass .= ' ' . $bodyClass;
	}

	/**
	 * @return string
	 */
	public function getBodyClass()
	{
		return $this->bodyClass;
	}

	/**
	 * Registers the core libraries used with the backend.
	 */
	protected function registerDefaultLibraries()
	{
		$this->registerLibrary('jquery-ui', array(
			'css' => 'shared/libs/jquery-ui-1.8.17/css/curry/jquery-ui-1.8.17.custom.css',
			'js' => 'shared/libs/jquery-ui-1.8.17/js/jquery-ui-1.8.17.custom.min.js',
			'init' => new \Zend_Json_Expr("function() {
				$.datepicker.setDefaults( {dateFormat: 'yy-mm-dd'} );
				$.extend($.ui.dialog.prototype.options, {
					modal: true,
					resizable: false,
					width: 600
				});
				// Workaround for tinymce crashing when sorting sortables
				$(document)
					.on('sortstart', '.ui-sortable', function(event) {
						$(this).data('curry-sortable-started', true);
					})
					.on('mouseup.sortable', '.ui-sortable', function(event) {
						if ($(this).data('curry-sortable-started')) {
							var item = $(this).data('sortable').currentItem;
							$(item).find('.tinymce').each(function() {
								var mce = $(this).tinymce();
								$(this).data('curry-sortable-mce', mce.settings);
								mce.remove();
							});
							$(this).data('curry-sortable-started', false);
						}
					})
					.on('sortbeforestop', '.ui-sortable', function(event, ui) {
						$(ui.item).find('.tinymce').each(function() {
							$(this).tinymce($(this).data('curry-sortable-mce'));
							$(this).removeData('curry-sortable-mce');
						});
					});
			}"),
			'preload' => true,
		));
		$this->registerLibrary('swfobject', array(
			'js' => \Curry_Flash::SWFOBJECT_PATH . 'swfobject.js',
		));
		$this->registerLibrary('flexigrid', array(
			'dep' => 'jquery-ui',
			'css' => 'shared/libs/flexigrid-1.0b3/flexigrid.css',
			'js' => 'shared/libs/flexigrid-1.0b3/flexigrid.js',
		));
		$this->registerLibrary('codemirror', array(
			'js' => array(
				'shared/libs/codemirror-3.02/lib/codemirror.js',
				'shared/libs/codemirror-3.02/mode/xml/xml.js',
				'shared/libs/codemirror-3.02/mode/javascript/javascript.js',
				'shared/libs/codemirror-3.02/mode/css/css.js',
			),
			'css' => array(
				'shared/libs/codemirror-3.02/lib/codemirror.css',
			),
			'sequential' => true,
		));
		$this->registerLibrary('tinymce', array(
			//'dep' => 'jquery-ui', // need to include jquery-ui before tinymce for some reason O_o
			'js' => 'shared/libs/tinymce-3.5.8-jquery/jquery.tinymce.js',
			'init' => new \Zend_Json_Expr("function() {
				if(!window.tinymceSettings)
					window.tinymceSettings = {};
				window.tinymceSettings = $.extend({
					width: '100%',
					script_url: 'shared/libs/tinymce-3.5.8-jquery/tiny_mce.js',
					// General options
					theme : 'advanced',
					plugins : 'style,table,advimage,advlink,currypopups,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist',
					// Theme options
					theme_advanced_buttons1 : 'bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,|,indent,outdent,|,undo,redo,styleselect,formatselect',
					theme_advanced_buttons2 : 'link,unlink,anchor,|,image,media,table,hr,charmap,|,blockquote,cite,abbr,acronym,del,ins,sub,sup,|,removeformat,cleanup,code,|,fullscreen,help',
					theme_advanced_buttons3 : '',//forecolor,backcolor,styleprops,attribs,|,nonbreaking,template,|,cut,copy,paste,pastetext,pasteword,|,search,replace'
					theme_advanced_buttons4 : '',
					theme_advanced_toolbar_location : 'top',
					theme_advanced_toolbar_align : 'left',
					theme_advanced_statusbar_location : 'bottom',
					theme_advanced_resizing : true,
					// Paste from word...
					paste_remove_spans: true,
					paste_remove_styles: true,
					paste_strip_class_attributes: 'all',
					// Example content CSS (should be your site CSS)
					content_css : 'css/content.css',
					// Drop lists for link/image/media/template dialogs
					template_external_list_url : 'lists/template_list.js',
					external_link_list_url : " . json_encode(url('', array('module' => '\Curry\Backend_Page', 'view' => 'TinyMceList'))->getAbsolute()) . ",
					// Replace values for the template plugin
					template_replace_values : {
					},
					file_browser_callback: function(fieldName, url, type, win) {
						$.util.openFinder(win.document.getElementById(fieldName));
					}
				}, window.tinymceSettings);
			}")
		));
		$this->registerLibrary('jquery-bw-url', array(
			'js' => 'shared/js/jquery.bw.url.js',
		));
		$this->registerLibrary('dynatree', array(
			'dep' => 'jquery-ui',
			'js' => array('shared/libs/dynatree-1.2.2/jquery/jquery.cookie.js', 'shared/libs/dynatree-1.2.2/src/jquery.dynatree.js'),
			'css' => 'shared/libs/dynatree-1.2.2/src/skin-vista/ui.dynatree.css',
			'sequential' => true,
		));
		$this->registerLibrary('colorpicker', array(
			'js' => 'shared/libs/colorpicker-20090523/js/colorpicker.js',
			'css' => 'shared/libs/colorpicker-20090523/css/colorpicker.css',
		));
		$this->registerLibrary('chosen', array(
			'js' => 'shared/libs/chosen-0.9.12/chosen.jquery.min.js',
			'css' => 'shared/libs/chosen-0.9.12/chosen.css',
		));
		$this->registerLibrary('modelview', array(
			'js' => 'shared/backend/common/js/modelview.js',
			'css' => 'shared/backend/common/css/modelview.css',
		));
	}

	/**
	 * Register a javascript library.
	 *
	 * @param string $name
	 * @param array $description
	 */
	public function registerLibrary($name, $description)
	{
		$this->libraries[$name] = $description;
	}

	/**
	 * Get the twig environment used with the backend.
	 *
	 * @return \Twig_Environment
	 */
	public function getTwig()
	{
		if (!$this->twig) {
			$path = \Curry_Util::path('shared', 'backend');
			$backendPath = \Curry_Util::path(true, \Curry\App::getInstance()->config->curry->wwwPath, $path);
			if (!$backendPath)
				$backendPath = \Curry_Util::path(true, \Curry\App::getInstance()->config->curry->basePath, $path);
			if (!$backendPath)
				throw new \Exception('Curry\Controller\Backend path (shared/backend) not found.');
			$templatePaths = array(
				\Curry_Util::path($backendPath, \Curry\App::getInstance()->config->curry->backend->theme, 'templates'),
				\Curry_Util::path($backendPath, 'common', 'templates'),
			);
			$templatePaths = array_filter($templatePaths, 'is_dir');
			$options = array(
				'debug' => true,
				'trim_blocks' => true,
				'base_template_class' => 'Curry_Twig_Template',
			);
			$loader = new \Twig_Loader_Filesystem($templatePaths);
			$twig = new \Twig_Environment($loader, $options);
			$twig->addFunction('url', new \Twig_Function_Function('url'));
			$twig->addFunction('L', new \Twig_Function_Function('L'));
			$twig->addFilter('rewrite', new \Twig_Filter_Function('Curry_String::getRewriteString'));
			$twig->addFilter('attr', new \Twig_Filter_Function('Curry_Html::createAttributes'));
			$this->twig = $twig;
		}
		return $this->twig;
	}

	public function show(Request $request)
	{
		return $this->render();
	}

	public function render()
	{
		$twig = $this->getTwig();
		$templateFile = 'backend.html';

		$htmlHead = $this->getHtmlHead();
		$htmlHead->addScript('shared/libs/jquery-ui-1.8.17/js/jquery-1.7.1.min.js');
		$htmlHead->addScript('shared/backend/common/js/core.js');
		$htmlHead->addScript('shared/backend/common/js/plugins.js');
		$htmlHead->addScript('shared/backend/common/js/main.js');
		$htmlHead->addScript('shared/backend/common/js/finder.js');
		$htmlHead->addScript('shared/js/URI.js');

		// Globals
		$encoding = \Curry\App::getInstance()->config->curry->outputEncoding;
		$twig->addGlobal('ProjectName', \Curry\App::getInstance()->config->curry->name);
		$twig->addGlobal('Encoding', $encoding);
		$twig->addGlobal('Version', \Curry_Core::VERSION);

		$user = \User::getUser();
		if (!$user) {
			$loginRedirect = '';
			if(isset($_POST['login_redirect']))
				$loginRedirect = $_POST['login_redirect'];
			else if(!isset($_GET['logout']) && count($_GET))
				$loginRedirect = (string)url('', $_GET);
			$twig->addGlobal('LoginRedirect', $loginRedirect);
			$this->addBodyClass('tpl-login');
			$templateFile = 'login.html';

			// Finalize HtmlHead and add global
			$htmlHead->addInlineScript('$.registerLibrary(' . \Zend_Json::encode($this->libraries, false, array('enableJsonExprFinder' => true)) . ');');
			$twig->addGlobal('HtmlHead', $htmlHead->getContent());
			$twig->addGlobal('BodyClass', $this->getBodyClass());

			$template = $twig->loadTemplate($templateFile);
			return $template->render(array());
		}

		$backendGroups = array(
			'Content' => array(),
			'Appearance' => array(),
			'Accounts' => array(),
			'System' => array(),
		);
		$app = App::getInstance();
		foreach($app->backend->views() as $viewName => $viewAndRoute) {
			list($view, $route) = $viewAndRoute;
			//if(!$user->hasAccess(get_class($view)))
			//	continue;
			$active = \Curry_String::startsWith($app->request->getPathInfo(), $view->url());
			$group = $view->getGroup();
			$moduleProperties = array(
				'Module' => $viewName,
				'Active' => $active,
				'Url' => $view->url(),
				'Name' => $view->getName(),
				'Title' => $view->getMessage(),
				'Notifications' => $view->getNotifications(),
			);
			if ($group) {
				if(!isset($backendGroups[$group]))
					$backendGroups[$group] = array();
				if(!isset($backendGroups[$group]['modules']))
					$backendGroups[$group]['modules'] = array();
				$backendGroups[$group]['modules'][$viewName] = $moduleProperties;
				$backendGroups[$group]['Name'] = $group;
				$backendGroups[$group]['Active'] = $active;
			}
			if ($active)
				$twig->addGlobal('module', $moduleProperties);
		}

		$twig->addGlobal('moduleGroups', $backendGroups);

		// Finalize HtmlHead and add global
		$htmlHead->addInlineScript('$.registerLibrary(' . \Zend_Json::encode($this->libraries, false, array('enableJsonExprFinder' => true)) . ');');
		$twig->addGlobal('HtmlHead', $htmlHead->getContent());
		$twig->addGlobal('BodyClass', $this->getBodyClass());

		// Render template
		$template = $twig->loadTemplate($templateFile);
		return $template->render(array(
			'breadcrumbs' => $this->breadcrumbs,
			'commands' => $this->commands,
			'menuItems' => $this->menuItems,
			'mainContent' => $this->mainContent,
			'menuContent' => $this->menuContent,
		));
	}

	/**
	 * Add breadcrumb item.
	 *
	 * @param string $name
	 * @param string $url
	 * @param array $attributes
	 */
	public function addBreadcrumb($name, $url, $attributes = array())
	{
		$this->breadcrumbs[] = array('Name' => $name, 'Url' => $url, 'Attributes' => $attributes);
	}

	/**
	 * Add menu item.
	 *
	 * @param string $name
	 * @param string $url
	 * @param string $message
	 * @param string $notifications
	 * @param array $attributes
	 */
	public function addMenuItem($name, $url, $message = "", $notifications = "", $attributes = array())
	{
		$env = url($url)->getVars();
		$view = isset($_GET['view']) ? $_GET['view'] : 'Main';
		$this->menuItems[] = array(
			'Name' => $name,
			'Url' => $url,
			'Message' => $message,
			'Notifications' => $notifications,
			'Active' => ($env['view'] == $view),
			'Attributes' => $attributes
		);
	}

	/**
	 * Add command item.
	 *
	 * @param string $name
	 * @param string $url
	 * @param string $icon
	 * @param array $attributes
	 */
	public function addCommand($name, $url, $icon, $attributes = array())
	{
		$attributes['class'] = isset($attributes['class']) ? $attributes['class'].' btn' : 'btn'; // TODO: remove this hack.
		$this->commands[] = array('Name' => $name, "Url" => $url, "Icon" => $icon, "Attributes" => $attributes);
	}

	/**
	 * Add message to main content.
	 *
	 * @param string $text
	 * @param string $class
	 * @param bool $escape
	 */
	public function addMessage($text, $class = self::MSG_NOTICE, $escape = true)
	{
		$this->addMainContent('<p class="text-'.$class.'">'.($escape ? htmlspecialchars($text) : $text).'</p>');
	}

	/**
	 * Add HTML content to the main template.
	 *
	 * @param mixed $content
	 */
	public function addMainContent($content)
	{
		if (!is_string($content)) {
			if (is_object($content) && method_exists($content, '__toString'))
				$content = $content->__toString();
			else
				$content = (string)$content;
		}
		$this->mainContent .= $content;
	}

	/**
	 * Add HTML content to menu.
	 *
	 * @param mixed $content
	 */
	public function addMenuContent($content)
	{
		if (!is_string($content)) {
			if (is_object($content) && method_exists($content, '__toString'))
				$content = $content->__toString();
			else
				$content = (string)$content;
		}
		$this->menuContent .= $content;
	}
}