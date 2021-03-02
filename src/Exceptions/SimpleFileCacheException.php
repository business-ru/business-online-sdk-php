<?php

namespace bru\api\Exception;

use Exception;
use Psr\SimpleCache\CacheException;

class SimpleFileCacheException extends Exception implements CacheException
{

}