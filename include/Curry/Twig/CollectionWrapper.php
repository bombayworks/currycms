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
 * Provide an interface similar to Curry_Twig_QueryWrapper for arrays.
 * 
 * @see Curry_Twig_QueryWrapper
 * 
 * @package Curry\Twig
 */
class Curry_Twig_CollectionWrapper implements Iterator, Countable {
	/**
	 * The collection
	 *
	 * @var array
	 */
	protected $collection;
	
	/**
	 * Filter function.
	 *
	 * @var callback|null
	 */
	protected $formatterFunction;
	
	/**
	 * The "page" we're paginating.
	 *
	 * @var int
	 */
	protected $page = 1;
	
	/**
	 * Items per page.
	 *
	 * @var int
	 */
	protected $maxPerPage = 0;
	
	/**
	 * Index of last page.
	 *
	 * @var int
	 */
	protected $lastPage = 0;
	
	/**
	 * Internal object to keep track of how which items to iterate over.
	 *
	 * @var int
	 */
	protected $limit = -1;
	
	/**
	 * Constructor
	 *
	 * @param array $collection
	 * @param callback|null $formatterFunction
	 */
	public function __construct($collection, $formatterFunction = null)
	{
		$this->collection = $collection;
		$this->formatterFunction = $formatterFunction;
	}
	
	/**
	 * Paginate over collection.
	 * 
	 * @todo Return a new object to iterate over a slice of the array instead.
	 *
	 * @param int $page
	 * @param int $maxPerPage
	 * @return Curry_Twig_CollectionWrapper
	 */
	public function paginate($page = 1, $maxPerPage = 10)
	{
		$this->page = $page;
		$this->maxPerPage = $maxPerPage;
		$this->lastPage = ceil(count($this->collection) / $maxPerPage);
		return $this;
	}
	
	/** {@inheritdoc} */
	public function rewind()
	{
		reset($this->collection);
		
		if($this->maxPerPage) {
			$this->limit = ($this->page - 1) * $this->maxPerPage;
			while($this->valid())
				$this->next();
			$this->limit = $this->maxPerPage;
		}
	}

	/** {@inheritdoc} */
	public function current()
	{
		$current = current($this->collection);
		return $this->formatterFunction ? call_user_func($this->formatterFunction, $current) : $current;
	}

	/** {@inheritdoc} */
	public function key()
	{
		return key($this->collection);
	}

	/** {@inheritdoc} */
	public function next()
	{
		next($this->collection);
		--$this->limit;
	}

	/** {@inheritdoc} */
	public function valid()
	{
		return key($this->collection) !== null && $this->limit != 0;
	}
	
	/** {@inheritdoc} */
	public function count()
	{
		return count($this->collection);
	}
	
	/**
	 * Number of items in full (unpaged) collection.
	 *
	 * @return int
	 */
	public function getNbResults()
	{
		return count($this->collection);
	}
	
	/**
	 * Does the collection contain more items than the max for one page?
	 *
	 * @return bool
	 */
	public function haveToPaginate()
	{
		return $this->maxPerPage && count($this->collection) > $this->maxPerPage;
	}
	
	/**
	 * Get links around the current page.
	 *
	 * @param int $nb_links
	 * @return array
	 */
	public function getLinks($nb_links = 5)
	{
		$links = array();
		$tmp	 = $this->page - floor($nb_links / 2);
		$check = $this->lastPage - $nb_links + 1;
		$limit = ($check > 0) ? $check : 1;
		$begin = ($tmp > 0) ? (($tmp > $limit) ? $limit : $tmp) : 1;

		$i = (int) $begin;
		while (($i < $begin + $nb_links) && ($i <= $this->lastPage)) {
			$links[] = $i++;
		}
		
		return $links;
	}
	
	/**
	 * Get the index of the first element in the page
	 * Returns 1 on the first page, $maxPerPage +1 on the second page, etc
	 * 
	 * @return     int
	 */
	public function getFirstIndex()
	{
		if ($this->page == 0) {
			return 1;
		} else {
			return ($this->page - 1) * $this->maxPerPage + 1;
		}
	}

	/**
	 * Get the index of the last element in the page
	 * Always less than or eaqual to $maxPerPage
	 * 
	 * @return     int
	 */
	public function getLastIndex()
	{
		if ($this->page == 0) {
			return $this->getNbResults();
		} else {
			if (($this->page * $this->maxPerPage) >= $this->getNbResults()) {
				return $this->getNbResults();
			} else {
				return ($this->page * $this->maxPerPage);
			}
		}
	}
	
	/**
	 * Check whether the current page is the first page
	 * 
	 * @return     boolean true if the current page is the first page
	 */
	public function isFirstPage()
	{
		return $this->getPage() == $this->getFirstPage();
	}

	/**
	 * Get the number of the first page
	 * 
	 * @return     int Always 1
	 */
	public function getFirstPage()
	{
		return 1;
	}

	/**
	 * Check whether the current page is the last page
	 * 
	 * @return     boolean true if the current page is the last page
	 */
	public function isLastPage()
	{
		return $this->getPage() == $this->getLastPage();
	}

	/**
	 * Get the number of the last page
	 * 
	 * @return     int
	 */
	public function getLastPage()
	{
		return $this->lastPage;
	}

	/**
	 * Get the number of the current page
	 * 
	 * @return     int
	 */
	public function getPage()
	{
		return $this->page;
	}
	
	/**
	 * Get the number of the next page
	 * 
	 * @return     int
	 */
	public function getNextPage()
	{
		return min($this->getPage() + 1, $this->getLastPage());
	}

	/**
	 * Get the number of the previous page
	 * 
	 * @return     int
	 */
	public function getPreviousPage()
	{
		return max($this->getPage() - 1, $this->getFirstPage());
	}

	/**
	 * Get the maximum number results per page
	 * 
	 * @return     int
	 */
	public function getMaxPerPage()
	{
		return $this->maxPerPage;
	}
}
