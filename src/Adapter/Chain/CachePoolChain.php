<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Adapter\Chain;

use Cache\Adapter\Chain\Exception\NoPoolAvailableException;
use Cache\Adapter\Chain\Exception\PoolFailedException;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Taggable\TaggablePoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements CacheItemPoolInterface, TaggablePoolInterface, LoggerAwareInterface
{
    /**
     * @type LoggerInterface
     */
    private $logger;

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
     * @param array $options {
     *
     *     @type bool $skip_on_failure If true we will remove a pool form the chain if it fails.
     * }
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
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'skip_on_failure' => false,
        ]);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
                $result = $pool->save($item) && $result;
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
                $result = $pool->saveDeferred($item) && $result;
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
                $result = $pool->commit() && $result;
            } catch (CachePoolException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
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
                    $result = $pool->clearTags($tags) && $result;
                } catch (CachePoolException $e) {
                    $this->handleException($poolKey, __FUNCTION__, $e);
                }
            }
        }

        return $result;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs with an arbitrary level if the logger exists.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    protected function log($level, $message, array $context = [])
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * @return array|\Psr\Cache\CacheItemPoolInterface[]
     */
    protected function getPools()
    {
        if (empty($this->pools)) {
            throw new NoPoolAvailableException('No valid cache pool available for the chain.');
        }

        return $this->pools;
    }

    /**
     * @param string             $poolKey
     * @param string             $operation
     * @param CachePoolException $exception
     *
     * @throws PoolFailedException
     */
    private function handleException($poolKey, $operation, CachePoolException $exception)
    {
        if (!$this->options['skip_on_failure']) {
            throw $exception;
        }

        $this->log(
            'warning',
            sprintf('Removing pool "%s" from chain because it threw an exception when executing "%s"', $poolKey, $operation),
            ['exception' => $exception]
        );

        unset($this->pools[$poolKey]);
    }
}
