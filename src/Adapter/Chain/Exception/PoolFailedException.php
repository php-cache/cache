<?php


namespace Cache\Adapter\Chain\Exception;

use Cache\Adapter\Common\Exception\PHPCacheException;

/**
 * When a cache pool fails with its operation.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PoolFailedException extends PHPCacheException
{

}