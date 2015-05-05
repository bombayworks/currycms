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

use Curry\Util\Console;
use Curry\Util\PathHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Curry setup/installation backend.
 *
 * @package Curry\Backend
 */
class Setup extends AbstractBackend {
	
	public function getGroup()
	{
		return null;
	}

	public function initialize()
	{
		$this->addViewFunction('permissions', array($this, 'showPermissions'));
		$this->addViewFunction('database', array($this, 'showDatabase'));
		$this->addViewFunction('createDatabase', array($this, 'showCreateDatabase'), 'create-database');
		$this->addViewFunction('configure', array($this, 'showConfigure'));
		$this->addViewFunction('complete', array($this, 'showSetupComplete'));
	}

	public function show(Request $request)
	{
		return self::redirect($this->permissions->url());
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
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::SELF_FIRST);
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
			return self::redirect($nextUrl);
		}
		return parent::render();
	}

	public function showDatabase(Request $request)
	{
		$nextUrl = $this->configure->url();

		$this->addMainContent('<h2>Configure database</h2>');
		$this->addBreadcrumb('Database', $this->database->url());
		$this->addCommand('Create database', $this->createDatabase->url(), 'icon-plus-sign', array('class' => 'dialog'));

		$cmsPath = $this->app['projectPath'];
		$propelConfig = PathHelper::path($cmsPath, 'config', 'propel.xml');
		if(!is_writable($propelConfig))
			$this->addMessage("Configuration file $propelConfig doesn't seem to be writable.", self::MSG_ERROR);

		$config = new \SimpleXMLElement(file_get_contents($propelConfig));
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
		if($request->isMethod('POST') && $form->isValid($request->request->all())) {
			if($form->test->isChecked()) {
				$status = self::testConnection($form->getValues());
				if ($status === true)
					$this->addMessage('Connection OK', self::MSG_SUCCESS);
				else
					$this->addMessage('Connection failed: ' . $status, self::MSG_ERROR);
			} else if($form->save->isChecked()) {
				if($this->saveConnection($form->getValues(), $propelConfig))
					return self::redirect($nextUrl);
			}
		}
		$this->addMainContent($form);
		return parent::render();
	}

	public function showCreateDatabase(Request $request)
	{
		$form = $this->getCreateDatabaseForm();
		if($request->isMethod('POST') && $form->isValid($request->request->all())) {
			try {
				$values = $form->getValues();
				$dsn = "mysql:host={$values['host']}";
				$username = strlen($values['admin_user']) ? $values['admin_user'] : null;
				$password = strlen($values['admin_password']) ? $values['admin_password'] : null;
				$pdo = new \PDO($dsn, $username, $password);
				$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
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
			catch(\Exception $e) {
				$this->addMessage($e->getMessage(), self::MSG_ERROR);
				$this->addMainContent($form);
			}
		} else {
			$this->addMainContent($form);
		}
		return parent::render();
	}

	public function showConfigure(Request $request)
	{
		$this->addBreadcrumb('Database', $this->database->url());
		$this->addBreadcrumb('Configure', $this->configure->url());

		$form = $this->getConfigureForm();
		if($request->isMethod('POST') && $form->isValid($request->request->all())) {
			$this->saveConfiguration($form->getValues());
			return self::redirect($this->complete->url());
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

	public function showSetupComplete(Request $request)
	{
		// Disable setup and enable backend authorization
		$config = $this->app->openConfiguration();
		$config->setup = false;
		$config->backend->noauth = false;
		$this->app->writeConfiguration($config);

		$backendUrl = $request->getBasePath().$this->app['backend.basePath'];
		$frontendUrl = $request->getBasePath().'/';
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
		$pdoDrivers = method_exists('PDO', 'getAvailableDrivers') ? \PDO::getAvailableDrivers() : array();
		$adapters = count($pdoDrivers) ? array_combine($pdoDrivers, $pdoDrivers) : array();
		$form = new \Curry_Form(array(
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
		$pdoDrivers = method_exists('PDO', 'getAvailableDrivers') ? \PDO::getAvailableDrivers() : array();
		$adapters = count($pdoDrivers) ? array_combine($pdoDrivers, $pdoDrivers) : array();
		$form = new \Curry_Form(array(
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
			$pdo = new \PDO($dsn, $username, $password);
			unset($pdo);
			return true;
		}
		catch(\Exception $e) {
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
		$config = new \SimpleXMLElement(file_get_contents($propelConfig));
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
		$content = \Curry_Backend_DatabaseHelper::propelGen('');
		if(!\Curry_Backend_DatabaseHelper::getPropelGenStatus($content)) {
			$this->addMessage('It seems there was an error when building propel', self::MSG_ERROR);
			$this->addMainContent('<pre class="console">'.Console::colorize($content).'</pre>');
			return false;
		}

		// Initialize propel
		\Propel::init($this->app['propel.conf']);

		// Set database charset
		if($params['set_charset']) {
			$con = \Propel::getConnection();
			$result = $con->exec('ALTER DATABASE '.$params['database'].' CHARACTER SET utf8 COLLATE utf8_general_ci');
			if(!$result) {
				$this->addMessage('Unable to change database charset', self::MSG_WARNING);
				$success = false;
			}
		}

		// Create tables
		if($params['create_tables']) {
			$content = \Curry_Backend_DatabaseHelper::propelGen('insert-sql');
			if(!\Curry_Backend_DatabaseHelper::getPropelGenStatus($content)) {
				$this->addMessage('It seems there was an error when creating database tables', self::MSG_ERROR);
				$this->addMainContent('<pre class="console">'.Console::colorize($content).'</pre>');
				return false;
			}
		}

		return $success;
	}

	protected function getConfigureForm()
	{
		$form = new \Curry_Form(array(
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
					'value' => '',
					'placeholder' => 'auto-detect',
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

		$form->addSubForm(new \Curry_Form_SubForm(array(
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
		)), 'admin', 4);

		return $form;
	}

	public function saveConfiguration($values)
	{
		// Create admin role
		$access = array('*', 'Curry_Backend_Content/*');
		$adminRole = self::createRole('Administrator', $access);
		if ($adminRole->isNew()) {
			self::createFilebrowserAccess($adminRole, 'Root', '');
		}

		// Create editor role
		$access = array(
			'Curry_Backend_FileBrowser',
			'Curry_Backend_Page',
			'Curry_Backend_Profile',
			'Curry_Backend_Translations',
			'Curry_Backend_Content/*'
		);
		$editorRole = self::createRole('Editor', $access);
		if ($editorRole->isNew()) {
			self::createFilebrowserAccess($editorRole, 'Shared', 'content/shared/');
		}

		// Create admin user
		if($values['admin']['username']) {
			$adminUser = self::createUser($values['admin']['username'], $values['admin']['password'], $adminRole);
			$adminUser->save();
		}

		// Create default meta-data items
		$metadatas = array('Title' => 'text', 'Keywords' => 'textarea', 'Description' => 'textarea', 'Image' => 'previewImage');
		foreach($metadatas as $name => $type) {
			$metadata = new \Metadata();
			$metadata->setName($name);
			$metadata->setDisplayName($name);
			$metadata->setType($type);
			$metadata->save();
		}

		// Create pages
		$rootPage = new \Page();
		$rootPage->setName("Root");
		$rootPage->setURL("root/");
		$rootPage->setVisible(true);
		$rootPage->setEnabled(true);
		$rootPage->makeRoot();
		$rootPage->save();
		$rootPage->createDefaultRevisions($rootPage);
		$rootPage->save();

		$templatePage = new \Page();
		$templatePage->setName('Templates');
		$templatePage->setURL("templates/");
		$templatePage->setIncludeInIndex(false);
		$templatePage->insertAsLastChildOf($rootPage);
		$templatePage->save();
		$templatePage->createDefaultRevisions();
		$pageRevision = $templatePage->getWorkingPageRevision();
		$pageRevision->setTemplate('Root.html.twig');
		$templatePage->save();

		$homePage = new \Page();
		$homePage->setName('Home');
		$homePage->setURL("/");
		$homePage->setVisible(true);
		$homePage->setEnabled(true);
		$homePage->insertAsLastChildOf($rootPage);
		$homePage->save();
		$homePage->createDefaultRevisions($templatePage);
		$homePage->save();

		// Create page access objects
		$pa = new \PageAccess();
		$pa->setUserRole($adminRole);
		$pa->setPage($rootPage);
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

		$pa = new \PageAccess();
		$pa->setUserRole($editorRole);
		$pa->setPage($rootPage);
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
		$pa->setPermPermissions(false);
		$pa->save();

		$pa = new \PageAccess();
		$pa->setUserRole($editorRole);
		$pa->setPage($templatePage);
		$pa->setPermSubpages(true);
		$pa->setPermVisible(false);
		$pa->setPermCreatePage(false);
		$pa->setPermCreateModule(false);
		$pa->setPermPublish(false);
		$pa->setPermProperties(false);
		$pa->setPermContent(false);
		$pa->setPermMeta(false);
		$pa->setPermModules(false);
		$pa->setPermRevisions(false);
		$pa->setPermPermissions(false);
		$pa->save();

		// Create template root
		$templateRoot = $this->app['template.root'];
		if(!file_exists($templateRoot))
			@mkdir($templateRoot, 0777, true);

		if (file_exists($this->app['configPath'])) {
			$config = $this->app->openConfiguration();
			$config->name = $values['name'];
			$config->adminEmail = $values['email'];
			if (!isset($config->backend))
				$config->backend = array();
			$config->backend->templatePage = $templatePage->getPageId();
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

	/**
	 * @param $name
	 * @param array $access
	 * @return \UserRole
	 */
	protected static function createRole($name, array $access = array())
	{
		$role = \UserRoleQuery::create()
			->filterByName($name)
			->findOneOrCreate();
		if ($role->isNew()) {
			foreach($access as $module) {
				$roleAccess = new \UserRoleAccess();
				$roleAccess->setUserRole($role);
				$roleAccess->setModule($module);
			}
		}
		return $role;
	}

	/**
	 * @param $name
	 * @param $password
	 * @param \UserRole $role
	 * @return \User
	 */
	protected static function createUser($name, $password, \UserRole $role)
	{
		$user = \UserQuery::create()
			->filterByName($name)
			->findOneOrCreate();
		$user->setPlainPassword($password);
		$user->setUserRole($role);
		return $user;
	}

	/**
	 * @param \User|\UserRole $userOrRole
	 * @param $name
	 * @param $path
	 * @param bool $write
	 * @return \FilebrowserAccess
	 * @throws \Exception
	 */
	protected static function createFilebrowserAccess($userOrRole, $name, $path, $write = true)
	{
		$fba = new \FilebrowserAccess();
		$fba->setName($name);
		if($userOrRole instanceof \User)
			$fba->setUser($userOrRole);
		else if($userOrRole instanceof \UserRole)
			$fba->setUserRole($userOrRole);
		$fba->setPath($path);
		$fba->setWrite($write);
		@mkdir(PathHelper::path(\Curry\App::getInstance()['wwwPath'], $path), 0777, true);
		return $fba;
	}
}
