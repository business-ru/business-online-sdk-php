<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class Request implements RequestInterface
{
	use MessageTrait;

	/**
	 * @var string
	 */
	private $method = 'GET';

	/**
	 * @var string|null
	 */
	private $requestTarget = null;

	/**
	 * @var UriInterface
	 */
	private $uri;

	/**
	 * Request constructor.
	 * @param string $method
	 * @param string $uri
	 * @param array $headers
	 * @param null $body
	 * @param string $protocol
	 */
	public function __construct(string $method = 'GET', $uri = '', array $headers = [], $body = null, string $protocol = '1.1')
	{
		$this->method = $method;
		$this->setUri($uri);
		$this->registerStream($body);
		$this->registerHeaders($headers);
		$this->registerProtocolVersion($protocol);

		if (!$this->hasHeader('host')) {
			$this->updateHostHeaderFromUri();
		}
	}

	/**
	 * @param $uri
	 */
	private function setUri($uri): void
	{
		if ($uri instanceof UriInterface) {
			$this->uri = $uri;
			return;
		}

		if (is_string($uri)) {
			$this->uri = new Uri($uri);
			return;
		}

		throw new InvalidArgumentException(sprintf(
			'Неверный формат URI - "%s". URI должен быть строкой, null либо реализовывать интерфейс "\Psr\Http\Message\UriInterface".',
			(is_object($uri) ? get_class($uri) : gettype($uri))
		));
	}

	/**
	 * @return string|null
	 */
	public function getRequestTarget(): string
	{
		if ($this->requestTarget !== null) {
			return $this->requestTarget;
		}

		$target = $this->uri->getPath();
		$query = $this->uri->getQuery();

		if ($target !== '' && $query !== '') {
			$target .= '?' . $query;
		}

		return $target ?: '/';
	}

	/**
	 * @param mixed $requestTarget
	 * @return $this|Request
	 */
	public function withRequestTarget(string $requestTarget): RequestInterface
    {
		if ($requestTarget === $this->requestTarget) {
			return $this;
		}

		if (!is_string($requestTarget) || preg_match('/\s/', $requestTarget)) {
			throw new InvalidArgumentException(sprintf(
				'Неверная цель запроса - "%s". Цель запроса должна быть строкой и не может содержать пробелы',
				(is_object($requestTarget) ? get_class($requestTarget) : gettype($requestTarget))
			));
		}

		$new = clone $this;
		$new->requestTarget = $requestTarget;
		return $new;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @param string $method
	 * @return $this
	 */
	public function withMethod($method): Request
	{
		if ($method === $this->method) {
			return $this;
		}

		if (!is_string($method)) {
			throw new InvalidArgumentException(sprintf(
				'Неверный метод. Метод должен быть строкой, получен - %s.',
				(is_object($method) ? get_class($method) : gettype($method))
			));
		}

		$new = clone $this;
		$new->method = $method;
		return $new;
	}

	/**
	 * @return UriInterface
	 */
	public function getUri(): UriInterface
	{
		return $this->uri;
	}

	/**
	 * @param UriInterface $uri
	 * @param false $preserveHost
	 * @return $this
	 */
	public function withUri(UriInterface $uri, $preserveHost = false): Request
	{
		if ($uri === $this->uri) {
			return $this;
		}

		$new = clone $this;
		$new->uri = $uri;

		if (!$preserveHost || !$this->hasHeader('host')) {
			$new->updateHostHeaderFromUri();
		}

		return $new;
	}

	private function updateHostHeaderFromUri(): void
	{
		$host = $this->uri->getHost();

		if ($host === '') {
			return;
		}

		if ($port = $this->uri->getPort()) {
			$host .= ':' . $port;
		}

		$this->headerNames['host'] = 'Host';
		$this->headers = [$this->headerNames['host'] => [$host]] + $this->headers;
	}
}
