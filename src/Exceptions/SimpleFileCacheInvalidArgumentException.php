<?php

namespace bru\api\Exceptions;

use Exception;
use Psr\SimpleCache\InvalidArgumentException;

class SimpleFileCacheInvalidArgumentException extends Exception implements InvalidArgumentException
{

}