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
 * Static class with utility functions for arrays.
 * 
 * @package Curry
 */
class ArrayHelper {
	/**
	 * Flag to sort in reverse order.
	 */
	const SORT_REVERSE = 1;
	
	/**
	 * Flag to sort on property instead of using function-value.
	 */
	const SORT_PROPERTY = 2;
	
	/**
	 * Flag to sort strings case-insensitively
	 */
	const SORT_CASEINSENSITIVE = 4;
	
	/**
	 * Sort an array of objects, based on the return value of a function for each of the objects.
	 *
	 * @param array|\Traversable $objects
	 * @param string|array $valueFunc	One or multiple functions/properties to sort on.
	 * @param int|array $flags			Flags how to sort.
	 * @param bool $preserveKeys		Preserve the keys.
	 */
	public static function sortOn(array &$objects, $valueFunc, $flags = 0, $preserveKeys = false)
	{
		if(!is_array($valueFunc))
			$valueFunc = array($valueFunc);
		if(!is_array($flags))
			$flags = array($flags);

		$f = 0;
		$cmp = '';
		foreach($valueFunc as $vf) {
			if(count($flags))
				$f = array_shift($flags);
			$reverse = $f & self::SORT_REVERSE;
			$valueMethod = ($f & self::SORT_PROPERTY) ? "['$vf']" : "->$vf()";
			$pre = $f & self::SORT_CASEINSENSITIVE ? 'strtolower' : '';
			
			$cmp = $cmp.'
				$aa = '.$pre.'($a'.$valueMethod.');
				$bb = '.$pre.'($b'.$valueMethod.');
				if ($aa !== $bb)
					return ($aa'.($reverse?'>':'<').'$bb) ? -1 : 1;';
				
		}
		$cmp.= 'return 0;';
		$cmp = create_function('$a,$b', $cmp);
		if($preserveKeys)
			uasort($objects, $cmp);
		else
			usort($objects, $cmp);
	}
	
	/**
	 * Convert an array of objects to a new array using $keyFunc to create
	 * keys and $valueFunc to create values. If $keyFunc is false, an indexed list will be created
	 * If $valueFunc is null, the object itself will be used as value.
	 * 
	 * @example
	 * $objects = array(new Page(), new Page());
	 * $assoc = objectsToArray($objects, 'getPageId', 'getName');
	 * 
	 * print_r($assoc) => array(1 => 'namn', 2 => 'namn')
	 *
	 * @param array|\Traversable $objects
	 * @param string|bool|null $keyFunc		String to use callback on object, null to preserve keys, false to re-index the array.
	 * @param string|callback|null $valueFunc
	 * @return array
	 */
	public static function objectsToArray($objects, $keyFunc, $valueFunc = null)
	{
		$assoc = array();
		foreach($objects as $k => $object) {
			if($valueFunc === null) {
				$value = $object;
			} else if(is_string($valueFunc) && is_object($object) && method_exists($object, $valueFunc)) {
				$value = $object->{$valueFunc}();
			} else if(is_string($valueFunc) && is_object($object) && property_exists($object, $valueFunc)) {
				$value = $object->{$valueFunc};
			} else if(is_string($valueFunc) && is_array($object) && isset($object[$valueFunc])) {
				$value = $object[$valueFunc];
			} else if(is_callable($valueFunc)) {
				$value = call_user_func($valueFunc, $object);
			} else {
				throw new \Exception('Invalid value callback');
			}
			if($keyFunc)
				$assoc[$object->{$keyFunc}()] = $value;
			else if($keyFunc === false)
				$assoc[] = $value;
			else
				$assoc[$k] = $value;
		}
		return $assoc;
	}
	
	/**
	 * Create groups from an array of objects.
	 * 
	 * Using groupFunc on each of the objects to create a new array, where
	 * the key is the groupFunc and the value is an array with all the objects
	 * in that group (ie with the same value for the groupFunc call).
	 *
	 * @param array|\Traversable $objects
	 * @param string $groupFunc
	 * @param string $valueFunc
	 * @param string $keyFunc
	 * @return array
	 */
	public static function objectsToGroups($objects, $groupFunc, $valueFunc = null, $keyFunc = null)
	{
		$assoc = array();
		foreach($objects as $object) {
			$key = $object->{$groupFunc}();
			$value = ($valueFunc ? $object->{$valueFunc}() : $object);
			if(!array_key_exists($key, $assoc))
				$assoc[$key] = array();
			if($keyFunc)
				$assoc[$key][$object->{$keyFunc}()] = $value;
			else
				$assoc[$key][] = $value;
		}
		return $assoc;
	}

	/**
	 * Extend one array (or object) with another, optionally recursively.
	 *
	 * @param array|object $array
	 * @param array|object $extender
	 * @param boolean $recursive
	 * @param bool $extendStdClass
	 * @return array
	 */
	public static function extend(&$array, $extender, $recursive = true, $extendStdClass = true)
	{
		if (is_array($array)) {
			foreach($extender as $key => $value) {
				if ($recursive && isset($array[$key]) &&
					(is_array($array[$key]) || ($extendStdClass && $array[$key] instanceof \stdClass)) &&
					(is_array($value) || ($extendStdClass && $value instanceof \stdClass))) {
					self::extend($array[$key], $value);
				} else {
					$array[$key] = $value;
				}
			}
		} else if(is_object($array)) {
			foreach($extender as $key => $value) {
				if ($recursive && isset($array->$key) &&
					(is_array($array->$key) || ($extendStdClass && $array->$key instanceof \stdClass)) &&
					(is_array($value) || ($extendStdClass && $value instanceof \stdClass))) {
					self::extend($array->$key, $value);
				} else {
					$array->$key = $value;
				}
			}
		}
		return $array;
	}
	
	/**
	 * Insert elements into array, specifying before what element.
	 *
	 * @param array $array		Array to insert into.
	 * @param array $insertion	Elements to insert.
	 * @param mixed $before		Key of element in $array to insert before. If key is not found, $insertion will be added at the end of the array.
	 */
	public static function insertBefore(array &$array, array $insertion, $before)
	{
		$inserted = false;
		$arr = array();
		foreach($array as $k => $v) {
			if($k === $before) {
				foreach($insertion as $ik => $iv)
					$arr[$ik] = $iv;
				$inserted = true;
			}
			if(!array_key_exists($k, $insertion))
				$arr[$k] = $v;
		}
		if(!$inserted) {
			foreach($insertion as $ik => $iv)
				$arr[$ik] = $iv;
		}
		$array = $arr;
	}
	
	/**
	 * Insert elements into array, specifying position, and preserve keys.
	 *
	 * @param array $array		Array to insert into.
	 * @param array $insertion	Elements to insert.
	 * @param int $position		Position in $array to insert before.
	 */
	public static function insertAt(array &$array, array $insertion, $position)
	{
		$inserted = false;
		$arr = array();
		$p = 0;
		foreach($array as $k => $v) {
			if($p === $position) {
				foreach($insertion as $ik => $iv)
					$arr[$ik] = $iv;
				$inserted = true;
			}
			if(!array_key_exists($k, $insertion))
				$arr[$k] = $v;
			++$p;
		}
		if(!$inserted) {
			foreach($insertion as $ik => $iv)
				$arr[$ik] = $iv;
		}
		$array = $arr;
	}
}
