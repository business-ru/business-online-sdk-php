<?php

namespace bru\api\Exception;

use Exception;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

class HttpNetworkException extends Exception implements NetworkExceptionInterface
{

	public function getRequest(): RequestInterface
	{
		// TODO: Implement getRequest() method.
	}
}