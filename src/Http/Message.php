<?php

namespace bru\api\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{

	public function getProtocolVersion()
	{
		// TODO: Implement getProtocolVersion() method.
	}

	public function withProtocolVersion($version)
	{
		// TODO: Implement withProtocolVersion() method.
	}

	public function getHeaders()
	{
		// TODO: Implement getHeaders() method.
	}

	public function hasHeader($name)
	{
		// TODO: Implement hasHeader() method.
	}

	public function getHeader($name)
	{
		// TODO: Implement getHeader() method.
	}

	public function getHeaderLine($name)
	{
		// TODO: Implement getHeaderLine() method.
	}

	public function withHeader($name, $value)
	{
		// TODO: Implement withHeader() method.
	}

	public function withAddedHeader($name, $value)
	{
		// TODO: Implement withAddedHeader() method.
	}

	public function withoutHeader($name)
	{
		// TODO: Implement withoutHeader() method.
	}

	public function getBody()
	{
		// TODO: Implement getBody() method.
	}

	public function withBody(StreamInterface $body)
	{
		// TODO: Implement withBody() method.
	}
}