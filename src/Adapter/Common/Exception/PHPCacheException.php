<?php

namespace Cache\Adapter\Common\Exception;

use Psr\Cache\CacheException;

/**
 * A base exception. All exceptions in this organization will extend this exception. 
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PHPCacheException extends \RuntimeException implements CacheException
{

}