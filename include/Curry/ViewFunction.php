<?php

namespace Curry;

use Symfony\Component\HttpFoundation\Request;

class ViewFunction extends View {
	protected $callback;
	function __construct($callback)
	{
		$this->callback = $callback;
	}

	public function show(Request $request)
	{
		return call_user_func($this->callback, $request, $this);
	}
}