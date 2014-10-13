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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * @package Curry\ModelView
 */
class Curry_ModelView_List extends \Curry\View {
	protected $id;
	protected $query;
	protected $columns = array();
	protected $actions = array();
	protected $options = array();

	protected static $defaultOptions = array(
		'title' => 'Untitled',
		'addDefaultActions' => true,
		'hide' => array(),
		'maxPerPage' => 10,
		'sortable' => false,
		'defaultSortColumn' => null,
	);
	protected static $defaultColumnOptions = array(
		//'label' => 'Name',
		//'order' => 1,
		'action' => null,
		'class' => '',
		'hide' => false,
		'sortable' => true,
		'escape' => true,
	);
	protected static $defaultActionOptions = array(
		//'action' => Curry_ModelView_Abstract|callback,
		//'label' => 'Name',
		//'url' => 'path/',
		'class' => '',
		'hide' => false,
		'general' => false,
		'single' => false,
		'multi' => false,
	);

	public function __construct($modelClassOrQuery, array $options = array())
	{
		if(is_string($modelClassOrQuery))
			$query = PropelQuery::from($modelClassOrQuery);
		else if($modelClassOrQuery instanceof ModelCriteria)
			$query = $modelClassOrQuery;
		else
			throw new Curry_Exception('Invalid argument');

		// Set options
		$bt = debug_backtrace();
		$this->id = 'list-'.substr(sha1($bt[0]['file'].':'.$bt[0]['line']), 0, 6);
		$this->setOptions(self::$defaultOptions);
		$this->setQuery($query, true);
		$this->setOptions(array(
			'title' => $this->getDefaultTitle(),
			'url' => null,
			'model' => $this->query->getModelName(),
		));
		// Set options, but delay actions until after default actions has been set
		$actions = null;
		if (array_key_exists('actions', $options)) {
			$actions = $options['actions'];
			unset($options['actions']);
		}
		$this->setOptions($options);
		if ($this->options['addDefaultActions']) {
			$this->addDefaultActions();
		}
		if ($actions !== null) {
			$this->setOptions(array('actions' => $actions));
		}
	}

	public function initialize()
	{
		foreach($this->actions as $name => $action) {
			if (isset($action['action']) && $action['action'] instanceof \Curry\View) {
				$this->addView($name, $action['action']);
			} else if (isset($action['action']) && is_callable($action['action'])) {
				$this->actions[$name]['action'] = $this->addViewFunction($name, $action['action']);
			}
		}
	}

	public function __toString()
	{
		$request = \Curry\App::getInstance()->request;
		if ($request->query->get('json')) {
			throw new \Curry\Exception\ResponseException($this->show($request));
		}
		return $this->getHtml($request->query->all());
	}

	public function show(Request $request)
	{
		$response = new Response(json_encode($this->getJson($request->query->all())));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	public function getModelClass()
	{
		return $this->query->getModelName();
	}

	protected function getDefaultTitle()
	{
		return preg_replace_callback('/(.)([A-Z])/', function($m) { return $m[1].' '.strtolower($m[2]); }, $this->query->getModelName()).'s';
	}

	public function addColumn($name, $options)
	{
		if (isset($this->columns[$name])) {
			if ($options === false) {
				$this->removeColumn($name);
				return;
			} else if (is_array($options))
				Curry_Array::extend($this->columns[$name], $options);
		} else {
			$o = self::$defaultColumnOptions;
			Curry_Array::extend($o, $options);
			if (!isset($o['label']))
				$o['label'] = ucfirst(str_replace('_', ' ', $name));
			if (!isset($o['order']))
				$o['order'] = count($this->columns);
			$this->columns[$name] = $o;
		}

		// Ordering
		$columnOrder = array();
		foreach($this->columns as $column) {
			$columnOrder[] = $column['order'];
		}
		array_multisort($columnOrder, $this->columns);
	}

	public function hasColumn($name)
	{
		return isset($this->actions[$name]);
	}

	public function removeColumn($name)
	{
		unset($this->columns[$name]);
	}

	public function addAction($name, $options)
	{
		if (isset($this->actions[$name])) {
			if ($options === false)
				$this->removeAction($name);
			else if (is_array($options))
				Curry_Array::extend($this->actions[$name], $options);
			return;
		}
		$o = self::$defaultActionOptions;
		Curry_Array::extend($o, $options);
		if (!isset($o['label']))
			$o['label'] = ucfirst(str_replace('_', ' ', $name));
		$this->actions[$name] = $o;
	}

	public function hasAction($name)
	{
		return isset($this->actions[$name]);
	}

	public function removeAction($name)
	{
		unset($this->actions[$name]);
	}

	public function setQuery(ModelCriteria $query, $addColumns = false)
	{
		$this->query = $query;
		if ($addColumns) {
			$this->addPkColumn();
			$this->addQueryColumns();
		}
	}

	protected function addPkColumn()
	{
		$this->addColumn('_pk', array(
			'hide' => true,
			'callback' => array($this, 'getItemKey'),
			'sortable' => false,
			'escape' => false,
		));
		$this->setOptions(array('idColumn' => '_pk'));
	}

	protected function addQueryColumns()
	{
		$tableMap = $this->query->getTableMap();
		foreach($tableMap->getColumns() as $column) {
			if($column->isForeignKey()) {
				continue;
			}
			$name = strtolower($column->getName());
			$this->addColumn($name, array(
				'hide' => $column->isPrimaryKey(),
				'phpName' => $column->getPhpName(),
				'action' => $column->isPrimaryString() ? 'edit' : null,
			));
		}

		// add virtual columns
		foreach($this->query->getAsColumns() as $colName => $clause) {
			$this->addColumn($colName, array(
				'phpName' => $colName,
			));
		}

		$behaviors = $tableMap->getBehaviors();
		if(array_key_exists('sortable', $behaviors)) {
			$rankCol = strtolower($behaviors['sortable']['rank_column']);
			if (isset($this->columns[$rankCol])) {
				$this->addColumn($rankCol, array('hide' => true));
				$this->setOptions(array('defaultSortColumn' => $rankCol));
			}
			$this->setOptions(array('sortable' => array($this, 'sortItems')));
		}

		$i18nTableName = $this->getI18nTableName($tableMap);
		if($i18nTableName !== null && $this->query->getJoin($i18nTableName) !== null) {
			$i18nTableMap = PropelQuery::from($i18nTableName)->getTableMap();
			foreach($i18nTableMap->getColumns() as $column) {
				if ($column->isPrimaryKey())
					continue;
				$this->addColumn(strtolower($column->getName()), array(
					'sort_func' => array($this, 'sortI18nColumn'),
					'phpName' => $column->getPhpName(),
				));
			}
		}
	}

	protected function getI18nTableName($tableMap)
	{
		$behaviors = $tableMap->getBehaviors();
		if (!array_key_exists('i18n', $behaviors))
			return null;
		return str_replace('%PHPNAME%', $tableMap->getPhpName(), $behaviors['i18n']['i18n_phpname']);
	}

	public function sortItems($params)
	{
		// Get primary keys
		$items = $_POST['item'];
		if (!is_array($items))
			throw new Exception('Expected array POST variable `item`.');
		$pks = array();
		foreach($items as $item) {
			$pk = json_decode($item, true);
			if ($pk === null)
				throw new Exception('Invalid primary key for item: '.$item);
			$pks[] = $pk;
		}
		Curry_Propel::sortableReorder($pks, $this->getModelClass());
	}

	protected function addDefaultActions()
	{
		$modelForm = isset($this->options['modelForm']) ? $this->options['modelForm'] : new Curry_ModelView_Form($this->query->getModelName());
		if($modelForm instanceof Curry_Form_ModelForm)
			$modelForm = new Curry_ModelView_Form($modelForm);
		$actions = array(
			'edit' => array(
				'label' => 'Edit',
				'action' => $modelForm,
				'single' => true,
				'class' => 'inline',
				'hide' => true,
			),
			'new' => array(
				'label' => 'Create new',
				'action' => $modelForm,
				'general' => true,
				'class' => 'dialog',
			),
			/*'delete' => array(
				'label' => 'Delete',
				'action' => new Curry_ModelView_Delete($this->query->getModelName()),
				'single' => true,
				'multi' => true,
				'class' => 'inline modelview-delete',
			),*/
		);
		$this->actions = Curry_Array::extend($actions, $this->actions);
	}

	public function sortI18nColumn($query, $colName, $order)
	{
		$column = $this->columns[$colName]['phpName'];
		$i18nTableName = $this->getI18nTableName($this->query->getTableMap());
		$query
			->useQuery($i18nTableName, $i18nTableName."Query")
				->{'orderBy'.$column}($order)
			->endUse();
	}

	public function setDisplay(array $columnNames)
	{
		foreach($this->columns as $name => $column) {
			$this->columns[$name]['hide'] = !in_array($name, $columnNames);
		}
	}

	public function setHide(array $columnNames)
	{
		foreach($this->columns as $name => $column) {
			if (in_array($name, $columnNames))
				$this->columns[$name]['hide'] = true;
		}
	}

	public function setOptions(array $options)
	{
		if(isset($options['actions'])) {
			foreach($options['actions'] as $name => $action) {
				$this->addAction($name, $action);
			}
			unset($options['actions']);
		}
		if(isset($options['columns'])) {
			foreach($options['columns'] as $name => $column) {
				$this->addColumn($name, $column);
			}
			unset($options['columns']);
		}
		foreach($options as $k => $v) {
			if(method_exists($this, 'set'.$k)) {
				$this->{'set'.$k}($v);
			} else if(property_exists($this, $k)) {
				if(is_array($this->$k))
					Curry_Array::extend($this->$k, $v);
				else
					$this->$k = $v;
			} else {
				continue;
			}
			unset($options[$k]);
		}
		Curry_Array::extend($this->options, $options);
	}

	public function getOption($name)
	{
		if (method_exists($this, 'get'.$name))
			return $this->{'get'.$name}();
		else if (property_exists($this, $name))
			return $this->$name;
		return array_key_exists($name, $this->options) ? $this->options[$name] : null;
	}

	public function getHtml($params)
	{
		$options = $this->options;
		if (!isset($options['url']))
			$options['url'] = $this->parent ? $this->url() : (string)(url('', $_GET)->add(array('json'=>true)));

		if ($options['sortable']) {
			$options['sortable'] = 'TODO';
		}

		$options['actions'] = array();
		foreach($this->actions as $name => $action) {
			if (isset($action['action']) && !isset($action['href'])) {
				if (!$action['action'] instanceof \Curry\View)
					throw new Exception("$name action is not of type View");
				$action['href'] = $this->$name->url();
				unset($action['action']);
			}
			$allowed = array('label', 'href', 'class', 'single', 'multi', 'general');
			$options['actions'][$name] = array_intersect_key($action, array_flip($allowed));
		}

		$options['columns'] = array();
		foreach($this->columns as $name => $column) {
			$allowed = array('label', 'sortable', 'escape', 'action', 'hide');
			$options['columns'][$name] = array_intersect_key($column, array_flip($allowed));
		}

		$allowed = array('title', 'url', 'model', 'paginate', 'maxPerPage', 'currentPage', 'numItems', 'sortable', 'quickSearch', 'actions', 'columns', 'idColumn');
		$options = array_intersect_key($options, array_flip($allowed));

		$options = Zend_Json::encode($options, false, array('enableJsonExprFinder' => true));
		return Curry_Html::createTag('div', array('class' => 'modelview', 'data-modelview' => $options));
	}

	public function getJson($params)
	{
		$query = clone $this->query;
		//$this->filterBySelection($query, $params);
		$this->filterByParams($query, $params);
		$this->filterByKeyword($query, $params);
		$this->sort($query, $params);

		$rows = array();
		$items = $this->find($query, $params);
		foreach($items as $obj) {
			$rows[] = $this->getItemValue($obj);
		}

		return $this->getResult($items, $rows);
	}

	protected function getResult($items, $rows)
	{
		return array(
			"page" => $items->getPage(),
			"total" => $items->getNbResults(),
			"rows" => $rows
		);
	}

	protected function getItemKey($obj)
	{
		return json_encode($obj->getPrimaryKey());
	}

	protected function getItemValue($obj)
	{
		$row = array();
		foreach ($this->columns as $column) {
			$row[] = $this->getColumnValue($obj, $column);
		}
		return $row;
	}

	protected function find($query, $params)
	{
		$page = isset($params['p']) ? $params['p'] : 1;
		$pager = $query->paginate($page, $this->options['maxPerPage']);
		return $pager;
	}

	protected function filterByKeyword($query, $params)
	{
		if (isset($params['q'])) {
			$columns = array();
			$peerClass = constant($this->query->getModelName() . '::PEER');
			$phpNames = call_user_func(array($peerClass, 'getFieldNames'));
			foreach ($this->columns as $name => $column) {
				if ($column['phpName']) {
					if (in_array($column['phpName'], $phpNames))
						$columns[] = $this->query->getModelName() . '.' . $column['phpName'];
				}
			}
			array_unshift($columns, '"|"');
			$query->where('CONCAT_WS(' . join(',', $columns) . ') LIKE ?', '%' . $params['q'] . '%');
		}
	}

	protected function sort($query, $params)
	{
		$sortColumn = null;
		if (isset($this->options['defaultSortColumn'])) {
			$sortColumn = $this->options['defaultSortColumn'];
		}
		if (isset($params['sort_column'])) {
			$sortColumn = $params['sort_column'];
		}
		$sortOrder = 'asc';
		if (isset($this->options['defaultSortOrder'])) {
			$sortOrder = $this->options['defaultSortOrder'];
		}
		if (isset($params['sort_order']) && in_array($params['sort_order'], array('asc', 'desc'))) {
			$sortOrder = $params['sort_order'];
		}
		if ($sortColumn !== null) {
			if (!isset($this->columns[$sortColumn]))
				throw new Exception('Column not found: ' . $sortColumn);
			$column = $this->columns[$sortColumn];
			$func = $column['sort_func'];
			if (is_callable($func))
				call_user_func($func, $query, $sortColumn, $sortOrder);
			else
				$query->{'orderBy' . $column['phpName']}($sortOrder);
		}
	}

	protected function filterByParams($query, $params)
	{
		if (isset($params['f'])) {
			$filters = (array)$params['f'];
			foreach ($filters as $column => $filter) {
				$phpName = $this->columns[$column]['phpName'];
				if (!$phpName)
					throw new Exception('Column not found: ' . $column);
				$query->{'filterBy' . $phpName}($filter);
			}
		}
	}

	protected function filterBySelection($query, $params)
	{
		$item = $this->getSelection($params);
		if ($item) {
			$relations = $this->query->getTableMap()->getRelations();
			foreach ($relations as $relation) {
				if ($relation->getRightTable()->getPhpName() == get_class($item) &&
					in_array($relation->getType(), array(RelationMap::MANY_TO_ONE))) {
					$query->{'filterBy' . $relation->getName()}($item);
				}
			}
		}
	}

	protected function getColumnValue($obj, $column)
	{
		if(isset($column['callback'])) {
			$val = call_user_func($column['callback'], $obj);
		} else if(isset($column['display'])) {
			$val = $this->templateToString($column['display'], $obj);
		} else {
			$val = $obj->{'get'.$column['phpName']}();
		}
		if($val === null)
			$val = '-';
		return $val;
	}

	public function templateToString($template, BaseObject $obj)
	{
		$tpl = Curry_Twig_Template::loadTemplateString($template);
		return $tpl->render(Curry_Propel::toTwig($obj));
	}
}
