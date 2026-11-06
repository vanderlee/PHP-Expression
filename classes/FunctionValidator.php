<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

/** @internal Registry validation shared by the facade and generated native code. */
final class FunctionValidator
{
    public static function assertArguments($target, int $count, string $alias, int $offset): void
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($target));
        $minimum = $reflection->getNumberOfRequiredParameters();
        if ($target === 'min' || $target === 'max') {
            $minimum = 2;
        }
        if ($count < $minimum || (!$reflection->isVariadic() && $count > $reflection->getNumberOfParameters())) {
            throw new DiagnosticException(
                'invalid_argument_count',
                'Invalid argument count for `' . $alias . '`',
                $offset
            );
        }
    }

    public static function alias(string $alias): string
    {
        if (preg_match('~^[a-z_]\w*$~i', $alias) !== 1) {
            throw new Exception(sprintf('Invalid function alias `%s`', $alias));
        }
        return strtolower($alias);
    }

    /** @param mixed $target */
    public static function target($target)
    {
        if (!is_string($target) && is_callable($target)) {
            return $target;
        }
        if (!is_string($target)) {
            throw new Exception('Invalid function target');
        }
        if (preg_match('~^[a-z_]\w*$~iD', $target) !== 1
            && preg_match('~^(?:[a-z_]\w*\\\\)*[a-z_]\w*::[a-z_]\w*$~iD', $target) !== 1) {
            throw new Exception(sprintf('Invalid function target `%s`', $target));
        }
        if (!is_callable($target)) {
            throw new Exception(sprintf('Function target `%s` is not callable', $target));
        }
        return $target;
    }
}
