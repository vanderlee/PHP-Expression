<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

interface DecimalUnit
{
    /** Return an exact decimal string; do not convert the amount to float. */
    public function convert(string $amount): string;
}
