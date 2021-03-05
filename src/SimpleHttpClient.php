<?php

namespace bru\api;

use bru\api\Exceptions\HttpClientException;
use bru\api\Http\Responce;
use bru\api\Http\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class SimpleHttpClient implements ClientInterface
{

	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 * @throws HttpClientException
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$c = curl_init();

		$url = (string)$request->getUri();
		$method = strtoupper($request->getMethod());

		$params_string = $request->getUri()->getQuery();

		if ($method !== 'GET')
		{
			$params_string = $request->getBody()->getContents();
		}
		if ($method === 'POST') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		} else if ($method === 'GET') {
			curl_setopt($c, CURLOPT_URL, $url . '?' . $params_string);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		} else if ($method === 'PUT') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		} else if ($method === 'DELETE') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		} else {
			throw new HttpClientException('Метод ' . $method . ' не поддерживается.');
		}


		$result = curl_exec($c);

		$stream = new Stream('php://temp/bruapi/response', 'w+');
		$stream->write($result);

		$status_code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);

		$responce = new Responce();

		$responce = $responce->withStatus($status_code);
		$responce = $responce->withBody($stream);

		curl_close($c);

		return $responce;
	}
}