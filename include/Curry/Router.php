<?php

namespace Curry;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

class Router implements ControllerResolverInterface {
	protected $routes = array();

	public function add(RequestMatcherInterface $route, $controller)
	{
		$this->routes[] = (object)array('route' => $route, 'controller' => $controller);
	}

	public function getArguments(Request $request, $controller)
	{
		return array();
	}

	public function getController(Request $request)
	{
		foreach($this->routes as $route) {
			if ($route->route->match($request))
				return $route->controller;
		}
		return false;
	}
}