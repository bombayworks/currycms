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
 * Flexigrid adapted for Propel model classes.
 * 
 * @package Curry
 */
class Curry_Flexigrid_Propel extends Curry_Flexigrid {
	/**
	 * Filter search query using SQL LIKE.
	 */
	const SEARCH_LIKE = 'like';
	
	/**
	 * Filter search query using =.
	 */
	const SEARCH_EQUAL = 'equal';
	
	/**
	 * Filter search terms matching all terms.
	 */
	const SEARCH_TERMS_ALL = 'terms_all';
	
	/**
	 * Filter search terms mathing any term.
	 */
	const SEARCH_TERMS_ANY = 'terms_any';
	
	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected $modelClass;
	
	/**
	 * Query used to fetch results.
	 *
	 * @var ModelCriteria
	 */
	protected $query;
	
	/**
	 * TableMap for model.
	 *
	 * @var TableMap
	 */
	protected $tableMap;
	
	/**
	 * Column callbacks.
	 *
	 * @var array
	 */
	protected $callbacks = array();
	
	/**
	 * Column search methods.
	 *
	 * @var array
	 */
	protected $searchMethod = array();
	
	/**
	 * Constructor.
	 *
	 * @param string $modelClass
	 * @param string $url
	 * @param array $options
	 * @param ModelCriteria|null $query
	 * @param string|null $id
	 * @param string|null $title
	 */
	public function __construct($modelClass, $url, $options = array(), $query = null, $id = null, $title = null) {
		// Accept Peerclass instead of modelclass for backwards compatibility
		if(substr($modelClass, -4) == 'Peer') {
			$modelClass = substr($modelClass, 0, -4);
			trace("Deprecated: Use plain modelClass instead of PeerClass for Flexigrid_Propel");
		}
		if($id === null)
			$id = $modelClass . "_flexigrid";
		if($title === null)
			$title = $modelClass."s";

		parent::__construct($id, $title, $url, $options);
		$this->modelClass = $modelClass;
		
		if($query)
			$this->setCriteria($query);
		else
			$this->query = PropelQuery::from($this->modelClass);
		
		$this->tableMap = PropelQuery::from($this->modelClass)->getTableMap();

		$colNames = array_map(create_function('$col', 'return strtolower($col->getName());'),$this->tableMap->getColumns());
		foreach($colNames as $colName) {
			$this->addColumn($colName, ucfirst(str_replace("_", " ", $colName)));
		}
		// Autoset the primary key if it's only one and hide it
		$pkColumns = $this->tableMap->getPrimaryKeys();
		if(count($pkColumns) == 1) {
			$pkColName = strtolower(array_pop($pkColumns)->getName());
			$this->setPrimaryKey($pkColName);
			$this->setColumnOption($pkColName, array('hide' => true));
		}
		if(in_array('sortable', array_keys($this->tableMap->getBehaviors()))) {
			$this->makeSortable();
			$tmp = $this->tableMap->getBehaviors();
			$this->setColumnOption($tmp['sortable']['rank_column'], array('hide' => true));
			$this->setDefaultSort($tmp['sortable']['rank_column']);
			//$this->query->orderByRank();
		}
	}
	
	/**
	 * Set Query/ModelCriteria object to use when fetching results.
	 *
	 * @param ModelCriteria|Criteria $query
	 */
	public function setCriteria($query)
	{
		if($query instanceof ModelCriteria)
			$this->setQuery($query);
		else if($query instanceof Criteria) {
			trace("Deprecated: Use ModelCriteria instead of Criteria for Flexigrid_Propel");
			$q = PropelQuery::from($this->modelClass);
			$q->mergeWith($query);
			$this->setQuery($q);
		} else
			throw new Exception('Invalid query/criteria argument.');
	}

	/**
	 * Set query, and add virtual columns from query.
	 *
	 * @param ModelCriteria $query
	 */
	public function setQuery($query = null)
	{
		$this->query = $query ? clone $query : PropelQuery::from($this->modelClass);
		// Add Virtual Columns
		foreach($this->query->getAsColumns() as $colName => $clause) {
			$this->addColumn($colName, ucfirst(str_replace("_", " ", $colName)));
		}
	}
	
	/**
	 * Add button to export excel document.
	 *
	 * @param string $url
	 */
	public function addExportExcelButton($url) {
		$this->addButton('Export', array('bclass' => 'icon-table', 'onpress' => new Zend_Json_Expr("function(com, grid) {
			var items = $('.trSelected',grid);
			var url = '$url';
			items.each(function(i) {
				url += '&{$this->primaryKey}[]=' + $.data(this, '{$this->primaryKey}');
			});
			top.location.href = url;
		}")));
	}

	/**
	 * Generate Excel document.
	 *
	 * @param string|null $filename
	 * @param bool $headers Include first row with column names in output
	 * @param bool $includeHidden Include hidden columns?
	 */
	public function returnExcel($filename = null, $headers = true, $includeHidden = false) {
		// Send response headers to the browser
		if (!$filename)
			$filename = $this->options['title'] . ".csv";
		header('Content-Type: text/csv' );
		header('Content-Disposition: attachment; filename='.Curry_String::escapeQuotedString($filename));
		$fp = fopen('php://output', 'w');
		
		// Print column headers
		$hidden = array();
		$values = array();
		foreach($this->columns as $opts) {
			$hide = $opts['hide'] && !$includeHidden;
			$hidden[] = $hide;
			if (!$hide)
				$values[] = $opts['display'];
		}
		if ($headers)
			fputcsv($fp, $values);
		
		// Print rows
		$q = $this->getCriteria();
		$q->setFormatter('PropelOnDemandFormatter');
		foreach($q->find() as $obj) {
			$values = array();
			$rowData = $this->getRow($obj);
			foreach($rowData["cell"] as $i => $columnValue) {
				if (!$hidden[$i])
					$values[] = $columnValue;
			}
			fputcsv($fp, $values);
		}
		fclose($fp);
		exit;
	}

	/**
	 * Set callback to get column value.
	 *
	 * @param string $column
	 * @param callback $callback
	 */
	public function setColumnCallback($column, $callback) {
		$this->callbacks[$column] = $callback;
	}

	/**
	 * Run flexigrid commands.
	 */
	protected function runCommands()
	{
		// execute commands before we return the list
		if (!isset($_POST['cmd']))
			return;
		switch($_POST['cmd']) {
			case 'delete':
				if(isset($_POST['id'])) {
					$id = $_POST['id'];
					PropelQuery::from($this->modelClass)
						->findPks(is_array($id) ? $id: array($id))
						->delete();
				}
				break;

			case 'reorder':
				if(isset($_POST['reorder'])) {
					$reorder = array();
					parse_str($_POST['reorder'], $reorder);
					
					if(isset($this->columns['sort_index'])) {
						// OLD SORTING METHOD
						// get objects
						$objs = array();
						foreach($reorder['row'] as $rowId)
							$objs[] = call_user_func(array($this->modelClass.'Peer', 'retrieveByPk'), $rowId);
	
						// get sort indices
						$sortIndices = array();
						foreach($objs as $obj)
							$sortIndices[] = $obj->getSortIndex();
	
						// sort our indices
						sort($sortIndices);
	
						// set new sort indices
						$i = 0;
						foreach($objs as $obj) {
							$obj->setSortIndex( $sortIndices[$i++] );
							$obj->save();
						}
					} else {
						// get ranks
						$objs = PropelQuery::from($this->modelClass)
							->findPks($reorder['row']);
						
						// move all objs with null rank to the bottom
						$ranks = array();
						foreach($objs as $obj) {
							if($obj->getRank() === null) {
								$obj->insertAtBottom();
								$obj->save();
							}
							$ranks[] = $obj->getRank();
						}
						
						// check for duplicate ranks
						$dups = array_filter(array_count_values($ranks), create_function('$a', 'return $a > 1;'));
						if($dups) {
							// Duplicate indices, move to bottom
							$tmp = $this->tableMap->getBehaviors();
							$scope = null;
							if(strtolower($tmp['sortable']['use_scope']) == 'true') {
								// need scope, find from one object
								$scope = PropelQuery::from($this->modelClass)
									->findPk(reset($reorder['row']))
									->getScopeValue();
							}
							foreach($dups as $rank => $f) {
								$objs = PropelQuery::from($this->modelClass)
									->filterByRank($rank, $scope)
									->offset(1)
									->find();
								foreach($objs as $obj) {
									$obj->insertAtBottom();
									$obj->save();
								}
							}
							$ranks = Curry_Array::objectsToArray($objs, null, 'getRank');
						}
						// sort our indices
						sort($ranks);
						// reorder
						PropelQuery::from($this->modelClass)
							->reorder(array_combine($reorder['row'], $ranks));
					}
				}
				break;
		}
	}
	
	/**
	 * Set search method for column.
	 *
	 * @param string $column
	 * @param string $searchMethod
	 */
	public function setColumnSearchMethod($column, $searchMethod)
	{
		$this->searchMethod[$column] = $searchMethod;
	}

	/**
	 * Get search criteria with filters and sorting applied.
	 *
	 * @return ModelCriteria
	 */
	public function getCriteria() {
		// build criteria
		$q = clone $this->query;

		// search field
		if ($_POST['query'] && $_POST['qtype']) {
			$qtype = $_POST['qtype'];
			$query = $_POST['query'];
			if($this->tableMap->hasColumn($qtype)) {
				$field = $this->modelClass.".".$this->tableMap->getColumn($qtype)->getPhpName();
			} elseif (Curry_Propel::hasI18nColumn($qtype, $this->tableMap)) {
				$i18nBehavior = Curry_Propel::getBehavior('i18n', $this->tableMap);
				$q->joinI18n(isset($_GET['locale']) ? $_GET['locale'] : $i18nBehavior['default_locale']);
				$field = "{$this->modelClass}I18n" . '.' . Curry_Propel::getI18nColumn($qtype, $this->tableMap)->getPhpName();
			} else {
				$field = $this->modelClass.".".$qtype;
			}
			$searchMethod = isset($this->searchMethod[$qtype]) ? $this->searchMethod[$qtype] : self::SEARCH_LIKE;
			switch($searchMethod) {
				case self::SEARCH_EQUAL:
					$q->where("$field = ?", $query);
					break;
				case self::SEARCH_LIKE:
					$q->where("$field ".Criteria::LIKE." ?", "%{$query}%");
					break;
				case self::SEARCH_TERMS_ANY:
				case self::SEARCH_TERMS_ALL:
					$terms = preg_split('/\s+/', trim($query));
					$conditions = array();
					foreach($terms as $i => $term) {
						$name = 'c'.$i;
						$q->condition($name, "$field ".Criteria::LIKE." ?", "%{$term}%");
						$conditions[] = $name;
					}
					$q->combine($conditions, $searchMethod == self::SEARCH_TERMS_ALL ? Criteria::LOGICAL_AND : Criteria::LOGICAL_OR);
					break;
				default:
			}
		}

		// sort on field
		if ($_POST['sortname'] && $_POST['sortorder']) {
			$q->clearOrderByColumns();
			try {
				if($this->tableMap->hasColumn($_POST['sortname'])) {
					$field = $this->tableMap->getColumn($_POST['sortname'])->getPhpName();
				}
				else {
					// Is raw PhpName in case of virtualColumns
					$field = $_POST['sortname'];
				}
				if($_POST['sortorder'] == 'asc')
					$q->orderBy($field, CRITERIA::ASC);
				if($_POST['sortorder'] == 'desc')
					$q->orderBy($field, CRITERIA::DESC);
			}
			catch (Exception $e) {
				trace($e);
			}
		}
		return $q;
	}

	/**
	 * Get row properties.
	 *
	 * @param BaseObject $obj
	 * @return array
	 */
	protected function getRow($obj)
	{
		$celldata = array();
		foreach($this->columns as $column => $opts) {
			if(isset($this->callbacks[$column])) {
				$value = call_user_func($this->callbacks[$column], $obj);
			}
			else {
				try {
					if($this->tableMap->hasColumn($column)) {
						$phpName = $this->tableMap->getColumn($column)->getPhpName();
					} else {
						$tmp = $this->query->getAsColumns();
						if(isset($tmp[$column])) {
							$phpName = $column;
						}
					}
					$value = $obj->{'get'.$phpName}();
				}
				catch(Exception $e) {
					throw new Exception("Unable to fetch value from column '$column'.");
				}
			}
			if(is_null($value))
				$value = 'Ø';
			else if(is_bool($value))
				$value = $value ? 'true' : 'false';
			else if(is_array($value))
				$value = join(', ', $value);
			$escape = (!isset($opts['escape']) ? true : (bool)$opts['escape']);
			$value = str_replace(PHP_EOL, "", $value);
			$celldata[] = $escape ? htmlspecialchars($value) : $value;
		}
		return array(
			"id" => "_".$obj->getPrimaryKey(),
			"cell" => $celldata
		);
	}

	/**
	 * Generate JSON ouput.
	 *
	 * @return string
	 */
	public function getJSON()
	{
		$this->runCommands();

		// create pager
		$rows = array();
		$pager = $this->getCriteria()->paginate($_POST['page'], $_POST['rp']);
		foreach($pager as $obj)
			$rows[] = $this->getRow($obj);

		// json encode result
		return json_encode(array(
			"page" => $pager->getPage(),
			"total" => $pager->getNbResults(),
			"rows" => $rows
		));
	}
}
