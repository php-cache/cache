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
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements CacheItemPoolInterface, TaggablePoolInterface
{
    /**
     * @type CacheItemPoolInterface[]
     */
    private $pools;
    /**
     * @type array
     */
    private $options;

    /**
     * @param array $pools
     * @param array $options
     */
    public function __construct(array $pools, array $options = [])
    {
        $this->pools   = $pools;
        $this->options = $options;

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    /**
     * @param mixed      $poolKey
     * @param \Exception $exception
     *
     * @throws \Exception
     */
    private function onPoolException($poolKey, \Exception $exception)
    {
        if (!$this->options['skip_on_failure']) {
            throw $exception;
        }
        unset($this->pools[$poolKey]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'skip_on_failure' => false,
        ]);
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

                if ($item->isHit()) {
                    $found  = true;
                    $result = $item;
                    break;
                }

                $needsSave[] = $pool;
            } catch (\Exception $e) {
                $this->onPoolException($poolKey, $e);
            }
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

                /** @type CacheItemInterface $item */
                foreach ($items as $item) {
                    if ($item->isHit()) {
                        $hits[$item->getKey()] = $item;
                    }
                }

                if (count($hits) === count($keys)) {
                    return $hits;
                }
            } catch (\Exception $e) {
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                $this->onPoolException($poolKey, $e);
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
                    $this->onPoolException($poolKey, $e);
                }
            }
        }

        return $result;
    }
}
