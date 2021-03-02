<?php

namespace bru\api\Exception;

use Exception;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

class HttpRequestException extends Exception implements RequestExceptionInterface
{

	public function getRequest(): RequestInterface
	{
		// TODO: Implement getRequest() method.
	}
}