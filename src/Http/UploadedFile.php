<?php


namespace bru\api\Http;


use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{

	public function getStream()
	{
		// TODO: Implement getStream() method.
	}

	public function moveTo($targetPath)
	{
		// TODO: Implement moveTo() method.
	}

	public function getSize()
	{
		// TODO: Implement getSize() method.
	}

	public function getError()
	{
		// TODO: Implement getError() method.
	}

	public function getClientFilename()
	{
		// TODO: Implement getClientFilename() method.
	}

	public function getClientMediaType()
	{
		// TODO: Implement getClientMediaType() method.
	}
}