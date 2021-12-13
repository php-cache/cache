<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\TagInterop;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

$r = new \ReflectionClass(CacheItemPoolInterface::class);
$m = $r->getMethod('getItem');
$param = $m->getParameters()[0];

/**
 * Interface for invalidating cached items using tags. This interface is a soon-to-be-PSR.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */

if ($param->getType() === null) {
    // definition for psr/cache 1.0.* version
    interface TaggableCacheItemPoolInterface extends CacheItemPoolInterface
    {
        /**
         * Invalidates cached items using a tag.
         *
         * @param string $tag The tag to invalidate
         *
         * @throws InvalidArgumentException When $tags is not valid
         *
         * @return bool True on success
         */
        public function invalidateTag($tag);

        /**
         * Invalidates cached items using tags.
         *
         * @param string[] $tags An array of tags to invalidate
         *
         * @throws InvalidArgumentException When $tags is not valid
         *
         * @return bool True on success
         */
        public function invalidateTags(array $tags);

        /**
         * {@inheritdoc}
         *
         * @return TaggableCacheItemInterface
         */
        public function getItem($key): CacheItemInterface;

        /**
         * {@inheritdoc}
         *
         * @return array|\Traversable|TaggableCacheItemInterface[]
         */
        public function getItems(array $keys = []);
    }
} else {
    // since 2.0 version of psr/cache was changed signature
    interface TaggableCacheItemPoolInterface extends CacheItemPoolInterface
    {
        /**
         * Invalidates cached items using a tag.
         *
         * @param string $tag The tag to invalidate
         *
         * @return bool True on success
         * @throws InvalidArgumentException When $tags is not valid
         *
         */
        public function invalidateTag($tag);

        /**
         * Invalidates cached items using tags.
         *
         * @param string[] $tags An array of tags to invalidate
         *
         * @return bool True on success
         * @throws InvalidArgumentException When $tags is not valid
         *
         */
        public function invalidateTags(array $tags);

        /**
         * {@inheritdoc}
         *
         * @return TaggableCacheItemInterface
         */
        public function getItem(string $key): CacheItemInterface;

        /**
         * {@inheritdoc}
         *
         * @return array|\Traversable|TaggableCacheItemInterface[]
         */
        public function getItems(array $keys = []): iterable;
    }
}
