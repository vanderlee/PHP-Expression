<?php
declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

/**
 * @author Martijn
 */
class Decibel implements Unit
{
    public function convert(float $amount): float
    {
        return pow(10., $amount * .1);
    }
}