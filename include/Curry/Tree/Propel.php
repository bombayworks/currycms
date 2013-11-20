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
 * Create tree from Propel nested set.
 * 
 * @package Curry
 */
class Curry_Tree_Propel extends Curry_Tree {
	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected $model;
	
	/**
	 * Query object.
	 *
	 * @var ModelCriteria
	 */
	protected $query;
	
	/**
	 * Lazy loading?
	 *
	 * @var bool
	 */
	protected $lazy = false;
	
	/**
	 * Constructor
	 *
	 * @param ModelCriteria|string $modelOrQuery A query object, or model class as string.
	 * @param array $options
	 */
	public function __construct($modelOrQuery, array $options = array())
	{
		if(is_string($modelOrQuery)) {
			$this->model = $modelOrQuery;
			$this->query = PropelQuery::from($this->model);
		} else if($modelOrQuery instanceof ModelCriteria) {
			$this->query = $modelOrQuery;
			$this->model = $this->query->getModelName();
		} else {
			throw new Exception('Invalid argument');
		}
		parent::__construct($options);
	}
	
	/**
	 * Lazy load nodes.
	 *
	 * @param bool $value
	 */
	public function setLazy($value)
	{
		$this->lazy = $value;
	}
	
	/**
	 * Get node properties from propel object.
	 *
	 * @param mixed|BaseObject $instance
	 * @param Curry_Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function objectToJson($instance, Curry_Tree $tree, $depth = 0)
	{
		$p = array(
			'title' => (string)$instance,
			'key' => (string)$instance->getPrimaryKey(),
		);
		if($instance->hasChildren()) {
			$p['children'] = array();
			if($this->lazy)
				$p['isLazy'] = true;
		}
		return $p;
	}
	
	/**
	 * Internal helper function to get node with children.
	 *
	 * @param BaseObject $instance
	 * @return array
	 */
	protected function _objectToJson(BaseObject $instance)
	{
		$nodes = $this->lazy ? $instance->getChildren($this->query) : $instance->getDescendants($this->query);
		if($nodes instanceof PropelCollection)
			$nodes = $nodes->getArrayCopy();
		array_unshift($nodes, $instance);
		$level = array();
		foreach($nodes as $node) {
			$p = call_user_func($this->nodeCallback, $node, $this, $node->getTreeLevel());
			$level[$node->getLevel()] = &$p;
			if(isset($level[$node->getLevel()-1]))
				$level[$node->getLevel()-1]['children'][] = &$p;
			unset($p);
		}
		return $level[$instance->getLevel()];
	}
	
	/**
	 * Move node callback.
	 *
	 * @param array $params
	 * @return bool
	 */
	public function actionMove($params)
	{
		$nodes = PropelQuery::from($this->model)->findPks($params['node_id']);
		$parent = PropelQuery::from($this->model)->findPk($params['parent_id']);
		$after = null;
		if($params['after'] !== 'null') {
			$after = PropelQuery::from($this->model)
				->childrenOf($parent)
				->orderByBranch()
				->offset($params['after'])
				->findOne();
		}
		if(!$parent)
			throw new Exception('Unable to find parent');
		foreach($nodes as $node) {
			// Move node
			if($after)
				$node->moveToNextSiblingOf($after);
			else
				$node->moveToFirstChildOf($parent);
			// Continue inserting after this node
			$after = $node;
		}
		return true;
	}
	
	/**
	 * Delete node callback.
	 *
	 * @param array $params
	 * @return bool
	 */
	public function actionDelete($params)
	{
		$node = PropelQuery::from($this->model)->findPk($params['node_id']);
		if(!$node)
			throw new Exception('Unable to find instance to remove');
		$node->delete();
		return true;
	}

	/**
	 * Get json response.
	 *
	 * @return mixed
	 */
	public function getJson()
	{
		if(isPost() && isset($_POST['action']))
			return $this->runAction($_POST['action']);
		
		if(!isset($_GET['key'])) {
			$root = PropelQuery::from($this->model)->findRoot();
			return $root ? array($this->_objectToJson($root)) : array();
		} else {
			$parent = PropelQuery::from($this->model)->findPk($_GET['key']);
			if(!$parent)
				return array();
			$p = $this->_objectToJson($parent);
			return $p['children'];
		}
	}
}
