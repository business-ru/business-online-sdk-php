<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest implements ServerRequestInterface
{
	use MessageTrait;

	/**
	 * @var array
	 */
	private $attributes = [];

	/**
	 * @var array
	 */
	private $cookieParams = [];

	/**
	 * @var array|object|null
	 */
	private $parsedBody;

	/**
	 * @var array
	 */
	private $queryParams;

	/**
	 * @var array
	 */
	private $serverParams;

	/**
	 * @var array
	 */
	private $uploadedFiles;

	/**
	 * @var string
	 */
	private $method = 'GET';

	/**
	 * @var UriInterface
	 */
	private $uri;

	/**
	 * @var null
	 */
	private $requestTarget = null;

	public function __construct(
		array $serverParams = [],
		array $uploadedFiles = [],
		array $cookieParams = [],
		array $queryParams = [],
		$parsedBody = null,
		string $method = 'GET',
		$uri = '',
		array $headers = [],
		$body = null,
		string $protocol = '1.1'
	)
	{
		$this->validateUploadedFiles($uploadedFiles);
		$this->uploadedFiles = $uploadedFiles;
		$this->serverParams = $serverParams;
		$this->cookieParams = $cookieParams;
		$this->queryParams = $queryParams;
		$this->parsedBody = $parsedBody;
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
	 * @return string
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
	 * @return $this
	 */
	public function withRequestTarget($requestTarget): ServerRequest
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
	 * @return string
	 */
	public function withMethod($method): RequestInterface
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

	public function getUri(): UriInterface
    {
		return $this->uri;
	}

	/**
	 * @param UriInterface $uri
	 * @param false $preserveHost
	 * @return $this
	 */
	public function withUri(UriInterface $uri, $preserveHost = false): ServerRequest
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

	/**
	 * @return array
	 */
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	/**
	 * @return array
	 */
	public function getCookieParams(): array
	{
		return $this->cookieParams;
	}

	/**
	 * @param array $cookies
	 * @return ServerRequest
	 */
	public function withCookieParams(array $cookies): ServerRequest
	{
		$new = clone $this;
		$new->cookieParams = $cookies;
		return $new;
	}

	/**
	 * @return array
	 */
	public function getQueryParams(): array
	{
		return $this->queryParams;
	}

	/**
	 * @param array $query
	 * @return ServerRequest
	 */
	public function withQueryParams(array $query): ServerRequest
	{
		$new = clone $this;
		$new->queryParams = $query;
		return $new;
	}

	/**
	 * @return array
	 */
	public function getUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * @param array $uploadedFiles
	 * @return ServerRequest
	 */
	public function withUploadedFiles(array $uploadedFiles): ServerRequest
	{
		$this->validateUploadedFiles($uploadedFiles);
		$new = clone $this;
		$new->uploadedFiles = $uploadedFiles;
		return $new;
	}

	/**
	 * @return array|mixed|object|null
	 */
	public function getParsedBody()
	{
		return $this->parsedBody;
	}

	/**
	 * @param array|object|null $data
	 * @return ServerRequest
	 */
	public function withParsedBody($data): ServerRequest
	{
		if (!is_array($data) && !is_object($data) && $data !== null) {
			throw new InvalidArgumentException(sprintf(
				'Неверныe параметры запроса - "%s". Параметры запроса должны быть объектом, массивом либо null.',
				gettype($data)
			));
		}

		$new = clone $this;
		$new->parsedBody = $data;
		return $new;
	}

	public function getAttributes(): array
    {
		return $this->attributes;
	}

	/**
	 * @param string $name
	 * @param null $default
	 * @return mixed|null
	 */
	public function getAttribute($name, $default = null)
	{
		if (array_key_exists($name, $this->attributes)) {
			return $this->attributes[$name];
		}

		return $default;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	public function withAttribute($name, $value): ServerRequest
	{
		if (array_key_exists($name, $this->attributes) && $this->attributes[$name] === $value) {
			return $this;
		}

		$new = clone $this;
		$new->attributes[$name] = $value;
		return $new;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function withoutAttribute($name): ServerRequest
	{
		if (!array_key_exists($name, $this->attributes)) {
			return $this;
		}

		$new = clone $this;
		unset($new->attributes[$name]);
		return $new;
	}

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

	/**
	 * @param array $uploadedFiles
	 */
	private function validateUploadedFiles(array $uploadedFiles): void
	{
		foreach ($uploadedFiles as $file) {
			if (is_array($file)) {
				$this->validateUploadedFiles($file);
				continue;
			}

			if (!UploadedFileInterface instanceof $file) {
				throw new InvalidArgumentException(sprintf(
					'Неверный объект в структуре загружаемых файлов.'
					. '"%s" не реализует интерфейс "\Psr\Http\Message\UploadedFileInterface".',
					(is_object($file) ? get_class($file) : gettype($file))
				));
			}
		}
	}
}
