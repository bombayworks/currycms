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
namespace Curry\Util;

/**
 * Static class with Propel utility functions.
 * 
 * @package Curry\Util
 */
class Propel {
	/**
	 * Get the number of SQL-queries executed for this request.
	 *
	 * @return int|null
	 */
	public static function getQueryCount()
	{
		try {
			$con = \Propel::getConnection(); // assume we're using default connection
			return ($con && $con->useDebug) ? $con->getQueryCount() : null;
		}
		catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Get a list of all Propel-model classes.
	 *
	 * @param bool $packages Group by package name.
	 * @return array
	 */
	public static function getModels($packages = true)
	{
		$classes = array();
		$configArray = \Propel::getConfiguration();
		foreach($configArray['classmap'] as $className => $file) {
			if(preg_match('@^(.*)/map/(\w+)TableMap\.php$@', $file, $match)) {
				$package = $match[1];
				$clazz = $match[2];
				if($packages) {
					if(!isset($classes[$package]))
						$classes[$package] = array();
					$classes[$package][] = $clazz;
				} else {
					$classes[] = $clazz;
				}
			}
		}
		if($packages) {
			ksort($classes, SORT_STRING);
			foreach($classes as $package => &$cl)
				sort($cl, SORT_STRING);
			unset($cl);
		}
		return $classes;
	}

	/**
	 * Produces a union-query from two queries.
	 * 
	 * @todo Implement support for ORDER, LIMIT etc.
	 *
	 * @param \ModelCriteria $mc1
	 * @param \ModelCriteria $mc2
	 * @return mixed
	 */
	public static function union(\ModelCriteria $mc1, \ModelCriteria $mc2)
	{
		$dbMap = \Propel::getDatabaseMap($mc1->getDbName());
		$db = \Propel::getDB($mc1->getDbName());
		$con = \Propel::getConnection($mc1->getDbName(), \Propel::CONNECTION_READ);
		
		// we may modify criteria, so copy it first
		$c1 = clone $mc1;
		$c2 = clone $mc2;
		
		// check that the columns of the main class are already added (if this is the primary ModelCriteria)
		if (!$c1->hasSelectClause() && !$c1->getPrimaryCriteria()) {
			$c1->addSelfSelectColumns();
		}
		
		if (!$c2->hasSelectClause() && !$c2->getPrimaryCriteria()) {
			$c2->addSelfSelectColumns();
		}
		
		$con->beginTransaction();
		try {
			$params = array();
			$sql1 = \BasePeer::createSelectSql($c1, $params);
			$sql2 = \BasePeer::createSelectSql($c2, $params);
			$stmt = $con->prepare("($sql1) UNION ALL ($sql2)");
			$db->bindValues($stmt, $params, $dbMap);
			$stmt->execute();
			$con->commit();
		} catch (\PropelException $e) {
			$con->rollback();
			throw $e;
		}

		return $c1->getFormatter()->init($c1)->format($stmt);
	}
	
    /**
     * Return true if $tableMap has the specified behavior else return false
     * 
     * @param string $behavior
     * @param \TableMap $tableMap
     * @return boolean
     */
    public static function hasBehavior($behavior, \TableMap $tableMap)
    {
        return array_key_exists(strtolower($behavior), $tableMap->getBehaviors());
    }

    /**
     * Return the specified behavior properties
     * 
     * @param string $behavior
     * @param \TableMap $tableMap
     * @return array
     */
    public static function getBehavior($behavior, \TableMap $tableMap)
    {
    	$behaviors = $tableMap->getBehaviors();
    	return (array) $behaviors[strtolower($behavior)];
    }

    /**
     * Whether $tableMap has i18n behavior
     * 
     * @param \TableMap $tableMap
     * @return boolean
     */
    public static function hasI18nBehavior(\TableMap $tableMap)
    {
        return self::hasBehavior('i18n', $tableMap);
    }

    /**
     * Return the i18n TableMap if $tableMap has I18n behavior
     * 
     * @param \TableMap $tableMap
     * @return \TableMap|null
     */
    public static function getI18nTableMap(\TableMap $tableMap)
    {
        if (self::hasI18nBehavior($tableMap)) {
            $i18nTablename = "{$tableMap->getPhpName()}I18n";
            return \PropelQuery::from($i18nTablename)->getTableMap();
        }

        return null;
    }

    /**
     * Whether $column is an I18n column
     * 
     * @param string $column
     * @param \TableMap $tableMap
     * @return bool
     */
    public static function hasI18nColumn($column, \TableMap $tableMap)
    {
        $i18nTableMap = self::getI18nTableMap($tableMap);
        if ($i18nTableMap !== null) {
            return $i18nTableMap->hasColumn($column);
        }

        return false;
    }

    /**
     * Return the ColumnMap for the i18n column
     * 
     * @param string $column
     * @param \TableMap $tableMap
     * @return \ColumnMap|null
     */
    public static function getI18nColumn($column, \TableMap $tableMap)
    {
        $i18nTableMap = self::getI18nTableMap($tableMap);
        if ($i18nTableMap !== null) {
            return $i18nTableMap->getColumn($column);
        }

        return null;
    }

	public static function doMultiInsert($tableName, $values, $dbName = null, $rawValues = false, $method = 'INSERT')
	{
		$query = \PropelQuery::from($tableName);
		if(!$dbName)
			$dbName = $query->getDbName();
		$adapter = \Propel::getDB($dbName);
		$tableMap = $query->getTableMap();
		$dbMap = $tableMap->getDatabaseMap();
		$con = \Propel::getConnection($dbName, \Propel::CONNECTION_WRITE);
		try {
			$sql = '';
			$p = 1;
			$params = array();
			$columns = array();
			$colNames = array();
			foreach($values as $row) {
				if($p === 1) {
					foreach(array_keys($row) as $phpName) {
						if ($tableMap->hasColumnByPhpName($phpName)) {
							$columns[$phpName] = $tableMap->getColumnByPhpName($phpName);
							$colNames[$phpName] = $columns[$phpName]->getColumnName();
						}
					}
				}
				$sql .= ($p === 1 ? '(' : ',(');
				$pp = $p;
				foreach($columns as $phpName => $column) {
					$sql .= ($p === $pp ? '' : ',').':p'.$p++;
					$value = $row[$phpName];
					if (!$rawValues) {
						$value = self::_getColumnRawValue($column, $value);
					}
					$params[] = array('column' => $colNames[$phpName], 'table' => $tableMap->getName(), 'value' => $value);
				}
				$sql .= ')';
			}
			
			if($p === 1)
				return 0;
			
			$table = $tableMap->getName();
			if ($adapter->useQuoteIdentifier()) {
				$colNames = array_map(array($adapter, 'quoteIdentifier'), $colNames);
				$table = $adapter->quoteIdentifierTable($table);
			}
			
			$sql = $method.' INTO ' . $table
				. ' (' . implode(',', $colNames) . ')'
				. ' VALUES ' . $sql;
			$adapter->cleanupSQL($sql, $params, new \Criteria($dbName), $dbMap);
			$stmt = $con->prepare($sql);
			$adapter->bindValues($stmt, $params, $dbMap, $adapter);
			$stmt->execute();
			return $stmt->rowCount();
		} catch (\Exception $e) {
			\Propel::log($e->getMessage(), \Propel::LOG_ERR);
			throw new \PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), $e);
		}
	}

	protected static function _getColumnRawValue(\ColumnMap $column, $value)
	{
		if ($value === null)
			return null;
		switch ($column->getType()) {
			case \PropelColumnTypes::ENUM:
				$valueSet = $column->getValueSet();
				return array_search($value, $valueSet);
			case \PropelColumnTypes::PHP_ARRAY:
				return '| ' . implode(' | ', $value) . ' |';
			case \PropelColumnTypes::OBJECT:
				return serialize($value);
		}
		return $value;
	}

	public static function sortableReorder($itemsOrPks, $modelClass = null, $con = null)
	{
		if (!count($itemsOrPks))
			return;

		// Check first item to decide if it's an array of objects or pks
		reset($itemsOrPks);
		$object = current($itemsOrPks);
		if (is_object($object) && $object instanceof \BaseObject) {
			// Array of objects
			$objects = $itemsOrPks;
			if ($modelClass === null)
				$modelClass = get_class($object);
		} else {
			// Array of primary keys
			if ($modelClass === null)
				throw new \Exception('Need to specify modelClass when using primary-keys.');
			// Rely on instance-pooling to fetch items
			\PropelQuery::from($modelClass)->findPks($itemsOrPks);
			$objects = array();
			foreach($itemsOrPks as $pk) {
				$object = \PropelQuery::from($modelClass)->findPk($pk);
				if (!$object)
					throw new \Exception('Unable to find item to sort.');
				$objects[] = $object;
			}
		}

		// Validate scope
		$scope = null;
		$tmp = \PropelQuery::from($modelClass)->getTableMap()->getBehaviors();
		$useScope = strtolower($tmp['sortable']['use_scope']) == 'true';
		if ($useScope) {
			foreach($objects as $object) {
				if ($object->getScopeValue() !== null) {
					if ($scope !== null && $object->getScopeValue() !== $scope)
						throw new \Exception('Unable to sort items in mixed scopes.');
					$scope = $object->getScopeValue();
				}
			}
		}

		// Fix null/duplicate ranks and invalid scopes
		$ranks = array();
		foreach($objects as $object) {
			if($useScope && $object->getScopeValue() !== $scope) {
				$object->setScopeValue($scope);
			}
			if($object->getRank() === null || in_array($object->getRank(), $ranks)) {
				$object->insertAtBottom();
			}
			if($object->isModified()) {
				$object->save();
			}
			$ranks[] = $object->getRank();
		}
		sort($ranks);

		// Update ranks
		if ($con === null) {
			$peer = constant($modelClass.'::PEER');
			$con = \Propel::getConnection($peer::DATABASE_NAME);
		}
		$con->beginTransaction();
		try {
			reset($ranks);
			foreach ($objects as $object) {
				$rank = current($ranks);
				next($ranks);
				if ($object->getRank() != $rank) {
					$object->setRank($rank);
					$object->save($con);
				}
			}
			$con->commit();
		} catch (\PropelException $e) {
			$con->rollback();
			throw $e;
		}
	}

	public static function toTwig(\BaseObject $obj, $checkToTwig = true, $includeRelated = true, $includeVirtual = true, $includeI18n = true)
	{
		if($checkToTwig && method_exists($obj, 'toTwig'))
			return $obj->toTwig();

		$tableMap = \PropelQuery::from(get_class($obj))->getTableMap();
		$p = $obj->toArray(\BasePeer::TYPE_PHPNAME);
		if ($includeRelated) {
			foreach ($tableMap->getRelations() as $relation) {
				if (in_array($relation->getType(), array(\RelationMap::ONE_TO_MANY, \RelationMap::MANY_TO_MANY))) {
					$name = $relation->getPluralName();
					$p[lcfirst($name)] = new \Curry\Util\OnDemand(function() use($obj, $name) {
						return ArrayHelper::objectsToArray($obj->{'get'.$name}(), null, array('Curry\Util\Propel', 'toTwig'));
					});
				} else {
					$name = $relation->getName();
					$p[lcfirst($name)] = new \Curry\Util\OnDemand(function() use($obj, $name) {
						$rel = $obj->{'get'.$name}();
						return $rel ? Propel::toTwig($rel) : null;
					});
				}
			}
		}

		// Automatic URL
		$p['Url'] = new \Curry\Util\OnDemand(function() use($obj, $tableMap) {
			$params = array();
			foreach ($tableMap->getPrimaryKeys() as $pk)
				$params[$pk->getName()] = $obj->{'get'.$pk->getPhpName()}();
			return url(L(get_class($obj).'Url'), $params);
		});
		// Virtual columns
		if ($includeVirtual)
			$p = array_merge($p, $obj->getVirtualColumns());
		// I18n behavior columns
		if ($includeI18n && self::hasBehavior('i18n', $tableMap)) {
			$translation = $obj->getCurrentTranslation();
			$p = array_merge($p, $translation->toArray());
		}
		return $p;
	}
}
