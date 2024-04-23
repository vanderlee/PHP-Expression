<?php
declare(strict_types=1);

namespace Vanderlee\Expression\Unit;

/**
 * @author Martijn
 */
class Percentage implements Unit
{
    /** @var float */
    private $range;

    public function __construct(float $range = 1.)
    {
        $this->range = $range;
    }

    public function set(float $range = 1.): void
    {
        $this->range = $range;
    }

    public function convert(float $amount): float
    {
        return $amount * $this->range / 100.;
    }
}