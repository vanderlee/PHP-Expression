<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

use Vanderlee\Expression\DiagnosticException;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\ResourceLimits;

/** @internal Shared tokenizer and precedence parser for built-in grammars. */
final class GrammarParser
{
    private const PRECEDENCE = [
        '||' => 10,
        'or' => 10,
        'xor' => 5,
        '^^' => 5,
        '&&' => 20,
        'and' => 20,
        '==' => 30,
        '!=' => 30,
        '===' => 30,
        '!==' => 30,
        '<=>' => 30,
        '<' => 35,
        '<=' => 35,
        '>' => 35,
        '>=' => 35,
        '+' => 40,
        '-' => 40,
        '*' => 50,
        '/' => 50,
        '%' => 50,
        '**' => 70,
    ];

    private $tokens = [];
    private $position = 0;
    private $functions;
    private $native;
    private $precedence;
    private $allowVariables;

    public function __construct(
        string $expression,
        array $functions,
        array $units,
        bool $native = false,
        bool $allowVariables = false
    ) {
        $this->allowVariables = $allowVariables;
        $this->native = $native;
        $this->precedence = self::PRECEDENCE;
        if ($native) {
            $this->precedence += ['|' => 22, '^' => 23, '&' => 24, '<<' => 38, '>>' => 38, '<>' => 30];
        }
        ResourceLimits::assertLength($expression);
        ResourceLimits::assertFunctionCount($expression);
        $this->functions = $functions;
        $suffixPattern = UnitSuffixes::pattern($units);
        $digits = '\d+(?:_\d+)*';
        $number = '(?:0x[0-9a-f]+(?:_[0-9a-f]+)*|0b[01]+(?:_[01]+)*|0o[0-7]+(?:_[0-7]+)*|(?:'
            . $digits . '(?:\.(?:' . $digits . ')?)?|\.' . $digits . ')(?:e[+-]?\d+)?)';
        $offset = 0;
        $parenthesisDepth = 0;
        while ($offset < strlen($expression)) {
            if (preg_match('~\G\s+~', $expression, $match, 0, $offset)) {
                $offset += strlen($match[0]);
                continue;
            }
            if (preg_match('~\G' . $number . '~i', $expression, $match, 0, $offset)) {
                $numberOffset = $offset;
                $offset += strlen($match[0]);
                $this->tokens[] = ['number', $match[0], $numberOffset];
                if ($suffixPattern !== '' && preg_match(
                        '~\G(?:' . $suffixPattern . ')(?![\w.])~',
                        $expression,
                        $suffix,
                        0,
                        $offset
                    )) {
                    if ($native && $parenthesisDepth >= 128) {
                        throw new Exception('expression too deeply nested after unit grouping');
                    }
                    $this->tokens[] = ['unit', $suffix[0]];
                    $offset += strlen($suffix[0]);
                }
                continue;
            }
            if (preg_match('~\G[a-z_]\w*~i', $expression, $match, 0, $offset)) {
                $word = strtolower($match[0]);
                $this->tokens[] = [
                    isset($this->precedence[$word]) || $word === 'not' ? 'operator' : 'name',
                    $word,
                    $offset
                ];
                $offset += strlen($match[0]);
                continue;
            }
            $operators = $native
                ? '~\G(?:===|!==|<=>|\*\*|&&|\|\||\^\^|<<|>>|<>|==|!=|<=|>=|[+*/%<>()!,&|^\-])~'
                : '~\G(?:===|!==|<=>|\*\*|&&|\|\||\^\^|==|!=|<=|>=|[+*/%<>()!,\-])~';
            if (preg_match($operators, $expression, $match, 0, $offset)) {
                if ($match[0] === '(') {
                    ++$parenthesisDepth;
                } elseif ($match[0] === ')') {
                    --$parenthesisDepth;
                }
                $this->tokens[] = ['operator', $match[0], $offset];
                $offset += strlen($match[0]);
                continue;
            }
            throw new DiagnosticException('unsupported_syntax', 'Unsupported syntax at byte ' . $offset, $offset);
        }
        if ($native) {
            for ($index = 0; $index + 1 < count($this->tokens); ++$index) {
                $first = $this->tokens[$index];
                $second = $this->tokens[$index + 1];
                if ($first[0] === 'operator' && in_array($first[1], ['+', '-'], true)
                    && $second[1] === $first[1] && $second[2] === $first[2] + 1) {
                    // Legacy signed-unit subtraction is valid, but PHP's
                    // increment/decrement spellings are not mathematical syntax.
                    $number = $this->tokens[$index + 2] ?? ['end', ''];
                    $unit = $this->tokens[$index + 3] ?? ['end', ''];
                    if ($first[1] !== '-' || $number[0] !== 'number' || $unit[0] !== 'unit'
                        || $number[2] !== $second[2] + 1) {
                        throw new Exception('Increment/decrement is outside the native grammar');
                    }
                }
            }
        }
        $this->tokens[] = ['end', '', strlen($expression)];
    }

    public function parse(): array
    {
        if ($this->peek()[0] === 'end') {
            throw new DiagnosticException('empty_expression', 'Empty expression', 0);
        }
        $tree = $this->expression(0, 0);
        if ($this->peek()[0] !== 'end') {
            throw new DiagnosticException(
                'unexpected_token',
                'Unexpected token `' . $this->peek()[1] . '`',
                $this->peek()[2] ?? null
            );
        }
        return $tree;
    }

    private function peek(): array
    {
        return $this->tokens[$this->position] ?? ['end', ''];
    }

    private function expression(int $minimum, int $depth): array
    {
        if ($depth > 128) {
            throw new Exception('decimal expression too deeply nested');
        }
        $left = $this->prefix($depth);
        $comparisonPriority = null;
        while (isset($this->precedence[$this->peek()[1]]) && $this->precedence[$this->peek()[1]] >= $minimum) {
            $operator = $this->take()[1];
            $priority = $this->precedence[$operator];
            if ($priority === 30 || $priority === 35) {
                if ($comparisonPriority === $priority) {
                    throw new Exception('Chained decimal comparisons require parentheses');
                }
                $comparisonPriority = $priority;
            }
            $right = $this->expression($priority + ($operator === '**' ? 0 : 1), $depth + 1);
            $left = ['binary', $operator, $left, $right];
        }
        return $left;
    }

    private function prefix(int $depth): array
    {
        $token = $this->take();
        if (in_array($token[1], ['+', '-', '!', 'not'], true)) {
            // A signed unit amount is converted before exponentiation, as on
            // the default backend. A plain -2**2 remains -(2**2).
            if (($token[1] === '+' || $token[1] === '-')
                && $this->peek()[0] === 'number'
                && ($this->tokens[$this->position + 1][0] ?? '') === 'unit'
                && $this->peek()[2] === $token[2] + 1) {
                $number = $this->take()[1];
                return ['unit', $token[1] . $number, $this->take()[1]];
            }
            return ['unary', $token[1], $this->expression(60, $depth + 1)];
        }
        if ($token[1] === '(') {
            $tree = $this->expression(0, $depth + 1);
            $this->expect(')');
            return $tree;
        }
        if ($token[0] === 'number') {
            return $this->peek()[0] === 'unit'
                ? ['unit', $token[1], $this->take()[1]]
                : ['number', $token[1]];
        }
        if ($token[0] === 'name') {
            if ($token[1] === 'true' || $token[1] === 'false') {
                return ['number', $token[1] === 'true' ? '1' : '0'];
            }
            if ($this->allowVariables && $this->peek()[1] !== '(') {
                return ['variable', $token[1], $token[2]];
            }
            if ($token[1] !== 'if' && !isset($this->functions[$token[1]])) {
                throw new DiagnosticException('unknown_function', 'Illegal function `' . $token[1] . '`', $token[2]);
            }
            $this->expect('(');
            $arguments = [];
            if ($this->peek()[1] !== ')') {
                do {
                    $arguments[] = $this->expression(0, $depth + 1);
                    if ($this->peek()[1] !== ',') {
                        break;
                    }
                    $this->take();
                } while (true);
            }
            $this->expect(')');
            if ($token[1] === 'if') {
                if (count($arguments) !== 3) {
                    throw new DiagnosticException('invalid_argument_count', 'if requires three arguments', $token[2]);
                }
                return ['conditional', 'if', $arguments];
            }
            return ['call', $this->functions[$token[1]], $arguments, $token[1], $token[2]];
        }
        throw new DiagnosticException('expected_operand', 'Expected a numeric operand', $token[2] ?? null);
    }

    private function take(): array
    {
        $token = $this->peek();
        ++$this->position;
        return $token;
    }

    private function expect(string $token): void
    {
        $actual = $this->take();
        if ($actual[1] !== $token) {
            throw new DiagnosticException('expected_token', 'Expected `' . $token . '`', $actual[2] ?? null);
        }
    }
}
