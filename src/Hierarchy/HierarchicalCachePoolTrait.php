<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Hierarchy;

use Cache\Taggable\TaggablePoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait HierarchicalCachePoolTrait
{
    /**
     * A temporary cache for keys.
     *
     * @type array
     */
    private $keyCache = [];

    /**
     * Get a value form the store. This must not be an PoolItemInterface.
     *
     * @param string $key
     *
     * @return string|null
     */
    abstract protected function getValueFormStore($key);

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     *
     * @param string $key      The original key
     * @param string &$pathKey A cache key for the path. If this key is changed everything beyond that path is changed.
     *
     * @return string
     */
    protected function getHierarchyKey($key, &$pathKey = null)
    {
        if (!$this->isHierarchyKey($key)) {
            return $key;
        }

        $key = $this->explodeKey($key);

        $keyString = '';
        // The comments below is for a $key = ["foo!tagHash", "bar!tagHash"]
        foreach ($key as $name) {
            // 1) $keyString = "foo!tagHash"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash"
            $keyString .= $name;
            $pathKey = sha1('path'.TaggablePoolInterface::TAG_SEPARATOR.$keyString);

            if (isset($this->keyCache[$pathKey])) {
                $index = $this->keyCache[$pathKey];
            } else {
                $index                    = $this->getValueFormStore($pathKey);
                $this->keyCache[$pathKey] = $index;
            }

            // 1) $keyString = "foo!tagHash![foo_index]!"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"
            $keyString .= TaggablePoolInterface::TAG_SEPARATOR.$index.TaggablePoolInterface::TAG_SEPARATOR;
        }

        // Assert: $pathKey = "path!foo!tagHash![foo_index]!bar!tagHash"
        // Assert: $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"

        // Make sure we do not get awfully long (>250 chars) keys
        return sha1($keyString);
    }

    /**
     * Clear the cache for the keys.
     */
    protected function clearHierarchyKeyCache()
    {
        $this->keyCache = [];
    }

    /**
     * A hierarchy key MUST begin with the separator.
     *
     * @param string $key
     *
     * @return bool
     */
    private function isHierarchyKey($key)
    {
        return substr($key, 0, 1) === HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
    }

    /**
     * This will take a hierarchy key ("|foo|bar") with tags ("|foo|bar!tagHash") and return an array with
     * each level in the hierarchy appended with the tags. ["foo!tagHash", "bar!tagHash"].
     *
     * @param string $key
     *
     * @return array
     */
    private function explodeKey($string)
    {
        list($key, $tag) = explode(TaggablePoolInterface::TAG_SEPARATOR, $string.TaggablePoolInterface::TAG_SEPARATOR);

        if ($key === HierarchicalPoolInterface::HIERARCHY_SEPARATOR) {
            $parts = ['root'];
        } else {
            $parts = explode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $key);
            // remove first element since it is always empty and replace it with 'root'
            $parts[0] = 'root';
        }

        return array_map(function ($level) use ($tag) {
            return $level.TaggablePoolInterface::TAG_SEPARATOR.$tag;
        }, $parts);
    }
}
