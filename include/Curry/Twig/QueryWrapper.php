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
use Curry\Util\Propel;

/**
 * Wraps a propel ModelCriteria to provide additional functionality when used in a template.
 * 
 * Query (or ModelCriteria) objects passed to Twig are automatically wrapped using this class.
 * 
 * * Delayed execution of SQL-queries to make sure query is only executed when used in template.
 * * Easy pagination directly from template without any additional SQL-queries.
 * * Implements Countable interface to allow length filter in Twig.
 * * Format results using a formatter function.
 *
 * @package Curry\Twig
 */
class Curry_Twig_QueryWrapper extends PropelObjectFormatter implements IteratorAggregate, ArrayAccess, Countable {
	/**
	 * The ModelCriteria
	 *
	 * @var ModelCriteria
	 */
	protected $query;
	
	/**
	 * The actual results
	 *
	 * @var PropelCollection
	 */
	protected $collection;
	
	/**
	 * Custom formatting function to filter results before returning them to Twig.
	 *
	 * @var callback|null
	 */
	protected $formatterFunction;
	
	/**
	 * Constructor
	 *
	 * @param ModelCriteria $query
	 * @param callback|bool|null $formatterFunction callback to filter objects through
	 *   callback. null to use PropelObjectFiltering. false to use query formatter.
	 */
	public function __construct(ModelCriteria $query, $formatterFunction = null)
	{
		$this->query = $query;
		$this->formatterFunction = $formatterFunction;
		if($formatterFunction !== false)
			$this->query->setFormatter($this);
	}
	
	/**
	 * Execute query, store result and return it.
	 *
	 * @return PropelCollection
	 */
	protected function getCollection()
	{
		if($this->collection === null)
			$this->collection = $this->query->find();
		return $this->collection;
	}
	
	/** {@inheritdoc} */
	public function offsetSet($offset, $value)
	{
		$collection = $this->getCollection();
		$collection[$offset] = $value;
	}
	
	/** {@inheritdoc} */
	public function offsetExists($offset)
	{
		$collection = $this->getCollection();
		return isset($collection[$offset]);
	}
	
	/** {@inheritdoc} */
	public function offsetUnset($offset)
	{
		$collection = $this->getCollection();
		unset($collection[$offset]);
	}
	
	/** {@inheritdoc} */
	public function offsetGet($offset)
	{
		$collection = $this->getCollection();
		return isset($collection[$offset]) ? $collection[$offset] : null;
	}
	
	/**
	 * Implements Countable interface.
	 * 
	 * @todo Perform SQL count instead of fetching the collection?
	 *
	 * @return int
	 */
	public function count()
	{
		$c = $this->getCollection();
		if(method_exists($c, 'count'))
			return $c->count();
		else if(is_array($c))
			return count($c);
		throw new Exception('count() not available');
	}
	
	/**
	 * Implement IteratorAggregate to allow iteration of the collection.
	 *
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->getCollection());
	}
	
	/**
	 * Paginate collection.
	 *
	 * @param int $page
	 * @param int $numPerPage
	 * @return mixed
	 */
	public function paginate($page, $numPerPage)
	{
		return $this->query->paginate($page, $numPerPage);
	}

	/**
	 * Override formatter function, filter results through formatter function or call toTwig/toArray on objects.
	 *
	 * @param PDOStatement $stmt
	 * @return PropelCollection
	 */
	public function format(PDOStatement $stmt)
	{
		$collection = parent::format($stmt);
		$result = new PropelCollection();
		
		if($this->formatterFunction) {
			foreach($collection as $c)
				$result[] = call_user_func($this->formatterFunction, $c);
		} else if($collection instanceof PropelObjectCollection) {
			foreach($collection as $c)
				$result[] = Propel::toTwig($c);
		} else {
			$result = $collection;
		}
		
		return $result;
	}
	
	/**
	 * Override formatter function for one item.
	 *
	 * @param PDOStatement $stmt
	 * @return mixed
	 */
	public function formatOne(PDOStatement $stmt)
	{
		$item = parent::format($stmt);
		
		if($this->formatterFunction)
			return call_user_func($this->formatterFunction, $item);
		
		if(is_object($item) && $item instanceof BaseObject)
			return Propel::toTwig($item);
			
		return $item;
	}
}
