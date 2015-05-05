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
namespace Curry\Tree;

/**
 * Tree view based on filesystem.
 * 
 * @package Curry\Tree
 */
class FilesystemTree extends Tree {
	/**
	 * Top node path.
	 *
	 * @var string
	 */
	protected $root;

	/**
	 * @var callable
	 */
	protected $iteratorFunction;

	/**
	 * Constructor
	 *
	 * @param string $root
	 * @param array $options
	 */
	public function __construct($root, array $options = array())
	{
		$this->setRoot($root);
		parent::__construct($options);
	}
	
	/**
	 * Set root path.
	 *
	 * @param string $value
	 */
	public function setRoot($value)
	{
		$this->root = $value;
	}

	/**
	 * @param callable $iteratorFunction
	 */
	public function setIteratorFunction($iteratorFunction)
	{
		$this->iteratorFunction = $iteratorFunction;
	}

	/**
	 * @return callable
	 */
	public function getIteratorFunction()
	{
		return $this->iteratorFunction;
	}

	/**
	 * Get root path.
	 *
	 * @return string
	 */
	public function getRoot()
	{
		return $this->root;
	}

	protected function getIterator($path)
	{
		return $this->iteratorFunction ?
			call_user_func($this->iteratorFunction, $path) :
			new \FilesystemIterator($path);
	}
	
	/**
	 * Default callback to get node properties for path.
	 *
	 * @param string $path
	 * @param Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function objectToJson($path, Tree $tree, $depth = 0)
	{
		if($path === null)
			$path = '';
		$rpath = realpath($this->root . '/' . $path);
		
		$icon = is_dir($rpath) ? 'icon-folder-open' : 'icon-file';
		
		$children = array();
		$dirs = array();
		$files = array();
		if(is_dir($rpath)) {
			$di = $this->getIterator($rpath);
			foreach($di as $file) {
				$fn = $file->getFilename();
				if($fn{0} == '.')
					continue;
				if($file->isDir())
					$dirs[$fn] = call_user_func($this->nodeCallback, ($path ? ($path . '/') : '') . $fn, $this, $depth + 1);
				else
					$files[$fn] = call_user_func($this->nodeCallback, ($path ? ($path . '/') : '') . $fn, $this, $depth + 1);
					
				if((count($dirs) + count($files)) > 15 && $depth > 1) {
					$children = null;
					break;
				}
			}
			if($children !== null) {
				array_multisort(array_map('strtolower', array_keys($dirs)), $dirs);
				array_multisort(array_map('strtolower', array_keys($files)), $files);
				$children = array_merge(array_values($dirs), array_values($files));
			}
		}
		
		$p = array(
			'title' => basename($rpath),
			'iconClass' => $icon,
			'key' => $path,
			'isFolder' => is_dir($rpath),
		);
		
		if($children !== null)
			$p['children'] = $children;
			
		return $p;
	}
}
