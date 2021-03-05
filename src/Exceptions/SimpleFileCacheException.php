<?php

namespace bru\api\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException;

class SimpleFileCacheException extends Exception implements CacheException
{

}