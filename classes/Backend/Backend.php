<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

interface Backend
{
    /**
     * Evaluate the original expression using this backend's numeric policy.
     *
     * @param array<string, callable> $functions Trusted alias-to-callable registry.
     * @param array<string, mixed> $units Original registered conversion values.
     * @return float|string A finite native float or a decimal string.
     * Decimal exactness and rounding are defined by the backend's policy.
     * No union return type is declared to retain PHP 7.1 compatibility.
     */
    public function evaluate(string $expression, array $functions, array $units);
}
