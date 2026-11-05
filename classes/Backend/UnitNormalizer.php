<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

/** Optional backend-specific unit validation before changing the registry. */
interface UnitNormalizer
{
    /** @param mixed $unit @return mixed The validated conversion value. */
    public function normalizeUnit($unit);
}
