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
 * Manage the database.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_Database extends Curry_Backend
{
	/**#@+
	 * Propel-gen method constants.
	 */
	const METHOD_AUTOREBUILD = "autoRebuild";
	const METHOD_AUTOMIGRATE = "autoMigrate";
	const METHOD_REBUILD = "rebuild";
	const METHOD_OM = "om";
	const METHOD_SQL = "sql";
	const METHOD_CONVERT_CONF = "convertConf";
	const METHOD_DATADUMP = "dataDump";
	const METHOD_DATASQL = "dataSql";
	const METHOD_INSERTSQL = "insertSql";
	const METHOD_DIFF = "diff";
	const METHOD_MIGRATE = "migrate";
	const METHOD_STATUS = "status";
	/**#@-*/

	/**#@+
	 * Import method constants.
	 */
	const IMPORT_REPLACE = "replace";
	const IMPORT_APPEND = "append";
	const IMPORT_UPDATE = "update";
	const IMPORT_UPDATE_OR_INSERT = "update-or-insert";
	/**#@-*/
	
	/** {@inheritdoc} */
	public static function getGroup()
	{
		return "System";
	}
	
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		// Override and increase max execution time if set
		$timeLimit = ini_get('max_execution_time');
		if($timeLimit && $timeLimit < 250) {
			@set_time_limit(250);
		}

		Propel::disableInstancePooling();
		Propel::setLogger(null);
		
		// make sure all classes are included
		foreach(Curry_Propel::getModels() as $classes)
			foreach($classes as $clazz)
				class_exists($clazz.'Peer', true);
	}

	protected function beginDetails($title, $class = self::MSG_NOTICE, $open = false)
	{
		$this->addMainContent('<details'.($open?' open':'').'><summary>');
		$this->addMainContent('<span class="text-'.$class.'">'.htmlspecialchars($title).'</span>');
		$this->addMainContent('</summary>');
	}

	protected function endDetails()
	{
		$this->addMainContent('</details>');
	}
	
	/**
	 * Add menu items.
	 */
	private function showMainMenu()
	{
		$this->addTrace("Database", url('', array("module")));
		
		$this->addMenuItem("Browse", url('', array("module", "view"=>"Main")));
		$this->addMenuItem("Backup", url('', array("module", "view"=>"Backup")));
		$this->addMenuItem("Restore", url('', array("module", "view"=>"Restore")));
		$this->addMenuItem("Cleanup", url('', array("module", "view"=>"Cleanup")));
		$this->addMenuItem("Propel", url('', array("module", "view"=>"Propel")));
		$this->addMenuItem("SQL", url('', array("module", "view"=>"SQL")));

		$tree = new Curry_Tree(array(
			'persist' => false,
			'ajaxUrl' => (string)url('', array('module','view'=>'Main', 'json' => 1)),
			'nodeCallback' => array($this, 'getTreeJson'),
		));
		$this->addMenuContent($tree);
	}
	
	/** {@inheritdoc} */
	public function showMain()
	{
		$this->showMainMenu();
	}
	
	/**
	 * Get properties for tree node.
	 */
	public function getTreeJson()
	{
		$packages = array();
		foreach(Curry_Propel::getModels() as $package => $classes) {
			$p = array(
				'title' => $package,
				'iconClass' => 'icon-folder-open',
				'key' => $package,
				'children' => array(),
				'expand' => true,
			);
			foreach($classes as $clazz) {
				$icon = 'icon-table';
				try {
					$count = call_user_func(array($clazz.'Peer', 'doCount'), new Criteria());
				}
				catch (Exception $e) {
					$count = '?';
					$icon = 'icon-warning-sign';
				}
				$p['children'][] = array(
					'title' => $clazz . ' ('.$count.')',
					'iconClass' => $icon,
					'key' => $clazz,
					'href' => (string)url('', array('module', 'view' => 'Table', 'table' => $clazz)),
				);
			}
			$packages[] = $p;
		}
		Curry_Application::returnJson($packages);
	}
	
	/**
	 * Show database table.
	 */
	public function showTable()
	{
		$this->showMainMenu();
		$this->addTrace("Table: ".$_GET['table'], url('', array("module", "table", "view"=>"Table")));
		$this->addMainContent( $this->getTableGrid()->getHtml() );
	}
	
	/**
	 * Show table json data.
	 */
	public function showTableJson()
	{
		if (isset($_POST['cmd'])) {
			switch($_POST['cmd']) {
				case 'empty': {
					PropelQuery::from($_GET['table'])->deleteAll();
					break;
				}
				case 'dodelete': {
					foreach((array)$_POST['id'] as $id) {
						$pk = json_decode($id);
						if($pk !== null) {
							$obj = PropelQuery::from($_GET['table'])->findPk($pk);
							if($obj)
								$obj->delete();
						}
					}
					break;
				}
			}
		}
		$this->returnJson( $this->getTableGrid()->getJSON() );
	}
	
	/**
	 * Get table grid.
	 * 
	 * @return Curry_Flexigrid_Propel
	 */
	private function getTableGrid()
	{
		$tableMap = PropelQuery::from($_GET['table'])->getTableMap();
		$flexigrid = new Curry_Flexigrid_Propel($_GET['table'], url('', array("module", "table", "view"=>"TableJson")), array("rp" => 25, "rpOptions" => array(10,25,40,50,100,200)));
		
		// Unhide Pk column as you may want to see it in db backend
		if($flexigrid->getPrimaryKey()) {
			$flexigrid->setColumnOption($flexigrid->getPrimaryKey(), array('hide' => false));
		}

		// Make columns searchable
		foreach($tableMap->getColumns() as $column) {
			$name = strtolower($column->getName());
			$display = ucfirst(str_replace("_", " ", $name));
			$flexigrid->addSearchItem($name, $display);
		}
		
		$flexigrid->addColumn('pk', 'Primary Key', array('hide' => true, 'escape' => false));
		$flexigrid->setPrimaryKey('pk');
		$flexigrid->setColumnCallback('pk', array('Curry_Backend_DatabaseHelper', 'getObjectPk'));
		
		$editUrl = url('', array("module", "table","view"=>"Row"));
		$flexigrid->addAddButton($editUrl);
		$flexigrid->addEditButton($editUrl);
		$flexigrid->addCommandButton('Delete', 'icon_delete', 'dodelete'); // TODO: Add confirmation.
		$flexigrid->addSeparator();
		$flexigrid->addCommandButton('Empty', 'icon_bin_empty', 'empty'); // TODO: Add confirmation.
		$flexigrid->addLinkButton('Check relations', 'icon_wrench', (string)url('', array('module','view'=>'Cleanup','table')));
		if(array_key_exists('nested_set', $tableMap->getBehaviors()))
			$flexigrid->addLinkButton('Repair nested set', 'icon_wrench', (string)url('', array('module','view'=>'RepairNestedSet','table')));
		$flexigrid->addSeparator();
		$flexigrid->addLinkButton('Import', 'icon_page_excel', (string)url('', array('module','view'=>'Import','table')));
		$flexigrid->addLinkButton('Export', 'icon_page_excel', (string)url('', array('module','view'=>'Export','table')));
		
		return $flexigrid;
	}
	
	/**
	 * Show form for table row.
	 */
	public function showRow()
	{
		$modelClass = $_GET['table'];
		if(isset($_GET['pk'])) {
			$pk = json_decode($_GET['pk']);
			if($pk !== null)
				$row = PropelQuery::from($modelClass)->findPk($pk);
			else
				throw new Exception('Invalid primary key');
		} else {
			$row = new $modelClass();
		}
		
		if(!$row)
			throw new Exception('Item not found');
		
		$form = Curry_Backend_DatabaseHelper::getRowForm($row);
		if (isPost() && $form->isValid($_POST)) {
			Curry_Backend_DatabaseHelper::saveRow($row, $form);
			$this->returnPartial('');
		}
		
		$this->returnPartial($form);
	}

	/**
	 * Export table to CSV file.
	 */
	public function showExport()
	{
		$this->getTableGrid()->returnExcel();
	}

	/**
	 * Import data into table from CSV file.
	 *
	 * @todo Add support for propel advanced columns (array).
	 *
	 * @throws Exception
	 */
	public function showImport()
	{
		$modelClass = $_GET['table'];
		$tableMap = PropelQuery::from($modelClass)->getTableMap();
		$columnOptions = Curry_Array::objectsToArray($tableMap->getColumns(), 'getName', 'getPhpName');
		$pks = array();
		foreach($tableMap->getColumns() as $column) {
			if($column->isPrimaryKey()) {
				$pks[] = $column->getName();
			}
		}

		$form = new Curry_Form(array(
			'method' => 'post',
			'action' => url('', $_GET),
			'elements' => array(
				'file' => array('file', array(
					'label' => 'CSV File',
					'valueDisabled' => true,
				)),
				'mode' => array('select', array(
					'label' => 'Mode',
					'multiOptions' => array(
						self::IMPORT_REPLACE => 'Replace',
						self::IMPORT_APPEND => 'Append',
						self::IMPORT_UPDATE => 'Update',
						self::IMPORT_UPDATE_OR_INSERT => 'Update or insert',
					),
				)),
				'skip_first' => array('checkbox', array(
					'label' => 'Skip first line',
					'value' => true,
				)),
				'columns' => array('text', array(
					'label' => 'Columns in file',
					'value' => join(',', array_keys($columnOptions)),
				)),
				'use_columns' => array('multiselect', array(
					'label' => 'Columns to use',
					'multiOptions' => $columnOptions,
					'value' => array_keys($columnOptions),
					'size' => min(10, count($columnOptions)),
				)),
				'delimiter' => array('text', array(
					'label' => 'Delimiter',
					'value' => ',',
				)),
				'enclosure' => array('text', array(
					'label' => 'Enclosure',
					'value' => '"',
				)),
				'escape' => array('text', array(
					'label' => 'Escape',
					'value' => '\\',
				)),
				'null_value' => array('text', array(
					'label' => 'Null',
					'value' => 'Ø',
				)),
				'submit' => array('submit', array(
					'label' => 'Import',
				)),
			),
		));
		$fields = array_slice(array_keys($form->getElements()), 2, -1);
		$form->addDisplayGroup($fields, 'advanced', array(
			'legend' => 'Advanced options',
			'class' => 'advanced',
			'order' => 2,
		));

		$this->addMainContent('<h2>Import: '.htmlspecialchars($modelClass).'</h2>');

		if(isPost() && $form->isValid($_POST)) {
			$mode = $form->mode->getValue();
			$skipFirst = $form->skip_first->getValue();
			$columns = explode(',', $form->columns->getValue());
			$useColumns = $form->use_columns->getValue();
			$delimiter = $form->delimiter->getValue();
			$enclosure = $form->enclosure->getValue();
			$escape = $form->escape->getValue();
			$nullValue = $form->null_value->getValue();
			if(!$form->file->isUploaded())
				throw new Exception('Error when uploading file.');

			// Check for non-existent columns
			$nonExistent = array_filter(array_diff($columns, array_keys($columnOptions)));
			if (count($nonExistent)) {
				throw new Exception('Unknown column in column list: '.join(', ', $nonExistent));
			}

			// Open csv file
			$fileInfo = $form->file->getFileInfo();
			$fp = fopen($fileInfo['file']['tmp_name'], "r");
			if(!$fp) {
				throw new Exception('Unable to open file');
			}

			// Wrap in transaction
			$deleted = 0;
			$updated = 0;
			$inserted = 0;
			$con = Propel::getConnection(PropelQuery::from($modelClass)->getDbName());
			$con->beginTransaction();
			try {
				// Replace will empty the table
				if ($mode === self::IMPORT_REPLACE) {
					$deleted = PropelQuery::from($modelClass)->deleteAll();
				}
				// Read csv lines
				while(($data = fgetcsv($fp, 0, $delimiter, $enclosure, $escape)) !== false) {
					if(count($data) !== count($columns)) {
						throw new Exception('Invalid column count '.count($data).', expected '.count($columns));
					}
					if($skipFirst) {
						$skipFirst = false;
						continue;
					}
					$data = array_combine($columns, $data);
					$pkData = array();
					// Check for null values and collect primary key
					foreach($data as $k => $v) {
						if ($v === $nullValue) {
							$data[$k] = $v = null;
						}
						if (in_array($k, $pks)) {
							$pkData[$k] = $v;
						}
					}
					$obj = null;
					if($mode === self::IMPORT_UPDATE || $mode === self::IMPORT_UPDATE_OR_INSERT) {
						// attempt to find existing object using pk
						if (count($pkData) === count($pks)) {
							$obj = new $modelClass;
							$obj->fromArray($pkData, BasePeer::TYPE_RAW_COLNAME);
							$obj = PropelQuery::from($modelClass)->findPk($obj->getPrimaryKey());
						}
						if(!$obj && $mode === self::IMPORT_UPDATE_OR_INSERT) {
							// not found, create new
							$obj = new $modelClass;
						}
					} else {
						// REPLACE, APPEND
						$obj = new $modelClass;
					}
					// Remove unused columns
					foreach($data as $k => $v) {
						if (!in_array($k, $useColumns)) {
							unset($data[$k]);
						}
					}
					if($obj) {
						// Unset primary key columns in data when appending
						if($mode === self::IMPORT_APPEND) {
							foreach($pks as $pk) {
								if (array_key_exists($pk, $data)) {
									unset($data[$pk]);
								}
							}
						}
						$obj->fromArray($data, BasePeer::TYPE_RAW_COLNAME);
						if($obj->isNew()) {
							// allows insert of custom primary key
							BasePeer::doInsert($obj->buildCriteria(), $con);
							++$inserted;
						} else {
							$updated += $obj->save();
						}
					}
				}
				$con->commit();
			}
			catch (Exception $e) {
				$con->rollBack();
				throw $e;
			}
			if($deleted)
				$this->addMessage('Deleted: '.$deleted);
			if($inserted)
				$this->addMessage('Inserted: '.$inserted);
			if($updated)
				$this->addMessage('Updated: '.$updated);
			$this->addMessage('All done.', self::MSG_SUCCESS);
		} else {
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Run propel phing commands
	 *
	 * @param string $cmd	phing target
	 * @param string $output Log generated by propel-gen
	 * @return bool Returns true if the propel-gen command was successful, false otherwise.
	 */
	protected function doPropelGen($cmd = '', &$output = "")
	{
		$output = Curry_Backend_DatabaseHelper::propelGen($cmd);
		$success = Curry_Backend_DatabaseHelper::getPropelGenStatus($output);
		$this->beginDetails("Running propel-gen $cmd", $success ? self::MSG_SUCCESS : self::MSG_ERROR, !$success);
		$this->addMainContent('<pre class="console">'.Curry_Console::colorize($output).'</pre>');
		$this->endDetails();
		return $success;
	}
	
	/**
	 * Show propel-gen commands.
	 */
	public function showPropel()
	{
		$this->showMainMenu();
		
		$this->addMessage('If you\'re not careful, this could mess up your database.', self::MSG_WARNING);
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'elements' => array(
				'method' => array('select', array(
					'label' => 'Command',
					'multiOptions' => array(
						self::METHOD_AUTOREBUILD => 'Auto Rebuild',
						self::METHOD_AUTOMIGRATE => 'Auto Migrate',
						self::METHOD_REBUILD => 'Rebuild',
						self::METHOD_OM => 'Object Model',
						self::METHOD_SQL => 'SQL',
						self::METHOD_CONVERT_CONF => 'Convert Configuration',
						self::METHOD_DATADUMP => 'Datadump',
						self::METHOD_DATASQL => 'Data to SQL',
						self::METHOD_INSERTSQL => 'Insert SQL',
						self::METHOD_DIFF => 'Diff',
						self::METHOD_MIGRATE => 'Migrate',
						self::METHOD_STATUS => 'Status',
					),
					'required' => true,
				)),
				'execute' => array('submit', array(
					'label' => 'Execute'
				))
			)
		));
	
		if(isPost() && $form->isValid($_POST)) {
			switch($form->method->getValue()) {
			case self::METHOD_AUTOREBUILD:
				$this->doAutoRebuild();
				break;
			case self::METHOD_AUTOMIGRATE:
				$this->doAutoMigrate();
				break;
			case self::METHOD_REBUILD:
				$this->doPropelGen();
				break;
			case self::METHOD_OM:
				$this->doPropelGen('om');
				break;
			case self::METHOD_SQL:
				$this->doPropelGen('sql');
				break;
			case self::METHOD_CONVERT_CONF:
				$this->doPropelGen('convert-conf');
				break;
			case self::METHOD_DATADUMP:
				$this->doPropelGen('datadump');
				break;
			case self::METHOD_DATASQL:
				$this->doPropelGen('datasql');
				break;
			case self::METHOD_INSERTSQL:
				$this->doPropelGen('insert-sql');
				break;
			case self::METHOD_DIFF:
				$this->doPropelGen('diff');
				break;
			case self::METHOD_MIGRATE:
				$this->doPropelGen('migrate');
				break;
			case self::METHOD_STATUS:
				$this->doPropelGen('status');
				break;
			}
		}
		
		$this->addMainContent($form);
	}
	
	/**
	 * Do auto rebuild.
	 * 
	 * * Backup database.
	 * * Rebuild propel class files.
	 * * Create tables.
	 * * Restore database.
	 * 
	 */
	public function doAutoRebuild()
	{
		if(!is_writable(Curry_Core::$config->curry->configPath)) {
			$this->addMessage("Configuration file doesn't seem to be writable.", self::MSG_ERROR);
			return;
		}
		$config = new Zend_Config(require(Curry_Core::$config->curry->configPath), true);
		$restoreConfig = clone $config;
		
		$config->curry->backend->noauth = true;
		if(!$config->curry->maintenance->enabled || $config->curry->maintenance->page) {
			$config->curry->maintenance->enabled = true;
			$config->curry->maintenance->page = false;
			$config->curry->maintenance->message = "Rebuilding database, please wait...";
		}
		
		$writer = new Zend_Config_Writer_Array();
		$writer->write(Curry_Core::$config->curry->configPath, $config);
		
		try {
			$filename = Curry_Backend_DatabaseHelper::createBackupName('backup_%Y-%m-%d_%H-%M-%S_autorebuild.txt');
			$this->beginDetails('Dumping database to: '.$filename);
			$status = Curry_Backend_DatabaseHelper::dumpDatabase($filename, null, $this);
			$this->endDetails();
			if(!$status)
				throw new Exception('Aborting: There was an error when dumping the database.');
			
			if(class_exists('Project', false))
				throw new Exception('Aborting: Table named Project detected, autorebuild will fail, use manual build/restore or rename the project table');
			
			if(!$this->doPropelGen())
				throw new Exception('Failed to rebuild propel');
			if(!$this->doPropelGen('insert-sql'))
				throw new Exception('Failed to rebuild database');
			
			// use remote url to restore (because rebuilding above may change class definitions)
			$url = url('', array("module","view"=>"AutoRestore","file"=>$filename));
			$content = file_get_contents($url->getAbsolute('&', true));
			$this->addMessage('Restore: '.$content, ($content == 'ok' ? self::MSG_SUCCESS : self::MSG_ERROR));
		}
		catch(Exception $e) {
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
		}
		
		if($restoreConfig !== null) {
			$writer = new Zend_Config_Writer_Array();
			$writer->write(Curry_Core::$config->curry->configPath, $restoreConfig);
		}
	}
	
	/**
	 * Migrate database automatically.
	 * 
	 * * Backup database.
	 * * Generate migration diff.
	 * * Run migration.
	 * * Rebuild propel class files.
	 */
	public function doAutoMigrate()
	{
		// Dump database to file
		$filename = Curry_Backend_DatabaseHelper::createBackupName("backup_%Y-%m-%d_%H-%M-%S_automigrate.txt");
		$this->beginDetails('Dumping database to: '.$filename);
		$status = Curry_Backend_DatabaseHelper::dumpDatabase($filename, null, $this);
		$this->endDetails();
		if(!$status)
			throw new Exception('Aborting: There was an error when dumping the database.');
		
		// Check migration status and make sure there are no pending migrations
		$output = "";
		if(!$this->doPropelGen('status', $output))
			return false;
		$status1 = '[propel-migration-status] No migration file found in';
		$status2 = '[propel-migration-status] All migration files were already executed - Nothing to migrate.';	
		if(strpos($output, $status1) === false && strpos($output, $status2) === false)
			throw new Exception('It seems there are pending migrations. Please remove or migrate manually.');
		
		// Generate diff, migrate and rebuild object models
		if(!$this->doPropelGen('diff'))
			return false;
		if(!$this->doPropelGen('migrate'))
			return false;
		if(!$this->doPropelGen())
			return false;
		return true;
	}
	
	/**
	 * Continue restore from file.
	 * 
	 * @todo Improve this. Maybe store filename in session?
	 */
	public function showAutoRestore()
	{
		// restore from temp file
		try {
			if(!Curry_URL::validate())
				throw new Exception('Invalid hash');
				
			if(!Curry_Backend_DatabaseHelper::restoreFromFile($_GET['file']))
				throw new Exception('Unable to restore all rows.');
			
			echo 'ok';
		}
		catch(Exception $e) {
			echo $e->getMessage();
		}
		exit;
	}
	
	/**
	 * Execute custom SQL.
	 */
	public function showSQL()
	{
		$this->showMainMenu();
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'elements' => array(
				'sql' => array('textarea', array(
					'label' => 'SQL',
					'cols' => 40,
					'rows' => 6,
					'wrap' => 'off'
				)),
				'file' => array('file', array(
					'label' => 'File',
					'valueDisabled' => true,
				)),
				'execute' => array('submit', array(
					'label' => 'Execute'
				))
			)
		));
	
		if(isPost() && $form->isValid($_POST)) {
			$sql = '';
			if($form->sql->getValue()) {
				$sql = $form->sql->getValue();
			} else if($form->file->isUploaded()) {
				$fileinfo = $form->file->getFileInfo();
				$sql = file_get_contents($fileinfo['file']['tmp_name']);
			}
			if($sql)
				Curry_Backend_DatabaseHelper::runStatements($sql, false, array($this, 'statementCallback'));
		}
		
		$this->addMainContent($form);
	}
	
	/**
	 * Show SQL statement result after execution.
	 * 
	 * @param PDOStatement|null $stmt
	 * @param string $statement The SQL-query that was executed
	 */
	public function statementCallback($stmt, $statement)
	{
		if ($stmt === null) {
			$this->addMessage('Unable to execute statement: ' . $statement, self::MSG_ERROR);
		} else if ($stmt instanceof PDOStatement) {
			if($stmt->columnCount() > 0) // SELECT
				$this->addMainContent($this->getStatementGrid($stmt, $statement));
			else // DELETE, UPDATE, INSERT
				$this->addMainContent("<p>".$stmt->rowCount()." rows affected.</p>");
		}
	}
	
	/**
	 * Get HTML grid for PDO result.
	 * 
	 * @param PDOStatement $stmt 
	 * @param string $caption
	 * @return string
	 */
	protected function getStatementGrid($stmt, $caption)
	{
		$first = true;
		$html = '<div class="sql-resultset"><table><caption>'.htmlspecialchars($caption).'</caption>';
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			if($first) {
				$html .= '<thead><tr>';
				foreach($row as $k => $v) {
					$html .= '<th>'.htmlspecialchars($k).'</th>';
				}
				$html .= '</tr></thead><tbody>';
				$first = false;
			}
			$html .= '<tr>';
			foreach($row as $v) {
				$html .= '<td>'.htmlspecialchars($v).'</td>';
			}
			$html .= '</tr>';
		}
		if(!$first)
			$html .= '</tbody>';
		$html .= '</table></div>';
		return $html;
	}
	
	/**
	 * Search for invalid foreign-keys and required columns.
	 */
	public function showCleanup()
	{
		$this->showMainMenu();
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'elements' => array(
				'fix' => array('checkbox', array(
					'label' => 'Set invalid FKs to null',
					'required' => true
				)),
				'delete' => array('checkbox', array(
					'label' => 'Delete rows with invalid required FKs',
					'required' => true
				)),
				'scan' => array('submit', array(
					'label' => 'Go'
				))
			)
		));
	
		if(isPost() && $form->isValid($_POST)) {
			$this->addMainContent($form);
			if($form->scan->isChecked()) {
				$numInvalidRows = 0;
				foreach(Curry_Propel::getModels() as $classes) {
					foreach($classes as $clazz) {
						$this->addMessage("Scanning table $clazz");
						$numInvalidRows += Curry_Backend_DatabaseHelper::scanTable($clazz, $form->fix->getValue(), $form->delete->getValue(), $this);
					}
				}
				if($numInvalidRows)
					$this->addMessage("$numInvalidRows invalid rows found.", self::MSG_WARNING);
				else
					$this->addMessage("No invalid rows found.", self::MSG_SUCCESS);
			}
		} else {
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Validate and repair tables using propel behaviour nested_set.
	 */
	public function showRepairNestedSet()
	{
		$form = new Curry_Form(array(
			'action' => (string)url('', $_GET),
			'method' => 'POST',
			'elements' => array(
				'table' => array('text', array(
					'label' => 'Table',
					'value' => $_GET['table'],
					'disabled' => true,
				)),
				'fix' => array('checkbox', array(
					'label' => 'Fix errors',
				)),
				'submit' => array('submit', array(
					'label' => 'Repair nested set',
				)),
			),
		));
		if(isPost() && $form->isValid($_POST)) {
			$fix = $form->fix->getValue();
			$numErrors = Curry_Backend_DatabaseHelper::repairNestedSet($_GET['table'], $fix);
			if(!$numErrors)
				$this->addMessage('No errors found', self::MSG_SUCCESS);
			else
				$this->addMessage(($fix?'Fixed ':'Found ').$numErrors.' errors', self::MSG_WARNING);
		}
		$this->addMainContent($form);
	}
	
	/**
	 * Backup database.
	 */
	public function showBackup()
	{
		$this->showMainMenu();
		
		$tables = array();
		$selectedTables = array();
		foreach(Curry_Propel::getModels() as $package => $classes) {
			$selectedTables = array_merge($selectedTables, array_values($classes));
			$tables[$package] = array();
			foreach($classes as $table)
				$tables[$package][$table] = $table;
		}
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view","page_id")),
			'method' => 'post',
			'elements' => array(
				'tables' => array('multiselect', array(
					'label' => 'Tables',
					'multiOptions' => $tables,
					'value' => $selectedTables,
					'size' => 15,
				)),
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'value' => 'backup_%Y-%m-%d_%H-%M-%S.txt',
					'description' => 'Name of the file, strftime() is used to format the string.',
				)),
				'type' => array('radio', array(
					'label' => 'Where do you want to save?',
					'multiOptions' => array(
						'online' => 'Save online',
						'local' => 'Save to local file',
					),
					'value' => 'online'
				)),
			)
		));		
		$form->addElement('submit', 'Go');
		
		if(isPost() && ($_POST['tables'] == '*' || $_POST['tables'] == array('*')))
			$_POST['tables'] = $selectedTables;
		
		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues();
			if($values['type'] == 'local') {
				// dump to temp, stream to client
				$fp = fopen("php://temp", 'r+');
				Curry_Backend_DatabaseHelper::dumpDatabase($fp, $values['tables'], $this);
				rewind($fp);
				$name = Curry_String::getRewriteString(Curry_Core::$config->curry->name).'-db.txt';
				Curry_Application::returnData($fp, 'application/octet-stream', $name);
			} else if($values['type'] == 'online') {
				$filename = Curry_Backend_DatabaseHelper::createBackupName($values['name']);
				$status = Curry_Backend_DatabaseHelper::dumpDatabase($filename, $values['tables'], $this);
				$this->addMessage('Backup created '.$filename, $status ? self::MSG_SUCCESS : self::MSG_ERROR);
			}
		}
		$this->addMainContent($form);
	}
	
	/**
	 * Restore database.
	 */
	public function showRestore()
	{
		$this->showMainMenu();
		
		$tables = array();
		$selectedTables = array();
		$disabledTables = array();
		foreach(Curry_Propel::getModels() as $package => $classes) {
			$tables[$package] = array();
			foreach($classes as $table) {
				$tables[$package][$table] = $table;
				if (method_exists($table, 'save'))
					$selectedTables[] = $table;
				else
					$disabledTables[] = $table;
			}
		}
		
		$files = array(
			'' => '-- Select file --',
			'upload' => '[ Upload from computer ]',
			'remote' => '[ From remote server ]',
		);
		$path = Curry_Backend_DatabaseHelper::createBackupName('*.txt');
		foreach(array_reverse(glob($path)) as $file)
			$files[$file] = basename($file). ' ('.Curry_Util::humanReadableBytes(filesize($file)).')';
			
		$form = new Curry_Form(array(
			'action' => url('', array("module","view","page_id")),
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'elements' => array(
				'tables' => array('multiselect', array(
					'label' => 'Tables',
					'multiOptions' => $tables,
					'value' => $selectedTables,
					'disable' => $disabledTables,
					'size' => 15,
				)),
				'file' => array('select', array(
					'label' => 'From file',
					'multiOptions' => $files,
					'class' => 'trigger-change',
					'onchange' => "$('#uploadfile-label').next().andSelf()[this.value == 'upload'?'show':'hide']();".
						"$('#remote-label').next().andSelf()[this.value == 'remote'?'show':'hide']();",
				)),
				'uploadfile' => array('file', array(
					'label' => 'Upload file',
					'valueDisabled' => true,
				)),
				'remote' => array('text', array(
					'label' => 'Remote',
				)),
				'max_execution_time' => array('text', array(
					'label' => 'Max execution time',
					'value' => '',
					'description' => 'Input time in seconds to allow interruption if the time taken to restore would exceed the maximum execution time.'
				)),
			)
		));
		$form->addElement('submit', 'Go');
		
		if (isPost() && $form->isValid($_POST)) {
			if($form->file->getValue() == 'upload') {
				if(!$form->uploadfile->isUploaded())
					throw new Exception('No file was uploaded.');
				$fileinfo = $form->uploadfile->getFileInfo();
				Curry_Backend_DatabaseHelper::restoreFromFile($fileinfo['uploadfile']['tmp_name'], $form->tables->getValue(), 0, 0, $this);
			} else if($form->file->getValue() == 'remote') {
				if(!$form->remote->getValue())
					throw new Exception('No remote URL set');
				$url = url($form->remote->getValue());
				$post = array(
					'login_username' => $url->getUser(),
					'login_password' => $url->getPassword(),
					'tables' => '*',
					'name' => 'db.txt',
					'type' => 'local',
				);
				
				$url
					->setUser(null)
					->setPassword(null)
					->setPath('/admin.php')
					->add(array('module'=>'Curry_Backend_Database', 'view'=>'Backup'));
				
				$context = stream_context_create(array(
					'http' => array(
						'method'  => 'POST',
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'content' => http_build_query($post),
						//'timeout' => 5,
					),
				));
				$fp = fopen($url, 'r', false, $context);
				Curry_Backend_DatabaseHelper::restoreFromFile($fp, $form->tables->getValue(), 0, 0, $this);
				fclose($fp);
			} else if($form->file->getValue()) {
				Curry_Backend_DatabaseHelper::restoreFromFile($form->file->getValue(), $form->tables->getValue(), (int)$form->max_execution_time->getValue(), 0, $this);
			}
		}

		$this->addMainContent($form);
	}
	
	/**
	 * Restore from file, using _GET request. 
	 * 
	 * @todo Verify this is working and that it's secure. Can we do this using session variables instead? 
	 */
	public function showContinueRestore()
	{
		if(!Curry_URL::validate())
			throw new Exception('Invalid hash');
		
		Curry_Backend_DatabaseHelper::restoreFromFile($_GET['file'], $_GET['tables'], $_GET['max_execution_time'], $_GET['line'], $this);
	}
	
}
