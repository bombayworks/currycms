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
use Curry\Controller\Frontend;
use Curry\Util\ArrayHelper;
use Curry\Util\Helper;
use Curry\Util\Html;
use Curry\Util\StringHelper;
use Curry\Util\Flash;
use Curry\Util\PathHelper;

/**
 * This module allows you to browse the filesystem.
 *
 * @package Curry\Backend
 */
class Curry_Backend_FileBrowser extends \Curry\Backend\AbstractLegacyBackend
{
	/** {@inheritdoc} */
	public function getName()
	{
		return "Files";
	}

	/**
	 * Constructor
	 */
	public function __construct(\Curry\App $app)
	{
		parent::__construct($app);
		$this->rootPath = $this->app->config->curry->wwwPath.DIRECTORY_SEPARATOR;
	}
	
	/**
	 * Check if path is writable.
	 *
	 * @param string $path
	 * @return bool
	 */
	public static function isWritable($path)
	{
		if(!is_writable($path))
			return false;
		
		if(ini_get('safe_mode')) {
			if(ini_get('safe_mode_gid') ? getmygid() != filegroup($path) : getmyuid() != fileowner($path)) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Get list of roots accessible for the logged in user.
	 *
	 * @return array
	 */
	public static function getRoots()
	{
		$user = User::getUser();
		$role = $user->getUserRole();
		$fbas = FilebrowserAccessQuery::create()
			->condition('user_null', 'FilebrowserAccess.UserId IS NULL')
			->condition('role_null', 'FilebrowserAccess.UserRoleId IS NULL')
			->filterByUser($user)
			->_or()
			->filterByUserRole($role)
			->_or()
			->where(array('user_null','role_null'), Criteria::LOGICAL_AND)
			->find();
		
		$roots = array();
		foreach($fbas as $fba) {
			$path = $fba->getPath();
			$realpath = realpath(\Curry\App::getInstance()->config->curry->wwwPath . DIRECTORY_SEPARATOR . $fba->getPath());
			if($realpath) {
				$roots[$fba->getName()] = array(
					'path' => $path,
					'realpath' => $realpath,
					'writable' => $fba->getWrite() && self::isWritable($realpath),
				);
			}
		}
		return $roots;
	}
	
	/**
	 * Map a virtual path to a physical.
	 * 
	 * A virtual path starts with the name of the root instead of the actual path.
	 *
	 * @param string $virtual
	 * @return string|null
	 */
	public static function virtualToPhysical($virtual)
	{
		$parts = explode('/', $virtual);
		$root = array_shift($parts);
		$path = array();
		foreach($parts as $part) {
			if($part == '' || $part == '.' || $part == '..')
				throw new Exception('Invalid path');
			$path[] = $part;
		}
		$path = count($path) ? '/' . join('/', $path) : '';
		
		$roots = self::getRoots();
		if(isset($roots[$root])) {
			return $roots[$root]['realpath'] . $path;
		}
		return null;
	}
	
	/**
	 * Map a virtual path to a public path.
	 *
	 * @param string $virtual
	 * @return string|null
	 */
	public static function virtualToPublic($virtual)
	{
		$physical = self::virtualToPhysical($virtual);
		if($physical !== null)
			return self::physicalToPublic($physical);
		return null;
	}
	
	/**
	 * Map a public path to a virtual path.
	 *
	 * @param string $public
	 * @return string
	 */
	public static function publicToVirtual($public)
	{
		$roots = self::getRoots();
		foreach($roots as $name => $root) {
			$dir = $root['path'] === '' ? '' : $root['path'] . '/';
			if(substr($public, 0, strlen($dir)) == $dir) {
				$path = $name . '/' . substr($public, strlen($dir));
				if(substr($path, -1) == '/')
					$path = substr($path, 0, -1);
				return $path;
			}
		}
		return null;
	}
	
	/**
	 * Map physical path to public path.
	 *
	 * @param string $physical
	 * @return string
	 */
	public static function physicalToPublic($physical)
	{
		$www = \Curry\App::getInstance()->config->curry->wwwPath;
		if(substr($physical, 0, strlen($www)) == $www)
			return substr($physical, strlen($www) + 1);
		throw new Curry_Exception('Unable to map "'.$physical.'" to public path');
	}

	/**
	 * Check if physical path is writable based on FilebrowserAccess.
	 *
	 * @param string $physical
	 * @return bool
	 */
	public static function isPhysicalWritable($physical)
	{
		$roots = self::getRoots();
		foreach($roots as $root) {
			if ($root['writable'] && StringHelper::startsWith($physical.'/', $root['realpath'].'/'))
				return true;
		}
		return false;
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMainContent($this->getFinder());
		$this->addMainContent('<script>$(".finder").finder();</script>');
	}
	
	/**
	 * Main view.
	 *
	 * @return string
	 */
	public function getFinder()
	{
		if(isPost() && isset($_REQUEST['action'])) {
			try {
				// call instance method
				$method = 'action'.$_REQUEST['action'];
				if(!method_exists($this, $method))
					throw new Curry_Exception('Action does not exist.');
				$contentType = isset($_GET['iframe']) ? 'text/html' : 'application/json';
				self::returnJson($this->$method($_REQUEST), "", $contentType);
			}
			catch(Exception $e) {
				if(isAjax())
					self::returnJson( array('status'=>0, 'error'=>$e->getMessage()) );
				else
					$this->addMessage($e->getMessage(), self::MSG_ERROR);
			}
		}
		
		$template = Curry_Twig_Template::loadTemplateString(<<<TPL
{% spaceless %}
<div class="finder">
  {% if selection %}
  <input type="hidden" name="selection" value="{{selection}}" />
  {% endif %}
  <div class="finder-overlay"><p></p></div>
  <div class="wrapper">
  {% for path in paths %}
  <ul class="folder {{path.IsRoot?'root':''}}" data-finder='{"path":"{{path.Path}}","action":"{{path.UploadUrl}}"}'>
    {% for file in path.files %}
    <li class="{{(file.IsFolder?'is-folder':'is-file')~(file.IsSelected?' selected':(file.IsHighlighted?' highlighted':''))}}"><a href="{{file.Url}}" class="navigate" data-finder='{"name":"{{file.Name}}","path":"{{file.Path}}"}'><span class="{{file.Icon}}"></span>{{file.Name}}</a></li>
    {% endfor %}
  </ul>
  {% endfor %}
  {% if fileInfo %}
  <ul class="fileinfo">
    {% for Key,Value in fileInfo %}
    <li class="fileinfo-{{Key|lower}}">{{Value|raw}}</li>
    {% endfor %}
  </ul>
  {% endif %}
  </div>
  <div class="btn-toolbar">
    <div class="btn-group">
      {% for action in actions %}
      <a href="{{action.Action}}" class="btn {{action.Class}}" data-finder='{{action.Data ? action.Data|json_encode : ''}}'>{{action.Label}}</a>
      {% endfor %}
    </div>
    <select></select>
    <div class="btn-group">
      <button class="btn cancel">Cancel</button>
      <button class="btn btn-primary select" {{selection?'':'disabled=""'}}>Select</button>
    </ul>
  </div>
</div>
{% endspaceless %}
TPL
);
		$vars = array();
		$selected = (array)$_GET['path'];
		if($_GET['public'] == 'true') {
			$virtual = array();
			foreach($selected as $s)
				$virtual[] = self::publicToVirtual($s);
			$selected = $virtual;
		}

		// Verify selection and show selection info
		if(count($selected)) {
			try {
				$vars['fileInfo'] = $this->getFileInfo($selected);
				$selection = array();
				foreach($selected as $s) {
					$physical = self::virtualToPhysical($s);
					$public = self::virtualToPublic($s);
					$selection[] = $public;
					if(isset($_GET['type'])) {
						if($_GET['type'] == 'folder' && !is_dir($physical)) {
							$selection = false;
							break;
						}
						if($_GET['type'] == 'file' && !is_file($physical)) {
							$selection = false;
							break;
						}
					}
				}
				if($selection)
					$vars['selection'] = join(PATH_SEPARATOR, $selection);
			}
			catch (Exception $e) {
				$selected = array();
			}
		}

		// Show actions
		if($selected && $selected[0]) {
			$vars['actions'] = array(
				array(
					'Label' => 'Download',
					'Action' => (string)url('', array('module','view'=>'Download','path'=>$selected)),
				),
			);
			if ($this->isPhysicalWritable(self::virtualToPhysical($selected[0]))) {
				$vars['actions'][] = array(
					'Label' => 'Upload',
					'Action' => (string)url('', array('module','view','path'=>$selected[0],'action'=>'Upload')),
					'Class' => 'upload',
				);
				$vars['actions'][] = array(
					'Label' => 'Delete',
					'Action' => (string)url('', array('module','view','path'=>$selected,'action'=>'Delete')),
					'Class' => 'delete',
				);
				$vars['actions'][] = array(
					'Label' => 'Create directory',
					'Action' => (string)url('', array('module','view','path'=>$selected[0],'action'=>'CreateDirectory')),
					'Class' => 'create-directory',
				);
				if(count($selected) == 1) {
					$vars['actions'][] = array(
						'Label' => 'Rename',
						'Action' => (string)url('', array('module','view','path'=>$selected[0],'action'=>'Rename')),
						'Class' => 'rename',
						'Data' => array('name' => basename($selected[0])),
					);
				}
			}
		}
		$vars['paths'] = self::getPaths($selected);
		$content = $template->render($vars);
		if(isAjax()) {
			self::returnJson(array(
				'content' => $content,
				'maxUploadSize' => Helper::computerReadableBytes(get_cfg_var('upload_max_filesize')),
				'path' => $selected,
			));
		} else {
			return $content;
		}
		return '';
	}
	
	/**
	 * Delete file.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionDelete($params)
	{
		$paths = (array)$params['path'];
		foreach($paths as $path) {
			$path = self::virtualToPhysical($path);
			if(!file_exists($path))
				throw new Exception('The file to delete could not be found.');
			self::trashFile($path);
		}
		return array('status'=>1);
	}
	
	/**
	 * Move file.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionMove($params)
	{
		$overwrite = $params['overwrite'];
		$destinationPath = self::virtualToPhysical($params['destination']);
		if(!file_exists($destinationPath))
			throw new Exception('Destination path could not be found.');
		
		$conflicted = array();
		$move = array();
		$paths = (array)$params['path'];
		foreach($paths as $path) {
			$path = self::virtualToPhysical($path);
			$destination = $destinationPath . DIRECTORY_SEPARATOR . basename($path);
			if(is_dir($path) && strpos($destinationPath.'/', $path.'/') === 0)
				throw new Exception('Invalid operation: Unable to move folder inside self.');
			if(file_exists($destination)) {
				if(!$overwrite) {
					$conflicted[] = basename($path);
					continue;
				}
				self::trashFile($destination);
			}
			$move[$path] = $destination;
		}
		
		if(count($conflicted))
			return array('status' => 0, 'error' => 'File already exist: ' . join(', ', $conflicted), 'overwrite' => true);
			
		foreach($move as $source => $destination) {
			rename($source, $destination);
		}
		
		return array('status' => 1);
	}
	
	/**
	 * Download file.
	 */
	public function showDownload()
	{
		$paths = (array)$_GET['path'];
		$zip = count($paths) > 1;
		$physicals = array();
		foreach($paths as $path) {
			$physical = self::virtualToPhysical($path);
			$physicals[] = $physical;
			if(is_dir($physical))
				$zip = true;
		}
		
		$name = 'files.zip';
		if(count($physicals) === 1)
			$name = basename($physicals[0]) . ($zip ? '.zip' : '');
		
		if($zip) {
			require_once 'pclzip/pclzip.lib.php';
			$tempfile = tempnam($this->app->config->curry->tempPath, "curry-download");
			$archive = new PclZip($tempfile);
			if(!$archive->create($physicals, PCLZIP_OPT_REMOVE_PATH, dirname($physicals[0]))) {
				$this->addMessage('Unable to create zip.');
				if(file_exists($tempfile))
					@unlink($tempfile);
			} else {
				self::returnFile($tempfile, 'application/octet-stream', $name, false);
				@unlink($tempfile);
				exit;
			}
		} else {
			self::returnFile($physicals[0], 'application/octet-stream', $name);
		}
	}
	
	/**
	 * Filter filename to only include "recommended" characters.
	 *
	 * @param string $filename
	 * @return string
	 */
	public static function filterFilename($filename)
	{
		$filename = iconv('utf-8', 'ASCII//TRANSLIT', $filename);
		$filename = preg_replace('/\s+/', '_', $filename);
		$filename = preg_replace('/([^a-z0-9._-]+)/i', '-', $filename);
		return $filename;
	}
	
	/**
	 * Create directory.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionCreateDirectory($params)
	{
		$name = $params['name'];
		$path = self::virtualToPhysical($params['path']);
		if (!self::isPhysicalWritable($path))
			throw new Exception('Access denied');
		if(is_file($path))
			$path = dirname($path);
		$path .= '/'.$name;
		
		if(file_exists($path))
			throw new Exception('Directory "'.$name.'" already exists.');
		
		if(!mkdir($path))
			throw new Exception('Unable to create directory');
			
		return array('status' => 1);
	}
	
	/**
	 * Rename file.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionRename($params)
	{
		$name = $params['name'];
		$path = self::virtualToPhysical($params['path']);
		if (!self::isPhysicalWritable($path))
			throw new Exception('Access denied');
		$target = dirname($path).'/'.$name;
		
		if(!file_exists($path))
			throw new Exception('Source does not exist');
			
		if(file_exists($target))
			throw new Exception('Destination already exists.');

		if(!rename($path, $target))
			throw new Exception('Unable to rename file');

		return array('status' => 1);
	}
	
	/**
	 * Upload file.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionUpload($params)
	{
		if(!isset($_FILES['file']))
			throw new Exception('No file to upload.');
		
		$result = array(
			'status' => 1,
			'overwrite' => array(),
			'uploaded_virtual' => array(),
			'uploaded_public' => array(),
		);
		$virtualPath = $params['path'];
		if($params['public'] == 'true')
			$virtualPath = self::publicToVirtual($virtualPath);
		$targetPath = self::virtualToPhysical($virtualPath);
		if (!self::isPhysicalWritable($targetPath))
			throw new Exception('Access denied');
		if(is_file($targetPath)) {
			$virtualPath = dirname($virtualPath);
			$targetPath = dirname($targetPath);
		}
		
		$overwrite = array();
		foreach((array)$_FILES['file']['error'] as $key => $error) {
			if($error)
				throw new Exception('Upload error: '.Helper::uploadCodeToMessage($error));
			$name = self::filterFilename($_FILES['file']['name'][$key]);
			$source = $_FILES['file']['tmp_name'][$key];
			$target = $targetPath . '/' . $name;
			if(file_exists($target)) {
				$targetHash = sha1_file($target);
				$sourceHash = sha1_file($source);
				if($targetHash !== $sourceHash) {
					$result['overwrite'][] = $name;
					$result['status'] = 0;
					$overwrite[$name] = array(
						'target' => $target,
						'temp' => $sourceHash,
					);
					$target = $this->app->config->curry->tempPath . DIRECTORY_SEPARATOR . $sourceHash;
					move_uploaded_file($source, $target);
					continue;
				}
			} else {
				move_uploaded_file($source, $target);
			}
			$result['uploaded_virtual'][] = $virtualPath . '/' . $name;
			$result['uploaded_public'][] = self::physicalToPublic($target);
		}
		$ses = new \Zend\Session\Container(__CLASS__);
		$ses->uploadOverwrite = $overwrite;
		return $result;
	}
	
	/**
	 * Upload pending overwrites.
	 *
	 * @param array $params
	 * @return array
	 */
	public function actionUploadOverwrite($params)
	{
		$files = (array)$params['overwrite'];
		$ses = new \Zend\Session\Container(__CLASS__);
		$sessionFiles = (array)$ses->uploadOverwrite;
		foreach($files as $name => $overwrite) {
			if(!isset($sessionFiles[$name]))
				throw new Exception('File to overwrite not found in session');
			$sessionFile = $sessionFiles[$name];
			$tempFile = $this->app->config->curry->tempPath . DIRECTORY_SEPARATOR . $sessionFile['temp'];
			if($overwrite === 'true') {
				if(file_exists($sessionFile['target']))
					self::trashFile($sessionFile['target']);
				rename($tempFile, $sessionFile['target']);
			} else {
				unlink($tempFile);
			}
		}
		$ses->uploadOverwrite = null;
		return array('status'=>1);
	}
	
	/**
	 * Get paths based on selection.
	 *
	 * @param array $selected
	 * @return array
	 */
	public function getPaths($selected)
	{
		$path = $selected[0];
		$roots = self::getRoots();
		$parts = explode('/', $path);
		$root = array_shift($parts);
		
		$pp = array(
			'Path' => '',
			'IsRoot' => true,
			'UploadUrl' => (string)url('', array('module','path'=>'','action'=>'Upload')),
			'files' => array(),
		);
		foreach($roots as $name => $r) {
			$pp['files'][] = array(
				'Name' => $name,
				'IsFolder' => true,
				'IsHighlighted' => $name == $root,
				'IsSelected' => ($name == $root) && (count($parts) == 0),
				'Url' => (string)url('', array('module','path' => $name)),
				'Path' => $name,
				'Icon' => 'icon-folder-'.($name == $root ? 'open' : 'close'),
			);
		}
		
		$paths = array($pp);
		if(!isset($roots[$root]))
			return $paths;
		
		$rootPath = $roots[$root]['path'];
		$current = '';
		while(1) {
			$currentPath = $rootPath . $current;
			if(!is_dir($this->app->config->curry->wwwPath . DIRECTORY_SEPARATOR . $currentPath))
				break;
			$next = count($parts) ? $parts[0] : '';
			$paths[] = $this->getPathInfo($currentPath, $root . $current, $next, $selected);
			if(!count($parts))
				break;
			$current .= '/' . array_shift($parts);
		}
		
		return $paths;
	}
	
	/**
	 * Get path details.
	 *
	 * @param string $path
	 * @param string $parent
	 * @param array $highlighted
	 * @param array $selected
	 * @return array
	 */
	public function getPathInfo($path, $parent, $highlighted, $selected)
	{
		$p = array(
			'Path' => $parent,
			'IsRoot' => false,
			'UploadUrl' => (string)url('', array('module','path'=>$parent,'action'=>'Upload')),
			'files' => array(),
		);
		
		$realpath = realpath($this->app->config->curry->wwwPath .DIRECTORY_SEPARATOR. $path);
		
		$dit = new DirectoryIterator($realpath);
		foreach($dit as $entry) {
			$name = $entry->getFilename();
			$vpath = $parent.'/'.$name;
			if($name{0} === '.')
				continue;
			if(isset($_GET['filter'])) {
				if($_GET['filter'] == 'folder' && !$entry->isDir())
					continue;
				if($entry->isFile() && !self::matchFilter($_GET['filter'], $name))
					continue;
			}
			$p['files'][] = array(
				'Name' => $name,
				'IsHighlighted' => $name == $highlighted,
				'IsSelected' => in_array($vpath, $selected),
				'IsFolder' => $entry->isDir(),
				'Icon' => $entry->isDir() ? 'icon-folder-'.($name == $highlighted ? 'open' : 'close') : PathHelper::getIconFromExtension(pathinfo($entry->getPathname(), PATHINFO_EXTENSION)),
				'Url' => (string)url('', array('module','path' => $vpath)),
				'Path' => $vpath,
			);
		}
		ArrayHelper::sortOn($p['files'], array('IsFolder', 'Name'), array(ArrayHelper::SORT_PROPERTY|ArrayHelper::SORT_REVERSE, ArrayHelper::SORT_PROPERTY|ArrayHelper::SORT_CASEINSENSITIVE));
		return $p;
	}
	
	/**
	 * Match filename against multiple shell-patterns.
	 *
	 * @param string $filters
	 * @param string $filename
	 * @return bool
	 */
	protected static function matchFilter($filters, $filename)
	{
		foreach(explode(";", $filters) as $filter) {
			if(fnmatch($filter, $filename, FNM_CASEFOLD))
				return true;
		}
		return false;
	}
	
	/**
	 * Get file details.
	 *
	 * @param array $selected
	 * @return array
	 */
	public function getFileInfo($selected)
	{
		if(count($selected) == 1) {
			try {
				$path = $selected[0];
				$physical = self::virtualToPhysical($path);
				$public = self::physicalToPublic($physical);
				
				if(!is_file($physical))
					return null;
				
				$owner = fileowner($physical);
				if(function_exists('posix_getpwuid')) {
					$owner = posix_getpwuid($owner);
					$owner = '<span title="'.$owner['uid'].'">'.htmlspecialchars($owner['name']).'</span>';
				}
				$group = filegroup($physical);
				if(function_exists('posix_getgrgid')) {
					$group = posix_getgrgid($group);
					$group = '<span title="'.$group['gid'].'">'.htmlspecialchars($group['name']).'</span>';
				}
				
				$fi = array(
					'Name' => '<h2>'.basename($physical).'</h2>',
					'Preview' => '',
					'Size' => '<strong>Size: </strong>' . Helper::humanReadableBytes(filesize($physical)),
					'Writable' => '<strong>Writable: </strong>'.(self::isWritable($physical)?'Yes':'No'),
					'Permissions' => '<strong>Permissions: </strong>' . PathHelper::getFilePermissions($physical),
					'Owner' => '<strong>Owner: </strong>' . $owner . ' / ' . $group,
				);
				
				switch(strtolower(pathinfo($physical, PATHINFO_EXTENSION))) {
					case 'jpg':
					case 'gif':
					case 'png':
					case 'bmp':
						$image = getimagesize($physical);
						$fi['Preview'] = '<img src="'.$public.'?'.filemtime($physical).'" alt="" class="preview" />';
						if($image[0] > 240 || $image[1] > 240)
							$fi['Preview'] = '<a href="'.$public.'?'.filemtime($physical).'" target="_blank" class="fullscreen-preview" title="Click to toggle fullscreen">'.$fi['Preview'].'</a>';
						$fi['Dimensions'] = '<strong>Dimension: </strong>'.$image[0].'x'.$image[1];
						if (self::isPhysicalWritable($physical))
							$fi['Actions'] = '<a href="'.url('', array('module','view'=>'PixlrEdit','image'=>$public)).'" class="dialog" onclick="$(this).data(\'dialog\').width = $(window).width() - 20; $(this).data(\'dialog\').height = $(window).height() - 20;" data-dialog=\'{"width":"90%","height":600,"resizable":false,"draggable":false}\'>Edit with Pixlr</a>';
						break;
					case 'ogg':
					case 'ogv':
					case 'mp4':
					case 'webm':
						$fi['Preview'] = '<video src="'.$public.'" class="preview" controls />';
						break;
					case 'mp3':
					case 'oga':
					case 'wav':
						$fi['Preview'] = '<audio src="'.$public.'" class="preview" controls />';
						break;
					case 'swf':
						$size = getimagesize($physical);
						$flash = Flash::embed(Flash::SWFOBJECT_STATIC, $public, $size[0], $size[1], '9.0.0', array());
						$fi['Preview'] = $flash['html'];
						break;
				}
				return $fi;
			}
			catch (Exception $e) {
				$this->app->logger->error($e->getMessage());
			}
		} else {
			$totalSize = 0;
			foreach($selected as $s) {
				$physical = self::virtualToPhysical($s);
				$totalSize += filesize($physical);
			}
			return array(
				'Name' => '<h2>'.count($selected).' files</h2>',
				'Size' => '<strong>Size: </strong>' . Helper::humanReadableBytes($totalSize),
			);
		}
		return null;
	}
	
	/**
	 * Get size of folder contents.
	 *
	 * @param string $folder
	 * @return int
	 */
	protected function getFolderSize($folder)
	{
		$size = 0;
		$it = new RecursiveDirectoryIterator($folder);
		foreach(new RecursiveIteratorIterator($it) as $file) {
			if($file->isFile())
				$size += $file->getSize();
		}
		return $size;
	}

	/**
	 * Move file to trash folder.
	 *
	 * @param string $file
	 */
	protected function trashFile($file)
	{
		$trashPath = $this->app->config->curry->trashPath . DIRECTORY_SEPARATOR;
		if($trashPath) {
			if(!file_exists($trashPath))
				mkdir($trashPath, 0777, true);
			$basename = basename($file);
			$target = $trashPath . $basename;
			if (file_exists($target)) {
				// target already exist, check if they are equal
				if(is_file($file) && filesize($file) == filesize($target) && sha1_file($file) == sha1_file($target)) {
					// file already exists in trash, just delete the file
					unlink($file);
					return;
				} else {
					// file exist in trash but is different, add timestamp
					$basename .= "_" . date("H-i-s");
					$target = $trashPath . $basename;
				}
			}
			rename($file,  $target);
		} else {
			if(is_dir($file))
				rmdir($file);
			else
				unlink($file);
		}
	}
	
	/**
	 * Edit / Save picture via pixlr
	 *
	 * @todo not very clever to send the logintoken to a 3rd party service...
	 */
	public function showPixlrEdit()
	{
		$image = $_GET['image'];
		$physical = self::virtualToPhysical(self::publicToVirtual($image));
		if (!self::isPhysicalWritable($physical))
			throw new Exception('Access denied');

		$user = User::getUser();
		$mtime = filemtime($image);
		$imageUrl = url($image.'?'.$mtime)->getAbsolute();
		$saveParams = array('module', 'view' => 'PixlrSave', 'original' => $image);
		if (!isset($_COOKIE[User::COOKIE_NAME]))
			$saveParams['logintoken'] = $user->getLoginToken(1440);
		$saveParams['digest'] = hash_hmac('sha1', $image, $user->getPassword());
		$saveUrl = url('',$saveParams)->getAbsolute();
		$exitUrl = url('',array('module', 'view' => 'PixlrExit'))->getAbsolute();
		
		$pixlrUrl = url('http://pixlr.com/editor/', array(
			'target' => $saveUrl,
			'exit' => $exitUrl,
			'method' => 'get',
			'image' => $imageUrl,
			'referrer' => $this->app->config->curry->name,
			'title' => basename($image),
		));
		
		$this->addMainContent(Html::tag('iframe', array(
			'frameborder' => 0,
			'style' => 'width: 100%; height: 100%; border: none; display: block;',
			'src' => $pixlrUrl,
		)));
	}
	
	/**
	 * After closing pixlr.
	 */
	public function showPixlrExit()
	{
		$this->addMainContent("You may close this popup now.");
		$this->addCloseScript();
	}
	
	/**
	 * Save callback for pixlr.
	 */
	public function showPixlrSave()
	{
		$original = $_GET['original'];
		$user = User::getUser();
		$digest = hash_hmac('sha1', $original, $user->getPassword());
		if ($_GET['digest'] !== $digest)
			throw new Exception('Invalid digest');

		$physical = self::virtualToPhysical(self::publicToVirtual($original));
		if (!self::isPhysicalWritable($physical))
			throw new Exception('Access denied');

		$target = dirname($physical) . DIRECTORY_SEPARATOR . $_GET['title'] . '.' . $_GET['type'];
		if (dirname($target) !== dirname($physical))
			throw new Exception('Invalid target');

		if (file_exists($target) && $target !== $physical) {
			$this->trashFile($target);
		}

		$contents = file_get_contents($_GET['image']);
		file_put_contents($target, $contents);
		$this->addMainContent('Your image was successfully saved.');
		$this->addCloseScript();
	}

	protected function addCloseScript()
	{
		$this->addMainContent(<<<SCRIPT
<script>
if (window.frameElement) {
	var el = window.frameElement,
		doc = el.ownerDocument,
		win = doc.defaultView,
		$$ = win.$;
	$$(el).closest('.dialog-container').trigger('dialogclose');
	$$(doc).trigger('finder-reload');
}
</script>
SCRIPT
		);
	}
}
