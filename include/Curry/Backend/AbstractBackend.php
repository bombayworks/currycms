<?php

namespace Curry\Backend;

use Curry\App;
use Curry\Util\PathHelper;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBackend extends \Curry\View {
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
	 * @var \Curry\Util\HtmlHead
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

	/**
	 * @var App
	 */
	protected $app;

	public function __construct(\Curry\App $app)
	{
		$this->app = $app;
		$this->htmlHead = new \Curry\Util\HtmlHead();
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
		$name = $rc->getShortName();
		if (($pos = strrpos($name, '_')) !== false)
			$name = substr($name, $pos + 1);
		return $name;
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
	 * @return \Curry\Util\HtmlHead
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
	 * Get the twig environment used with the backend.
	 *
	 * @return \Twig_Environment
	 */
	public function getTwig()
	{
		if (!$this->twig) {
			$backendPath = PathHelper::path(true, $this->app->config->curry->basePath, 'shared', 'backend');
			if (!$backendPath)
				throw new \Exception('Curry\Controller\Backend path (shared/backend) not found.');
			$templatePaths = array(
				PathHelper::path($backendPath, $this->app->config->curry->backend->theme, 'templates'),
				PathHelper::path($backendPath, 'common', 'templates'),
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
			$twig->addFilter('rewrite', new \Twig_Filter_Function('Curry\Util\StringHelper::getRewriteString'));
			$twig->addFilter('attr', new \Twig_Filter_Function('Curry\Util\Html::attr'));
			$this->twig = $twig;
		}
		return $this->twig;
	}

	public function render()
	{
		$twig = $this->getTwig();
		$templateFile = 'backend.html';

		$htmlHead = $this->getHtmlHead();
		$htmlHead->addScript('shared/libs/build/all.min.js');
		$htmlHead->addStylesheet('shared/libs/build/all.css');

		// Globals
		$encoding = $this->app->config->curry->outputEncoding;
		$twig->addGlobal('ProjectName', $this->app->config->curry->name);
		$twig->addGlobal('Encoding', $encoding);
		$twig->addGlobal('Version', App::VERSION);

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
			$active = \Curry\Util\StringHelper::startsWith($app->request->getPathInfo(), $view->url());
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
		$this->mainContent .= \Curry\Util\Helper::stringify($content);
	}

	/**
	 * Add HTML content to menu.
	 *
	 * @param mixed $content
	 */
	public function addMenuContent($content)
	{
		$this->menuContent .= \Curry\Util\Helper::stringify($content);
	}
}