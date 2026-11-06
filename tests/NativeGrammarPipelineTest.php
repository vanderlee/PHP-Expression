<?php

declare(strict_types=1);

use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Expression;

require_once __DIR__ . '/NativePipelineTest.php';

class NativeGrammarPipelineTest extends NativePipelineTest
{
    protected function expression(): Expression
    {
        return new Expression(new NativeGrammarBackend());
    }
}
