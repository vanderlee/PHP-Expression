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
        // Scan only parentheses in PHP; PCRE skips the other source bytes.
        preg_match_all('~[()]~', $expression, $parentheses);
        foreach ($parentheses[0] as $parenthesis) {
            if ($parenthesis === '(') {
                $maxDepth = max($maxDepth, ++$depth);
            } else {
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
