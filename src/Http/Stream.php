<?php


namespace bru\api\Http;


use http\Exception\RuntimeException;
use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
	/**
	 * @var StreamInterface
	 * Поток данных
	 */
	private $stream;

	/**
	 * @var string
	 * Модификатор доступа
	 */
	private $mode;

	/**
	 * @var array
	 * Метаданные потока
	 */
	private $metadata;

	/**
	 * Stream constructor.
	 * @param StreamInterface $stream
	 * @param string $mode
	 */
	public function __construct(StreamInterface $stream, string $mode)
	{
		//TODO добавить проверку $mode

		$this->stream = $stream;
		$this->mode = $mode;
		$this->metadata = stream_get_meta_data($this->stream);
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		$this->stream->seek(0);
		return $this->stream->getContents();
	}

	public function close()
	{
		// TODO: Implement close() method.
	}

	public function detach()
	{
		// TODO: Implement detach() method.
	}

	public function getSize()
	{
		// TODO: Implement getSize() method.
	}

	public function tell()
	{
		// TODO: Implement tell() method.
	}

	public function eof()
	{
		// TODO: Implement eof() method.
	}

	public function isSeekable()
	{
		// TODO: Implement isSeekable() method.
	}

	public function seek($offset, $whence = SEEK_SET)
	{
		// TODO: Implement seek() method.
	}

	public function rewind()
	{
		// TODO: Implement rewind() method.
	}

	public function isWritable()
	{
		// TODO: Implement isWritable() method.
	}

	public function write($string)
	{
		// TODO: Implement write() method.
	}

	public function isReadable()
	{
		// TODO: Implement isReadable() method.
	}

	public function read($length)
	{
		// TODO: Implement read() method.
	}


	/**
	 * @return false|string
	 */
	public function getContents()
	{
		if ($c = stream_get_contents($this->stream)) return $c;
		throw new RuntimeException('Нет прав для чтения потока');
		return '';
	}


	/**
	 * @param null $key
	 * @return array|mixed|null
	 */
	public function getMetadata($key = null)
	{
		if (is_null($key)) return $this->metadata;
		if (isset($this->metadata[$key])) return $this->metadata[$key];
		return null;
	}
}