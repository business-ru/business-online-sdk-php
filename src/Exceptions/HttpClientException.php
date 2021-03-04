<?php

namespace bru\api\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Exception;

class HttpClientException extends Exception implements ClientExceptionInterface
{

}