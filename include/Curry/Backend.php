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
 * Base class for backend modules.
 * 
 * @package Curry\Backend
 */
abstract class Curry_Backend {
	
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
	 * Proxy views.
	 *
	 * @var string
	 */
	public $proxyViews = array();
	
	/**
	 * List of backend modules.
	 *
	 * @var array|null
	 */
	private static $backendList;
	
	/**
	 * Constructor.
	 *
	 */
	public function __construct()
	{
		Curry_Core::triggerHook(get_class($this).'::construct', $this);
	}
	
	/**
	 * Override in subclasses to specify the name of the group the module belongs to.
	 *
	 * @return string
	 */
	public static function getGroup()
	{
		return "Content";
	}

	/**
	 * Override in subclasses to get a number next to the module
	 * 
	 * @return string|int
	 */
	public static function getNotifications()
	{
		return 0;
	}

	/**
	 * Override in subclasses to get a mouse-over text
	 * 
	 * @return string
	 */
	public static function getMessage()
	{
		return "";
	}
	
	/**
	 * Override in subclasses to set the displayed name.
	 *
	 * @return string|null
	 */
	public static function getName()
	{
		return null;
	}
	
	/**
	 * Get permissions specific to this module.
	 *
	 * @return array
	 */
	public static function getPermissions()
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
		$user = User::getUser();
		$module = get_class($this);
		return $user->hasAccess($module.'/'.$permission);
	}
	
	/**
	 * Get a list of all backend modules.
	 *
	 * @return array
	 */
	public static function getBackendList()
	{
		if(self::$backendList)
			return self::$backendList;
			
		// find all backend directories
		$dirs = glob(Curry_Util::path(\Curry\App::getInstance()->config->curry->projectPath,'include','*','Backend'), GLOB_ONLYDIR);
		if(!$dirs)
			$dirs = array();
		$dirs[] = Curry_Util::path(\Curry\App::getInstance()->config->curry->basePath,'include','Curry','Backend');
		
		// find all php files in the directories
		self::$backendList = array();
		foreach($dirs as $dir) {
			$it = new Curry_FileFilterIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)), '/\.php$/');
			foreach($it as $file) {
				$path = realpath($file->getPathname());
				$pos = strrpos($path, DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR);
				if($pos !== FALSE) {
					$pi = pathinfo($path);
					$className = str_replace(DIRECTORY_SEPARATOR, '_', substr($path, $pos + 9, -4));
					if (class_exists($className, true)) {
						$r = new ReflectionClass($className);
						if (is_subclass_of($className, 'Curry_Backend') && !$r->isAbstract())
							self::$backendList[$className] = $pi['filename'];
					}
				}
			}
		}
		
		ksort(self::$backendList);
		return self::$backendList;
	}

	/**
	 * Redirect to URL or close dialog.
	 *
	 * @param string $url
	 * @param bool $dialogRedirect If true, this will redirect dialogs as well, otherwise just close the dialog.
	 */
	public static function redirect($url, $dialogRedirect = true)
	{
		$url = (string)$url;
		$redirectJs = '<script type="text/javascript">window.location.href = '.json_encode($url).';</script>';
		if(isAjax()) // we're in a dialog, use javascript to redirect
			Curry_Application::returnPartial($dialogRedirect ? $redirectJs : '');
		else
			url($url)->redirect();
	}
	
	/**
	 * This function will be called before the view (by default showMain) function.
	 * 
	 */
	public function preShow()
	{
		Curry_Core::triggerHook(get_class($this).'::preShow', $this, isset($_GET['view']) ? $_GET['view'] : 'Main');
	}
	
	/**
	 * This function will be called after the view (by default showMain) function.
	 * 
	 */
	public function postShow()
	{
		$currentView = empty($_GET['view']) ? 'Main' : $_GET['view'];
		foreach($this->proxyViews as $view => $options){
			if((count($options['views']) == 1 || in_array($currentView, $options['views'])) && $options['addMenuItem']){
				$vars = $_GET;
				$vars['view'] = $view;	
				$this->addMenuItem($options['menuName'], url('',$vars)->getAbsolute());
			}
		}
		Curry_Core::triggerHook(get_class($this).'::postShow', $this, $currentView);
	}
	
	/**
	 * The default view function.
	 *
	 */
	abstract public function showMain();
	
	/**
	 * This is the main function called by Curry_Admin.
	 * 
	 * This will call the show{X}() function, where X is specified
	 * by the GET-variable 'view'. It will then render the backend using
	 * the render() function, and return the content.
	 *
	 * @return string
	 */
	public function show()
	{
		try {
			$this->preShow();
			$view = empty($_GET['view']) ? 'Main' : $_GET['view'];
			$func = 'show' . $view;
			if(method_exists($this, $func)) {
				$this->$func();
			} elseif(isset($this->proxyViews[$view])){
				$callback = $this->proxyViews[$view]['callback'];
				call_user_func($callback);

			} else {
				throw new Exception('Invalid view');
			}
			
			$this->postShow();
		}
		catch (Exception $e) {
			if(!headers_sent())
				header("HTTP/1.0 500 Internal server error: ".str_replace("\n", "  ", $e->getMessage()));
			\Curry\App::getInstance()->logger->error($e->getMessage());
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
			if(\Curry\App::getInstance()->config->curry->developmentMode)
				$this->addMainContent("<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
		}
		
		return $this->render();
	}
	
	/**
	 * Use template to render the backend.
	 *
	 * @return string
	 */
	protected function render()
	{
		if (isset($_GET['curry_context']) && $_GET['curry_context'] == 'main') {
			Curry_Application::returnPartial($this->mainContent);
		}
		$twig = Curry_Admin::getInstance()->getTwig();
		$template = $twig->loadTemplate('backend.html');
		return $template->render(array(
			'breadcrumbs' => $this->breadcrumbs,
			'commands' => $this->commands,
			'menuItems' => $this->menuItems,
			'mainContent' => $this->mainContent,
			'menuContent' => $this->menuContent,
			'moduleName' => $this->getName() ? $this->getName() : substr(get_class($this), 14),
		));
	}

	public function addEvent($event)
	{
		header('X-Trigger-Events: '.json_encode($event), false);
	}

	public function createModelUpdateEvent($modelClass, $primaryKey, $action = 'update')
	{
		$this->addEvent(array(
			'type' => 'model-update',
			'params' => array($modelClass, $primaryKey, $action),
		));
	}

	/**
	 * Add proxy view and additional menu item.
	 * 
	 * @param string $name
	 * @param callback $callback
	 * @param array $displayWithViews
	 * @param mixed $menuName
	 * 
	 */
	public function addProxyView($name, $callback, $displayWithViews = array(), $menuName = NULL)
	{
		if(method_exists($this, 'show'.$name))
			throw new Exception("Unable to add proxy view, method $name already exists.");
		
		if(isset($this->proxyViews[$name]))
			throw new Exception("Proxy view with the same name($name) has already been added.");
		
		array_push($displayWithViews, $name);
		$this->proxyViews[$name] = array(
			'callback' => $callback,
			'menuName' => $menuName ? $menuName : $name,
			'views' => $displayWithViews,
			'addMenuItem' => $menuName == false ? false : true,
		);
	}

	/**
	 * Add trace (aka breadcrumb) item.
	 *
	 * @deprecated Use addBreadcrumb() instead.
	 * @param string $name
	 * @param string $url
	 */
	public function addTrace($name, $url)
	{
		$this->addBreadcrumb($name, $url);
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
	 * Add a command item which opens in a dialog.
	 *
	 * @param string $name
	 * @param string $url
	 * @param string $bclass
	 * @param string $dialogTitle
	 * @param array $dialogOptions
	 */
	public function addDialogCommand($name, $url, $bclass, $dialogTitle = null, $dialogOptions = array())
	{
		if($dialogTitle === null)
			$dialogTitle = $name;
		$this->addCommand($name, $url, $bclass, array('title' => $dialogTitle, 'class' => 'dialog', 'data-dialog' => json_encode($dialogOptions)));
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
		$this->mainContent .= (string)$content;
	}
	
	/**
	 * Add HTML content to menu.
	 *
	 * @param mixed $content
	 */
	public function addMenuContent($content)
	{
		$this->menuContent .= (string)$content;
	}
	
	/**
	 * Return json-data to browser and exit. Will set content-type header and encode the data.
	 *
	 * @deprecated Use Curry_Application::returnJson() instead.
	 * @param mixed $content	Data to encode with json_encode. Note that this must be utf-8 encoded. Strings will not be encoded.
	 */
	public function returnJson($content)
	{
		Curry_Application::returnJson($content);
	}
	
	/**
	 * Return partial html-content to browser and exit. Will set content-type header and return the content.
	 *
	 * @deprecated Use Curry_Application::returnPartial() instead.
	 * @param mixed $content
	 */
	public function returnPartial($content)
	{
		Curry_Application::returnPartial($content);
	}
}
