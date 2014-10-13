<?php

namespace Curry;

interface RouteInterface {
	public function match($url);
	public function create($params);
}