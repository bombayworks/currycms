<?php

namespace Curry;

use Symfony\Component\HttpFoundation\Request;

abstract class View extends Configurable implements \ArrayAccess {
	protected $parent = null;
	protected $subviews = null;
	protected $route = null;
	protected $params = array();

	public function addView($name, View $view, $route = null)
	{
		if ($route === null)
			$route = $name.'/';
		if (is_string($route))
			$route = new Route($route);
		$view->setParentAndRoute($this, $route);
		$this->subviews[$name] = $view;
	}

	protected function setParentAndRoute(View $parent, RouteInterface $route)
	{
		if ($this->parent)
			throw new \Exception('Unable to add subview, view already has parent');
		$this->parent = $parent;
		$this->route = $route;
	}

	public function addViewFunction($name, $viewFunction, $route = null)
	{
		$view = new ViewFunction($viewFunction);
		$this->addView($name, $view, $route);
		return $view;
	}

	public function findView($target)
	{
		if ($target == '')
			return $this;
		foreach($this->getViews() as $view) {
			$match = $view->route->match($target);
			if ($match !== false) {
				list($params, $rest) = $match;
				$view->params = $params;
				$subview = $view->findView($rest);
				if ($subview)
					return $subview;
				// TODO: revert params?
			}
		}
		return null;
	}

	/**
	 * Initializes subviews.
	 */
	public function initialize() { }

	/**
	 * Create url to view, inserting specified parameters.
	 *
	 * @param array|null $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function url($parameters = null)
	{
		if ($this->parent === null || $this->route === null)
			throw new \Exception('Missing parent/route for view, unable to construct url');

		// insert parameters into route, and prepend parent url
		$params = $parameters !== null ? $parameters : $this->params;
		return $this->parent->url() . $this->route->create($params);
	}

	/**
	 * Get array of all subviews.
	 *
	 * @return View[]
	 */
	public function getViews()
	{
		if ($this->subviews === null) {
			$this->subviews = array();
			$this->initialize();
		}
		return $this->subviews;
	}

	/**
	 * Get subview by name.
	 *
	 * @param $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function getView($name)
	{
		$views = $this->getViews();
		if (!isset($views[$name]))
			throw new \Exception('View not found: '.$name);
		return $views[$name];
	}

	/**
	 * Magic method to access subviews property-style.
	 *
	 * @param $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name)
	{
		return $this->getView($name);
	}

	public function offsetGet($name)
	{
		return $this->params[$name];
	}
	public function offsetSet($name, $value)
	{
		$this->params[$name] = $value;
	}
	public function offsetExists($name)
	{
		return isset($this->params[$name]);
	}

	public function offsetUnset($name)
	{
		unset($this->params[$name]);
	}

	public abstract function show(Request $request);
}
