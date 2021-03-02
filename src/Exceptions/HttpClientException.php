<?php

namespace bru\api\Exception;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

class HttpClientException extends Exception implements ClientExceptionInterface
{

}