<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed;

use Cache\Prefixed\Exception\InvalidArgumentException;

/**
 * Utility to reduce code suplication between the Prefixed* decoratrs.
 *
 * @author ndobromirov
 */
trait PrefixedUtilityTrait
{
    private const ENCODING_MARKER = '_x';

    private string $prefix;

    private function encodePrefix(string $prefix): string
    {
        $encoded = '';
        $length = \strlen($prefix);
        for ($index = 0; $index < $length; ++$index) {
            $byte = $prefix[$index];
            $ordinal = \ord($byte);
            $portable = ($ordinal >= 48 && $ordinal <= 57)
                || ($ordinal >= 65 && $ordinal <= 90)
                || ($ordinal >= 97 && $ordinal <= 122)
                || '_' === $byte
                || '.' === $byte;
            $startsMarker = '_' === $byte && 'x' === ($prefix[$index + 1] ?? null);

            $encoded .= $portable && !$startsMarker
                ? $byte
                : self::ENCODING_MARKER.strtoupper(bin2hex($byte)).'_';
        }

        return $encoded;
    }

    /**
     * Add namespace prefix on the key.
     *
     * @param string $key Reference to the key. It is mutated.
     */
    private function prefixValue(string &$key): void
    {
        $this->validateKey($key);
        $key = $this->prefix.$key;
    }

    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key cannot be an empty string');
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
        }
    }

    /**
     * Adds a namespace prefix on a list of keys.
     *
     * @param array<array-key, mixed> $keys
     *
     * @return array<array-key, string>
     */
    private function prefixValues(array $keys): array
    {
        foreach ($keys as $index => $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given', get_debug_type($key)));
            }

            $this->prefixValue($key);
            $keys[$index] = $key;
        }

        return $keys;
    }
}
