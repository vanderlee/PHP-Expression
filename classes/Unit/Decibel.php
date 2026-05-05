<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

class Decibel implements Unit
{
    public function convert(float $amount): float
    {
        return 10. ** ($amount * .1);
    }
}
