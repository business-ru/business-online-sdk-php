<?php

namespace bru\api\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use Exception;

class HttpClientException extends Exception implements ClientExceptionInterface
{

}