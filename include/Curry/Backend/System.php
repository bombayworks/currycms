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
namespace Curry\Backend;
use Curry\Archive\Archive;
use Curry\Controller\Frontend;

/**
 * Change system settings.
 * 
 * @package Curry\Controller\Backend
 */
class System extends \Curry\Backend {
	
	/** {@inheritdoc} */
	public function getGroup()
	{
		return "System";
	}
	
	/**
	 * Add menu items.
	 */
	public function addMainMenu()
	{
		$this->addMenuItem("Settings", url('', array("module","view"=>"Main")));
		$this->addMenuItem("Bundle", url('', array("module","view"=>"Bundle")));
		$this->addMenuItem("Clear cache", url('', array("module","view"=>"ClearCache")));
		$this->addMenuItem("Info", url('', array("module","view"=>"Info")));
		$this->addMenuItem("Upgrade", url('', array("module","view"=>"Upgrade")));
	}
	
	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMainMenu();
		
		$configFile = \Curry\App::getInstance()->config->curry->configPath;
		if(!$configFile)
			$this->addMessage("Configuration file not set.", self::MSG_ERROR);
		else if(!is_writable($configFile))
			$this->addMessage("Configuration file doesn't seem to be writable.", self::MSG_ERROR);
			
		$config = \Curry_Core::openConfiguration();
		$defaultConfig = $config;//\Curry_Core::getDefaultConfiguration();
		
		$form = new \Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post'
		));

		$themes = array();
		$backendPath = \Curry_Util::path(true, \Curry\App::getInstance()->config->curry->wwwPath, 'shared', 'backend');
		if($backendPath) {
			foreach (new \DirectoryIterator($backendPath) as $entry) {
				$name = $entry->getFilename();
				if (!$entry->isDot() && $entry->isDir() && $name !== 'common') {
					$themes[$name] = $name;
				}
			}
		}
		$activeTheme = isset($config->curry->backend->theme) ? $config->curry->backend->theme : false;
		if($activeTheme && !array_key_exists($activeTheme, $themes)) {
			$themes[$activeTheme] = $activeTheme;
		}

		$pages = \PagePeer::getSelect();
		
		// General
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'General',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'value' => isset($config->curry->name) ? $config->curry->name : '',
					'description' => 'Name of site, shown in backend header and page title by default.',
					'placeholder' => $defaultConfig->curry->name,
				)),
				'baseUrl' => array('text', array(
					'label' => 'Base URL',
					'value' => isset($config->curry->baseUrl) ? $config->curry->baseUrl : '',
					'description' => 'The URL to use when creating absolute URLs. This should end with a slash, and may include a path.',
					'placeholder' => $defaultConfig->curry->baseUrl,
				)),
				'adminEmail' => array('text', array(
					'label' => 'Admin email',
					'value' => isset($config->curry->adminEmail) ? $config->curry->adminEmail : '',
				)),
				'divertOutMailToAdmin' => array('checkbox', array(
						'label' => 'Divert outgoing email to adminEmail',
						'value' => isset($config->curry->divertOutMailToAdmin) ? $config->curry->divertOutMailToAdmin : '',
						'description' => 'All outgoing Curry_Mail will be diverted to adminEmail.',
				)),
				'developmentMode' => array('checkbox', array(
					'label' => 'Development mode',
					'value' => isset($config->curry->developmentMode) ? $config->curry->developmentMode : '',
				)),
				'forceDomain' => array('checkbox', array(
					'label' => 'Force domain',
					'value' => isset($config->curry->forceDomain) ? $config->curry->forceDomain : '',
					'description' => 'If the domain of the requested URL doesn\'t match the domain set by Base URL, the user will be redirected to the correct domain.',
				)),
				'fallbackLanguage' => array('select', array(
					'label' => 'Fallback Language',
					'multiOptions' => array('' => '[ None ]') + \LanguageQuery::create()->find()->toKeyValue('PrimaryKey','Name'),
					'value' => isset($config->curry->fallbackLanguage) ? $config->curry->fallbackLanguage : '',
					'description' => 'The language used when no language has been specified for the rendered page. Also the language used in backend context.',
				)),
			)
		)), 'general');
		
		// Backend
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Backend',
			'class' => 'advanced',
			'elements' => array(
				'theme' => array('select', array(
					'label' => 'Theme',
					'multiOptions' => array('' => '[ Default ]') + $themes,
					'value' => isset($config->curry->backend->theme) ? $config->curry->backend->theme : '',
					'description' => 'Theme for the administrative back-end.',
				)),
				'logotype' => array('filebrowser', array(
					'label' => 'Backend Logotype',
					'value' => isset($config->curry->backend->logotype) ? $config->curry->backend->logotype : '',
					'description' => 'Path to the backend logotype. The height of this image should be 100px.',
				)),
				'templatePage' => array('select', array(
					'label' => 'Template page',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->curry->backend->templatePage) ? $config->curry->backend->templatePage : '',
					'description' => 'The page containing page templates (i.e. pages to be used as base pages). When creating new pages or editing a page using the Content tab, only this page and pages below will be shown as base pages.',
				)),
				'defaultEditor' => array('text', array(
					'label' => 'Default HTML editor',
					'value' => isset($config->curry->defaultEditor) ? $config->curry->defaultEditor : '',
					'description' => 'The default WYSIWYG editor to use with the article module.',
					'placeholder' => $defaultConfig->curry->defaultEditor,
				)),
				'autoBackup' => array('text', array(
					'label' => 'Automatic database backup',
					'value' => isset($config->curry->autoBackup) ? $config->curry->autoBackup : '',
					'placeholder' => $defaultConfig->curry->autoBackup,
					'description' => 'Specifies the number of seconds since last backup to create automatic database backups when logged in to the backend.',
				)),
				'revisioning' => array('checkbox', array(
					'label' => 'Revisioning',
					'value' => isset($config->curry->revisioning) ? $config->curry->revisioning : '',
					'description' => 'When enabled, a new working revision will automatically be created when you create a page. You will also be warned when editing a published page revision',
				)),
				'autoPublish' => array('checkbox', array(
					'label' => 'Auto Publish',
					'value' => isset($config->curry->autoPublish) ? $config->curry->autoPublish : '',
					'description' => 'When enabled, a check will be made on every request to check if there are any pages that should be published (using publish date).',
				)),
				'noauth' => array('checkbox', array(
					'label' => 'Disable Backend Authorization',
					'value' => isset($config->curry->backend->noauth) ? $config->curry->backend->noauth : '',
					'description' => 'This will completely disable authorization for the backend.',
				)),
				'autoUpdateIndex' => array('checkbox', array(
					'label' => 'Auto Update Search Index',
					'value' => isset($config->curry->autoUpdateIndex) ? $config->curry->autoUpdateIndex : '',
					'description' => 'Automatically update (rebuild) search index when changing page content.',
				)),
			)
		)), 'backend');

		// Live edit
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Live edit',
			'class' => 'advanced',
			'elements' => array(
				'liveEdit' => array('checkbox', array(
					'label' => 'Enable Live Edit',
					'value' => isset($config->curry->liveEdit) ? $config->curry->liveEdit : $defaultConfig->curry->liveEdit,
					'description' => 'Enables editing of content directly in the front-end.',
				)),
				'placeholderExclude' => array('textarea', array(
					'label' => 'Excluded placeholders',
					'value' => isset($config->curry->backend->placeholderExclude) ? join(PHP_EOL, $config->curry->backend->placeholderExclude->toArray()) : '',
					'description' => 'Prevent placeholders from showing up in live edit mode. Use newlines to separate placeholders.',
					'rows' => 5,
				)),
			),
		)), 'liveEdit');
		
		// Error pages
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Error pages',
			'class' => 'advanced',
			'elements' => array(
				'notFound' => array('select', array(
					'label' => 'Page not found (404)',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->curry->errorPage->notFound) ? $config->curry->errorPage->notFound : '',
				)),
				'unauthorized' => array('select', array(
					'label' => 'Unauthorized (401)',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->curry->errorPage->unauthorized) ? $config->curry->errorPage->unauthorized : '',
				)),
				'error' => array('select', array(
					'label' => 'Internal server error (500)',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->curry->errorPage->error) ? $config->curry->errorPage->error : '',
				)),
			)
		)), 'errorPage');
		
		// Maintenance
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Maintenance',
			'class' => 'advanced',
			'elements' => array(
				'enabled' => array('checkbox', array(
					'label' => 'Enabled',
					'required' => true,
					'value' => isset($config->curry->maintenance->enabled) ? $config->curry->maintenance->enabled : '',
					'description' => 'When maintenance is enabled, users will not be able to access the pages. Only a page (specified below) will be shown. If no page is specified, the message will be shown.',
				)),
				'page' => array('select', array(
					'label' => 'Page to show',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->curry->maintenance->page) ? $config->curry->maintenance->page : '',
				)),
				'message' => array('textarea', array(
					'label' => 'Message',
					'value' => isset($config->curry->maintenance->message) ? $config->curry->maintenance->message : '',
					'rows' => 6,
					'cols' => 40,
				))
			)
		)), 'maintenance');
		
		// Mail
		$testEmailUrl = url('', array('module', 'view' => 'TestEmail'));
		$dlgOpts = array('width' => 600, 'minHeight' => 150);
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Mail',
			'class' => 'advanced',
			'elements' => array(
				'method' => array('select', array(
					'label' => 'Transport',
					'multiOptions' => array('' => '[ Default ]', 'smtp' => 'SMTP', 'sendmail' => 'PHP mail() function, ie sendmail.'),
					'value' => isset($config->curry->mail->method) ? $config->curry->mail->method : '',
				)),
				'host' => array('text', array(
					'label' => 'Host',
					'value' => isset($config->curry->mail->host) ? $config->curry->mail->host : '',
				)),
				'port' => array('text', array(
					'label' => 'Port',
					'value' => isset($config->curry->mail->options->port) ? $config->curry->mail->options->port : '',
				)),
				'auth' => array('select', array(
					'label' => 'Auth',
					'multiOptions' => array('' => '[ Default ]', 'plain' => 'plain', 'login' => 'login', 'cram-md5' => 'cram-md5'),
					'value' => isset($config->curry->mail->options->auth) ? $config->curry->mail->options->auth : '',
				)),
				'username' => array('text', array(
					'label' => 'Username',
					'value' => isset($config->curry->mail->options->username) ? $config->curry->mail->options->username : '',
				)),
				'password' => array('password', array(
					'label' => 'Password',
				)),
				'ssl' => array('select', array(
					'label' => 'SSL',
					'multiOptions' => array('' => 'Disabled', 'ssl' => 'SSL', 'tls' => 'TLS'),
					'value' => isset($config->curry->mail->options->ssl) ? $config->curry->mail->options->ssl : '',
				)),
				'mailTest' => array('rawHtml', array(
					'value' => '<a href="'.$testEmailUrl.'" class="btn dialog" data-dialog="'.htmlspecialchars(json_encode($dlgOpts)).'">Test email</a>',
				)),
			)
		)), 'mail');
		
		// Paths
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Paths',
			'class' => 'advanced',
			'elements' => array(
				'basePath' => array('text', array(
					'label' => 'Base path',
					'value' => isset($config->curry->basePath) ? $config->curry->basePath : '',
					'placeholder' => $defaultConfig->curry->basePath
				)),
				'projectPath' => array('text', array(
					'label' => 'Project Path',
					'value' => isset($config->curry->projectPath) ? $config->curry->projectPath : '',
					'placeholder' => $defaultConfig->curry->projectPath
				)),
				'wwwPath' => array('text', array(
					'label' => 'WWW path',
					'value' => isset($config->curry->wwwPath) ? $config->curry->wwwPath : '',
					'placeholder' => $defaultConfig->curry->wwwPath
				)),
				'vendorPath' => array('text', array(
					'label' => 'Vendor path',
					'value' => isset($config->curry->vendorPath) ? $config->curry->vendorPath : '',
					'placeholder' => $defaultConfig->curry->vendorPath
				)),
			)
		)), 'paths');
		
		// Encoding
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Encoding',
			'class' => 'advanced',
			'elements' => array(
				'internal' => array('text', array(
					'label' => 'Internal Encoding',
					'value' => isset($config->curry->internalEncoding) ? $config->curry->internalEncoding : '',
					'description' => 'The internal encoding for PHP.',
					'placeholder' => $defaultConfig->curry->internalEncoding,
				)),
				'output' => array('text', array(
					'label' => 'Output Encoding',
					'value' => isset($config->curry->outputEncoding) ? $config->curry->outputEncoding : '',
					'description' => 'The default output encoding for pages.',
					'placeholder' => $defaultConfig->curry->outputEncoding,
				)),
			)
		)), 'encoding');
		
		// Misc
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Misc',
			'class' => 'advanced',
			'elements' => array(
				'error_notification' => array('checkbox', array(
					'label' => 'Error notification',
					'value' => isset($config->curry->errorNotification) ? $config->curry->errorNotification : '',
					'description' => 'If enabled, an attempt to send error-logs to the admin email will be performed when an error occur.'
				)),
				'log_propel' => array('checkbox', array(
					'label' => 'Propel Logging',
					'value' => isset($config->curry->propel->logging) ? $config->curry->propel->logging : '',
					'description' => 'Database queries and other debug information will be logged to the selected logging facility.'
				)),
				'debug_propel' => array('checkbox', array(
					'label' => 'Debug Propel',
					'value' => isset($config->curry->propel->debug) ? $config->curry->propel->debug : '',
					'description' => 'Enables query counting but doesn\'t log queries.',
				)),
				'log' => array('select', array(
					'label' => 'Logging',
					'multiOptions' => array('' => '[ Other ]', 'none' => 'Disable logging', 'firebug' => 'Firebug'),
					'value' => isset($config->curry->log->method) ? $config->curry->log->method : '',
				)),
				'update_translations' => array('checkbox', array(
					'label' => 'Update Language strings',
					'value' => isset($config->curry->updateTranslationStrings) ? $config->curry->updateTranslationStrings : '',
					'description' => 'Add strings as they are used and record last used timestamp',
				)),

			)
		)), 'misc');
		
		$form->addElement('submit', 'save', array(
			'label' => 'Save',
			'disabled' => $configFile ? null : 'disabled',
		));
		
		if (isPost() && $form->isValid($_POST)) {
			$this->saveSettings($config, $form->getValues());
		}
		
		$this->addMainContent($form);
	}

	/**
	 * Save the config file.
	 *
	 * @param \Zend\Config\Config $config
	 * @param array $values
	 */
	private function saveSettings(&$config, array $values)
	{
		// General
		self::setvar($config->curry, 'name', $values['general']['name']);
		self::setvar($config->curry, 'baseUrl', $values['general']['baseUrl']);
		self::setvar($config->curry, 'adminEmail', $values['general']['adminEmail']);
		$config->curry->divertOutMailToAdmin = (bool)$values['general']['divertOutMailToAdmin'];
		self::setvar($config->curry, 'fallbackLanguage', $values['general']['fallbackLanguage'] ? $values['general']['fallbackLanguage'] : null);
		$config->curry->developmentMode = (bool)$values['general']['developmentMode'];
		$config->curry->forceDomain = (bool)$values['general']['forceDomain'];
		
		// backend
		$config->curry->revisioning = (bool)$values['backend']['revisioning'];
		$config->curry->autoPublish = (bool)$values['backend']['autoPublish'];
		$config->curry->backend->noauth = (bool)$values['backend']['noauth'];
		self::setvar($config->curry, 'defaultEditor', $values['backend']['defaultEditor']);
		self::setvar($config->curry->backend, 'theme', $values['backend']['theme']);
		self::setvar($config->curry->backend, 'templatePage', $values['backend']['templatePage'] ? (int)$values['backend']['templatePage'] : null);
		self::setvar($config->curry->backend, 'logotype', $values['backend']['logotype']);
		self::setvar($config->curry, 'autoBackup', $values['backend']['autoBackup']);
		self::setvar($config->curry, 'autoUpdateIndex', $values['backend']['autoUpdateIndex']);

		// Live edit
		$excludedPlaceholders = array_filter(array_map('trim', explode(PHP_EOL, $values['liveEdit']['placeholderExclude'])));
		if (!count($excludedPlaceholders))
			$excludedPlaceholders = null;
		$config->curry->liveEdit = (bool)$values['liveEdit']['liveEdit'];
		self::setvar($config->curry->backend, 'placeholderExclude', $excludedPlaceholders);

		// Encoding
		self::setvar($config->curry, 'internalEncoding', $values['encoding']['internal']);
		self::setvar($config->curry, 'outputEncoding', $values['encoding']['output']);
		
		// Paths
		self::setvar($config->curry, 'basePath', $values['paths']['basePath']);
		self::setvar($config->curry, 'projectPath', $values['paths']['projectPath']);
		self::setvar($config->curry, 'wwwPath', $values['paths']['wwwPath']);
		self::setvar($config->curry, 'vendorPath', $values['paths']['vendorPath']);
			
		// Mail
		if($values['mail']['method']) {
			if(!$config->curry->mail)
				$config->curry->mail = array();
			$config->curry->mail->method = $values['mail']['method'];
			
			// Smtp
			if($values['mail']['method'] == 'smtp') {
				$config->curry->mail->host = $values['mail']['host'];
				if(!$config->curry->mail->options)
					$config->curry->mail->options = array();
				self::setvar($config->curry->mail->options, 'port', $values['mail']['port']);
				self::setvar($config->curry->mail->options, 'ssl', $values['mail']['ssl']);
				self::setvar($config->curry->mail->options, 'auth', $values['mail']['auth']);
				self::setvar($config->curry->mail->options, 'username', $values['mail']['username']);
				self::setvar($config->curry->mail->options, 'password', $values['mail']['password']);
			}
		} else if(isset($config->curry->mail->method)) {
			unset($config->curry->mail->method);
		}
		
		// Misc
		$config->curry->errorNotification = (bool)$values['misc']['error_notification'];
		$config->curry->propel->logging = (bool)$values['misc']['log_propel'];
		$config->curry->propel->debug = (bool)$values['misc']['debug_propel'];
		if($values['misc']['log'])
			$config->curry->log->method = $values['misc']['log'];
		$config->curry->updateTranslationStrings = (bool)$values['misc']['update_translations'];
			
		// Error pages
		$config->curry->errorPage->notFound = $values['errorPage']['notFound'] ? (int)$values['errorPage']['notFound'] : null;
		$config->curry->errorPage->unauthorized = $values['errorPage']['unauthorized'] ? (int)$values['errorPage']['unauthorized'] : null;
		$config->curry->errorPage->error = $values['errorPage']['error'] ? (int)$values['errorPage']['error'] : null;
		
		// Maintenance
		$config->curry->maintenance->enabled = (bool)$values['maintenance']['enabled'];
		$config->curry->maintenance->page = $values['maintenance']['page'] ? (int)$values['maintenance']['page'] : null;
		$config->curry->maintenance->message = $values['maintenance']['message'];
		
		// Set migration version if missing
		if (!isset($config->curry->migrationVersion))
			$config->curry->migrationVersion = \Curry_Core::MIGRATION_VERSION;
			
		// Unset upgrade version if present
		if (isset($config->curry->upgradeVersion))
			unset($config->curry->upgradeVersion);
		
		try {
			\Curry_Core::writeConfiguration($config);
			$this->addMessage("Settings saved.", self::MSG_SUCCESS);
		}
		catch (\Exception $e) {
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
		}
	}
	
	/**
	 * Set configuration variable. If value is an empty string, the variable will be unset.
	 *
	 * @param \Zend\Config\Config $config
	 * @param string $name
	 * @param string $value
	 */
	private static function setvar(&$config, $name, $value)
	{
		if($config instanceof \Zend\Config\Config) {
			if($value != '')
				$config->$name = $value;
			else
				unset($config->$name);
		}
	}
	
	/**
	 * Create an archive of the project.
	 */
	public function showBundle()
	{
		
		$this->addMainMenu();
		
		$this->addMessage('You can install this bundle using <a href="'.url('', array('module','view'=>'InstallScript')).'">this installation script</a>.', self::MSG_NOTICE, false);
		
		$form = new \Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'elements' => array(
				'project' => array('checkbox', array(
					'label' => 'Project',
					'value' => true,
				)),
				'www' => array('checkbox', array(
					'label' => 'WWW folder',
					'value' => true,
				)),
				'base' => array('checkbox', array(
					'label' => 'Curry Core',
					'value' => true,
				)),
				'database' => array('checkbox', array(
					'label' => 'Database',
					'value' => true,
				)),
				'compression' => array('select',array(
					'label' => 'Compression',
					'multiOptions' => array(
						Archive::COMPRESSION_NONE => 'None',
						Archive::COMPRESSION_GZ => 'Gzip',
						//Archive::COMPRESSION_BZ2 => 'Bzip2',
					),
				)),
				'save' => array('submit', array(
					'label' => 'Create bundle',
				)),
			)
		));
		
		if (isPost() && $form->isValid($_POST)) {
			// create archive
			@set_time_limit(0);
			$compression = $form->compression->getValue();
			$tar = new Archive('', $compression);
			
			// set up file list
			$options = array(
				array(
					'pattern' => '*.svn*',
					'pattern_subject' => 'path',
					'skip' => true,
				),
				array(
					'pattern' => '*.git*',
					'pattern_subject' => 'path',
					'skip' => true,
				),
				array(
					'pattern' => '.DS_Store',
					'skip' => true,
				),
				array(
					'pattern' => 'Thumbs.db',
					'skip' => true,
				),
				array(
					'pattern' => '._*',
					'skip' => true,
				),
			);
			
			if($form->project->isChecked()) {
				$tar->add(\Curry\App::getInstance()->config->curry->projectPath, 'cms/', array_merge($options, array(
					array('path' => 'data/', 'pattern' => 'data/*/*', 'pattern_subject' => 'path', 'skip' => true),
				)));
			}
			
			if($form->www->isChecked()) {
				$tar->add(\Curry\App::getInstance()->config->curry->wwwPath, 'www/', array_merge($options, array(
					array('path' => 'shared', 'skip' => true),
					array('path' => 'shared/', 'skip' => true),
				)));
			}
			
			if($form->base->isChecked()) {
				$sharedPath = realpath(\Curry\App::getInstance()->config->curry->wwwPath . '/shared');
				if($sharedPath)
					$tar->add($sharedPath, 'www/shared/', $options);
				$tar->add(\Curry\App::getInstance()->config->curry->basePath.'/include', 'curry/include/', $options);
				$tar->add(\Curry\App::getInstance()->config->curry->basePath.'/propel', 'curry/propel/', $options);
				$tar->add(\Curry\App::getInstance()->config->curry->basePath.'/vendor', 'curry/vendor/', $options);
				$tar->add(\Curry\App::getInstance()->config->curry->basePath.'/.htaccess', 'curry/', $options);
			}
			
			if($form->database->isChecked()) {
				$fiveMBs = 5 * 1024 * 1024;
				$fp = fopen("php://temp/maxmemory:$fiveMBs", 'r+');
				if(!\Curry_Backend_DatabaseHelper::dumpDatabase($fp))
					throw new \Exception('Aborting: There was an error when dumping the database.');
				
				fseek($fp, 0);
				$tar->addString('db.txt', stream_get_contents($fp));
				fclose($fp);
			}
			
			$filename = str_replace(" ", "_", \Curry\App::getInstance()->config->curry->name)."-bundle-".date("Ymd").".tar" . ($compression ? ".$compression" : '');
			header("Content-type: " . Archive::getCompressionMimeType($compression));
			header("Content-disposition: attachment; filename=" . \Curry_String::escapeQuotedString($filename));
			
			// do not use output buffering
			while(ob_end_clean())
				;
			
			$tar->stream();
			exit;
		}
		
		$this->addMainContent($form);
	}
	
	/**
	 * Create installation script.
	 */
	public function showInstallScript()
	{
		$classes = array(
			'Curry_Install',
			'Curry_String',
			'Curry_Util',
			'Curry_Archive_FileInfo',
			'Curry_Archive_Reader',
			'Curry_Archive_Iterator',
			'Curry_Archive',
		);
		$contents = "<?php\n\n// CurryCMS v".\Curry_Core::VERSION." Installation Script\n// Created on ".strftime('%Y-%m-%d %H:%M:%S')."\n";
		$contents .= str_repeat('/', 60)."\n\n";
		
		$contents .= "ini_set('error_reporting', E_ALL & ~E_NOTICE);\n";
		$contents .= "ini_set('display_errors', 1);\n";
		$contents .= "umask(0002);\n\n";

		$autoloader = \Curry_Core::getAutoloader();
		foreach($classes as $clazz) {
			$file = $autoloader->findFile($clazz);
			$contents .= "// $clazz ($file)\n";
			$contents .= str_repeat('/', 60)."\n\n";
			$contents .= preg_replace('/^<\?php\s+/', '', file_get_contents($file)) . "\n\n";
		}

		$contents.= "//////////////////////////////////////////////////////////\n\n";
		$contents.= 'Curry_Install::show(isset($_GET[\'step\']) ? $_GET[\'step\'] : \'\');';

		$contents = str_replace("{{INSTALL_CSS}}", file_get_contents(\Curry\App::getInstance()->config->curry->basePath.'/shared/backend/common/css/install.css'), $contents);

		Frontend::returnData($contents, 'text/plain', 'install.php');
	}
	
	/**
	 * Clear cache.
	 * 
	 * @todo Clear templates.
	 */
	public function showClearCache()
	{
		$this->addMainMenu();
		
		\Curry\App::getInstance()->cache->clean(\Zend_Cache::CLEANING_MODE_ALL);
		\Curry_Twig_Template::getSharedEnvironment()->clearCacheFiles();
		if(extension_loaded('apc'))
			@apc_clear_cache();
		
		$this->addMessage('Cache cleaned', self::MSG_SUCCESS);
	}
	
	/**
	 * Show info with version numbers
	 *
	 */
	public function showInfo()
	{
		$this->addMainMenu();
		
		$this->addMessage('Curry: '. \Curry_Core::VERSION);
		$this->addMessage('PHP: '. PHP_VERSION . ' (<a href="'.url('', array('module','view'=>'PhpInfo')).'">phpinfo</a>)', self::MSG_NOTICE, false);
		$this->addMessage('Zend Framework: '. \Zend_Version::VERSION);
		$this->addMessage('Propel: '. \Propel::VERSION);
		$this->addMessage('Twig: '. \Twig_Environment::VERSION);
		
		$license = \Curry\App::getInstance()->config->curry->basePath.'/LICENSE.txt';
		if (file_exists($license))
			$this->addMainContent('<pre>'.htmlspecialchars(file_get_contents($license)).'</pre>');
		else
			$this->addMessage('Unable to find license file.', self::MSG_ERROR);
	}
	
	/**
	 * Show phpinfo().
	 */
	public function showPhpInfo()
	{
		phpinfo();
		exit;
	}

	protected static function getReleases()
	{
		try {
			// Override user agent
			$opts = array(
				'http' => array(
					'header' => "User-Agent: CurryCMS/".\Curry_Core::VERSION." (http://currycms.com)\r\n"
				)
			);
			$context = stream_context_create($opts);
			$tags = file_get_contents('https://api.github.com/repos/bombayworks/currycms/tags', null, $context);
			$tags = json_decode($tags);
			$versions = array();
			foreach($tags as $tag) {
				if(preg_match('/^v?([\d\.]+.*)$/', strtolower($tag->name), $m)) {
					$tag->version = $m[1];
					$versions[$m[1]] = $tag;
				}
			}
			uksort($versions, 'version_compare');
			return $versions;
		}
		catch (\Exception $e) {
			trace_warning('Failed to fetch release list: '.$e->getMessage());
			return null;
		}
	}
	
	protected static function getButtonForm($name, $display)
	{
		return new \Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'elements' => array(
				$name => array('submit', array(
					'label' => $display,
				))
			)
		));
	}
	
	/**
	 * Migrate curry to newer version.
	 */
	public function showUpgrade()
	{
		$this->addMainMenu();
		
		$releases = self::getReleases();
		if($releases === null) {
			$this->addMessage('Unable to check for new releases', self::MSG_WARNING);
		} else {
			$latest = count($releases) ? array_pop($releases) : null;
			if($latest) {
				$this->addMessage('Installed version: '.\Curry_Core::VERSION);
				$this->addMessage('Latest version: '.$latest->version);
				if (version_compare($latest->version, \Curry_Core::VERSION, '>')) {
					$this->addMessage('New release found: '.$latest->name);
				} else {
					$this->addMessage('You already have the latest version.', self::MSG_SUCCESS);
				}
			} else {
				$this->addMessage('No releases could be found.', self::MSG_WARNING);
			}
		}
		
		if(!\Curry_Core::requireMigration())
			return;
		
		$form = self::getButtonForm('migrate', 'Migrate');
		if (isPost() && $form->isValid($_POST) && $form->migrate->isChecked()) {
			$currentVersion = \Curry\App::getInstance()->config->curry->migrationVersion;
			while($currentVersion < \Curry_Core::MIGRATION_VERSION) {
				$nextVersion = $currentVersion + 1;
				$migrateMethod = 'doMigrate'.$nextVersion;
				if(method_exists($this, $migrateMethod)) {
					try {
						if($this->$migrateMethod()) {
							// update configuration migrateVersion number
							$config = \Curry_Core::openConfiguration();
							$config->curry->migrateVersion = $nextVersion;
							\Curry_Core::writeConfiguration($config);
							$currentVersion = $nextVersion;
							$this->addMessage('Migration to version '.$nextVersion.' was successful!', self::MSG_SUCCESS);
						} else {
							$this->addMessage("Migrating to version $nextVersion returned failure.", self::MSG_ERROR);
							break;
						}
					}
					catch (\Exception $e) {
						$this->addMessage("Unable to migrate to version $nextVersion: ".$e->getMessage(), self::MSG_ERROR);
						break;
					}
				} else {
					$this->addMessage("Unable to find migration method '$migrateMethod'.", self::MSG_ERROR);
					break;
				}
			}
		} else {
			$backupUrl = url('', array('module','view'=>'Bundle'));
			$this->addMessage('Curry CMS has been updated and you need to migrate your project before you can continue using the backend. You should <a href="'.$backupUrl.'">backup</a> your data and click the migrate button when you\'re ready!', self::MSG_WARNING, false);
			$this->addMainContent($form);
		}
	}
	
	public function showTestEmail()
	{
		$form = $this->getTestEmailForm();
		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues(true);
			$ret = $this->sendTestEmail($values);
			Frontend::returnPartial('<pre>'.$ret.'</pre>');
		}
		$this->addMainContent($form);
	}
	
	protected function getTestEmailForm()
	{
		return new \Curry_Form(array(
			'action' => url('', array('module', 'view')),
			'method' => 'post',
			'elements' => array(
				'toEmail' => array('text', array(
					'label' => 'To email',
					'required' => true,
					'placeholder' => 'Enter your email address',
				)),
				'message' => array('textarea', array(
					'label' => 'Message',
					'rows' => 4,
				)),
				'send' => array('submit', array('label' => 'Send test email')),
			),
		));
	}
	
	protected function sendTestEmail(array $values)
	{
		$projectName = \Curry\App::getInstance()->config->curry->name;
		$body =<<<HTML
<p>If you can read this email message, then you have correctly configured your email settings.</p>
<p>This is an automated email. Please do not reply.</p>
<pre style="font-size:11pt">
{$values['message']}
</pre>
<br/>
<p>{$projectName}</p>
HTML;

		try {
			$mail = new \Curry_Mail();
			$mail->addTo($values['toEmail'], $values['toEmail'])
				->setFrom(\Curry\App::getInstance()->config->curry->adminEmail, $projectName)
				->setSubject('Test email from '.\Curry\App::getInstance()->config->curry->name)
				->setBodyHtml($body)
				->setBodyText(strip_tags($body))
				->send()
				;
			if (\Curry\App::getInstance()->config->curry->divertOutMailToAdmin) {
				$ret = 'Outgoing email was diverted to adminEmail at '.\Curry\App::getInstance()->config->curry->adminEmail;
			} else {
				$ret = 'An email has been sent to your email address at '.$values['toEmail'];
			}
		} catch (\Exception $e) {
			$ret = 'An exception has been thrown: '.$e->getMessage();
		}
		
		return $ret;
	}
	
}
