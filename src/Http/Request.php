<?php


namespace bru\api\Http;


use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface
{

	/**
	 * @var MessageInterface
	 * Сообщение
	 */
	private $message;

	/**
	 * @var string
	 * Адрес запроса
	 */
	private $requestTarget;

	/**
	 * @var string
	 * Метод запроса
	 */
	private $method;

	/**
	 * @var UriInterface
	 * URI запроса
	 */
	private $uri;

	/**
	 * @return string
	 */
	public function getProtocolVersion(): string
	{
		if (isset($this->message)) return $this->message->getProtocolVersion();
		return '';
	}

	public function withProtocolVersion($version): self
	{
		if (isset($this->message)) $this->message->withProtocolVersion($version);
		return $this;
	}

	/**
	 * @return array
	 */
	public function getHeaders(): array
	{
		if (isset($this->message)) return $this->message->getHeaders();
		return [];
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function hasHeader($name): bool
	{
		if (isset($this->message) && $this->message->hasHeader($name)) return true;
		return false;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	public function getHeader($name): array
	{
		if (isset($this->message)) return $this->message->getHeader($name);
		return [];
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function getHeaderLine($name): string
	{
		if (isset($this->message)) return $this->message->getHeaderLine();
		return '';
	}

	/**
	 * @param string $name
	 * @param string|string[] $value
	 * @return $this
	 */
	public function withHeader($name, $value): self
	{
		if (isset($this->message)) {
			$this->message->withHeader($name, $value);
			return $this;
	}
		return $this;
	}

	/**
	 * @param string $name
	 * @param string|string[] $value
	 * @return $this
	 */
	public function withAddedHeader($name, $value): self
	{
		if (isset($this->message)) {
			$this->message->withHeader($name, $value);
			return $this;
		}
		return $this;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function withoutHeader($name): self
	{
		if (isset($this->message)) {
			$this->message->withoutHeader($name);
			return $this;
		}
		return $this;
	}

	/**
	 * @return object
	 */
	public function getBody(): object
	{
		if (isset($this->message)) {
			return $this->message->getBody();
		}
		return new Stream();
	}

	/**
	 * @param StreamInterface $body
	 * @return $this
	 */
	public function withBody(StreamInterface $body): self
	{
		if (isset($this->message)) {
			$this->message->withBody($body);
			return $this;
		}
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRequestTarget(): string
	{
		if (!isset($this->requestTarget)) return '';
		return $this->requestTarget;
	}

	/**
	 * @param mixed $requestTarget
	 * @return $this
	 */
	public function withRequestTarget($requestTarget): self
	{
		$this->requestTarget = $requestTarget;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		if (isset($this->method)) return $this->method;
		return '';
	}

	/**
	 * @param string $method
	 * @return $this
	 */
	public function withMethod($method): self
	{
		$this->method = $method;
		return $this;
	}

	/**
	 * @return UriInterface
	 */
	public function getUri(): UriInterface
	{
		if (isset($this->uri)) return $this->uri;
		return new Uri();
	}

	/**
	 * @param UriInterface $uri
	 * @param false $preserveHost
	 * @return $this
	 */
	public function withUri(UriInterface $uri, $preserveHost = false): self
	{
		//TODO добавить обработку $preserveHost
		$this->uri = $uri;
		return $this;
	}
}