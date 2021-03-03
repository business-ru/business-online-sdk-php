<?php

namespace bru\api;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

final class Responce implements ResponseInterface
{
	use MessageTrait;

	private static $phrases = [
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		103 => 'Early Hints',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Payload Too Large',
		414 => 'URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		421 => 'Misdirected Request',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Too Early',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		451 => 'Unavailable For Legal Reasons',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required',
	];

	/**
	 * @var int
	 */
	private $statusCode;

	/**
	 * @var string
	 */
	private $reasonPhrase;


	/**
	 * @return int
	 */
	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	/**
	 * @param int $code
	 * @param string $reasonPhrase
	 * @return Responce
	 */
	public function withStatus($code, $reasonPhrase = ''): Responce
	{
		if (!is_int($code)) {
			if (!is_numeric($code) || is_float($code)) {
				throw new InvalidArgumentException(sprintf(
					'Response status code is not valid. It must be an integer, %s received.',
					(is_object($code) ? get_class($code) : gettype($code))
				));
			}
			$code = (int) $code;
		}

		if (!is_string($reasonPhrase)) {
			throw new InvalidArgumentException(sprintf(
				'Response reason phrase is not valid. It must be a string, %s received.',
				(is_object($reasonPhrase) ? get_class($reasonPhrase) : gettype($reasonPhrase))
			));
		}

		$new = clone $this;
		$new->setStatus($code, $reasonPhrase);
		return $new;
	}

	/**
	 * @return string
	 */
	public function getReasonPhrase(): string
	{
		return $this->reasonPhrase;
	}

	/**
	 * @param int $statusCode
	 * @param string $reasonPhrase
	 */
	private function setStatus(int $statusCode, string $reasonPhrase = ''): void
	{
		if ($statusCode < 100 || $statusCode > 599) {
			throw new InvalidArgumentException(sprintf(
				'Response status code "%d" is not valid. It must be in 100..599 range.',
				$statusCode
			));
		}

		$this->statusCode = $statusCode;
		$this->reasonPhrase = $reasonPhrase ?: (self::$phrases[$statusCode] ?? '');
	}
}