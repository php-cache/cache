<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Hierarchy;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait HierarchicalArrayCachePoolTrait
{
    use HierarchicalCachePoolTrait;

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     * With this overwrite we'll return array as keys.
     *
     * @param string $key      The original key
     *
     * @return array
     */
    protected function getHierarchyKey($key)
    {
        if (!$this->isHierarchyKey($key)) {
            return [$key];
        }

        return $this->explodeKey($key);
    }
}
