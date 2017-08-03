<?php

namespace Cache\Adapter\Chain;

use Cache\Adapter\Chain\Exception\NoPoolAvailableException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

class SimpleCacheChain implements CacheInterface
{
    /**
     * @type CacheInterface[]
     */
    private $pools;
    
    /**
     * @type array
     */
    private $options;
    
    /**
     * @type LoggerInterface
     */
    private $logger;
    
    /**
     * @param array $pools
     * @param array $options {
     * @type bool $skip_on_failure If true we will remove a pool form the chain if it fails.
     *                       }
     * @param LoggerInterface|null $logger
     * @throws NoPoolAvailableException
     */
    public function __construct(array $pools, array $options = [], LoggerInterface $logger = null)
    {
        if (empty($pools)) {
            throw new NoPoolAvailableException('No valid cache pool available for the chain.');
        }
        
        $this->pools = $pools;
        
        if (!isset($options['skip_on_failure'])) {
            $options['skip_on_failure'] = false;
        }
        
        $this->options = $options;
        $this->logger = $logger ?: new NullLogger();
    }
    
    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $value = $pool->get($key, $default);
                
                if ($value !== $default) {
                    return $value;
                }
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
        
        return $default;
    }
    
    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        $result = true;
        
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $result = $pool->set($key, $value, $ttl) && $result;
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return true;
    }
    
    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        $result = true;
        
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $result = $result && $pool->delete($key);
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return $result;
    }
    
    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     */
    public function clear()
    {
        $result = true;
        
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $result = $result && $pool->clear();
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return $result;
    }
    
    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $values = [];
        
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $values[] = $pool->getMultiple($keys, $default);
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        // Flatten multi-dimensional array of values.
        return call_user_func_array('array_merge', $values);
    }
    
    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $result = true;
    
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $result = $pool->setMultiple($values, $ttl) && $result;
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return true;
    }
    
    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $result = true;
        
        foreach ($this->pools as $poolKey => $pool) {
            try {
                $result = $result && $pool->deleteMultiple($keys);
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return $result;
    }
    
    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     * @throws \Psr\SimpleCache\CacheException
     * @throws NoPoolAvailableException
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        foreach ($this->pools as $poolKey => $pool) {
            try {
                if ($pool->has($key)) {
                    return true;
                }
            } catch (CacheException $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }
    
        return false;
    }
    
    /**
     * Logs with an arbitrary level if the logger exists.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    protected function log($level, $message, array $context = [])
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
    
    /**
     * @param string $poolKey
     * @param string $operation
     * @param CacheException $exception
     * @throws CacheException
     */
    private function handleException($poolKey, $operation, CacheException $exception)
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
