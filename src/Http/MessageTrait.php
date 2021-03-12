<?php


namespace bru\api\Http;


use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

trait MessageTrait
{
	/**
	 * @var string[]
	 */
	private static $supportedProtocolVersions = ['1.0', '1.1', '2.0', '2'];

	/**
	 * @var array
	 */
	private $headers = [];

	/**
	 * @var array
	 */
	private $headerNames = [];

	/**
	 * @var StreamInterface
	 */
	private $stream;

	/**
	 * @var string
	 */
	private $protocol;

	/**
	 * @return string
	 */
	public function getProtocolVersion(): string
	{
		return $this->protocol;
	}

	/**
	 * @param $version
	 * @return MessageInterface
	 */
	public function withProtocolVersion($version): MessageInterface
	{
		if ($version === $this->protocol) {
			return $this;
		}

		$this->validateProtocolVersion($version);
		$new = clone $this;
		$new->protocol = $version;
		return $new;
	}

	/**
	 * @return array
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function hasHeader($name): bool
	{
		return (is_string($name) && isset($this->headerNames[strtolower($name)]));
	}

	/**
	 * @param $name
	 * @return array
	 */
	public function getHeader($name): array
	{
		if (!$this->hasHeader($name)) {
			return [];
		}

		return $this->headers[$this->headerNames[strtolower($name)]];
	}

	/**
	 * @param $name
	 * @return string
	 */
	public function getHeaderLine($name): string
	{
		if (!$value = $this->getHeader($name)) {
			return '';
		}

		return implode(',', $value);
	}

	/**
	 * @param $name
	 * @param $value
	 * @return MessageInterface
	 */
	public function withHeader($name, $value): MessageInterface
	{
		$normalized = $this->normalizeHeaderName($name);
		$value = $this->normalizeHeaderValue($value);
		$new = clone $this;

		if (isset($new->headerNames[$normalized])) {
			unset($new->headers[$new->headerNames[$normalized]]);
		}

		$new->headerNames[$normalized] = $name;
		$new->headers[$name] = $value;
		return $new;
	}

	/**
	 * @param $name
	 * @param $value
	 * @return MessageInterface
	 */
	public function withAddedHeader($name, $value): MessageInterface
	{
		if (!$this->hasHeader($name)) {
			return $this->withHeader($name, $value);
		}

		$header = $this->headerNames[$this->normalizeHeaderName($name)];
		$value = $this->normalizeHeaderValue($value);

		$new = clone $this;
		$new->headers[$header] = array_merge($this->headers[$header], $value);
		return $new;
	}

	/**
	 * @param $name
	 * @return MessageInterface
	 */
	public function withoutHeader($name): MessageInterface
	{
		if (!$this->hasHeader($name)) {
			return $this;
		}

		$normalized = $this->normalizeHeaderName($name);
		$new = clone $this;
		unset($new->headers[$this->headerNames[$normalized]], $new->headerNames[$normalized]);
		return $new;
	}

	/**
	 * @return StreamInterface
	 */
	public function getBody(): StreamInterface
	{
		if ($this->stream === null) {
			$this->stream = new Stream();
		}

		return $this->stream;
	}

	/**
	 * @param StreamInterface $body
	 * @return MessageInterface
	 */
	public function withBody(StreamInterface $body): MessageInterface
	{
		if ($this->stream === $body) {
			return $this;
		}

		$new = clone $this;
		$new->stream = $body;
		return $new;
	}

	/**
	 * @param $stream
	 * @param string $mode
	 */
	private function registerStream($stream, string $mode = 'wb+'): void
	{
		if ($stream === null || $stream instanceof StreamInterface) {
			$this->stream = $stream;
			return;
		}

		if (is_string($stream) || is_resource($stream)) {
			$this->stream = new Stream($stream, $mode);
			return;
		}

		throw new InvalidArgumentException(sprintf(
			'Поток должен быть строкой - идентификатором потока, реализовывать интерфейс `Psr\Http\Message\StreamInterface`, 
			 ресурсом либо null. Получен -  `%s`.',
			(is_object($stream) ? get_class($stream) : gettype($stream))
		));
	}

	/**
	 * @param array $originalHeaders
	 */
	private function registerHeaders(array $originalHeaders = []): void
	{
		$this->headers = [];
		$this->headerNames = [];

		foreach ($originalHeaders as $name => $value) {
			$this->headerNames[$this->normalizeHeaderName($name)] = $name;
			$this->headers[$name] = $this->normalizeHeaderValue($value);
		}
	}

	/**
	 * @param string $protocol
	 */
	private function registerProtocolVersion(string $protocol): void
	{
		if (!empty($protocol) && $protocol !== $this->protocol) {
			$this->validateProtocolVersion($protocol);
			$this->protocol = $protocol;
		}
	}

	/**
	 * @param $name
	 * @return string
	 */
	private function normalizeHeaderName($name): string
	{
		if (!is_string($name) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
			throw new InvalidArgumentException(sprintf(
				'`%s` is not valid header name.',
				(is_object($name) ? get_class($name) : (is_string($name) ? $name : gettype($name)))
			));
		}

		return strtolower($name);
	}

	/**
	 * @param $value
	 * @return array
	 */
	private function normalizeHeaderValue($value): array
	{
		$value = is_array($value) ? array_values($value) : [$value];

		if (empty($value)) {
			throw new InvalidArgumentException(
				'Header value must be a string or an array of strings, empty array given.',
			);
		}

		foreach ($value as $v) {
			if ((!is_string($v) && !is_numeric($v)) || !preg_match('/^[ \t\x21-\x7E\x80-\xFF]*$/', (string) $v)) {
				throw new InvalidArgumentException(sprintf(
					'"%s" is not valid header value.',
					(is_object($v) ? get_class($v) : (is_string($v) ? $v : gettype($v)))
				));
			}
		}

		return $value;
	}

	/**
	 * @param $protocol
	 */
	private function validateProtocolVersion($protocol): void
	{
		if (!in_array($protocol, self::$supportedProtocolVersions, true)) {
			throw new InvalidArgumentException(sprintf(
				'Unsupported HTTP protocol version "%s" provided. The following strings are supported: "%s".',
				is_string($protocol) ? $protocol : gettype($protocol),
				implode('", "', self::$supportedProtocolVersions),
			));
		}
	}
}