<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

use Vanderlee\Expression\DiagnosticException;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\FunctionValidator;
use Vanderlee\Expression\ResourceLimits;

/** Parses a mathematical grammar and evaluates only generated PHP. */
final class NativeGrammarBackend extends AbstractNativeBackend implements TreeBackend
{
    public function evaluate(string $expression, array $functions, array $units): float
    {
        ResourceLimits::assertExpression($expression);
        self::assertNativeSource($expression);
        $tree = (new GrammarParser($expression, $functions, $units, true))->parse();
        return $this->evaluateTree($tree, $functions, $units, []);
    }

    public function evaluateTree(
        array $tree,
        array $functions,
        array $units,
        array $variables,
        bool $exact = false
    ): float {
        $amounts = [];
        $bindings = [];
        $php = $this->generate($tree, $amounts, 0, $bindings);
        return self::executePrepared($php, $amounts, $this->hasConditional($tree), $functions, $units, $variables);
    }

    /** @internal Prepare immutable syntax without retaining results or registry objects. */
    public function prepareTree(array $tree): callable
    {
        $amounts = [];
        $bindings = [];
        // Validate and generate the entire tree before invoking any host code.
        $php = $this->generate($tree, $amounts, 0, $bindings);
        $conditional = $this->hasConditional($tree);
        return static function (array $functions, array $units, array $variables) use ($php, $amounts, $conditional, $bindings): float {
            foreach ($bindings as $name => $offset) {
                if (!array_key_exists($name, $variables)) {
                    throw new DiagnosticException('missing_variable', 'Unknown variable `' . $name . '`', $offset);
                }
            }
            return self::executePrepared($php, $amounts, $conditional, $functions, $units, $variables);
        };
    }

    private static function executePrepared(
        string $php,
        array $amounts,
        bool $conditional,
        array $functions,
        array $units,
        array $variables
    ): float {
        $converted = [];
        if (!$conditional) {
            foreach ($amounts as $index => $amount) {
                $converted[$index] = self::convertUnitAmount($amount[0], $units[$amount[1]]);
            }
        }
        $convert = static function ($index) use ($amounts, $units, $converted) {
            if (array_key_exists($index, $converted)) {
                return $converted[$index];
            }
            $amount = $amounts[$index];
            return self::convertUnitAmount($amount[0], $units[$amount[1]]);
        };
        return self::runExpression($php, [], $functions, $variables, $convert);
    }

    private function generate(array $node, array &$amounts, int $depth, array &$bindings): string
    {
        // Flat left-associative chains can exceed source parenthesis depth.
        // The input byte limit bounds this tree independently of parser nesting.
        if ($depth > 2048) {
            throw new Exception('native grammar tree too deeply nested');
        }
        if ($node[0] === 'variable') {
            if (!array_key_exists($node[1], $bindings)) {
                $bindings[$node[1]] = $node[2] ?? null;
            }
            return '($variables[' . var_export($node[1], true) . '])';
        }
        if ($node[0] === 'conditional') {
            $parts = [];
            foreach ($node[2] as $part) {
                $parts[] = $this->generate($part, $amounts, $depth + 1, $bindings);
            }
            return '(' . $parts[0] . ' ? ' . $parts[1] . ' : ' . $parts[2] . ')';
        }
        if ($node[0] === 'number') {
            return '(' . self::literal($node[1]) . ')';
        }
        if ($node[0] === 'unit') {
            if (preg_match('~^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$~D', $node[1]) !== 1) {
                throw new Exception('Native grammar units require plain decimal amounts');
            }
            $index = count($amounts);
            $amounts[] = [$node[1], $node[2]];
            return '($convert(' . $index . '))';
        }
        if ($node[0] === 'call') {
            $target = FunctionValidator::target($node[1]);
            $arguments = [];
            foreach ($node[2] as $argument) {
                $arguments[] = $this->generate($argument, $amounts, $depth + 1, $bindings);
            }
            if (isset($node[3])) {
                return '($functions[' . var_export($node[3], true) . '](' . implode(',', $arguments) . '))';
            }
            return '(\\' . $target . '(' . implode(',', $arguments) . '))';
        }
        $operators = ['and' => '&&', 'or' => '||', '^^' => 'xor', 'not' => '!'];
        $operator = $operators[$node[1]] ?? $node[1];
        if ($node[0] === 'unary') {
            return '(' . $operator . $this->generate($node[2], $amounts, $depth + 1, $bindings) . ')';
        }
        $left = $this->generate($node[2], $amounts, $depth + 1, $bindings);
        $right = $this->generate($node[3], $amounts, $depth + 1, $bindings);
        return '(' . $left . ' ' . $operator . ' ' . $right . ')';
    }

    private static function literal(string $literal): string
    {
        $literal = self::normalizeNumericLiteral($literal);
        if (preg_match('~^0\d+$~D', $literal) === 1 && preg_match('~^0[0-7]+$~D', $literal) !== 1) {
            throw new Exception('Invalid octal literal');
        }
        return $literal;
    }

    private function hasConditional(array $node): bool
    {
        if ($node[0] === 'conditional') {
            return true;
        }
        if ($node[0] === 'call') {
            foreach ($node[2] as $child) {
                if ($this->hasConditional($child)) {
                    return true;
                }
            }
        } elseif ($node[0] === 'unary') {
            return $this->hasConditional($node[2]);
        } elseif ($node[0] === 'binary') {
            return $this->hasConditional($node[2]) || $this->hasConditional($node[3]);
        }
        return false;
    }

    public function validateTree(array $tree): void
    {
        $amounts = [];
        $bindings = [];
        $this->generate($tree, $amounts, 0, $bindings);
    }
}
