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

    /** @var callable|null Generated native source owned by this compiled formula. */
    private $nativeExecution;

    public function __construct(
        Expression $owner,
        array $tree,
        int $revision,
        array $metadata,
        ?callable $nativeExecution = null
    ) {
        $this->owner = $owner;
        $this->tree = $tree;
        $this->revision = $revision;
        $this->metadata = $metadata;
        $this->nativeExecution = $nativeExecution;
    }

    public function inspect(): array
    {
        return $this->metadata;
    }

    public function evaluate(array $variables = []): float
    {
        return (float)$this->owner->executeCompiled($this->tree, $this->revision, $variables, 'float', $this->nativeExecution);
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
