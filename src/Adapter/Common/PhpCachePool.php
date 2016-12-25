<?php

namespace Cache\Adapter\Common;

use Psr\Cache\CacheItemPoolInterface;

/**
 *
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface PhpCachePool extends CacheItemPoolInterface, TagAwarePool
{

}
