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
  * Class to manage installation, removal and upgrading of packages.
  */
class Curry_PackageManager {
	/**
	 * List of all packages in all repositories.
	 * 
	 * @var array|null
	 */
	protected static $packages = null;
	
	/**
	 * Installed packages and their versions.
	 * 
	 * @var array|null
	 */
	protected static $installed = null;
	
	/**
	 * Get array of all installed packages.
	 * 
	 * @return array
	 */
	public static function getInstalledPackages()
	{
		if(self::$installed === null) {
			self::$installed = PackageQuery::create()
				->find()
				->toKeyValue('Name', 'Version');
		}
		return self::$installed;
	}
	
	/**
	 * Check if a package (and version) is installed.
	 * 
	 * @param string $name
	 * @param string|null $version
	 * @param string $operator
	 * @return bool
	 */
	public static function isInstalled($name, $version = null, $operator = '>=')
	{
		$installed = self::getInstalledPackages();
		if (!array_key_exists($name, $installed))
			return false;
		return version_compare($version, $installed[$name], $operator);
	}
	
	/**
	 * Get an array of all packages in all repositories.
	 * 
	 * @return array
	 */
	public static function getPackages()
	{
		if(self::$packages === null) {
			$packages = array();
			foreach(RepositoryQuery::create()->find() as $repository) {
				$content = self::getCachedFile($repository->getUrl());
				$packages = array_merge($packages, json_decode(file_get_contents($content), true));
			}
			
			$entries = array();
			foreach($packages as $package) {
				$packageName = $package['name'];
				if(!isset($entries[$packageName]))
					$entries[$packageName] = array();
				$entries[$packageName][] = $package;
					continue;
			}
			
			foreach($entries as $name => $packageInfos)
				uasort($entries[$name], array(__CLASS__, 'comparePackageVersions'));
			
			self::$packages = $entries;
		}
		return self::$packages;
	}
	
	/**
	 * Get package details by name and possibly version.
	 * 
	 * @param string $name
	 * @param string|null $version
	 * @return array|null
	 */
	public static function getPackage($name, $version = null)
	{
		$packages = self::getPackages();
		
		if(!array_key_exists($name, $packages))
			return null;
		$p = $packages[$name];
		if($version == null)
			return array_shift($p);
		foreach($p as $package) {
			if($package['version'] == $version)
				return $package;
		}
		return null;
	}
	
	/**
	 * Get dependencies for package.
	 * 
	 * @param string $name
	 * @param string|null $version
	 * @param bool $recursive
	 * @return array
	 */
	public static function getPackageDependencies($name, $version = null, $recursive = true)
	{
		$dependencies = array();
		$dependencyCheck = array($name => $version);
		while(count($dependencyCheck)) {
			list($depName, $depVersion) = each($dependencyCheck); 
			array_shift($dependencyCheck);
			$depPackage = Curry_PackageManager::getPackage($depName);
			if(!$depPackage)
				throw new Exception('Unable to find package with name '.$depName);
			if($depVersion !== null && version_compare($depPackage['version'], $depVersion, '<'))
				throw new Exception('Required version '.$depVersion.' of package '.$depName.' not found');
			if(isset($depPackage['require'])) {
				foreach($depPackage['require'] as $reqName => $reqVersion) {
					if(!array_key_exists($reqName, $dependencies) || version_compare($reqVersion, $dependencies[$reqName], '>')) {
						$dependencies[$reqName] = $reqVersion;
						if(!in_array($reqName, array('currycms', 'php')))
							$dependencyCheck[$reqName] = $reqVersion;
					}
				}
			}
			if(!$recursive)
				break;
		}
		return $dependencies;
	}
	
	/**
	 * Update cached package info for repositories.
	 */
	public static function updateRepositories()
	{
		foreach(RepositoryQuery::create()->find() as $repository) {
			try {
				self::getCachedFile($repository->getUrl(), true);
				self::log($repository->getUrl().' updated!', Curry_Backend::MSG_SUCCESS);
			}
			catch (Exception $e) {
				self::log($repository->getUrl().' update failed! '.$e->getMessage(), Curry_Backend::MSG_ERROR);
			}
		}
	}
	
	/**
	 * Installs a package.
	 * 
	 * @param string $name
	 * @param bool $simulate
	 * @return bool
	 */
	public static function installPackage($name, $simulate = false)
	{
		$package = Curry_PackageManager::getPackage($name);
		if(!$package)
			throw new Exception('Package not found');
		
		$installedPackage = PackageQuery::create()->findPk($name);
		if($installedPackage)
			throw new Exception('Package already installed');
		
		if(!$simulate && !self::execTask($package, 'preInstall', true))
			return new Exception('Package installation prevented by preInstall hook.');
		
		if(!$simulate) {
			$installedPackage = new Package();
			$installedPackage->setName($package['name']);
			$installedPackage->setVersion($package['version']);
			$installedPackage->save();
		}
		
		$tar = new Curry_Archive(self::getCachedFile($package['source'], true));
		foreach($tar as $tarFile) {
			$file = $tarFile->getPathname();
			try {
				$target = PackageFile::mapFile($file);
			}
			catch(Exception $e) {
				self::log('Skipping: ' . $file);
				continue;
			}
			
			// create directory
			if($tarFile->isDir()) {
				if(!$simulate) {
					if(!file_exists($target))
						mkdir($target, 0777, true);
				}
				continue;
			}
			
			// Extract file
			self::log('Adding: ' . $file, Curry_Backend::MSG_SUCCESS);
			if(!$simulate) {
				if(file_exists($target)) {
					$backupTarget = $target . "." . date("-Ymd_His");
					if(file_exists($backupTarget))
						throw new Exception('Unable to backup existing file.');
					rename($target, $backupTarget);
				}
				$tarFile->extract($target);
				$packageFile = new PackageFile();
				$packageFile->setPackage($installedPackage);
				$packageFile->setFilename($file);
				$packageFile->setChecksum(sha1_file($target));
				$packageFile->save();
			}
		}
		
		if(!$simulate)
			self::execTaskWithReload($package, 'postInstall');
		
		self::$installed = null;
		
		return true;
	}
	
	/**
	 * Upgrade package.
	 * 
	 * @param string $name
	 * @param bool $simulate
	 * @return bool
	 */
	public static function upgradePackage($name, $simulate = false)
	{
		$package = Curry_PackageManager::getPackage($name);
		if(!$package)
			return false;
		
		$installedPackage = PackageQuery::create()->findPk($name);
		if(!$installedPackage)
			return false;
			
		$oldPackage = Curry_PackageManager::getPackage($installedPackage->getName(), $installedPackage->getVersion());
		if(!$oldPackage)
			return false;
			
		// make sure we are trying to install a newer package
		if(version_compare($package['version'], $installedPackage->getVersion()) <= 0)
			return false;
		
		// run preUpgrade task
		if(!$simulate && !self::execTask($package, 'preUpgrade', true, array('fromVersion' => $installedPackage->getVersion(), 'toVersion' => $package['version'])))
			return false;
		
		$diff = '/usr/bin/diff';
		$patch = '/usr/bin/patch';
		$installedFiles = Curry_Array::objectsToArray($installedPackage->getPackageFiles(), 'getFilename');
		$tar = new Curry_Archive($package['source']);
		$oldTar = new Curry_Archive($oldPackage['source']);
		$tempFile = tempnam('/tmp', 'curry');

		foreach($tar as $tarFile) {
			$file = $tarFile->getPathname();
			try {
				$target = PackageFile::mapFile($file);
			}
			catch(Exception $e) {
				self::log('Skipping: ' . $file);
				continue;
			}
			
			// create directory
			if($tarFile->isDir()) {
				if(!$simulate) {
					if(!file_exists($target))
						mkdir($target, 0777, true);
				}
				continue;
			}
			
			// file is already installed?
			if(array_key_exists($file, $installedFiles)) {
				$packageFile = $installedFiles[$file];
				unset($installedFiles[$file]); // do not remove this file
				
				// read checksum of new file
				if($tarFile->getSize() > 102400) {
					$tarFile->extract($tempFile);
					$newChecksum = sha1_file($tempFile);
				} else {
					$newChecksum = sha1($tarFile->getContents());
				}
				
				if(!file_exists($target)) {
					// Installed file is missing
					self::log('Re-installing: '.$file, Curry_Backend::MSG_WARNING);
					if(!$simulate) {
						$tarFile->extract($target);
						$packageFile->setChecksum($newChecksum);
						$packageFile->save();
					}
				} else if($packageFile->getChecksum() == $newChecksum) {
					// File hasnt changed in package, so skip it
					self::log('Unchanged: '.$file);
				} else if($packageFile->fileIsModified()) {
					// Installed file was modified
					self::log('Updating modified: ' . $file, Curry_Backend::MSG_SUCCESS);
					if(!$simulate) {
						$backupFile = $packageFile->backup();
						$tarFile->extract($target);
						$packageFile->setChecksum($newChecksum);
						$packageFile->save();
						
						// Diff
						$oldTar->getFile($file)->extract($tempFile);
						$command = $diff . ' -u ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($backupFile);
						$p = `$command`;
						
						// Patch file
						file_put_contents($tempFile, $p);
						$command = $patch . ' ' . escapeshellarg($target) . ' ' . escapeshellarg($tempFile);
						$d = `$command`;
					}
				} else {
					// file is not modified so we should be able to just delete it and install the new one
					self::log('Updating: '.$file);
					if(!$simulate) {
						unlink($target);
						$tarFile->extract($target);
						$packageFile->setChecksum(sha1_file($target));
						$packageFile->save();
					}
				}
			} else {
				self::log('Adding: ' . $file, Curry_Backend::MSG_SUCCESS);
				if(!$simulate) {
					if(file_exists($target)) {
						// backup file before overwriting
						$backupTarget = $target . "." . date("Ymd_His");
						if(file_exists($backupTarget))
							throw new Exception('Unable to backup existing file.');
						rename($target, $backupTarget);
					}
					
					$tarFile->extract($target);
					$packageFile = new PackageFile();
					$packageFile->setPackage($installedPackage);
					$packageFile->setFilename($file);
					$packageFile->setChecksum(sha1_file($target));
					$packageFile->save();
				}
			}
		}
		
		// remove remaining files in $installedFiles
		foreach($installedFiles as $installedFile) {
			self::log('Remove: ' . $installedFile->getFilename(), Curry_Backend::MSG_WARNING);
			if(!$simulate) {
				if(!$installedFile->fileIsModified())
					$installedFile->backup();
				else
					unlink($installedFile->getRealpath());
				$installedFile->delete();
			}
		}
		
		if(!$simulate) {
			$installedPackage->setVersion($package['version']);
			$installedPackage->save();
		}
		
		if(!$simulate)
			self::execTaskWithReload($package, 'postUpgrade', true, array('fromVersion' => $installedPackage->getVersion(), 'toVersion' => $package['version']));
			
		self::$installed = null;
		
		return true;
	}
	
	/**
	 * Remove package.
	 * 
	 * @param string $name
	 * @param bool $simulate
	 * @return bool
	 */
	public static function removePackage($name, $simulate = false)
	{
		$installedPackage = PackageQuery::create()->findPk($name);
		if(!$installedPackage)
			return false;
		
		$package = Curry_PackageManager::getPackage($installedPackage->getName(), $installedPackage->getVersion());
		if(!$package)
			return false;
		if(!$simulate && !self::execTask($package, 'preRemove', true))
			return false;
		
		foreach($installedPackage->getPackageFiles() as $file) {
			$filename = $file->getRealpath();
			
			if(!file_exists($filename)) {
				self::log('Skipping non-existent file: '.$file->getFilename(), Curry_Backend::MSG_WARNING);
				if(!$simulate)
					$file->delete();
				continue;
			}
			
			self::log('Removing: '.$file->getFilename(), Curry_Backend::MSG_SUCCESS);
			if(!$simulate) {
				if($file->fileIsModified()) {
					$file->backup();
				} else {
					unlink($filename);
				}
				$file->delete();
			}
		}
		
		if(!$simulate) {
			$installedPackage->delete();
			self::execTaskWithReload($package, 'postRemove');
		}
		
		self::$installed = null;
				
		return true;
	}

	
	/**
	 * Execute package task.
	 * 
	 * @param array $package
	 * @param string $task
	 * @param mixed $defaultReturnValue
	 * @param array|null $variables
	 * @return mixed
	 */
	public static function execTask($package, $task, $defaultReturnValue = null, $variables = null)
	{
		if($variables !== null)
			extract($variables);
		if(isset($package['tasks'], $package['tasks'][$task]) && $package['tasks'][$task])
			return call_user_func($package['tasks'][$task]);
		return $defaultReturnValue;
	}
	
	/**
	 * Execute package task in seperate request.
	 * 
	 * @param array $package
	 * @param string $task
	 * @param mixed $defaultReturnValue
	 * @param array|null $variables
	 * @return mixed
	 */
	protected static function execTaskWithReload($package, $task, $defaultReturnValue = null, $variables = null)
	{
		if(isset($package['tasks'], $package['tasks'][$task]) && $package['tasks'][$task]) {
			$url = url('', array(
				'module'=>'Curry_Backend_PackageManager',
				'view'=>'ExecTask',
				'package'=>$package['name'],
				'version'=>$package['version'],
				'task'=>$task,
				'default'=>serialize($defaultReturnValue),
				'variables'=>serialize($variables),
				'logintoken'=> User::getUser()->getLoginToken(60),
			));
			try {
				return unserialize(file_get_contents($url->getAbsolute()));
			}
			catch (Exception $e) {
				self::log("Execution of remote task '$task' failed: ".$e->getMessage(), Curry_Backend::MSG_ERROR);
			}
		}
		return $defaultReturnValue;
	}
	
	/**
	 * Save url to file and return cached path.
	 * 
	 * @param string $url
	 * @param bool $forceUpdate
	 * @return string
	 */
	protected static function getCachedFile($url, $forceUpdate = false)
	{
		$packagesPath = Curry_Util::path(Curry_Core::$config->curry->projectPath, 'data', 'cache', 'packages');
		if(!file_exists($packagesPath))
			mkdir($packagesPath, 0777, true);
		$target = Curry_Util::path($packagesPath, sha1($url));
		if($forceUpdate || !file_exists($target))
			file_put_contents($target, file_get_contents($url));
		return $target;
	}
	
	/**
	 * Sorting function to compare package versions.
	 * 
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	public static function comparePackageVersions($a, $b)
	{
		return version_compare($b['version'], $a['version']);
	}
	
	/**
	 * Log to backend instance.
	 * 
	 * @param string $msg
	 * @param string $class
	 */
	public static function log($msg, $class = Curry_Backend::MSG_NOTICE)
	{
		Curry_Admin::getInstance()->getBackend()->addMessage($msg, $class);
	}
}
