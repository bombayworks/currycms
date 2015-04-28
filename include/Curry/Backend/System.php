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
use Curry\App;
use Curry\Archive\Archive;
use Curry\Controller\Frontend;
use Curry\Form\Form;
use Curry\Mail;
use Curry\Util\PathHelper;
use Curry\Util\StringHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zend\Config\Config;

/**
 * Change system settings.
 * 
 * @package Curry\Backend
 */
class System extends AbstractBackend
{

	public function initialize() {
		$this->addViewFunction('testemail', array($this, 'showTestEmail'));
		$this->addViewFunction('bundle', array($this, 'showBundle'));
		$this->addViewFunction('cache', array($this, 'showClearCache'));
		$this->addViewFunction('info', array($this, 'showInfo'))
			->addViewFunction('phpinfo', array($this, 'showPhpInfo'));
		$this->addViewFunction('upgrade', array($this, 'showUpgrade'));
	}


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
		$this->addMenuItem("Settings", $this->url());
		$this->addMenuItem("Bundle", $this->bundle->url());
		$this->addMenuItem("Clear cache", $this->cache->url());
		$this->addMenuItem("Info", $this->info->url());
		$this->addMenuItem("Upgrade", $this->upgrade->url());
	}
	
	/** {@inheritdoc} */
	public function show(Request $request)
	{
		$this->addMainMenu();
		
		$configFile = $this->app['configPath'];
		if(!$configFile)
			$this->addMessage("Configuration file not set.", self::MSG_ERROR);
		else if(!is_writable($configFile))
			$this->addMessage("Configuration file doesn't seem to be writable.", self::MSG_ERROR);
			
		$config = $this->app->openConfiguration();
		$defaultConfig = $this->app->getDefaultConfiguration();
		
		$form = new \Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post'
		));

		$themes = array();
		$backendPath = PathHelper::path(true, $this->app['wwwPath'], 'shared', 'backend');
		if($backendPath) {
			foreach (new \DirectoryIterator($backendPath) as $entry) {
				$name = $entry->getFilename();
				if (!$entry->isDot() && $entry->isDir() && $name !== 'common') {
					$themes[$name] = $name;
				}
			}
		}
		$activeTheme = isset($config->backend->theme) ? $config->backend->theme : false;
		if($activeTheme && !array_key_exists($activeTheme, $themes)) {
			$themes[$activeTheme] = $activeTheme;
		}

		$pages = \PagePeer::getSelect();

		$loggers = $this->getDefaultLoggers($config);
		$enabledLoggers = isset($config->log) ? array_filter($config->log->toArray(), function($log) {
			return !isset($log['enabled']) || $log['enabled'];
		}) : array();
		
		// General
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'General',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'value' => isset($config->name) ? $config->name : '',
					'description' => 'Name of site, shown in backend header and page title by default.',
					'placeholder' => $defaultConfig->name,
				)),
				'baseUrl' => array('text', array(
					'label' => 'Base URL',
					'value' => isset($config->baseUrl) ? $config->baseUrl : '',
					'description' => 'The URL to use when creating absolute URLs. This should end with a slash, and may include a path.',
					'placeholder' => $defaultConfig->baseUrl,
				)),
				'adminEmail' => array('text', array(
					'label' => 'Admin email',
					'value' => isset($config->adminEmail) ? $config->adminEmail : '',
				)),
				'divertOutMailToAdmin' => array('checkbox', array(
						'label' => 'Divert outgoing email to admin email',
						'value' => isset($config->divertOutMailToAdmin) ? $config->divertOutMailToAdmin : '',
						'description' => 'All outgoing Curry\Mail will be diverted to admin email.',
				)),
				'developmentMode' => array('checkbox', array(
					'label' => 'Development mode',
					'value' => isset($config->developmentMode) ? $config->developmentMode : '',
				)),
				'forceDomain' => array('checkbox', array(
					'label' => 'Force domain',
					'value' => isset($config->forceDomain) ? $config->forceDomain : '',
					'description' => 'If the domain of the requested URL doesn\'t match the domain set by Base URL, the user will be redirected to the correct domain.',
				)),
				'fallbackLanguage' => array('select', array(
					'label' => 'Fallback Language',
					'multiOptions' => array('' => '[ None ]') + \LanguageQuery::create()->find()->toKeyValue('PrimaryKey','Name'),
					'value' => isset($config->fallbackLanguage) ? $config->fallbackLanguage : '',
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
					'value' => isset($config->backend->theme) ? $config->backend->theme : '',
					'description' => 'Theme for the administrative back-end.',
				)),
				'logotype' => array('filebrowser', array(
					'label' => 'Backend Logotype',
					'value' => isset($config->backend->logotype) ? $config->backend->logotype : '',
					'description' => 'Path to the backend logotype. The height of this image should be 100px.',
				)),
				'templatePage' => array('select', array(
					'label' => 'Template page',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->backend->templatePage) ? $config->backend->templatePage : '',
					'description' => 'The page containing page templates (i.e. pages to be used as base pages). When creating new pages or editing a page using the Content tab, only this page and pages below will be shown as base pages.',
				)),
				'defaultEditor' => array('text', array(
					'label' => 'Default HTML editor',
					'value' => isset($config->defaultEditor) ? $config->defaultEditor : '',
					'description' => 'The default WYSIWYG editor to use with the article module.',
					'placeholder' => $defaultConfig->defaultEditor,
				)),
				'autoBackup' => array('text', array(
					'label' => 'Automatic database backup',
					'value' => isset($config->autoBackup) ? $config->autoBackup : '',
					'placeholder' => $defaultConfig->autoBackup,
					'description' => 'Specifies the number of seconds since last backup to create automatic database backups when logged in to the backend.',
				)),
				'revisioning' => array('checkbox', array(
					'label' => 'Revisioning',
					'value' => isset($config->revisioning) ? $config->revisioning : '',
					'description' => 'When enabled, a new working revision will automatically be created when you create a page. You will also be warned when editing a published page revision',
				)),
				'autoPublish' => array('checkbox', array(
					'label' => 'Auto Publish',
					'value' => isset($config->autoPublish) ? $config->autoPublish : '',
					'description' => 'When enabled, a check will be made on every request to check if there are any pages that should be published (using publish date).',
				)),
				'noauth' => array('checkbox', array(
					'label' => 'Disable Backend Authorization',
					'value' => isset($config->backend->noauth) ? $config->backend->noauth : '',
					'description' => 'This will completely disable authorization for the backend.',
				)),
				'autoUpdateIndex' => array('checkbox', array(
					'label' => 'Auto Update Search Index',
					'value' => isset($config->autoUpdateIndex) ? $config->autoUpdateIndex : '',
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
					'value' => isset($config->liveEdit) ? $config->liveEdit : $defaultConfig->liveEdit,
					'description' => 'Enables editing of content directly in the front-end.',
				)),
				'placeholderExclude' => array('textarea', array(
					'label' => 'Excluded placeholders',
					'value' => isset($config->backend->placeholderExclude) ? join(PHP_EOL, $config->backend->placeholderExclude->toArray()) : '',
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
					'value' => isset($config->errorPage->notFound) ? $config->errorPage->notFound : '',
				)),
				'unauthorized' => array('select', array(
					'label' => 'Unauthorized (401)',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->errorPage->unauthorized) ? $config->errorPage->unauthorized : '',
				)),
				'error' => array('select', array(
					'label' => 'Internal server error (500)',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->errorPage->error) ? $config->errorPage->error : '',
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
					'value' => isset($config->maintenance->enabled) ? $config->maintenance->enabled : '',
					'description' => 'When maintenance is enabled, users will not be able to access the pages. Only a page (specified below) will be shown. If no page is specified, the message will be shown.',
				)),
				'page' => array('select', array(
					'label' => 'Page to show',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'value' => isset($config->maintenance->page) ? $config->maintenance->page : '',
				)),
				'message' => array('textarea', array(
					'label' => 'Message',
					'value' => isset($config->maintenance->message) ? $config->maintenance->message : '',
					'rows' => 6,
					'cols' => 40,
				))
			)
		)), 'maintenance');
		
		// Mail
		$dlgOpts = array('width' => 600, 'minHeight' => 150);
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Mail',
			'class' => 'advanced',
			'elements' => array(
				'fromEmail' => array('text', array(
					'label' => 'From email',
					'value' => isset($config->mail->from->email) ? $config->mail->from->email : '',
				)),
				'fromName' => array('text', array(
					'label' => 'From name',
					'value' => isset($config->mail->from->name) ? $config->mail->from->name : '',
				)),
				'replytoEmail' => array('text', array(
					'label' => 'ReplyTo email',
					'value' => isset($config->mail->replyto->email) ? $config->mail->replyto->email : '',
				)),
				'replytoName' => array('text', array(
					'label' => 'ReplyTo name',
					'value' => isset($config->mail->replyto->name) ? $config->mail->replyto->name : '',
				)),
				'method' => array('select', array(
					'label' => 'Transport',
					'multiOptions' => array('' => '[ Default ]', 'smtp' => 'SMTP', 'sendmail' => 'PHP mail() function, ie sendmail.'),
					'value' => isset($config->mail->method) ? $config->mail->method : '',
				)),
				'host' => array('text', array(
					'label' => 'Host',
					'value' => isset($config->mail->host) ? $config->mail->host : '',
				)),
				'port' => array('text', array(
					'label' => 'Port',
					'value' => isset($config->mail->options->port) ? $config->mail->options->port : '',
				)),
				'auth' => array('select', array(
					'label' => 'Auth',
					'multiOptions' => array('' => '[ Default ]', 'plain' => 'plain', 'login' => 'login', 'cram-md5' => 'cram-md5'),
					'value' => isset($config->mail->options->auth) ? $config->mail->options->auth : '',
				)),
				'username' => array('text', array(
					'label' => 'Username',
					'value' => isset($config->mail->options->username) ? $config->mail->options->username : '',
				)),
				'password' => array('password', array(
					'label' => 'Password',
				)),
				'ssl' => array('select', array(
					'label' => 'SSL',
					'multiOptions' => array('' => 'Disabled', 'ssl' => 'SSL', 'tls' => 'TLS'),
					'value' => isset($config->mail->options->ssl) ? $config->mail->options->ssl : '',
				)),
				'mailTest' => array('rawHtml', array(
					'value' => '<a href="'.$this->testemail->url().'" class="btn dialog" data-dialog="'.htmlspecialchars(json_encode($dlgOpts)).'">Test email</a>',
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
					'value' => isset($config->basePath) ? $config->basePath : '',
					'placeholder' => $defaultConfig->basePath
				)),
				'projectPath' => array('text', array(
					'label' => 'Project Path',
					'value' => isset($config->projectPath) ? $config->projectPath : '',
					'placeholder' => $defaultConfig->projectPath
				)),
				'wwwPath' => array('text', array(
					'label' => 'WWW path',
					'value' => isset($config->wwwPath) ? $config->wwwPath : '',
					'placeholder' => $defaultConfig->wwwPath
				)),
				'vendorPath' => array('text', array(
					'label' => 'Vendor path',
					'value' => isset($config->vendorPath) ? $config->vendorPath : '',
					'placeholder' => $defaultConfig->vendorPath
				)),
			)
		)), 'paths');
		
		// Misc
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Misc',
			'class' => 'advanced',
			'elements' => array(
				'error_notification' => array('checkbox', array(
					'label' => 'Error notification',
					'value' => isset($config->errorNotification) ? $config->errorNotification : '',
					'description' => 'If enabled, an attempt to send error-logs to the admin email will be performed when an error occur.'
				)),
				'log_propel' => array('checkbox', array(
					'label' => 'Propel Logging',
					'value' => isset($config->propel->logging) ? $config->propel->logging : '',
					'description' => 'Database queries and other debug information will be logged to the selected logging facility.'
				)),
				'debug_propel' => array('checkbox', array(
					'label' => 'Debug Propel',
					'value' => isset($config->propel->debug) ? $config->propel->debug : '',
					'description' => 'Enables query counting but doesn\'t log queries.',
				)),
				'log' => array('multiselect', array(
					'label' => 'Logging',
					'multiOptions' => array_combine(array_keys($loggers), array_keys($loggers)),
					'value' => array_keys($enabledLoggers),
				)),
				'update_translations' => array('checkbox', array(
					'label' => 'Update Language strings',
					'value' => isset($config->updateTranslationStrings) ? $config->updateTranslationStrings : '',
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
		return parent::render();
	}

	protected function getDefaultLoggers(Config $config = null)
	{
		$loggers = isset($config->log) ? $config->log->toArray() : array();
		return $loggers + array(
			'firebug' => array(
				'type' => 'Monolog\Handler\FirePHPHandler',
			),
			'file' => array(
				'fingersCrossed' => true,
				'type' => 'Monolog\Handler\StreamHandler',
				'arguments' => array(
					$this->app['projectPath'].'/data/log/app.log',
				),
			),
		);
	}

	/**
	 * Save the config file.
	 *
	 * @param Config $config
	 * @param array $values
	 */
	private function saveSettings(&$config, array $values)
	{
		// General
		self::setvar($config, 'name', $values['general']['name']);
		self::setvar($config, 'baseUrl', $values['general']['baseUrl']);
		self::setvar($config, 'adminEmail', $values['general']['adminEmail']);
		$config->divertOutMailToAdmin = (bool)$values['general']['divertOutMailToAdmin'];
		self::setvar($config, 'fallbackLanguage', $values['general']['fallbackLanguage'] ? $values['general']['fallbackLanguage'] : null);
		$config->developmentMode = (bool)$values['general']['developmentMode'];
		$config->forceDomain = (bool)$values['general']['forceDomain'];
		
		// backend
		$config->revisioning = (bool)$values['backend']['revisioning'];
		$config->autoPublish = (bool)$values['backend']['autoPublish'];
		$config->backend->noauth = (bool)$values['backend']['noauth'];
		self::setvar($config, 'defaultEditor', $values['backend']['defaultEditor']);
		self::setvar($config, 'backend.theme', $values['backend']['theme']);
		self::setvar($config, 'backend.templatePage', $values['backend']['templatePage'] ? (int)$values['backend']['templatePage'] : null);
		self::setvar($config, 'backend.logotype', $values['backend']['logotype']);
		self::setvar($config, 'autoBackup', $values['backend']['autoBackup']);
		self::setvar($config, 'autoUpdateIndex', $values['backend']['autoUpdateIndex']);

		// Live edit
		$excludedPlaceholders = array_filter(array_map('trim', explode(PHP_EOL, $values['liveEdit']['placeholderExclude'])));
		if (!count($excludedPlaceholders))
			$excludedPlaceholders = null;
		$config->liveEdit = (bool)$values['liveEdit']['liveEdit'];
		self::setvar($config, 'backend.placeholderExclude', $excludedPlaceholders);

		// Paths
		self::setvar($config, 'basePath', $values['paths']['basePath']);
		self::setvar($config, 'projectPath', $values['paths']['projectPath']);
		self::setvar($config, 'wwwPath', $values['paths']['wwwPath']);
		self::setvar($config, 'vendorPath', $values['paths']['vendorPath']);
			
		// Mail
		self::setvar($config, 'mail.from.email', $values['mail']['fromEmail']);
		self::setvar($config, 'mail.from.name', $values['mail']['fromName']);
		self::setvar($config, 'mail.replyto.email', $values['mail']['replytoEmail']);
		self::setvar($config, 'mail.replyto.name', $values['mail']['replytoName']);
		self::setvar($config, 'mail.method', $values['mail']['method']);
		// Mail / Smtp
		if($values['mail']['method'] == 'smtp') {
			self::setvar($config, 'mail.host', $values['mail']['host']);
			self::setvar($config, 'mail.options.port', $values['mail']['port']);
			self::setvar($config, 'mail.options.ssl', $values['mail']['ssl']);
			self::setvar($config, 'mail.options.auth', $values['mail']['auth']);
			self::setvar($config, 'mail.options.username', $values['mail']['username']);
			self::setvar($config, 'mail.options.password', $values['mail']['password']);
		}

		// Misc
		$config->errorNotification = (bool)$values['misc']['error_notification'];
		$config->propel->logging = (bool)$values['misc']['log_propel'];
		$config->propel->debug = (bool)$values['misc']['debug_propel'];
		$loggers = $this->getDefaultLoggers($config);
		foreach($loggers as $name => $logger) {
			if (in_array($name, $values['misc']['log'])) {
				if (!isset($config->log->$name)) {
					$config->log->$name = $logger;
				}
				unset($config->log->$name->enabled);
			} else if (isset($config->log->$name)) {
				$config->log->$name->enabled = false;
			}
		}
		$config->updateTranslationStrings = (bool)$values['misc']['update_translations'];
			
		// Error pages
		$config->errorPage->notFound = $values['errorPage']['notFound'] ? (int)$values['errorPage']['notFound'] : null;
		$config->errorPage->unauthorized = $values['errorPage']['unauthorized'] ? (int)$values['errorPage']['unauthorized'] : null;
		$config->errorPage->error = $values['errorPage']['error'] ? (int)$values['errorPage']['error'] : null;
		
		// Maintenance
		$config->maintenance->enabled = (bool)$values['maintenance']['enabled'];
		$config->maintenance->page = $values['maintenance']['page'] ? (int)$values['maintenance']['page'] : null;
		$config->maintenance->message = $values['maintenance']['message'];
		
		// Set migration version if missing
		if (!isset($config->migrationVersion))
			$config->migrationVersion = App::MIGRATION_VERSION;
			
		// Unset upgrade version if present
		if (isset($config->upgradeVersion))
			unset($config->upgradeVersion);
		
		try {
			$this->app->writeConfiguration($config);
			$this->addMessage("Settings saved.", self::MSG_SUCCESS);
		}
		catch (\Exception $e) {
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
		}
	}
	
	/**
	 * Set configuration variable. If value is an empty string, the variable will be unset.
	 *
	 * @param Config $config
	 * @param string $name
	 * @param string $value
	 */
	private static function setvar(Config $config, $name, $value)
	{
		if (strpos($name, '.') !== false) {
			$parts = explode('.', $name);
			do {
				$name = array_shift($parts);
				if (!isset($config->$name)) {
					if ($value === '') {
						return;
					}
					// this will actually be converted to a Config object, so we don't have to use references below.
					$config->$name = array();
				}
				$config = $config->$name;
			} while (count($parts) > 1);
			$name = array_shift($parts);
		}
		if($value != '')
			$config->$name = $value;
		else
			unset($config->$name);
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
				$tar->add($this->app['projectPath'], 'cms/', array_merge($options, array(
					array('path' => 'data/', 'pattern' => 'data/*/*', 'pattern_subject' => 'path', 'skip' => true),
				)));
			}
			
			if($form->www->isChecked()) {
				$tar->add($this->app['wwwPath'], 'www/', array_merge($options, array(
					array('path' => 'shared', 'skip' => true),
					array('path' => 'shared/', 'skip' => true),
				)));
			}
			
			if($form->base->isChecked()) {
				$sharedPath = realpath($this->app['wwwPath'] . '/shared');
				if($sharedPath)
					$tar->add($sharedPath, 'www/shared/', $options);
				$tar->add($this->app['basePath'].'/include', 'curry/include/', $options);
				$tar->add($this->app['basePath'].'/propel', 'curry/propel/', $options);
				$tar->add($this->app['basePath'].'/vendor', 'curry/vendor/', $options);
				$tar->add($this->app['basePath'].'/.htaccess', 'curry/', $options);
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
			
			$filename = str_replace(" ", "_", $this->app['name'])."-bundle-".date("Ymd").".tar" . ($compression ? ".$compression" : '');
			header("Content-type: " . Archive::getCompressionMimeType($compression));
			header("Content-disposition: attachment; filename=" . StringHelper::escapeQuotedString($filename));
			
			// do not use output buffering
			while(ob_end_clean())
				;
			
			$tar->stream();
			exit;
		}
		
		$this->addMainContent($form);
		return parent::render();
	}
	
	/**
	 * Create installation script.
	 */
	public function showInstallScript()
	{
		$classes = array(
			'Curry_Install',
			'Curry\Util\StringHelper',
			'Curry\Util\Helper',
			'Curry_Archive_FileInfo',
			'Curry_Archive_Reader',
			'Curry_Archive_Iterator',
			'Curry_Archive',
		);
		$contents = "<?php\n\n// CurryCMS v". App::VERSION ." Installation Script\n// Created on ".strftime('%Y-%m-%d %H:%M:%S')."\n";
		$contents .= str_repeat('/', 60)."\n\n";
		
		$contents .= "ini_set('error_reporting', E_ALL & ~E_NOTICE);\n";
		$contents .= "ini_set('display_errors', 1);\n";
		$contents .= "umask(0002);\n\n";

		$autoloader = $this->app->autoloader;
		foreach($classes as $clazz) {
			$file = $autoloader->findFile($clazz);
			$contents .= "// $clazz ($file)\n";
			$contents .= str_repeat('/', 60)."\n\n";
			$contents .= preg_replace('/^<\?php\s+/', '', file_get_contents($file)) . "\n\n";
		}

		$contents.= "//////////////////////////////////////////////////////////\n\n";
		$contents.= 'Curry_Install::show(isset($_GET[\'step\']) ? $_GET[\'step\'] : \'\');';

		$contents = str_replace("{{INSTALL_CSS}}", file_get_contents($this->app['basePath'].'/shared/backend/common/css/install.css'), $contents);

		self::returnData($contents, 'text/plain', 'install.php');
	}
	
	/**
	 * Clear cache.
	 */
	public function showClearCache(Request $request)
	{
		$this->addMainMenu();

		$form = new Form(array(
			'fields' => array(
				'clear' => array(
					'type' => 'submit',
					'class' => 'btn btn-danger',
				),
			),
		));

		if ($request->isMethod('POST') && $form->isValid($request->request->all()) && $form->clear->isClicked()) {
			$this->app->cache->clean(\Zend_Cache::CLEANING_MODE_ALL);
			\Curry_Twig_Template::getSharedEnvironment()->clearCacheFiles();
			if(extension_loaded('apc'))
				@apc_clear_cache();
			$this->addMessage('Cache cleared!', self::MSG_SUCCESS);
		} else {
			$this->addMainContent($form);
		}

		return parent::render();
	}
	
	/**
	 * Show info with version numbers
	 *
	 */
	public function showInfo()
	{
		$this->addMainMenu();
		
		$this->addMessage('Curry: '. App::VERSION);
		$this->addMessage('PHP: '. PHP_VERSION . ' (<a href="'.$this->info->phpinfo->url().'">phpinfo</a>)', self::MSG_NOTICE, false);
		$this->addMessage('Zend Framework: '. \Zend_Version::VERSION);
		$this->addMessage('Propel: '. \Propel::VERSION);
		$this->addMessage('Twig: '. \Twig_Environment::VERSION);
		
		$license = $this->app['basePath'].'/LICENSE.txt';
		if (file_exists($license))
			$this->addMainContent('<pre>'.htmlspecialchars(file_get_contents($license)).'</pre>');
		else
			$this->addMessage('Unable to find license file.', self::MSG_ERROR);
		return parent::render();
	}
	
	/**
	 * Show phpinfo().
	 */
	public function showPhpInfo()
	{
		ob_start();
		phpinfo();
		return ob_get_clean();
	}

	protected static function getReleases()
	{
		try {
			// Override user agent
			$opts = array(
				'http' => array(
					'header' => "User-Agent: CurryCMS/". App::VERSION ." (http://currycms.com)\r\n"
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
			App::getInstance()->logger->warning('Failed to fetch release list: '.$e->getMessage());
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
				$this->addMessage('Installed version: '. App::VERSION);
				$this->addMessage('Latest version: '.$latest->version);
				if (version_compare($latest->version, App::VERSION, '>')) {
					$this->addMessage('New release found: '.$latest->name);
				} else {
					$this->addMessage('You already have the latest version.', self::MSG_SUCCESS);
				}
			} else {
				$this->addMessage('No releases could be found.', self::MSG_WARNING);
			}
		}
		
		if(!$this->app->requireMigration())
			return parent::render();
		
		$form = self::getButtonForm('migrate', 'Migrate');
		if (isPost() && $form->isValid($_POST) && $form->migrate->isChecked()) {
			$currentVersion = $this->app['migrationVersion'];
			while($currentVersion < App::MIGRATION_VERSION) {
				$nextVersion = $currentVersion + 1;
				$migrateMethod = 'doMigrate'.$nextVersion;
				if(method_exists($this, $migrateMethod)) {
					try {
						if($this->$migrateMethod()) {
							// update configuration migrateVersion number
							$config = $this->app->openConfiguration();
							$config->migrateVersion = $nextVersion;
							$this->app->writeConfiguration($config);
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

		return parent::render();
	}
	
	public function showTestEmail()
	{
		$form = $this->getTestEmailForm();
		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues(true);
			$ret = $this->sendTestEmail($values);
			$this->addMessage($ret);
		} else {
			$this->addMainContent($form);
		}
		return parent::render();
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
		$projectName = $this->app['name'];
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
			$mail = new Mail();
			$mail->addTo($values['toEmail'], $values['toEmail'])
				->setFrom($this->app['adminEmail'], $projectName)
				->setSubject('Test email from '.$this->app['name'])
				->setBodyHtml($body)
				->setBodyText(strip_tags($body))
				->send()
				;
			if ($this->app['divertOutMailToAdmin']) {
				$ret = 'Outgoing email was diverted to adminEmail at '.$this->app['adminEmail'];
			} else {
				$ret = 'An email has been sent to your email address at '.$values['toEmail'];
			}
		} catch (\Exception $e) {
			$ret = 'An exception has been thrown: '.$e->getMessage();
		}
		
		return $ret;
	}
	
}
