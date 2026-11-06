<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

/** Optional execution contract for reusable, validated syntax trees. */
interface TreeBackend extends Backend
{
    public function evaluateTree(array $tree, array $functions, array $units, array $variables, bool $exact = false);
}
