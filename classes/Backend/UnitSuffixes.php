<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

/** @internal Longest-first, escaped suffix alternatives for every grammar. */
final class UnitSuffixes
{
    public static function pattern(array $units): string
    {
        $suffixes = array_keys($units);
        usort($suffixes, static function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });
        return implode('|', array_map(static function (string $suffix): string {
            return preg_quote($suffix, '~');
        }, $suffixes));
    }
}
