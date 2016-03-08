<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Chain;

use Cache\Taggable\TaggablePoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements CacheItemPoolInterface, TaggablePoolInterface
{
    /**
     * @type CacheItemPoolInterface[]
     */
    private $pools;
    /** @type bool */
    private $skipOnFailure;

    /**
     * @param array $pools
     * @param bool  $skipOnFailure
     */
    public function __construct(array $pools, $skipOnFailure = false)
    {
        $this->pools         = $pools;
        $this->skipOnFailure = $skipOnFailure;
        if (empty($this->pools)) {
            throw new \LogicException('At least one pool is required for the chain.');
        }
    }

    /**
     * @return array|\Psr\Cache\CacheItemPoolInterface[]
     */
    public function getPools()
    {
        if (empty($this->pools)) {
            throw new \LogicException('No valid cache pool available for the chain.');
        }

        return $this->pools;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $found     = false;
        $result    = null;
        $needsSave = [];

        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $item = $pool->getItem($key);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
                break;
            }
            if ($item->isHit()) {
                $found  = true;
                $result = $item;
                break;
            }

            $needsSave[] = $pool;
        }

        if ($found) {
            foreach ($needsSave as $pool) {
                $pool->save($result);
            }

            $item = $result;
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $hits  = [];
        $items = [];
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $items = $pool->getItems($keys);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
                break;
            }
            /** @type CacheItemInterface $item */
            foreach ($items as $item) {
                if ($item->isHit()) {
                    $hits[$item->getKey()] = $item;
                }
            }

            if (count($hits) === count($keys)) {
                return $hits;
            }
        }

        // We need to accept that some items where not hits.
        return array_merge($hits, $items);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                if ($pool->hasItem($key)) {
                    return true;
                }
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
                break;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->clear();
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->deleteItem($key);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->deleteItems($keys);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->save($item);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->saveDeferred($item);
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $result && $pool->commit();
            } catch (\Exception $e) {
                if (!$this->skipOnFailure) {
                    throw $e;
                }
                unset($this->pools[$poolKey]);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clearTags(array $tags)
    {
        $result = true;
        foreach ($this->getPools() as $poolKey => $pool) {
            if ($pool instanceof TaggablePoolInterface) {
                try {
                    $result = $result && $pool->clearTags($tags);
                } catch (\Exception $e) {
                    if (!$this->skipOnFailure) {
                        throw $e;
                    }
                    unset($this->pools[$poolKey]);
                }
            }
        }

        return $result;
    }
}
