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
 * Behavior to inject file into propel generated base classes.
 *
 * @package Curry
 */
class InjectFile extends Behavior
{
	/**
	 * Parameter default values.
	 *
	 * @var array
	 */
	protected $parameters = array(
		'object' => null,
		'peer' => null,
		'query' => null,
	);
	
	/**
	 * Returns file to inject, or false if none specified.
	 *
	 * @param OMBuilder $builder
	 * @param string $type
	 * @return string|bool
	 */
	public function inject($builder, $type) {
		if(($file = $this->getParameter($type))) {
			$path = $builder->getBuildProperty('projectDir') . DIRECTORY_SEPARATOR . $file;
			if(!file_exists($path))
				throw new Exception('Inject-file doesnt exist: ' . $path);
			return file_get_contents($path);
		}
		return false;
	}
	
	/**
	 * Add object methods.
	 *
	 * @param ObjectBuilder $builder
	 * @return string|bool
	 */
	public function objectMethods($builder) {
		return $this->inject($builder, 'object');
	}
	
	/**
	 * Add peer methods.
	 *
	 * @param PeerBuilder $builder
	 * @return string|bool
	 */
	public function staticMethods($builder) {
		return $this->inject($builder, 'peer');
	}
	
	/**
	 * Add query methods.
	 *
	 * @param QueryBuilder $builder
	 * @return string|bool
	 */
	public function queryMethods($builder) {
		return $this->inject($builder, 'query');
	}
}
