<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encryption;

use Cache\Adapter\Common\JsonBinaryArmoring;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\TagInterop\TaggableCacheItemInterface;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Encrypt and Decrypt all the stored items.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedItemDecorator implements TaggableCacheItemInterface
{
    use JsonBinaryArmoring;

    /** The cache item should always contain encrypted data. */
    private TaggableCacheItemInterface $cacheItem;

    private Key $key;

    public function __construct(TaggableCacheItemInterface $cacheItem, Key $key, private readonly object $owner)
    {
        $this->cacheItem = $cacheItem;
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->cacheItem->getKey();
    }

    public function getCacheItem(): TaggableCacheItemInterface
    {
        return $this->cacheItem;
    }

    public function isOwnedBy(object $owner): bool
    {
        return $this->owner === $owner;
    }

    public function set(mixed $value): static
    {
        $type = \gettype($value);

        if (\is_object($value) || \is_array($value)) {
            $value = serialize($value);
        } elseif (null === $value) {
            $value = '';
        } elseif (\is_bool($value)) {
            $value = $value ? '1' : '';
        } elseif (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        } elseif (!\is_string($value)) {
            throw new \InvalidArgumentException('Encrypted cache values must be serializable.');
        }

        $json = json_encode(['type' => $type, 'value' => static::jsonArmor($value)], \JSON_THROW_ON_ERROR);

        $this->cacheItem->set(Crypto::encrypt($json, $this->key));

        return $this;
    }

    public function get(): mixed
    {
        if (!$this->cacheItem->isHit() || !$this->decode($decodedValue)) {
            return null;
        }

        return $decodedValue;
    }

    public function isHit(): bool
    {
        return $this->cacheItem->isHit() && $this->decode($decodedValue);
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->cacheItem->expiresAt($expiration);

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->cacheItem->expiresAfter($time);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getPreviousTags(): array
    {
        return $this->cacheItem->getPreviousTags();
    }

    public function setTags(array $tags): static
    {
        $this->cacheItem->setTags($tags);

        return $this;
    }

    /**
     * Creating a copy of the original CacheItemInterface object.
     */
    public function __clone(): void
    {
        $this->cacheItem = clone $this->cacheItem;
    }

    /**
     * Transform value back to it original type.
     *
     * @param array{type: string, value: string} $item
     *
     * @return array{bool, mixed}
     */
    private function transform(array $item): array
    {
        $value = static::jsonDeArmor($item['value']);

        return match ($item['type']) {
            'object', 'array' => PhpUnserializer::unserialize($value, $decodedValue) ? [true, $decodedValue] : [false, null],
            'boolean' => [true, (bool) $value],
            'integer' => [true, (int) $value],
            'double' => [true, (float) $value],
            'string' => [true, $value],
            'NULL' => [true, null],
            default => throw new \UnexpectedValueException(\sprintf('Unsupported encrypted cache value type "%s".', $item['type'])),
        };
    }

    private function decode(mixed &$decodedValue): bool
    {
        $encrypted = $this->cacheItem->get();
        if (!\is_string($encrypted)) {
            throw new \UnexpectedValueException('Encrypted cache payload must be a string.');
        }

        $item = json_decode(Crypto::decrypt($encrypted, $this->key), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($item)
            || !isset($item['type'], $item['value'])
            || !\is_string($item['type'])
            || !\is_string($item['value'])
        ) {
            throw new \UnexpectedValueException('Encrypted cache payload is malformed.');
        }

        [$valid, $decodedValue] = $this->transform($item);

        return $valid;
    }
}
