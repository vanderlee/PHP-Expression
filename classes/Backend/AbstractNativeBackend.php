<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

use Throwable;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\ResourceLimits;
use Vanderlee\Expression\Unit\Unit;

/** Shared native numeric policy and buffered execution; subclasses own syntax. */
abstract class AbstractNativeBackend implements FloatBackend, UnitNormalizer
{
    protected static function assertNativeSource(string $expression): void
    {
        ResourceLimits::assertLength($expression);
        if (strpos($expression, "\0") !== false) {
            throw new Exception('Illegal NUL byte');
        }
    }

    /** @param mixed $unit @return int|float */
    protected static function convertUnitAmount(string $amount, $unit)
    {
        try {
            $result = $unit instanceof Unit ? $unit->convert((float)$amount) : $amount * $unit;
        } catch (Throwable $error) {
            throw new Exception($error->getMessage(), 0, $error);
        }
        if (!is_finite($result)) {
            throw new Exception('unit conversion result must be finite');
        }
        return $result;
    }

    /** Canonicalize a validated explicit-base or underscore-decimal token. */
    protected static function normalizeNumericLiteral(string $literal): string
    {
        $literal = str_replace('_', '', $literal);
        $prefix = strtolower(substr($literal, 0, 2));
        if (!in_array($prefix, ['0x', '0b', '0o'], true)) {
            return $literal;
        }
        $digits = substr($literal, 2);
        $value = $prefix === '0x' ? hexdec($digits) : ($prefix === '0b' ? bindec($digits) : octdec($digits));
        if (!is_finite((float)$value)) {
            throw new Exception('numeric literal must be finite');
        }
        return self::formatNumericLiteral($value);
    }

    /** @param int|float $value A finite number. */
    protected static function formatNumericLiteral($value): string
    {
        if (is_int($value)) {
            return (string)$value;
        }
        // Binary64 round-trip text, independent of PHP precision INI settings.
        $literal = sprintf('%.17g', $value);
        $point = localeconv()['decimal_point'];
        if ($point !== '.') {
            $literal = str_replace($point, '.', $literal);
        }
        return strpbrk($literal, '.eE') === false ? $literal . '.0' : $literal;
    }

    /** Execute validated/generated PHP, with fixed local unit-array references. */
    protected static function runExpression(
        string $expression,
        array $convertedUnits = [],
        array $functions = [],
        array $variables = [],
        ?callable $convert = null
    ): float {
        ob_start();
        try {
            $result = (float)eval('return(' . $expression . ');');
        } catch (Throwable $error) {
            ob_end_clean();
            throw new Exception($error->getMessage(), 0, $error);
        }
        if (ob_get_clean() !== '') {
            throw new Exception('Syntax error');
        }
        if (!is_finite($result)) {
            throw new Exception('expression result must be finite');
        }
        return $result;
    }

    public function normalizeUnit($unit)
    {
        if ($unit instanceof Unit) {
            return $unit;
        }
        if (!is_numeric($unit)) {
            throw new Exception('unit conversion must be numeric or a Unit');
        }
        $unit = (float)$unit;
        if (!is_finite($unit)) {
            throw new Exception('unit conversion must be finite');
        }
        return $unit;
    }
}
