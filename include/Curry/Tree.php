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
 * HTML Tree View, implemented using dynatree javascript.
 * 
 * @link http://code.google.com/p/dynatree/
 * 
 * @package Curry
 */
class Curry_Tree {
	/**
	 * id of tree container.
	 *
	 * @var string
	 */
	protected $id;
	
	/**
	 * Callback to use on each node when fetching nodes using ajax.
	 *
	 * @var callback
	 */
	protected $nodeCallback;
	
	/**
	 * Specifies callback for executing actions through ajax.
	 *
	 * @var array
	 */
	protected $actionCallback = array();
	
	/**
	 * Specifies options used to initialize dynatree.
	 *
	 * @var array
	 */
	protected $options = array();
	
	/**
	 * Constructor
	 *
	 * @param array $options
	 */
	public function __construct(array $options = array())
	{
		$bt = debug_backtrace();
		$this->id = 'tree-'.substr(sha1($bt[0]['file'].':'.$bt[0]['line']), 0, 6);
		$this->nodeCallback = array(__CLASS__, 'objectToJson');
		$this->options['persist'] = true;
		$this->options['cookieId'] = $this->id;
		$this->options['imagePath'] = 'shared/images/icons/';
		$this->options['initAjax'] = array('url' => (string)url('', $_GET)->add(array('json'=>'1')));
		$this->options['onActivate'] = new Zend_Json_Expr('function(node) {
			if(node.data.href)
				window.location.href = node.data.href;
		}');
		$this->options['onLazyRead'] = new Zend_Json_Expr('function(node) {
			node.appendAjax({url: node.tree.options.initAjax.url, data: {"key": node.data.key}});
		}');
		$this->options['onPostInit'] = new Zend_Json_Expr('function(isReloading, isError, xhr, textStatus, errorThrown) {
			this.activateKey(null);
		}');
		$this->nodeCallback = array($this, 'objectToJson');
		$this->setOptions($options);
	}
	
	/**
	 * Set id of tree container.
	 *
	 * @param string $value
	 */
	public function setId($value)
	{
		$this->id = $value;
	}
	
	/**
	 * Get id of tree container.
	 *
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}
	
	/**
	 * Set ajax url.
	 *
	 * @param string $value
	 */
	public function setAjaxUrl($value)
	{
		$this->options['initAjax'] = array('url' => (string)$value);
	}
	
	/**
	 * Set callback function for fetching node properties.
	 *
	 * @param callback $value
	 */
	public function setNodeCallback($value)
	{
		$this->nodeCallback = $value;
	}
	
	/**
	 * Associate a callback with an action.
	 *
	 * @param string $name
	 * @param callback $callback
	 */
	public function setActionCallback($name, $callback)
	{
		$this->actionCallback[$name] = $callback;
	}
	
	/**
	 * Specifies drag-and-drop callback.
	 *
	 * @param callback $callback
	 */
	public function setDndCallback($callback)
	{
		$this->options['dnd'] = array(
			'onDragStart' => new Zend_Json_Expr('function(node) {
				return true;
			}'),
			'autoExpandMS' => 1000,
			'preventVoidMoves' => true,
			// Prevent dropping a parent below it's own child
			'onDragEnter' => new Zend_Json_Expr('function(node, sourceNode) {
				return true;
			}'),
			'onDragOver' => new Zend_Json_Expr('function(node, sourceNode, hitMode) {
				if(node.isDescendantOf(sourceNode))
					return false;
			}'),
			'onDrop' => new Zend_Json_Expr('function(node, sourceNode, hitMode, ui, draggable) {
				$.post(node.tree.options.initAjax.url, {action: "dnd", target: node.data.key, source: sourceNode.data.key, mode: hitMode}, function(result) {
					if(result && result.success) {
						sourceNode.move(node, hitMode);
						node.expand(true);
					}
				});
			}'),
		);
		$this->setActionCallback('dnd', $callback);
	}
	
	/**
	 * Specify dynatree options.
	 *
	 * @param array $options
	 */
	public function setOptions(array $options)
	{
		foreach($options as $key => $value) {
			$methodName = 'set'.$key;
			if(method_exists($this, $methodName))
				$this->{$methodName}($value);
			else
				$this->options[$key] = $value;
		}
	}
	
	/**
	 * Get option by name.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getOption($name)
	{
		return $this->options[$name];
	}
	
	/**
	 * Cast to string, return HTML or JSON.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try {
			if(isset($_GET['json']))
				Curry_Application::returnJson($this->getJson());
			else
				return $this->getHtml();
		}
		catch(Exception $e) {
			return $e->getMessage();
		}
		return '';
	}
	
	/**
	 * Get HTML code to create tree component.
	 *
	 * @return string
	 */
	public function getHtml()
	{
		return <<<JS
<div id="{$this->id}">You need javascript enabled to view this tree.</div>
<script type="text/javascript">
$(document).ready(function () {
	$('#{$this->id}').html('');
	$.require('dynatree', function() {
		{$this->getJavaScript()}
	});
});
</script>
JS;
	}
	
	/**
	 * Get javascript to initialize dynatree component.
	 *
	 * @return string
	 */
	public function getJavaScript()
	{
		$treeConfig = Zend_Json::encode($this->options, false, array('enableJsonExprFinder' => true));
		return "$('#{$this->id}').dynatree($treeConfig);";
	}
	
	/**
	 * Create object from node.
	 *
	 * @param mixed $id
	 * @param Curry_Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function objectToJson($id, Curry_Tree $tree, $depth = 0)
	{
		return array();
	}
	
	/**
	 * Execute action.
	 *
	 * @param string $action
	 * @return string
	 */
	protected function runAction($action)
	{
		// call user callback?
		if(isset($this->actionCallback[$action]))
			return call_user_func($this->actionCallback[$action], $_POST);
		// call instance method
		$method = 'action'.$action;
		if(!method_exists($this, $method))
			throw new Curry_Exception('Action does not exist.');
		return $this->$method($_POST);
	}
	
	/**
	 * Get JSON response.
	 *
	 * @return string
	 */
	public function getJson()
	{
		if(isPost() && isset($_POST['action']))
			return $this->runAction($_POST['action']);
		
		$node = isset($_GET['key']) ? $_GET['key'] : null;
		return call_user_func($this->nodeCallback, $node, $this, 0);
	}
}