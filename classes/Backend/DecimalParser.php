<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

/** @internal Decimal grammar, also used by portable validation. */
final class DecimalParser
{
    private $parser;

    public function __construct(string $expression, array $functions, array $units)
    {
        $this->parser = new GrammarParser($expression, $functions, $units);
    }

    public function parse(): array
    {
        return $this->parser->parse();
    }
}