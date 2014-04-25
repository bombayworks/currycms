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
 * Implements model routing.
 * 
 * @package Curry\Route
 */
class Curry_Route_ModelRoute implements Curry_IRoute {
	/**
	 * Cached map of Page URL to Model.
	 *
	 * @var string[]
	 */
	protected static $urlToModel = null;
	
	/**
	 * Find model from URL.
	 *
	 * @param string $url
	 * @return string|null
	 */
	protected static function findPageModel($url)
	{
		if(self::$urlToModel === null) {
			$cacheName = __CLASS__ . '_' . 'UrlToModel';
			if((self::$urlToModel = \Curry\App::getInstance()->cache->load($cacheName)) === false) {
				self::$urlToModel = PageQuery::create()
					->filterByModelRoute(null, Criteria::ISNOTNULL)
					->find()
					->toKeyValue('Url', 'ModelRoute');
				\Curry\App::getInstance()->cache->save(self::$urlToModel, $cacheName);
			}
		}
		return isset(self::$urlToModel[$url]) ? self::$urlToModel[$url] : null;
	}
	
	/**
	 * Perform routing.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request)
	{
		$p = explode('?', $request->getUri(), 2);
		$parts = explode("/", $p[0]);
		$query = isset($p[1]) ? "?".$p[1] : '';

		$url = "";
		while(count($parts)) {
			$model = self::findPageModel($url ? $url : '/');
			if($model) {
				$slug = array_shift($parts);
				$remaining = join("/", $parts);
				$params = self::getParamFromSlug($model, $slug);
				if($params) {
					// add params to request
					foreach($params as $name => $value)
						$request->setParam('get', $name, $value);
					$query .= (strlen($query) ? '&' : '?') . http_build_query($params);
					// rebuild uri
					$request->setUri($url.$remaining.$query);
					// continue routing
					return true;
				}
				// put slug back and continue searching
				array_unshift($parts, $slug);
			}
			$url .= array_shift($parts) . "/";
		}
		
		return null;
	}
	
	/**
	 * From an internal URL, create the public URL.
	 *
	 * @param string $path
	 * @param array|string $query
	 */
	public static function reverse(&$path, &$query)
	{
		$parts = explode("/", $path);
		$url = "";
		$newpath = array();
		while(count($parts)) {
			// find model for current url
			$model = self::findPageModel($url ? $url : '/');
			if($model) {
				$env = array();
				parse_str($query, $env);
				$slug = self::reverseModelSlug($model, $env);
				if($slug !== null) {
					$newpath[] = $slug;
					$query = http_build_query($env, null, '&');
				}
			}
			// build next url
			while(count($parts)) {
				$part = array_shift($parts);
				$newpath[] = $part;
				if($part) {
					$url .= $part . "/";
					break;
				}
			}
		}
		$path = join('/', $newpath);
	}
	
	/**
	 * From model and variables, attempt to find a slug.
	 *
	 * @param string $model
	 * @param array $env
	 * @return string|null The slug if found, otherwise null.
	 */
	protected static function reverseModelSlug($model, &$env)
	{
		// Find primary-key values
		$primaryKeyColumns = PropelQuery::from($model)
			->getTableMap()
			->getPrimaryKeys();
		$pk = array();
		foreach($primaryKeyColumns as $primaryKeyColumn) {
			$name = strtolower($primaryKeyColumn->getName());
			if(!isset($env[$name]))
				return null;
			$pk[] = $env[$name];
		}
		// Find model object from primary-key
		$modelObject = PropelQuery::from($model)
			->findPk(count($pk) == 1 ? $pk[0] : $pk);
		if(!$modelObject)
			return null;
		// Unset environment variables
		foreach($primaryKeyColumns as $primaryKeyColumn) {
			$name = strtolower($primaryKeyColumn->getName());
			unset($env[$name]);
		}
		return $modelObject->getSlug();
	}
	
	/**
	 * From model and slug, attempt to find parameters.
	 *
	 * @param string $model
	 * @param string $slug
	 * @return array
	 */
	protected static function getParamFromSlug($model, $slug)
	{
		if(in_array('Curry_IRoutable', class_implements($model)))
			return call_user_func(array($model, "getParamFromSlug"), $slug);
		$modelObject = PropelQuery::from($model)
			->findOneBySlug($slug);
		if($modelObject) {
			$param = array();
			$tableMap = PropelQuery::from($model)
				->getTableMap();
			foreach($tableMap->getPrimaryKeys() as $primaryKeyColumn) {
				$name = strtolower($primaryKeyColumn->getName());
				$phpName = $primaryKeyColumn->getPhpName();
				$value = $modelObject->{'get'.$phpName}();
				$param[$name] = $value;
			}
			return $param;
		}
		return null;
	}
}
