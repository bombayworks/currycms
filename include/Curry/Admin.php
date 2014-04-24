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
 * Main class for the backend.
 * 
 * @package Curry\Backend
 */
class Curry_Admin {
	
	/**
	 * Singleton instance
	 * @var Curry_Admin
	 */
	protected static $instance = null;
	
	/**
	 * Twig environment used for the backend.
	 *
	 * @var Twig_Environment
	 */
	protected $twig;
	
	/**
	 * The currently active backend module.
	 *
	 * @var Curry_Backend
	 */
	protected $backend;
	
	/**
	 * Object to modify HTML head section.
	 *
	 * @var Curry_HtmlHead
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
	 * Get the singleton instance.
	 *
	 * @return Curry_Admin
	 */
	public static function getInstance()
	{
		if(!self::$instance)
			self::$instance = new Curry_Admin();
		return self::$instance;
	}
	
	/**
	 * This is a singleton class - use the getInstance() method instead.
	 */
	private function __construct()
	{
		$this->htmlHead = new Curry_HtmlHead();
		$this->registerDefaultLibraries();
	}
	
	/**
	 * Get the object used to modify the HTML head section for the backend.
	 *
	 * @return Curry_HtmlHead
	 */
	public function getHtmlHead()
	{
		return $this->htmlHead;
	}
	
	/**
	 * Get the currently active Backend module.
	 *
	 * @return Curry_Backend
	 */
	public function getBackend()
	{
		return $this->backend;
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
		$this->bodyClass .= ' '.$bodyClass;
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
			'init' => new Zend_Json_Expr("function() {
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
			'js' => Curry_Flash::SWFOBJECT_PATH.'swfobject.js',
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
			'init' => new Zend_Json_Expr("function() {
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
					external_link_list_url : ".json_encode(url('', array('module'=>'Curry_Backend_Page','view'=>'TinyMceList'))->getAbsolute()).",
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
	 * @return Twig_Environment
	 */
	public function getTwig()
	{
		if(!$this->twig) {
			$path = Curry_Util::path('shared', 'backend');
			$backendPath = Curry_Util::path(true, Curry_Core::$config->curry->wwwPath, $path);
			if (!$backendPath)
				$backendPath = Curry_Util::path(true, Curry_Core::$config->curry->basePath, $path);
			if (!$backendPath)
				throw new Exception('Backend path (shared/backend) not found.');
			$templatePaths = array(
				Curry_Util::path($backendPath, Curry_Core::$config->curry->backend->theme, 'templates'),
				Curry_Util::path($backendPath, 'common', 'templates'),
			);
			$templatePaths = array_filter($templatePaths, 'is_dir');
			$options = array(
				'debug' => true,
				'trim_blocks' => true,
				'base_template_class' => 'Curry_Twig_Template',
			);
			$loader = new Twig_Loader_Filesystem($templatePaths);
			$twig = new Twig_Environment($loader, $options);
			$twig->addFunction('url', new Twig_Function_Function('url'));
			$twig->addFunction('L', new Twig_Function_Function('L'));
			$twig->addFilter('rewrite', new Twig_Filter_Function('Curry_String::getRewriteString'));
			$twig->addFilter('attr', new Twig_Filter_Function('Curry_Html::createAttributes'));
			$this->twig = $twig;
		}
		return $this->twig;
	}
	
	/**
	 * Shows the backend. This is the main method of this class.
	 */
	public function show()
	{
		$twig = $this->getTwig();
		$templateFile = 'main.html';
		$backendList = null;

		// Set content-type with charset (some webservers may otherwise override the charset)
		$encoding = Curry_Core::$config->curry->outputEncoding;
		header("Content-type: text/html; charset=".$encoding);
		
		$htmlHead = $this->getHtmlHead();
		$htmlHead->addScript('shared/libs/jquery-ui-1.8.17/js/jquery-1.7.1.min.js');
		$htmlHead->addScript('shared/backend/common/js/core.js');
		$htmlHead->addScript('shared/backend/common/js/plugins.js');
		$htmlHead->addScript('shared/backend/common/js/main.js');
		$htmlHead->addScript('shared/backend/common/js/finder.js');
		$htmlHead->addScript('shared/js/URI.js');
		
		// Project backend css/js
		if (file_exists( Curry_Core::$config->curry->wwwPath . '/css/backend.css' ))
			$htmlHead->addStylesheet('css/backend.css');
		if (file_exists( Curry_Core::$config->curry->wwwPath . '/js/backend.js' ))
			$htmlHead->addScript('js/backend.js');
		
		// Set language
		if(Curry_Core::$config->curry->fallbackLanguage)
			Curry_Language::setLanguage(Curry_Core::$config->curry->fallbackLanguage);
		
		// Globals
		$twig->addGlobal('ProjectName', Curry_Core::$config->curry->name);
		$twig->addGlobal('Encoding', $encoding);
		$twig->addGlobal('Version', Curry_Core::VERSION);
		
		// Logotype
		if(Curry_Core::$config->curry->backend->logotype)
			$twig->addGlobal('Logotype', Curry_Core::$config->curry->backend->logotype);

		// Current module
		$currentModule = 'Curry_Backend_Page';
		if(isset($_GET['module']))
			$currentModule = $_GET['module'];

		if(Curry_Core::$config->curry->setup) {
			if ($currentModule !== 'Curry_Backend_Setup')
				url('', array('module' => 'Curry_Backend_Setup'))->redirect();
			if(!class_exists('User'))
				eval("class User { public static function getUser(){ return new self; } public function hasAccess() { return true; } public function getName() { return 'Dummy'; } }");
			else
				User::dummyAuth();
			$backendList = array('Curry_Backend_Setup' => 'Setup');
		} else if(Curry_Core::$config->curry->backend->noauth)
			User::dummyAuth();
		
		$user = User::getUser();
		if(!$user) {
			$loginRedirect = '';
			if(isset($_POST['login_redirect']))
				$loginRedirect = $_POST['login_redirect'];
			else if(!isset($_GET['logout']) && count($_GET))
				$loginRedirect = (string)url('', $_GET);
			$twig->addGlobal('LoginRedirect', $loginRedirect);
			$this->addBodyClass('tpl-login');
			$templateFile = 'login.html';
		} else {
			$twig->addGlobal('user', array(
				'Name' => $user->getName()
			));
			
			// Current module
			if ($backendList === null)
				$backendList = Curry_Backend::getBackendList();
			if ($currentModule != 'Curry_Backend_Setup')
				unset($backendList['Curry_Backend_Setup']);
			if (!array_key_exists($currentModule, $backendList))
				throw new Exception('Backend module "'.$currentModule.'" not found');

			// Do we need to upgrade?
			$systemModules = array('Curry_Backend_System','Curry_Backend_Database','Curry_Backend_Setup');
			if(Curry_Core::requireMigration() && !in_array($currentModule, $systemModules)) {
				url('', array('module'=>'Curry_Backend_System', 'view' => 'Upgrade'))->redirect();
			}
			
			// Modules
			$backendGroups = array(
				'Content' => array(),
				'Appearance' => array(),
				'Accounts' => array(),
				'System' => array(),
			);
			
			foreach($backendList as $module => $moduleName) {
				if(!$user->hasAccess($module))
					continue;
				
				$group = "Other";
				if(method_exists($module, 'getGroup'))
					$group = call_user_func(array($module, 'getGroup'));
					
				$name = $moduleName;
				if(method_exists($module, 'getName')) {
					$n = call_user_func(array($module, 'getName'));
					if($n)
						$name = $n;
				}
				
				$message = '';
				if(method_exists($module, 'getMessage'))
					$message = call_user_func(array($module, 'getMessage'));
				
				$notifications = '';
				if(method_exists($module, 'getNotifications')) {
					try {
						$notifications = call_user_func(array($module, 'getNotifications'));
						if (!isset($backendGroups[$group]['Notifications']))
							$backendGroups[$group]['Notifications'] = 0;
						$backendGroups[$group]['Notifications'] += (int)$notifications;
					}
					catch(Exception $e) { }
				}

				$moduleProperties = array(
					'Module' => $module,
					'Active' => ($module === $currentModule),
					'Url' => url('', array("module"=>$module)),
					'Name' => $name,
					'Title' => $message,
					'Notifications' => $notifications,
				);

				if ($group) {
					if(!isset($backendGroups[$group]))
						$backendGroups[$group] = array();
					if(!isset($backendGroups[$group]['modules']))
						$backendGroups[$group]['modules'] = array();
					$backendGroups[$group]['modules'][$module] = $moduleProperties;
					$backendGroups[$group]['Name'] = $group;
					$backendGroups[$group]['Active'] = $module == $currentModule;
				}
				if($module == $currentModule) {
					$twig->addGlobal('module', $moduleProperties);
				}
			}
			$twig->addGlobal('moduleGroups', $backendGroups);
			
			if ($currentModule && class_exists($currentModule)) {
				if ($user->hasAccess($currentModule)) {
					$this->backend = new $currentModule($this);
					if ($this->backend) {
						if(!in_array($currentModule, $systemModules)) {
							if(self::isPropelBuildInvalid())
								$this->backend->addMessage('Propel has been upgraded and you need to rebuild your database, use <a href="'.url('', array('module' => 'Curry_Backend_Database', 'view' => 'Propel')).'">auto rebuild</a>.', Curry_Backend::MSG_WARNING, false);
							if(Curry_Core::$config->curry->backend->noauth)
								$this->backend->addMessage('Authorization has been disabled for backend. You can re-enable it if you go to <a href="'.url('', array('module' => 'Curry_Backend_System')).'">System Settings</a>.', Curry_Backend::MSG_WARNING, false);
							if(Curry_Core::$config->curry->maintenance->enabled)
								$this->backend->addMessage('Site has been disabled for maintenance. You can re-enable it in <a href="'.url('', array('module' => 'Curry_Backend_System')).'">System Settings</a>.', Curry_Backend::MSG_WARNING, false);
							$this->doAutoBackup();
						}
						$twig->addGlobal('content', $this->backend->show());
					}
				} else {
					header('HTTP/1.1 403 Forbidden');
					header('Status: 403 Forbidden');
					$twig->addGlobal('content', 'Access denied');
				}
			}
		}
		
		// Finalize HtmlHead and add global
		$htmlHead->addInlineScript('$.registerLibrary('.Zend_Json::encode($this->libraries, false, array('enableJsonExprFinder' => true)).');');
		$twig->addGlobal('HtmlHead', $htmlHead->getContent());
		$twig->addGlobal('BodyClass', $this->getBodyClass());

		// Render template
		$template = $twig->loadTemplate($templateFile);
		$template->display(array());
	}
	
	/**
	 * Create an automatic backup of the database.
	 */
	public function doAutoBackup()
	{
		$autoBackup = Curry_Core::$config->curry->autoBackup;
		if($autoBackup) {
			$filename = Curry_Backend_DatabaseHelper::createBackupName("backup_%Y-%m-%d_%H-%M-%S_autobackup.txt");
			
			$lastModified = 0;
			foreach(new DirectoryIterator(dirname($filename)) as $entry) {
				if($entry->isFile())
					$lastModified = max($lastModified, $entry->getMTime());
			}
			
			if((time() - $lastModified) >= $autoBackup && !file_exists($filename)) {
				$status = Curry_Backend_DatabaseHelper::dumpDatabase($filename);
				if($this->backend) {
					if($status)
						$this->backend->addMessage('An automatic backup of the database has been created successfully.', Curry_Backend::MSG_SUCCESS);
					else
						$this->backend->addMessage('There was an error when trying to create the automatic backup of the database.', Curry_Backend::MSG_ERROR);
				}
			}
		}
	}
	
	/**
	 * Check if the propel build files was built with the running Propel-version.
	 *
	 * @return bool
	 */
	public static function isPropelBuildInvalid()
	{
		$pc = Propel::getConfiguration();
		return $pc['generator_version'] != Propel::VERSION;
	}
}
