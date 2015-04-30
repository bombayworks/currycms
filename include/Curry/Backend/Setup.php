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
use Curry\URL;
use Curry\Util\Console;
use Curry\Util\PathHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Curry setup/installation backend.
 *
 * @package Curry\Backend
 */
class Curry_Backend_Setup extends \Curry\Backend\AbstractBackend {
	
	public function getGroup()
	{
		return 'Installation';
	}

	public function initialize()
	{
		$this->addViewFunction('permissions', array($this, 'showPermissions'));
		$this->addViewFunction('database', array($this, 'showDatabase'));
		$this->addViewFunction('create-database', array($this, 'showCreateDatabase'));
		$this->addViewFunction('configure', array($this, 'showConfigure'));
		$this->addViewFunction('complete', array($this, 'showSetupComplete'));
	}

	public function show(Request $request)
	{
		return RedirectResponse::create($this->permissions->url());
	}
	
	public function showPermissions()
	{
		$this->addMainContent('<h2>Checking file permissions</h2>');
		$error = false;
		$projectPath = $this->app['projectPath'];
		$wwwPath = $this->app['wwwPath'];
		$paths = array(
			PathHelper::path($projectPath, 'data'),
			PathHelper::path($projectPath, 'templates'),
			PathHelper::path($projectPath, 'propel', 'build'),
			PathHelper::path($projectPath, 'config'),
			PathHelper::path($wwwPath, 'cache'),
		);
		foreach ($paths as $path) {
			if(!is_writable($path)) {
				$this->addMessage($path.' is not writable', self::MSG_WARNING);
				$error = true;
			} else if(is_dir($path)) {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
					RecursiveIteratorIterator::SELF_FIRST);
				foreach ($iterator as $item) {
					if(!$item->isWritable()) {
						$this->addMessage($item->getPathname().' is not writable', self::MSG_WARNING);
						$error = true;
					}
				}
			}
		}
		$nextUrl = $this->database->url();
		if($error) {
			$this->addMainContent('<p>Please fix the errors above and reload the page. If you\'re unable to fix the errors, you may attempt to <a href="'.$nextUrl.'">continue installation anyway</a>.</p>');
		} else {
			return RedirectResponse::create($nextUrl);
		}
		return parent::render();
	}

	public function showDatabase()
	{
		$nextUrl = $this->configure->url();

		$this->addMainContent('<h2>Configure database</h2>');
		$this->addBreadcrumb('Database', $this->database->url());
		$url = url('', array('module', 'view'=>'CreateDatabase'));
		$this->addCommand('Create database', $this['create-database']->url(), 'icon-plus-sign', array('class' => 'dialog'));

		$cmsPath = $this->app['projectPath'];
		$propelConfig = PathHelper::path($cmsPath, 'config', 'propel.xml');
		if(!is_writable($propelConfig))
			$this->addMessage("Configuration file $propelConfig doesn't seem to be writable.", self::MSG_ERROR);

		$config = new SimpleXMLElement(file_get_contents($propelConfig));
		$defaultDataSource = (string)$config->propel->datasources['default'];
		$params = array(
			'host' => 'localhost',
			'database' => '',
			'user' => '',
			'password' => '',
			'set_charset' => true,
			'create_tables' => true,
		);
		foreach($config->propel->datasources->datasource as $datasource) {
			if((string)$datasource['id'] == $defaultDataSource) {
				switch((string)$datasource->adapter) {
					case 'mysql':
						$params['adapter'] = 'mysql';
						if (preg_match('/^mysql:host=([^;]+);dbname=([^;]+)?$/', $datasource->connection->dsn, $matches)) {
							$params['host'] = $matches[1];
							$params['database'] = $matches[2];
						}
						$params['user'] = (string)$datasource->connection->user;
						$params['password'] = (string)$datasource->connection->password;
						break;
				}
				break;
			}
		}

		$form = $this->getDatabaseForm($params);
		if(isPost() && $form->isValid($_POST)) {
			if($form->test->isChecked()) {
				$status = self::testConnection($form->getValues());
				if ($status === true)
					$this->addMessage('Connection OK', self::MSG_SUCCESS);
				else
					$this->addMessage('Connection failed: ' . $status, self::MSG_ERROR);
			} else if($form->save->isChecked()) {
				if($this->saveConnection($form->getValues(), $propelConfig))
					return RedirectResponse::create($nextUrl);
			}
		}
		$this->addMainContent($form);
		return parent::render();
	}

	public function showCreateDatabase()
	{
		$form = $this->getCreateDatabaseForm();
		if(isPost() && $form->isValid($_POST)) {
			try {
				$values = $form->getValues();
				$dsn = "mysql:host={$values['host']}";
				$username = strlen($values['admin_user']) ? $values['admin_user'] : null;
				$password = strlen($values['admin_password']) ? $values['admin_password'] : null;
				$pdo = new PDO($dsn, $username, $password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				if ($values['user']) {
					$stmt = $pdo->prepare("CREATE USER :user@'localhost' IDENTIFIED BY :password");
					$stmt->execute(array(':user' => $values['user'], ':password' => $values['password']));
				}
				if ($values['database']) {
					$pdo->exec("CREATE DATABASE IF NOT EXISTS `".$values['database']."` CHARACTER SET utf8 COLLATE utf8_general_ci;");
					if ($values['user']) {
						$stmt = $pdo->prepare("GRANT ALL ON `".$values['database']."`.* TO :user@'localhost'");
						$stmt->execute(array(':user' => $values['user']));
					}
				}
				// Use javascript to update the values of our parent form
				$transferValues = array('host','database','user','password');
				$javascript = 'var el;';
				foreach($transferValues as $name) {
					$javascript .= 'el = document.getElementById('.json_encode($name).');';
					$javascript .= 'if (el) { el.value = '.json_encode($values[$name]).'; }';
				}
				$this->addMainContent('<script>'.$javascript.'</script>');
				$this->addMessage('Success!', self::MSG_SUCCESS);
			}
			catch(Exception $e) {
				$this->addMessage($e->getMessage(), self::MSG_ERROR);
				$this->addMainContent($form);
			}
		} else {
			$this->addMainContent($form);
		}
		return parent::render();
	}

	public function showConfigure()
	{
		$this->addBreadcrumb('Database', url('', array('module', 'view'=>'Database')));
		$this->addBreadcrumb('Configure', url('', array('module', 'view'=>'Configure')));

		$form = $this->getConfigureForm();
		if(isPost() && $form->isValid($_POST)) {
			$this->saveConfiguration($form->getValues());
			return RedirectResponse::create(url('', array('module','view'=>'SetupComplete')));
		} else {
			$this->addMainContent('<h2>Basic configuration</h2>');

			$configFile = $this->app['configPath'];
			if(!$configFile)
				$this->addMessage("Configuration file not set.", self::MSG_ERROR);
			else if(!is_writable($configFile))
				$this->addMessage("Configuration file $configFile doesn't seem to be writable.", self::MSG_ERROR);

			$this->addMainContent($form);
		}
		return parent::render();
	}

	public function showSetupComplete()
	{
		// Disable setup and enable backend authorization
		$config = $this->app->openConfiguration();
		$config->setup = false;
		$config->backend->noauth = false;
		$this->app->writeConfiguration($config);

		$backendUrl = url($this->app['backend.basePath']);
		$frontendUrl = url('~/');
		$this->addMainContent(<<<HTML
<div style="text-align:center">
  <h1>Installation complete!</h1>
  <p><img src="shared/backend/common/images/install-finished.png" alt="" /></p>
  <p>Proceed to <a href="$backendUrl">login in to the backend</a> or <a href="$frontendUrl">visit your webpage</a>.</p>
</div>
HTML
);
		return parent::render();
	}

	protected function getCreateDatabaseForm()
	{
		$pdoDrivers = method_exists('PDO', 'getAvailableDrivers') ? PDO::getAvailableDrivers() : array();
		$adapters = count($pdoDrivers) ? array_combine($pdoDrivers, $pdoDrivers) : array();
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'elements' => array(
				'adapter' => array('select', array(
					'label' => 'Adapter',
					'multiOptions' => $adapters,
					'value' => 'mysql',
					'id' => 'create-id',
				)),
				'host' => array('text', array(
					'label' => 'Host',
					'value' => 'localhost',
					'id' => 'create-host',
				)),
				'admin_user' => array('text', array(
					'label' => 'Admin user',
					'value' => 'root',
					'id' => 'create-admin-user',
				)),
				'admin_password' => array('password', array(
					'label' => 'Admin password',
					'id' => 'create-admin-password',
				)),
				'database' => array('text', array(
					'label' => 'Database',
					'value' => 'curry_db',
					'id' => 'create-database',
				)),
				'user' => array('text', array(
					'label' => 'New user',
					'value' => 'curry_user',
					'id' => 'create-user',
				)),
				'password' => array('password', array(
					'label' => 'Password',
					'id' => 'create-password',
				)),
				'save' => array('submit', array(
					'label' => 'Create',
				)),
			),
		));
		return $form;
	}

	protected function getDatabaseForm($params)
	{
		$pdoDrivers = method_exists('PDO', 'getAvailableDrivers') ? PDO::getAvailableDrivers() : array();
		$adapters = count($pdoDrivers) ? array_combine($pdoDrivers, $pdoDrivers) : array();
		$form = new Curry_Form(array(
			'csrfCheck' => false,
			'elements' => array(
				'adapter' => array('select', array(
					'label' => 'Adapter',
					'multiOptions' => $adapters,
				)),
				'host' => array('text', array(
					'label' => 'Host',
					'value' => $params['host'],
				)),
				'database' => array('text', array(
					'label' => 'Database',
					'value' => $params['database'],
				)),
				'user' => array('text', array(
					'label' => 'User',
					'value' => $params['user'],
				)),
				'password' => array('password', array(
					'label' => 'Password',
					'renderPassword' => true,
					'value' => $params['password'],
				)),
				'set_charset' => array('checkbox', array(
					'label' => 'Set database charset to UTF-8',
					'value' => $params['set_charset'],
				)),
				'create_tables' => array('checkbox', array(
					'label' => 'Create tables',
					'value' => $params['create_tables'],
				)),
				'test' => array('submit', array(
					'label' => 'Test connection',
				)),
				'save' => array('submit', array(
					'label' => 'Save',
				)),
			),
		));
		$form->addDisplayGroup(array('test','save'), 'buttons', array('class'=>'horizontal-group'));
		return $form;
	}

	protected static function testConnection($params)
	{
		try {
			$dsn = "mysql:host={$params['host']};dbname={$params['database']}";
			$username = strlen($params['user']) ? $params['user'] : null;
			$password = strlen($params['password']) ? $params['password'] : null;
			$pdo = new PDO($dsn, $username, $password);
			unset($pdo);
			return true;
		}
		catch(Exception $e) {
			return $e->getMessage();
		}
	}

	protected function saveConnection($params, $propelConfig)
	{
		$success = true;

		// Get adapter configuration
		$adapter = null;
		switch($params['adapter']) {
			case 'mysql':
				$adapter = 'mysql';
				$dsn = "mysql:host={$params['host']};dbname={$params['database']}";
				$username = strlen($params['user']) ? $params['user'] : null;
				$password = strlen($params['password']) ? $params['password'] : null;
				break;

			default:
				$this->addMessage("Adapter configuration not supported, please configure database settings manually.", self::MSG_ERROR);
				return false;
		}

		// Update runtime configuration
		$config = new SimpleXMLElement(file_get_contents($propelConfig));
		$defaultDataSource = (string)$config->propel->datasources['default'];
		foreach($config->propel->datasources->datasource as $datasource) {
			if((string)$datasource['id'] == $defaultDataSource) {
				$datasource->adapter = $adapter;
				$datasource->connection->dsn = $dsn;
				$datasource->connection->user = $username;
				$datasource->connection->password = $password;
			}
		}
		$config = $config->asXML();

		// Write database configuration
		if(!@file_put_contents($propelConfig, $config)) {
			$this->addMessage('Failed to write propel build configuration, please make sure this is the content of '.$propelConfig.':', self::MSG_ERROR);
			$this->addMainContent('<pre>'.htmlspecialchars($config).'</pre>');
			return false;
		}

		// Generate propel classes and configuration
		$content = Curry_Backend_DatabaseHelper::propelGen('');
		if(!Curry_Backend_DatabaseHelper::getPropelGenStatus($content)) {
			$this->addMessage('It seems there was an error when building propel', self::MSG_ERROR);
			$this->addMainContent('<pre class="console">'.Console::colorize($content).'</pre>');
			return false;
		}

		// Initialize propel
		Propel::init($this->app['propel.conf']);

		// Set database charset
		if($params['set_charset']) {
			$con = Propel::getConnection();
			$result = $con->exec('ALTER DATABASE '.$params['database'].' CHARACTER SET utf8 COLLATE utf8_general_ci');
			if(!$result) {
				$this->addMessage('Unable to change database charset', self::MSG_WARNING);
				$success = false;
			}
		}

		// Create tables
		if($params['create_tables']) {
			$content = Curry_Backend_DatabaseHelper::propelGen('insert-sql');
			if(!Curry_Backend_DatabaseHelper::getPropelGenStatus($content)) {
				$this->addMessage('It seems there was an error when creating database tables', self::MSG_ERROR);
				$this->addMainContent('<pre class="console">'.Console::colorize($content).'</pre>');
				return false;
			}
		}

		return $success;
	}

	protected function getConfigureForm()
	{
		$contentTemplates = array(
			'empty' => 'Empty page',
			//'curry' => 'Curry CMS example',
			//'html5boilerplate' => 'HTML5 Boilerplate',
			//'twitter-bootstrap' => 'Twitter Bootstrap',
		);

		if(file_exists('db.txt')) {
			$contentTemplates = array('backup' => '[ Restore from backup ]') + $contentTemplates;
		}

		$scriptPath = URL::getScriptPath();
		if (($pos = strrpos($scriptPath, '/')) !== false)
			$scriptPath = substr($scriptPath, 0, $pos + 1);
		else
			$scriptPath = '';

		$form = new Curry_Form(array(
			'csrfCheck' => false,
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Project name',
					'value' => $this->app['name'],
				)),
				'email' => array('text', array(
					'label' => 'Webmaster email',
					'value' => $this->app['adminEmail'],
				)),
				'base_url' => array('text', array(
					'label' => 'Base URL',
					'value' => $scriptPath == '/' ? '' : $scriptPath,
					'placeholder' => 'auto-detect',
				)),
				'template' => array('select', array(
					'label' => 'Content template',
					'multiOptions' => $contentTemplates,
				)),
				'development_mode' => array('checkbox', array(
					'label' => 'Development mode',
					'value' => $this->app['developmentMode'],
				)),
				'save' => array('submit', array(
					'label' => 'Save',
				))
			),
		));

		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Create administrator account',
			'elements' => array(
				'username' => array('text', array(
					'label' => 'Username',
					'value' => 'admin',
				)),
				'password' => array('password', array(
					'label' => 'Password',
					'renderPassword' => true,
				)),
				'password_confirm' => array('password', array(
					'label' => 'Confirm password',
					'renderPassword' => true,
					'validators' => array(
						array('identical', false, array('token' => 'password'))
					),
				)),
			)
		)), 'admin', 5);

		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Create user account',
			'elements' => array(
				'username' => array('text', array(
					'label' => 'Username',
					'value' => 'user',
				)),
				'password' => array('password', array(
					'label' => 'Password',
					'renderPassword' => true,
				)),
				'password_confirm' => array('password', array(
					'label' => 'Confirm password',
					'renderPassword' => true,
					'validators' => array(
						array('identical', false, array('token' => 'password'))
					),
				)),
			)
		)), 'user', 6);

		return $form;
	}

	public function saveConfiguration($values)
	{
		// Restore database from backup?
		if($values['template'] == 'backup') {
			if(!Curry_Backend_DatabaseHelper::restoreFromFile('db.txt')) {
				$this->addMessage('Unable to restore database content from db.txt', self::MSG_WARNING);
			}
		}

		// Create admin user
		if($values['admin']['username']) {
			$access = array('*', 'Curry_Backend_Content/*');
			$adminRole = self::createRole('Super', $access);
			$adminUser = self::createUser($values['admin']['username'], $values['admin']['password'], $adminRole);
			if($adminUser->isNew()) {
				self::createFilebrowserAccess($adminRole, 'Root', '');
			}
			$adminUser->save();
		}

		// Create light user
		if($values['user']['username']) {
			$access = array(
				'Curry_Backend_FileBrowser',
				'Curry_Backend_Page',
				'Curry_Backend_Profile',
				'Curry_Backend_Translations',
				'Curry_Backend_Content/*'
			);
			$userRole = self::createRole('User', $access);
			$user = self::createUser($values['user']['username'], $values['user']['password'], $userRole);
			if($user->isNew()) {
				$user->save();
				self::createFilebrowserAccess($user, 'Home', 'user-content/'.$user->getUserId().'/');
				self::createFilebrowserAccess($userRole, 'Shared', 'content/');
			}
			$user->save();
		}

		if($values['template'] != 'backup') {
			// Create default meta-data items
			$metadatas = array('Title' => 'text', 'Keywords' => 'textarea', 'Description' => 'textarea', 'Image' => 'previewImage');
			foreach($metadatas as $name => $type) {
				$metadata = new Metadata();
				$metadata->setName($name);
				$metadata->setDisplayName($name);
				$metadata->setType($type);
				$metadata->save();
			}

			$page = new Page();
			$page->setName("Home");
			$page->setURL("/");
			$page->setVisible(true);
			$page->setEnabled(true);
			$page->makeRoot();
			$page->save();

			$page->createDefaultRevisions();
			$page->save();

			$pageRev = $page->getWorkingPageRevision();
			$pageRev->setTemplate('Root.html');
			$pageRev->save();

			$pa = new PageAccess();
			$pa->setPage($page);
			$pa->setPermSubpages(true);
			$pa->setPermVisible(true);
			$pa->setPermCreatePage(true);
			$pa->setPermCreateModule(true);
			$pa->setPermPublish(true);
			$pa->setPermProperties(true);
			$pa->setPermContent(true);
			$pa->setPermMeta(true);
			$pa->setPermModules(true);
			$pa->setPermRevisions(true);
			$pa->setPermPermissions(true);
			$pa->save();
		}

		// Create template root
		$templateRoot = $this->app['template.root'];
		if(!file_exists($templateRoot))
			@mkdir($templateRoot, 0777, true);

		switch($values['template']) {
			case 'empty':
			case 'curry':
				$source = PathHelper::path($this->app['basePath'], 'shared', 'backend', 'common', 'templates', 'project-empty.html');
				$templateFile = PathHelper::path($templateRoot, 'Root.html');
				if(!file_exists($templateFile))
					@copy($source, $templateFile);
				break;

			case 'twitter-bootstrap':
			case 'html5boilerplate':
		}

		if (file_exists($this->app['configPath'])) {
			$config = $this->app->openConfiguration();
			$config->name = $values['name'];
			$config->adminEmail = $values['email'];
			if ($values['base_url'])
				$config->baseUrl = $values['base_url'];
			else
				unset($config->baseUrl);
			$config->developmentMode = (bool)$values['development_mode'];
			$config->secret = sha1(uniqid(mt_rand(), true) . microtime());
			$this->app->writeConfiguration($config);
		}

		return true;
	}

	protected static function createRole($name, array $access = array())
	{
		$role = UserRoleQuery::create()
			->filterByName($name)
			->findOneOrCreate();
		if ($role->isNew()) {
			foreach($access as $module) {
				$roleAccess = new UserRoleAccess();
				$roleAccess->setUserRole($role);
				$roleAccess->setModule($module);
			}
		}
		return $role;
	}

	protected static function createUser($name, $password, UserRole $role)
	{
		$user = UserQuery::create()
			->filterByName($name)
			->findOneOrCreate();
		$user->setPlainPassword($password);
		$user->setUserRole($role);
		return $user;
	}

	protected static function createFilebrowserAccess($userOrRole, $name, $path, $write = true)
	{
		$fba = new FilebrowserAccess();
		$fba->setName($name);
		if($userOrRole instanceof User)
			$fba->setUser($userOrRole);
		else if($userOrRole instanceof UserRole)
			$fba->setUserRole($userOrRole);
		$fba->setPath($path);
		$fba->setWrite($write);
		@mkdir(PathHelper::path(\Curry\App::getInstance()['wwwPath'], $path), 0777, true);
		return $fba;
	}
}
