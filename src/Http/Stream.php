<?php

namespace bru\api;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{

	/**
	 * @var resource|null
	 */
	private $resource;

	/**
	 * Stream constructor.
	 * @param $stream
	 * @param string $mode
	 */
	public function __construct($stream = 'php://temp', string $mode = 'wb+')
	{
		if (is_string($stream)) {
			$stream = ($stream === '') ? false : @fopen($stream, $mode);

			if ($stream === false) {
				throw new RuntimeException('Невозможно открыть поток');
			}
		}

		if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
			throw new InvalidArgumentException(
				'Поток должен быть передан в виде идентификатора потока или ресурса');
		}

		$this->resource = $stream;
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		if ($this->isSeekable()) {
			$this->rewind();
		}

		return $this->getContents();
	}

	public function close(): void
	{
		if ($this->resource) {
			$resource = $this->detach();
			fclose($resource);
		}
	}

	/**
	 * @return resource|null
	 */
	public function detach()
	{
		$resource = $this->resource;
		$this->resource = null;
		return $resource;
	}

	/**
	 * @return int|null
	 */
	public function getSize(): ?int
	{
		if ($this->resource === null) {
			return null;
		}

		$stats = fstat($this->resource);
		return isset($stats['size']) ? (int) $stats['size'] : null;
	}

	public function tell()
	{
		if (!$this->resource) {
			throw new RuntimeException('Нет ресурса для указания текущей позиции');
		}

		if (!is_int($result = ftell($this->resource))) {
			throw new RuntimeException('Ошибка определения позиции указателя');
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public function eof(): bool
	{
		return (!$this->resource || feof($this->resource));
	}

	/**
	 * @return bool
	 */
	public function isSeekable(): bool
	{
		return ($this->resource && $this->getMetadata('seekable'));
	}

	/**
	 * @param int $offset
	 * @param int $whence
	 */
	public function seek($offset, $whence = SEEK_SET): void
	{
		if (!$this->resource) {
			throw new RuntimeException('Нет ресурса для изменения позиции указателя');
		}

		if (!$this->isSeekable()) {
			throw new RuntimeException('Stream is not seekable.');
		}

		if (fseek($this->resource, $offset, $whence) !== 0) {
			throw new RuntimeException('Error seeking within stream.');
		}
	}

	public function rewind(): void
	{
		$this->seek(0);
	}

	/**
	 * @return bool
	 */
	public function isWritable(): bool
	{
		if (!is_string($mode = $this->getMetadata('mode'))) {
			return false;
		}

		return (
			strpos($mode, 'w') !== false
			|| strpos($mode, '+') !== false
			|| strpos($mode, 'x') !== false
			|| strpos($mode, 'c') !== false
			|| strpos($mode, 'a') !== false
		);
	}

	/**
	 * @param string $string
	 * @return false|int
	 */
	public function write($string)
	{
		if (!$this->resource) {
			throw new RuntimeException('Нет ресурса для записи');
		}

		if (!$this->isWritable()) {
			throw new RuntimeException('Невозможно записать данные в поток');
		}

		if (!is_int($result = fwrite($this->resource, $string))) {
			throw new RuntimeException('Ошибка записи в поток');
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public function isReadable(): bool
	{
		if (!is_string($mode = $this->getMetadata('mode'))) {
			return false;
		}

		return (strpos($mode, 'r') !== false || strpos($mode, '+') !== false);
	}

	/**
	 * @param int $length
	 * @return false|string
	 */
	public function read($length)
	{
		if (!$this->resource) {
			throw new RuntimeException('Нет ресурса для чтения');
		}

		if (!$this->isReadable()) {
			throw new RuntimeException('Невозможно прочитать данные из потока');
		}

		if (!is_string($result = fread($this->resource, $length))) {
			throw new RuntimeException('Ошибка чтения из потока');
		}

		return $result;
	}

	/**
	 * @return false|string
	 */
	public function getContents()
	{
		if (!$this->isReadable()) {
			throw new RuntimeException('Невозможно прочитать данные из потока');
		}

		if (!is_string($result = stream_get_contents($this->resource))) {
			throw new RuntimeException('Error reading stream.');
		}

		return $result;
	}

	/**
	 * @param null $key
	 * @return array|mixed|null
	 */
	public function getMetadata($key = null)
	{
		if (!$this->resource) {
			return $key ? null : [];
		}

		$metadata = stream_get_meta_data($this->resource);

		if ($key === null) {
			return $metadata;
		}

		if (array_key_exists($key, $metadata)) {
			return $metadata[$key];
		}

		return null;
	}
}