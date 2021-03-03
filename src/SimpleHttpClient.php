<?php

namespace bru\api;


use bru\api\Http\Responce;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SimpleHttpClient implements ClientInterface
{

	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{


		$responce = new Responce();
		return $responce;
	}
}