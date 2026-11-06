<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

/** Native rounding helpers; decimal backends implement these without floats. */
final class NativeRounding
{
    public static function halfEven($value, $precision = 0): float
    {
        return round($value, self::precision($precision), PHP_ROUND_HALF_EVEN);
    }

    private static function precision($value): int
    {
        if (!is_numeric($value) || (float)(int)$value !== (float)$value || abs((float)$value) > 999) {
            throw new DiagnosticException(
                'invalid_argument',
                'Rounding precision must be an integer between -999 and 999'
            );
        }
        return (int)$value;
    }

    public static function halfUp($value, $precision = 0): float
    {
        return round($value, self::precision($precision), PHP_ROUND_HALF_UP);
    }

    public static function towardZero($value, $precision = 0): float
    {
        $factor = 10 ** self::precision($precision);
        return ($value < 0 ? ceil($value * $factor) : floor($value * $factor)) / $factor;
    }

    public static function increment($value, $increment): float
    {
        if ($increment <= 0 || !is_finite((float)$increment)) {
            throw new DiagnosticException('invalid_argument', 'Rounding increment must be positive and finite');
        }
        return round($value / $increment, 0, PHP_ROUND_HALF_UP) * $increment;
    }
}
