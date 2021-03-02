<?php

namespace bru\api\Http;

use http\Exception\InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{

	/**
	 * @var string
	 * Версия протокола
	 */
	private $protocolVersion;

	/**
	 * @var array
	 * Заголовки сообщения
	 */
	private $headers = [];
	/**
	 * @var StreamInterface
	 * Тело сообщения
	 */
	private $body;

	public function getProtocolVersion(): string
	{
		if (isset($this->protocolVersion)) return $this->protocolVersion;
		return '';
	}

	/**
	 * @param string $version
	 * @return Message
	 */
	public function withProtocolVersion($version): self
	{
		$this->protocolVersion = $version;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function hasHeader($name): bool
	{
		if (isset($this->headers[$name])) return true;
		return false;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	public function getHeader($name): array
	{
		if (isset($this->headers[$name])) {
			if (is_array($this->headers[$name])) return $this->headers[$name];
			if (is_string($this->headers[$name])) return [$this->headers[$name]];
		}
		return [];
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function getHeaderLine($name): string
	{
		if (!empty($this->headers))
		{
			return implode(',', $this->headers);
		}
		return '';
	}

	/**
	 * @param string $name
	 * @param string|string[] $value
	 * @return static
	 */
	public function withHeader($name, $value): self
	{
		if (!is_string($value) && !is_array($value)) throw new InvalidArgumentException('Значение заголовка должно быть строкой либо массивом');
		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * @param string $name
	 * @param string|string[] $value
	 * @return $this
	 */
	public function withAddedHeader($name, $value): self
	{
		if (!is_string($value) && !is_array($value)) throw new InvalidArgumentException('Значение заголовка должно быть строкой либо массивом');
		if (!isset($this->headers[$name])) $this->headers[$name] = $value;
		else {
			if (is_string($this->headers[$name]))
			{
				$temp = $this->headers[$name];
				$this->headers = [];
				$this->headers[$name][] = $temp;
				if (is_string($value)) $this->headers[$name][] = $value;
				if (is_array($value)) {
					foreach ($value as $header) {
						$this->headers[$name][] = $header;
					}
				}
			}
			elseif (is_array($this->headers[$name]))
			{
				if (is_string($value)) $this->headers[$name][] = $value;
				if (is_array($value)) {
					foreach ($value as $header) {
						$this->headers[$name][] = $header;
					}
				}
			}
		}
		return $this;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function withoutHeader($name): self
	{
		if (isset($this->headers[$name])) unset($this->headers[$name]);
		return $this;
	}

	/**
	 * @return object
	 */
	public function getBody(): object
	{
		if (isset($this->body)) return $this;
		return new Stream();
	}

	/**
	 * @param StreamInterface $body
	 * @return $this
	 */
	public function withBody(StreamInterface $body): self
	{
		//TODO добавить проверку $body c выбросом исключения
		$this->body = $body;
		return $this;
	}
}