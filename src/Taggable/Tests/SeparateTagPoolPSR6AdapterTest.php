<?php

namespace Cache\Taggable\Tests;

use Cache\IntegrationTests\TaggableCachePoolTest;
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyArrayAdapter;

class SeparateTagPoolTaggablePSR6AdapterTest extends TaggableCachePoolTest
{
    public function createCachePool()
    {
        return TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter, new SymfonyArrayAdapter);
    }
}
