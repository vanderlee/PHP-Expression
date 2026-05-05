<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

interface Unit
{
    public function convert(float $amount): float;
}
