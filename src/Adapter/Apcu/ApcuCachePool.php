<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu;

use Cache\Adapter\Common\AbstractCachePool;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ApcuCachePool extends AbstractCachePool
{
    /**
     * @type bool
     */
    private $skipOnCli;

    /**
     * @param bool $skipOnCli
     */
    public function __construct($skipOnCli = false)
    {
        $this->skipOnCli = $skipOnCli;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        if ($this->skipIfCli()) {
            return [false, null, []];
        }

        $success = false;
        $data    = apcu_fetch($key, $success);

        return [$success, $data, []];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return apcu_clear_cache();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        apcu_delete($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        if ($this->skipIfCli()) {
            return false;
        }

        if ($ttl < 0) {
            return false;
        }

        return apcu_store($item->getKey(), $item->get(), $ttl);
    }

    /**
     * Returns true if CLI and if it should skip on cli.
     *
     * @return bool
     */
    private function skipIfCli()
    {
        return php_sapi_name() === 'cli' && $this->skipOnCli;
    }
}
