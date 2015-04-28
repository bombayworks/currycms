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
use Curry\App;
use Curry\Backend\AbstractBackend;
use Curry\Backend\AbstractLegacyBackend;
use Curry\Util\ArrayHelper;
use Curry\Util\Propel;
use Curry\Util\Html;

/**
 * Static helper functions for database backend.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_DatabaseHelper {
	/**
	 * Maximum number of rows to buffer when inserting multiple rows.
	 */
	const MULTIINSERT_MAXBUFFER = 1024;
	
	/**
	 * Maximum line length when reading database dump.
	 */
	const MAX_LINE_LENGTH = 1000000;

	/**
	 * Database dump format version.
	 */
	const VERSION = 1;

	/**
	 * Get primary-key of object as string.
	 * 
	 * @param BaseObject $obj
	 * @return string
	 */
	public static function getObjectPk(BaseObject $obj)
	{
		return json_encode($obj->getPrimaryKey());
	}
	
	/**
	 * Create backup filename with path.
	 * 
	 * @param string $format strftime() formatted string
	 * @return string
	 */
	public static function createBackupName($format)
	{
		$basepath = \Curry\App::getInstance()['projectPath'] . '/data/backup/';
		if(!file_exists($basepath))
			mkdir($basepath, 0777, true);
		
		$name = strftime($format);
		$filename = $basepath.$name;
		return $filename;
	}
	
	/**
	 * Save model object from form.
	 * 
	 * @param BaseObject $row
	 * @param Curry_Form_ModelForm $form
	 */
	public static function saveRow(BaseObject $row, Curry_Form_ModelForm $form)
	{
		$selectCriteria = $row->buildPkeyCriteria(); // gets criteria w/ primary key(s) - these may be modified below:
		
		$form->fillModel($row);
		foreach($form->getElementColumns() as $column) {
			$nullElement = $form->getElement(strtolower($column->getName()).'__null__');
			if($nullElement && $nullElement->getValue())
				$row->{'set'.$column->getPhpName()}(null);
		}
		
		if($row->isModified()) {
			if($row->isNew())
				$row->save();
			else {
				$con = Propel::getConnection(constant($_GET['table'].'Peer::DATABASE_NAME'), Propel::CONNECTION_WRITE);
				$criteria = $row->buildCriteria();
				BasePeer::doUpdate($selectCriteria, $criteria, $con);
			}
		}
	}
	
	/**
	 * Get form from model.
	 * 
	 * @param BaseObject $row
	 * @return Curry_Form_ModelForm
	 */
	public static function getRowForm(BaseObject $row)
	{
		$form = new Curry_Form_ModelForm($_GET['table'], array(
			'ignorePks' => false,
			'ignoreFks' => false,
			'action' => url('', array("module","view","table","pk")),
			'method' => 'post',
			'class' => 'dialog-form',
		));
		$form->fillForm($row);
		
		$displayGroup = 1;
		foreach($form->getElementColumns() as $column) {
			$element = $form->getElement(strtolower($column->getName()));
			if(!$element)
				continue;
			
			// Remove column if it's auto generated
			if($row->isNew() && $column->isPrimaryKey() && $column->getTable()->isUseIdGenerator()) {
				$form->removeElement($element->getName());
				continue;
			}
			
			// Make sure we have an option for foreign keys
			if($column->isForeignKey() && !$row->isNew()) {
				$related = $element->getMultioptions();
				if(!array_key_exists($element->getValue(), $related)) {
					$related[$element->getValue()] = $element->getValue();
					$element->setMultioptions($related);
				}
			}
			
			// Add null checkbox
			if(!$column->isNotNull()) {
				$isNull = $element->getValue() === null;
				if($isNull)
					$element->setAttrib("disabled", "disabled");
				
				if(!$column->isNotNull()) {
					$name = $element->getName().'__null__';
					$form->addElement('checkbox', $name, array(
						'label' => 'Null',
						'title' => 'Is Null?',
						'onclick' => "document.getElementById('".$element->getId()."').disabled = this.checked;",
						'value' => $isNull,
					));
					$form->addDisplayGroup(array($element->getName(), $name), 'displayGroup'.($displayGroup++), array(
						'class' => 'horizontal-group',
						'legend' => $element->getLabel(),
					));
				}
			}
		}
		
		$form->addElement('submit', 'save', array('label' => $row->isNew() ? 'Insert' : 'Save'));
		return $form;
	}
	
	/**
	 * Dump database to file.
	 *
	 * @param string|resource $file
	 * @param array|null $tables
	 * @param AbstractLegacyBackend|null $backend
	 * @return bool	True on success
	 */
	public static function dumpDatabase($file, $tables = null, AbstractLegacyBackend $backend = null)
	{
		$fp = is_string($file) ? fopen($file, "w") : $file;
		$totalRows = 0;
		$error = false;
		
		// write header
		$data = json_encode(array(
			'header' => array(
				'version' => Curry_Backend_DatabaseHelper::VERSION,
				'name' => \Curry\App::getInstance()['name'],
				'curry-version' => App::VERSION,
				'page-version' => defined('Page::VERSION') ? Page::VERSION : 0,
				'date' => date(DATE_RFC822),
			)
		));
		fwrite($fp, $data . "\n");
		
		// write tables
		foreach(Propel::getModels() as $classes) {
			foreach($classes as $table) {
				if(is_array($tables) && !in_array($table, $tables))
					continue;
				$numRows = self::dumpTable($table, $fp, $error, $backend);
				$totalRows += $numRows;
			}
		}
		if($backend)
			$backend->addMessage("Dumped $totalRows rows.");
		
		if(is_string($file))
			fclose($fp);
		
		return !$error;
	}
	
	/**
	 * Dump table to file.
	 * 
	 * @param string $table
	 * @param resource $fp
	 * @param bool $error
	 * @param AbstractLegacyBackend|null $backend
	 * @return int Number of rows dumped.
	 */
	public static function dumpTable($table, $fp, &$error, AbstractLegacyBackend $backend = null)
	{
		$goodRows = 0;
		$totalRows = 0;
		
		try {
			$query = PropelQuery::from($table)
				->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
			$columns = $query->getTableMap()->getColumns();
			foreach($query->find() as $obj) {
				++$totalRows;
				try {
					$row = array();
					foreach($columns as $column)
						$row[$column->getPhpName()] = self::getColumnValue($obj, $column);
					
					// Write serialized data of this row
					$data = array("table" => $table, "values" => $row);
					fwrite($fp, json_encode($data) . "\n");
					++$goodRows;
				}
				catch (Exception $e) {
					if($backend)
						$backend->addMessage('Unable to dump row: '.$e->getMessage(), AbstractBackend::MSG_ERROR);
					$error = true;
				}
			}
			if($backend)
				$backend->addMessage("Dumped $goodRows / $totalRows rows in table $table", $goodRows == $totalRows ? AbstractBackend::MSG_SUCCESS : AbstractBackend::MSG_ERROR);
		}
		catch (Exception $e) {
			if($backend)
				$backend->addMessage('Unable to dump table: '.$e->getMessage(), AbstractBackend::MSG_ERROR);
			$error = true;
		}
		return $goodRows;
	}
	
	/**
	 * Scan table for errors.
	 * 
	 * @param string $table
	 * @param bool $fix
	 * @param bool $delete
	 * @param AbstractLegacyBackend|null $backend
	 * @return int Number of invalid rows.
	 */
	public static function scanTable($table, $fix, $delete, $backend = null)
	{
		$query = PropelQuery::from($table);
		$tableMap = $query->getTableMap();

		$numInvalidRows = 0;
		foreach($query->find() as $obj) {
			if($obj->isDeleted())
				continue;
			
			$objName = 'PK('.join(',', (array)$obj->getPrimaryKey()).')';
			
			// Check all columns for errors
			$error = array();
			foreach($tableMap->getColumns() as $column) {
				$columnName = $column->getPhpName();
				$columnValue = $obj->{'get'.$columnName}();
				
				if($columnValue === null) {
					if($column->isNotNull())
						$error[] = "required $columnName = $columnValue is null";
					continue;
				}
				
				if($column->isForeignKey()) {
					$relObjects = PropelQuery::from($column->getRelatedTable()->getPhpName())
						->filterBy($column->getRelatedColumn()->getPhpName(), $columnValue)
						->limit(1)
						->count();
					if(!$relObjects) {
						if($column->isNotNull()) {
							if($delete) {
								if($backend)
									$backend->addMessage("Deleting $objName (required $columnName was invalid).", AbstractBackend::MSG_WARNING);
								$obj->delete();
								$error = array();
								break; // dont have to check the other columns
							} else {
								$error[] = "required $columnName = $columnValue is invalid";
							}
						} else {
							if($fix) {
								// attempt to fix
								if($backend)
									$backend->addMessage("Fixing $objName (invalid $columnName will be set to null).", AbstractBackend::MSG_WARNING);
								$obj->{'set'.$column->getPhpName()}(null);
								$obj->save();
							} else {
								$error[] = "$columnName = $columnValue is invalid";
							}
						}
					}
				}
			}
			
			if(count($error)) {
				++$numInvalidRows;
				if($backend) {
					// Add message with link to edit row
					$url = (string)url('', array('module' => 'Curry_Backend_Database', 'view' => 'Row', 'table' => $table, 'pk' => self::getObjectPk($obj)));
					$link = Html::tag('a', array('href' => $url, 'title' => 'Edit '.$objName, 'class' => 'dialog'), $objName);
					$backend->addMessage("$link: ".join(', ', $error).'.', AbstractBackend::MSG_WARNING, false);
				}
			}
		}
		return $numInvalidRows;
	}
	
	/**
	 * Repair tables with nested set behaviour.
	 * 
	 * @param string $table
	 * @param bool $fix
	 * @return int Number of invalid nodes.
	 */
	public static function repairNestedSet($table, $fix)
	{
		$errors = 0;
		$query = PropelQuery::from($table);
		$leftCol = constant("{$table}Peer::LEFT_COL");
		$scoped = method_exists($query, 'findRoots');
		$roots = $scoped ? $query->findRoots() : array($query->findRoot());
		foreach($roots as $root) {
			$left = 1; // 1
			$level = 0;
			$root->setLeftValue($left);
			$root->setLevel(0);
			$ancestors = array($level => $root);
			$previous = $root;
			$query = PropelQuery::from($table)
				->addUsingAlias($leftCol, 1, Criteria::NOT_EQUAL) // do not include root
				->orderByBranch();
			if($scoped)
				$query->inTree($root->getScopeValue());
			$descendants = $query->find();
			foreach($descendants as $descendant) {
				$l = $descendant->getLevel();
				if($l > $level) {
					if(($l - $level) > 1) {
						$l = $level + 1;
						$descendant->setLevel($l);
					}
					// moving down the tree
					$descendant->setLeftValue(++$left);
					$level = $l;
				} else if ($l == $level) {
					// same level
					$previous->setRightValue(++$left);
					$descendant->setLeftValue(++$left);
				} else {
					// moving up the tree
					while($level >= $l)
						$ancestors[$level--]->setRightValue(++$left);
					$descendant->setLeftValue(++$left);
					$level = $l;
				}
				$ancestors[$l] = $descendant;
				$previous = $descendant;
			}
			// update parents
			while($level >= 0)
				$ancestors[$level--]->setRightValue(++$left);
			if($root->isModified()) {
				++$errors;
				if($fix)
					$root->save();
			}
			foreach($descendants as $descendant) {
				if($descendant->isModified()) {
					++$errors;
					if($fix)
						$descendant->save();
				}
			}
		}
		return $errors;
	}
	
	/**
	 * Execute SQL statements, and apply callback to result.
	 * 
	 * @param string $sql
	 * @param bool $abortOnError
	 * @param callback|null $stmtCallback
	 */
	public static function runStatements($sql, $abortOnError = true, $stmtCallback = null)
	{
		$connection = Propel::getConnection();
		$parser = new PropelSQLParser();
		$parser->setSQL($sql);
		$parser->convertLineFeedsToUnixStyle();
		$parser->stripSQLCommentLines();
		$statements = $parser->explodeIntoStatements();
		foreach ($statements as $statement) {
			try {
				$stmt = $connection->prepare($statement);
				if (!$stmt) {
					throw new Exception('Failed to create statement');
				}
				$stmt->execute();
				if ($stmtCallback) {
					call_user_func($stmtCallback, $stmt, $statement);
				}
			}
			catch(Exception $e) {
				if ($abortOnError) {
					throw new Curry_Exception('Unable to execute statement: ' . $statement);
				} else if ($stmtCallback) {
					call_user_func($stmtCallback, null, $statement);
				}
			}
		}
	}
	
	/**
	 * Run propel phing commands
	 *
	 * @param string $cmd	phing target
	 * @param array $argv arguments
	 * @return string
	 */
	public static function propelGen($cmd = '', $argv = array())
	{
		$autoloader = App::getInstance()->autoloader;
		$generatorBase = dirname(dirname(dirname($autoloader->findFile('AbstractPropelDataModelTask'))));
		$buildXml = $generatorBase . '/build.xml';
		$projectPath = \Curry\App::getInstance()['projectPath'] . '/propel';

		$argv[] = '-logger';
		$argv[] = 'phing.listener.AnsiColorLogger';
		$argv[] = '-f';
		$argv[] = $buildXml;
		$argv[] = '-Dproject.dir='.$projectPath;
		if ($cmd)
			$argv[] = $cmd;

		$cwd = getcwd();
		$stream = fopen("php://temp", 'r+');
		$outputStream = new OutputStream($stream);
		Phing::setOutputStream($outputStream);
		Phing::setErrorStream($outputStream);
		Phing::startup();
		Phing::fire($argv);
		rewind($stream);
		$content = stream_get_contents($stream);
		Phing::shutdown();
		chdir($cwd);

		if (extension_loaded('apc'))
			@apc_clear_cache();
		
		return $content;
	}

	public static function getPropelGenStatus($content)
	{
		if(preg_match("/\033\[(\d+;)*31(;\d+)*m/", $content))
			return false;
		if(preg_match("/\033\[(\d+;)*35(;\d+)*m/", $content))
			return false;
		return true;
	}
	
	/**
	 * Restore database from file.
	 * 
	 * @todo Fix $maxExecutionTime.
	 *
	 * @param string|resource $file
	 * @param array|null $tables
	 * @param float $maxExecutionTime
	 * @param int $continueLine
	 * @param AbstractLegacyBackend|null $backend
	 * @return bool	True on success, false otherwise.
	 */
	public static function restoreFromFile($file, $tables = null, $maxExecutionTime = 0, $continueLine = 0, AbstractLegacyBackend $backend = null)
	{
		global $CURRY_DATABASE_RESTORE;
		$CURRY_DATABASE_RESTORE = true;
		
		$fp = is_string($file) ? fopen($file, "r") : $file;
		$t = microtime(true);
		$total = 0;
		$skipped = 0;
		$failed = 0;
		$session = new \Zend\Session\Container(__CLASS__);
		
		$con = Propel::getConnection();
		$con->beginTransaction();
		
		$adapter = Propel::getDB();
		if($adapter instanceof DBMySQL)
			$con->exec("SET foreign_key_checks = 0");
		
		// Read header
		$firstline = stream_get_line($fp, self::MAX_LINE_LENGTH, "\n");
		$header = json_decode($firstline, true);
		if(is_array($header) && isset($header['header'])) {
			$header = $header['header'];
			// Check header version
			$version = isset($header['version']) ? (int)$header['version'] : 0;
			if($version > self::VERSION)
				throw new Exception('Unsupported database version. The file you are trying to restore from is from a newer version of currycms.');
			// Check page version
			$pageVersion = isset($header['page-version']) ? (int)$header['page-version'] : 0;
			if ($pageVersion > Page::VERSION) {
				throw new Exception('Unsupported page version. The file you are trying to restore from is from a newer version of currycms.');
			}
			if ($backend)
				$backend->addMessage("Restoring from ".$header['date']);
			if ($pageVersion !== Page::VERSION) {
				if ($backend)
					$backend->addMessage("Migrating data from version $pageVersion to ".Page::VERSION, AbstractBackend::MSG_WARNING);
				Page::preMigrate($pageVersion);
			}
		} else {
			throw new Exception('Invalid header');
		}
		
		// Empty tables
		if($continueLine == 0) {
			foreach(Propel::getModels() as $classes) {
				foreach($classes as $table) {
					try {
						if(is_array($tables) && !in_array($table, $tables))
							continue;
						if(!method_exists($table, 'delete')) {
							if($backend)
								$backend->addMessage("Skipping read-only table: $table", AbstractBackend::MSG_WARNING);
							continue;
						}
						
						$tableName = PropelQuery::from($table)->getTableMap()->getName();
						// use basePeer to avoid foreign key emulation in Normal peer class
						BasePeer::doDeleteAll($tableName, $con);
					}
					catch (Exception $e) {
						throw new Exception('Unable to empty table '.$table.': '.$e->getMessage());
					}
				}
			}
			if($backend)
				$backend->addMessage("Cleared tables in ".round(microtime(true) - $t, 2)."s");
			$t = microtime(true);
		} else {
			$total = $session->total;
			$skipped = $session->skipped;
			$failed = $session->failed;
			if($backend)
				$backend->addMessage("Continuing from line $continueLine.");
			for($i = 0; $i < $continueLine; ++$i) {
				stream_get_line($fp, self::MAX_LINE_LENGTH, "\n");
			}
		}
		
		$currentTable = null;
		$buffer = array();
		while (!feof($fp)) {
			// Read line
			$data = json_decode(stream_get_line($fp, self::MAX_LINE_LENGTH, "\n"), true);
			++$total;
			
			if(is_array($data) && isset($data['table'])) {
				if((is_array($tables) && !in_array($data['table'], $tables)) || !method_exists($data['table'], 'delete')) {
					++$skipped;
					continue;
				}
				// Verify columns for new table
				if($data['table'] !== $currentTable && $currentTable !== null && $backend) {
					$backend->addMessage('Restoring rows for table '.$data['table']);
					$columns = ArrayHelper::objectsToArray(PropelQuery::from($data['table'])->getTableMap()->getColumns(), null, 'getPhpName');
					$added = array_diff($columns, array_keys($data['values']));
					$removed = array_diff(array_keys($data['values']), $columns);
					if(count($added))
						$backend->addMessage('New column(s): '.join(', ', $added), AbstractBackend::MSG_WARNING);
					if(count($removed))
						$backend->addMessage('Removed column(s): '.join(', ', $removed), AbstractBackend::MSG_WARNING);
				}
				// Flush buffer when changing tables
				if($data['table'] !== $currentTable || count($buffer) >= self::MULTIINSERT_MAXBUFFER) {
					if($currentTable !== null && count($buffer))
						Propel::doMultiInsert($currentTable, $buffer);
					$currentTable = $data['table'];
					$buffer = array();
				}
				// Migrate data
				if ($pageVersion !== Page::VERSION) {
					if (!Page::migrateData($data['table'], $data['values'], $pageVersion)) {
						continue;
					}
				}
				$buffer[] = $data['values'];
			} else {
				if($backend)
					$backend->addMessage('Unable to read data on line '.$total, AbstractBackend::MSG_ERROR);
				++$failed;
			}
			// check execution time
			if ($maxExecutionTime && App::getInstance()->getExecutionTime() > $maxExecutionTime) {
				if($currentTable !== null && count($buffer))
					Propel::doMultiInsert($currentTable, $buffer);
				$session->total = $total;
				$session->skipped = $skipped;
				$session->failed = $failed;
				$params = array(
					'module' => 'Curry_Backend_Database',
					'view' => 'ContinueRestore',
					'file' => $file,
					'tables' => $tables,
					'line' => $total,
					'max_execution_time' => $maxExecutionTime,
				);
				AbstractLegacyBackend::redirect(url('', $params)->getAbsolute("&", true));
			}
		}
		
		// Flush buffer
		if($currentTable !== null && count($buffer))
			Propel::doMultiInsert($currentTable, $buffer);

		if ($pageVersion !== Page::VERSION) {
			Page::postMigrate($pageVersion);
		}
		
		if($adapter instanceof DBMySQL)
			$con->exec("SET foreign_key_checks = 1");
		
		$con->commit();
		$CURRY_DATABASE_RESTORE = false;
		
		if($backend) {
			if($skipped)
				$backend->addMessage("Skipped $skipped rows");
			if($failed)
				$backend->addMessage("Failed to add $failed rows", AbstractBackend::MSG_ERROR);
			$backend->addMessage("Added " . ($total - $skipped - $failed) . " / $total rows in ".round(microtime(true) - $t, 2)."s", !$failed ? AbstractBackend::MSG_SUCCESS : AbstractBackend::MSG_ERROR);
		}
		
		if(is_string($file))
			fclose($fp);
			
		return !$failed;
	}
	
	/**
	 * Get value for column to use in database dump.
	 * 
	 * @param BaseObject $obj
	 * @param ColumnMap $column
	 * @return mixed
	 */
	protected static function getColumnValue(BaseObject $obj, ColumnMap $column)
	{
		switch($column->getType()) {
		case PropelColumnTypes::DATE:
			return $obj->{'get'.$column->getPhpName()}('Y-m-d');
			break;
		case PropelColumnTypes::TIMESTAMP:
			return $obj->{'get'.$column->getPhpName()}('Y-m-d H:i:s');
			break;
		case PropelColumnTypes::TIME:
			return $obj->{'get'.$column->getPhpName()}('H:i:s');
			break;
		default:
			return $obj->{'get'.$column->getPhpName()}();
		}
	}
}
