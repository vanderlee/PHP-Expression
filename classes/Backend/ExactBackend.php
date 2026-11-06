<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

/** Backends with an optional rounding policy can explicitly bypass it. */
interface ExactBackend extends Backend
{
    public function evaluateExact(string $expression, array $functions, array $units): string;
}
