<?php


namespace bru\api\Http;


use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{

	/**
	 * @var MessageInterface
	 * Сообщение
	 */
	private $message;

	/**
	 * @var int
	 * Код ответа
	 */
	private $statusCode;

	/**
	 * @var string
	 * Строка ответа
	 */
	private $reasonPhrase;

	/**
	 * @return string
	 */
	public function getProtocolVersion(): string
	{
		if (isset($this->message)) return $this->message->getProtocolVersion();
		return '';
	}

	/**
	 * @param string $version
	 * @return $this
	 */
	public function withProtocolVersion($version): self
	{
		if (isset($this->message)) {
			$this->message->withProtocolVersion($version);
			return $this;
		}

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
		if (isset($this->message)) return $this->message->getHeaderLine($name);
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
			$this->message->withAddedHeader($name, $value);
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
		if (isset($this->message))
		{
			$this->message->withoutHeader($name);
			return $this;
		}
		return $this;
	}

	/**
	 * @return Stream|StreamInterface
	 */
	public function getBody()
	{
		if (isset($this->message)) return $this->message->getBody();
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
	 * @return int
	 */
	public function getStatusCode(): int
	{
		if (isset($this->statusCode)) return $this->statusCode;
		return 0;
	}

	/**
	 * @param int $code
	 * @param string $reasonPhrase
	 * @return $this
	 */
	public function withStatus($code, $reasonPhrase = ''): self
	{
		//TODO добавить проверку кода
		$this->statusCode = $code;
		if (isset($reasonPhrase)) $this->reasonPhrase = $reasonPhrase;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getReasonPhrase(): string
	{
		if (isset($this->reasonPhrase)) return $this->reasonPhrase;
		return '';
	}
}