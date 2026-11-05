<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

/** @internal Shared input and normalized-expression limits. */
final class ResourceLimits
{
    public static function assertExpression(string $expression): void
    {
        self::assertLength($expression);
        $depth = 0;
        $maxDepth = 0;
        $length = strlen($expression);
        for ($index = 0; $index < $length; ++$index) {
            if ($expression[$index] === '(') {
                $maxDepth = max($maxDepth, ++$depth);
            } elseif ($expression[$index] === ')') {
                --$depth;
            }
        }
        if ($maxDepth > 128) {
            throw new Exception('expression too deeply nested');
        }
        self::assertFunctionCount($expression);
    }

    public static function assertLength(string $expression): void
    {
        if (strlen($expression) > 4096) {
            throw new Exception('expression too long');
        }
    }

    public static function assertFunctionCount(string $expression): void
    {
        if (preg_match_all('~\b[a-z_]\w*\s*\(~i', $expression) > 128) {
            throw new Exception('too many function calls');
        }
    }
}
