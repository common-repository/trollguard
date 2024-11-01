<?php

/**
 * The response from a HttpRequest
 */
class HttpResponse
{
	function HttpResponse($s, $e, $h, $c)
	{
		assert(is_bool($s));
		assert(is_string($e));
		assert(is_string($h));
		assert(is_string($c));

		$this->success = $s;
		$this->errorMessage = $e;
		$this->headers = $h;
		$this->content = $c;
	}

	var $success;
	var $errorMessage;
	var $headers;
	var $content;
}

/**
 * Abstraction for a HTTP request.
 */
class HttpRequest 
{
	function request($url, $useGet, $headers, $content='')
	{
		die('HttpRequest::request should not be called. Call the method of the subclass.');
	}
}

?>