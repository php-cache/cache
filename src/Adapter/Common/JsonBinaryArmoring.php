<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

/**
 * Encodes binary and non-UTF8 values for JSON-backed adapters.
 *
 * @author Stephen Clouse <stephen.clouse@noaa.gov>
 */
trait JsonBinaryArmoring
{
    private const ESCAPE_JSON_CHARACTERS = [
        "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
        "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
        "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
        "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
    ];

    private const ENCODED_JSON_CHARACTERS = [
        '\u0000', '\u0001', '\u0002', '\u0003', '\u0004', '\u0005', '\u0006', '\u0007',
        '\u0008', '\u0009', '\u000A', '\u000B', '\u000C', '\u000D', '\u000E', '\u000F',
        '\u0010', '\u0011', '\u0012', '\u0013', '\u0014', '\u0015', '\u0016', '\u0017',
        '\u0018', '\u0019', '\u001A', '\u001B', '\u001C', '\u001D', '\u001E', '\u001F',
    ];

    /**
     * Armor a value going into a JSON document.
     */
    protected static function jsonArmor(string $value): string
    {
        return str_replace(
            self::ESCAPE_JSON_CHARACTERS,
            self::ENCODED_JSON_CHARACTERS,
            self::latin1ToUtf8($value)
        );
    }

    /**
     * De-armor a value from a JSON document.
     */
    protected static function jsonDeArmor(string $value): string
    {
        return self::utf8ToLatin1(str_replace(
            self::ENCODED_JSON_CHARACTERS,
            self::ESCAPE_JSON_CHARACTERS,
            $value
        ));
    }

    private static function latin1ToUtf8(string $value): string
    {
        return preg_replace_callback(
            '/[\x80-\xFF]/',
            static function (array $match): string {
                $byte = ord($match[0]);

                return chr(0xC0 | ($byte >> 6)).chr(0x80 | ($byte & 0x3F));
            },
            $value
        ) ?? $value;
    }

    private static function utf8ToLatin1(string $value): string
    {
        return preg_replace_callback(
            '/[\xC2-\xC3][\x80-\xBF]/',
            static function (array $match): string {
                return chr(((ord($match[0][0]) & 0x03) << 6) | (ord($match[0][1]) & 0x3F));
            },
            $value
        ) ?? $value;
    }
}
