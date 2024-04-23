<?php
declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

/**
 * @author Martijn
 */
interface Unit
{
    public function convert(float $amount): float;
}