<?php

namespace Curry\Exception;

use Symfony\Component\HttpFoundation\Response;

class ResponseException extends \Exception {
	protected $response;

	public function __construct($content = '', $status = 200, $headers = array())
	{
		if ($content instanceof Response)
			$this->response = $content;
		else
			$this->response = new Response($content, $status, $headers);
	}

	public function getResponse()
	{
		return $this->response;
	}

}