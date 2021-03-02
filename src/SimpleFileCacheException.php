<?php

namespace bru\api;

use Exception;
use Psr\SimpleCache\CacheException;

class SimpleFileCacheException extends Exception implements CacheException
{

}