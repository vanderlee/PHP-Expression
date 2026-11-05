<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

/** Reusable syntax tree bound to its originating evaluator configuration. */
final class CompiledExpression
{
    private $owner;
    private $tree;
    private $revision;
    private $metadata;

    public function __construct(Expression $owner, array $tree, int $revision, array $metadata)
    {
        $this->owner = $owner;
        $this->tree = $tree;
        $this->revision = $revision;
        $this->metadata = $metadata;
    }

    public function inspect(): array
    {
        return $this->metadata;
    }

    public function evaluate(array $variables = []): float
    {
        return (float)$this->owner->executeCompiled($this->tree, $this->revision, $variables, 'float');
    }

    public function evaluateDecimal(array $variables = []): string
    {
        return $this->owner->executeCompiled($this->tree, $this->revision, $variables, 'decimal');
    }

    public function evaluateExact(array $variables = []): string
    {
        return $this->owner->executeCompiled($this->tree, $this->revision, $variables, 'exact');
    }
}
