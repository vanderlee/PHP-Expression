<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

use Vanderlee\Expression\Backend\GrammarParser;

/** @internal Side-effect-free validation of the shared built-in backend grammar. */
final class PortableValidator
{
    public static function validate(string $expression, array $functions, array $units): void
    {
        // The decimal parser treats adjacent signs as separate operators, while
        // PHP tokenizes them as increment/decrement. Require explicit spacing.
        if (strpos($expression, '++') !== false || strpos($expression, '--') !== false) {
            throw new Exception('Portable adjacent signs require whitespace or parentheses');
        }
        // Parsing requires no BCMath extension and never calls registered code.
        $tree = (new GrammarParser($expression, $functions, $units, false, true))->parse();
        self::visit($tree, $units);
    }

    /** Return whether this subtree produces a boolean in native PHP. */
    private static function visit(array $node, array $units): bool
    {
        if ($node[0] === 'variable') {
            return false;
        }
        if ($node[0] === 'conditional') {
            self::visit($node[2][0], $units);
            self::requireNumeric(self::visit($node[2][1], $units));
            self::requireNumeric(self::visit($node[2][2], $units));
            return false;
        }
        if ($node[0] === 'number' || $node[0] === 'unit') {
            $literal = ltrim($node[1], '+-');
            $pattern = $node[0] === 'unit'
                ? '~^(?:0|[1-9][0-9]*)(?:\.[0-9]*)?$|^\.[0-9]+$~D'
                : '~^(?:(?:0|[1-9][0-9]*)(?:\.[0-9]*)?|\.[0-9]+)(?:e[+-]?[0-9]+)?$~iD';
            if (preg_match($pattern, $literal) !== 1) {
                throw new Exception('Non-portable numeric literal `' . $node[1] . '`');
            }
            if ($node[0] === 'unit') {
                if (preg_match('~^[a-z]+$~D', $node[2]) !== 1 || !is_numeric($units[$node[2]])) {
                    throw new Exception('Portable units require lowercase alphabetic suffixes and numeric factors');
                }
            }
            return false;
        }
        if ($node[0] === 'unary') {
            $boolean = self::visit($node[2], $units);
            if ($node[1] === '!' || $node[1] === 'not') {
                return true;
            }
            self::requireNumeric($boolean);
            return false;
        }
        if ($node[0] === 'call') {
            if (!is_string($node[1])) {
                throw new Exception('Custom callables are not portable');
            }
            $name = strtolower($node[1]);
            $helpers = [
                'vanderlee\\expression\\nativerounding::halfeven' => 'round',
                'vanderlee\\expression\\nativerounding::halfup' => 'round',
                'vanderlee\\expression\\nativerounding::towardzero' => 'round',
                'vanderlee\\expression\\nativerounding::increment' => 'round_increment'
            ];
            $name = $helpers[$name] ?? $name;
            $arity = [
                'abs' => [1, 1],
                'ceil' => [1, 1],
                'floor' => [1, 1],
                'sqrt' => [1, 1],
                'pow' => [2, 2],
                'round' => [1, 2],
                'min' => [2, 128],
                'max' => [2, 128],
                'round_increment' => [2, 2]
            ];
            if (!isset($arity[$name])) {
                throw new Exception('Non-portable function `' . $node[1] . '`');
            }
            $count = count($node[2]);
            if ($count < $arity[$name][0] || $count > $arity[$name][1]) {
                throw new Exception('Invalid portable function argument count');
            }
            foreach ($node[2] as $argument) {
                self::requireNumeric(self::visit($argument, $units));
            }
            if ($name === 'pow') {
                self::requireIntegerLiteral($node[2][1], 1000);
            } elseif ($name === 'round' && $count === 2) {
                self::requireIntegerLiteral($node[2][1], 999);
            }
            return false;
        }
        $operator = $node[1];
        if (in_array($operator, ['===', '!==', '%'], true)) {
            throw new Exception('Non-portable operator `' . $operator . '`');
        }
        $leftBoolean = self::visit($node[2], $units);
        $rightBoolean = self::visit($node[3], $units);
        if (in_array($operator, ['&&', 'and', '||', 'or', 'xor', '^^'], true)) {
            return true;
        }
        self::requireNumeric($leftBoolean);
        self::requireNumeric($rightBoolean);
        if ($operator === '**') {
            self::requireIntegerLiteral($node[3], 1000);
        }
        return in_array($operator, ['==', '!=', '<', '<=', '>', '>='], true);
    }

    private static function requireNumeric(bool $boolean): void
    {
        if ($boolean) {
            throw new Exception(
                'Portable boolean results may only be used by logical operators or as the final result'
            );
        }
    }

    private static function requireIntegerLiteral(array $node, int $maximum): void
    {
        if ($node[0] === 'unary' && in_array($node[1], ['+', '-'], true)) {
            $node = $node[2];
        }
        if ($node[0] !== 'number' || preg_match('~^(?:0|[1-9][0-9]*)$~D', $node[1]) !== 1
            || strlen($node[1]) > 4 || (int)$node[1] > $maximum) {
            throw new Exception(
                'Portable exponent/rounding precision requires an integer literal within its supported range'
            );
        }
    }

    public static function validateTree(array $tree, array $units): void
    {
        self::visit($tree, $units);
    }
}
