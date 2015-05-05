<?php

namespace Curry;

use Symfony\Component\HttpFoundation\Request;

abstract class View extends Configurable implements \ArrayAccess {
	protected $parent = null;
	protected $subviews = null;
	protected $params = array();

	public function addView($name, View $view, $route = null)
	{
		if ($route === null)
			$route = $name.'/';
		if (is_string($route))
			$route = new Route($route);
		$this->subviews[$name] = array($view, $route);
		if ($view->parent)
			throw new \Exception('Unable to add sub-view, view already has parent');
		$view->parent = $this;
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
		foreach($this->views() as $viewAndRoute) {
			list($view, $route) = $viewAndRoute;
			$match = $route->match($target);
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

	public function initialize() { }
	//public function beforeShow($view) { }
	//public function afterShow($view, $content) { return $content; }

	public function url($parameters = null)
	{
		if (!$this->parent)
			return '';
		// need to loop parent subviews to find route
		foreach($this->parent->subviews as $viewAndRoute) {
			list($view, $route) = $viewAndRoute;
			if ($view === $this) {
				// insert parameters
				$params = $parameters !== null ? $parameters : $view->params;
				return $this->parent->url() . $route->create($params);
			}
		}
		throw new \Exception('Unable to find view in parent route when constructing url');
	}

	public function views()
	{
		if ($this->subviews === null) {
			$this->subviews = array();
			$this->initialize();
		}
		return $this->subviews;
	}

	public function view($name)
	{
		$views = $this->views();
		if (!isset($views[$name]))
			throw new \Exception('View not found: '.$name);
		return $views[$name];
	}

	public function __get($name)
	{
		$view = $this->view($name);
		return $view[0];
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
