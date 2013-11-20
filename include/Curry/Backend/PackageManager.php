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
 * Package manager to install, remove and upgrade packages.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_PackageManager extends Curry_ModelBackend {
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->setModelClasses(array('Repository'));
	}
	
	/** @inheritdoc */
	public static function getName()
	{
		return "Package manager";
	}
	
	/** @inheritdoc */
	public static function getGroup()
	{
		return "System";
	}
	
	/** @inheritdoc */
	public function showMain()
	{
		$this->addMainContent( $this->getPackageGrid()->getHtml() );
	}
	
	/**
	 * Get flexigrid object for packages.
	 * 
	 * @return Curry_Flexigrid
	 */
	private function getPackageGrid()
	{
		$flexigrid = new Curry_Flexigrid('PackageManager', 'Packages', url('', array("module", "view"=>"Json")));
		$flexigrid->setPrimaryKey('name');
		
		$flexigrid->addLinkButton('Install / Upgrade', 'icon_package_add', url('', array('module','view'=>'Install')), 1);
		$flexigrid->addLinkButton('Remove', 'icon_package_delete', url('', array('module','view'=>'Remove')), 1);
		$flexigrid->addDialogButton('Show details', 'icon_magnifier', 'PackageManagerDetails', 'Show details', url('', array('module','view'=>'Details')), array(), 1);
		
		$flexigrid->addSeparator();
		$flexigrid->addLinkButton('Update repositories', 'icon_world_go', url('', array('module','view'=>'UpdateRepositories')));
		$flexigrid->addLinkButton('Manage repositories', 'icon_world_edit', url('', array('module','view'=>'Repositories')), -1);
		
		$flexigrid->addColumn('icon', '', array('width' => 20, 'sortable'=>false));
		$flexigrid->addColumn('name', 'Name', array('width' => 150, 'sortable'=>false));
		$flexigrid->addColumn('installed_version', 'Installed version', array('width'=>100, 'sortable'=>false));
		$flexigrid->addColumn('version', 'Latest version', array('width'=>100, 'sortable'=>false));
		$flexigrid->addColumn('size', 'Size', array('width'=>50, 'sortable'=>false));
		$flexigrid->addColumn('summary', 'Summary', array('sortable'=>false));
		
		return $flexigrid;
	}

	/**
	 * View to update repositories.
	 */
	public function showUpdateRepositories()
	{
		Curry_PackageManager::updateRepositories();
	}
	
	/**
	 * Show package details.
	 */
	public function showDetails()
	{
		$package = Curry_PackageManager::getPackage($_GET['name']);
		$html = "";
		foreach($package as $k => $v) {
			if($k != 'tasks' && $k != 'name') {
				if(is_array($v))
					$v = json_encode($v);
				$html .= "<tr><th>$k</th><td>".htmlspecialchars($v)."</td></tr>";
			}
		}
		$this->returnPartial("<table><caption>".$package['name']."</caption>$html</table>");
	}
	
	/**
	 * Show install package.
	 */
	public function showInstall()
	{
		$packageName = $_GET['name'];
		$package = Curry_PackageManager::getPackage($packageName);
		if(!$package) {
			$this->addMessage('Unable to find package', self::MSG_ERROR);
			return;
		}
		
		if(Curry_PackageManager::isInstalled($packageName, $package['version'])) {
			$this->addMessage('This package is already installed and is the latest version or newer.', self::MSG_ERROR);
			return;
		}
		
		$upgrade = array();
		$install = array();
		$dependencies = Curry_PackageManager::getPackageDependencies($packageName);
		$dependencies[$packageName] = $package['version'];
		foreach($dependencies as $depName => $depVersion) {
			if(in_array($depName, array('currycms', 'php'))) {
				if($depName == 'php' && version_compare($depVersion, PHP_VERSION, '>'))
					throw new Exception('Package requires php '.$depVersion);
				if($depName == 'currycms' && version_compare($depVersion, Curry_Core::VERSION, '>'))
					throw new Exception('Package requires currycms '.$depVersion);
			}
			else if(!Curry_PackageManager::isInstalled($depName))
				$install[] = $depName;
			else if(!Curry_PackageManager::isInstalled($depName, $depVersion))
				$upgrade[] = $depName;
		}
		
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'elements' => array(
				'simulate' => array('checkbox', array(
					'label' => 'Simulate',
					'description' => 'Run simulation, will not make any modifications to the system.',
				)),
				'submit' => array('submit', array(
					'label' => 'Continue',
				)),
			)
		));
		
		if(isPost() && $form->isValid($_POST)) {
			$simulate = $form->simulate->isChecked();
			foreach($upgrade as $packageName) {
				$status = Curry_PackageManager::upgradePackage($packageName, $simulate);
				if($status)
					$this->addMessage($packageName . ' successfully upgraded!', self::MSG_SUCCESS);
				else
					$this->addMessage('There was an error when upgrading ' . $packageName, self::MSG_ERROR);
			}
			
			foreach($install as $packageName) {
				$status = Curry_PackageManager::installPackage($packageName, $simulate);
				if($status)
					$this->addMessage($packageName . ' successfully installed!', self::MSG_SUCCESS);
				else
					$this->addMessage('There was an error when installing ' . $packageName, self::MSG_ERROR);
			}
		} else {
			if(count($upgrade)) {
				$this->addMessage('The following packages will be upgraded:');
				foreach($upgrade as $packageName) {
					$this->addMessage($packageName);
				}
			}
			
			if(count($install)) {
				$this->addMessage('The following packages will be installed:');
				foreach($install as $packageName) {
					$this->addMessage($packageName);
				}
			}
			
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Show remove package.
	 */
	public function showRemove()
	{
		$packageName = $_GET['name'];
		
		// Check dependencies on installed packages
		$depend = array();
		$installedPackages = PackageQuery::create()
			->filterByName($packageName, Criteria::NOT_EQUAL)
			->find();
		foreach($installedPackages as $installedPackage) {
			$dependencies = Curry_PackageManager::getPackageDependencies($installedPackage->getName(), $installedPackage->getVersion());
			foreach($dependencies as $depName => $depVersion) {
				if($depName == $packageName)
					$depend[] = $installedPackage->getName();
			}
		}
		
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'elements' => array(
				'simulate' => array('checkbox', array(
					'label' => 'Simulate',
					'description' => 'Run simulation, will not make any modifications to the system.',
				)),
				'submit' => array('submit', array(
					'label' => 'Continue'
				)),
			)
		));
		
		if(isPost() && $form->isValid($_POST)) {
			$simulate = $form->simulate->isChecked();
			$status = Curry_PackageManager::removePackage($packageName, $simulate);
			if($status)
				$this->addMessage($packageName . ' successfully removed!', self::MSG_SUCCESS);
			else
				$this->addMessage('There was an error when uninstalling ' . $packageName, self::MSG_ERROR);
		} else {
			$this->addMessage('The following package will be removed: ' . $packageName, self::MSG_WARNING);
			if(count($depend))
				$this->addMessage('The following packages depend on ' . $packageName . ': ' . join(', ', $depend), self::MSG_ERROR);
			$this->addMainContent($form);
		}
	}

	/**
	 * View to execute task in seperate request.
	 */
	protected function showExecTask()
	{
		// TODO: make this a POST request
		$task = $_GET['task'];
		$defaultReturnValue = unserialize($_GET['default']);
		$variables = unserialize($_GET['variables']);
		$package = Curry_PackageManager::getPackage($_GET['package'], $_GET['version']);
		$returnValue = $package ? Curry_PackageManager::execTask($package, $task, $defaultReturnValue, $variables) : $defaultReturnValue;
		Curry_Application::returnPartial(serialize($returnValue));
	}
	
	/**
	 * Show package grid json.
	 */
	public function showJson()
	{
		$entries = array();
		foreach(Curry_PackageManager::getPackages() as $packageInfo) {
			$packageInfo = array_shift($packageInfo);
			$installedPackage = PackageQuery::create()->findPk($packageInfo['name']);
			$hasUpgrade = $installedPackage ? version_compare($installedPackage->getVersion(), $packageInfo['version']) < 0 : false;
			$icon = '<img src="shared/images/icons/'.($hasUpgrade ? 'package_go' : 'package').'.png" alt="" title="'.($hasUpgrade ? 'You can upgrade this package' : 'This package is up to date').'" /> ';
			$entries[] = array(
				'id' => $packageInfo['name'],
				'cell' => array(
					$icon,
					$packageInfo['name'],
					($installedPackage ? $installedPackage->getVersion() : 'Not installed'),
					$packageInfo['version'],
					isset($packageInfo['filesize']) ? Curry_Util::humanReadableBytes($packageInfo['filesize']) : 'n/a',
					$packageInfo['summary'],
				)
			);
		}
		
		$this->returnJson(array(
			'page' => 1,
			'total' => count($entries),
			'rows' => $entries,
		));
	}

	/**
	 * Show repositories grid.
	 */
	public function showRepositories()
	{
		$this->showGrid('Repository');
	}
	
	/**
	 * Override repository grid.
	 */
	public function getGrid($modelClass = null, $options = array()) {
		$grid = parent::getGrid($modelClass, $options);
		if($modelClass === 'Repository') {
			$grid->setOption(array('title' => 'Repositories'));
		}
		return $grid;
	}
}
