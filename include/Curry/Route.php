<?php

namespace Curry;

class Route implements RouteInterface {
	protected $pattern = '';
	protected $regex = '';
	protected $paramNames = array();

	public function __construct($pattern) {
		$paramNames = array();
		$this->pattern = $pattern;
		$this->regex = preg_replace_callback('@:([\w]+)@', function($m) use(&$paramNames) {
				$paramNames[] = $m[1];
				return '(?P<' . $m[1] . '>[^/]+)';
			}, $pattern);
		$this->paramNames = $paramNames;
	}

	public function match($url)
	{
		if (preg_match('@^'.$this->regex.'@', $url, $matches)) {
			$params = array();
			foreach($this->paramNames as $param) {
				$params[$param] = $matches[$param];
			}
			$rest = substr($url, strlen($matches[0]));
			return array($params, $rest);
		}
		return false;
	}

	public function create($params)
	{
		$replace = array();
		foreach($params as $name => $value) {
			$replace[":".$name] = urlencode($value);
		}
		return str_replace(array_keys($replace), $replace, $this->pattern);
	}
}