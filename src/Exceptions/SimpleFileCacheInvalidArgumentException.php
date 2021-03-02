<?php

namespace bru\api\Exception;

use Exception;
use Psr\SimpleCache\InvalidArgumentException;

class SimpleFileCacheInvalidArgumentException extends Exception implements InvalidArgumentException
{

}