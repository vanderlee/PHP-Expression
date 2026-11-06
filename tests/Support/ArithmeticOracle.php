<?php

declare(strict_types=1);

/** Test-only rational oracle. No eval, BCMath, or package parser is used. */
final class ArithmeticOracle
{
    public static function reduce(int $numerator, int $denominator): array
    {
        if ($denominator === 0) { throw new DomainException('Zero denominator'); }
        if ($denominator < 0) { $numerator = -$numerator; $denominator = -$denominator; }
        $a = abs($numerator);
        $b = $denominator;
        while ($b !== 0) { $remainder = $a % $b; $a = $b; $b = $remainder; }
        return [intdiv($numerator, $a), intdiv($denominator, $a)];
    }

    public static function evaluate(array $node): array
    {
        if ($node[0] === 'literal') { return [$node[1], $node[2]]; }
        $left = self::evaluate($node[1]);
        if ($node[0] === 'negate') { return [-$left[0], $left[1]]; }
        if ($node[0] === 'abs') { return [abs($left[0]), $left[1]]; }
        $right = self::evaluate($node[2]);
        switch ($node[0]) {
            case '+': $result = [$left[0] * $right[1] + $right[0] * $left[1], $left[1] * $right[1]]; break;
            case '-': $result = [$left[0] * $right[1] - $right[0] * $left[1], $left[1] * $right[1]]; break;
            case '*': $result = [$left[0] * $right[0], $left[1] * $right[1]]; break;
            case '/': $result = [$left[0] * $right[1], $left[1] * $right[0]]; break;
            default: throw new LogicException('Unknown generated operator');
        }
        $result = self::reduce($result[0], $result[1]);
        // Bound intermediate products well inside even 32-bit integer range.
        if (abs($result[0]) > 10000 || $result[1] > 10000) { throw new OverflowException('Regenerate bounded tree'); }
        return $result;
    }

    public static function render(array $node): string
    {
        if ($node[0] === 'literal') { return '(' . self::decimal($node[1], $node[2]) . ')'; }
        if ($node[0] === 'negate') { return '-(' . self::render($node[1]) . ')'; }
        if ($node[0] === 'abs') { return 'abs(' . self::render($node[1]) . ')'; }
        return '( ' . self::render($node[1]) . ' ' . $node[0] . ' ' . self::render($node[2]) . ' )';
    }

    public static function decimal(int $numerator, int $denominator): string
    {
        list($numerator, $denominator) = self::reduce($numerator, $denominator);
        $negative = $numerator < 0;
        $numerator = abs($numerator);
        $result = (string) intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        if ($remainder !== 0) {
            $result .= '.';
            for ($digit = 0; $remainder !== 0; ++$digit) {
                if ($digit >= 30) { throw new DomainException('Oracle requires terminating decimal'); }
                $remainder *= 10;
                $result .= (string) intdiv($remainder, $denominator);
                $remainder %= $denominator;
            }
        }
        return ($negative ? '-' : '') . $result;
    }

    public static function rounded(int $numerator, int $denominator, int $scale, string $policy): string
    {
        $factor = 10 ** $scale;
        $scaled = abs($numerator) * $factor;
        $divisor = abs($denominator);
        $quotient = intdiv($scaled, $divisor);
        $remainder = $scaled % $divisor;
        if ($policy !== 'toward_zero' && (2 * $remainder > $divisor
            || (2 * $remainder === $divisor && ($policy === 'half_up' || $quotient % 2 !== 0)))) {
            ++$quotient;
        }
        if (($numerator < 0) !== ($denominator < 0)) { $quotient = -$quotient; }
        return self::decimal($quotient, $factor);
    }
}

/** Local small-state PRNG, reproducible without changing PHP global PRNG state. */
final class ArithmeticGenerator
{
    private $state;

    public function __construct(int $seed) { $this->state = $seed; }

    public static function seeds(): array
    {
        $seed = getenv('EXPRESSION_TEST_SEED');
        if ($seed === false || $seed === '') { return [37, 9973, 24117]; }
        if (!ctype_digit($seed) || (int) $seed > 32748) { throw new InvalidArgumentException('EXPRESSION_TEST_SEED must be 0..32748'); }
        return [(int) $seed];
    }

    public function next(int $bound): int
    {
        $this->state = ($this->state * 251 + 17) % 32749;
        return $this->state % $bound;
    }

    public function tree(int $depth): array
    {
        if ($depth === 0 || $this->next(5) === 0) {
            return ['literal', $this->next(19) - 9, $this->next(2) === 0 ? 1 : 10];
        }
        $operator = ['+', '-', '*', '/', 'negate', 'abs'][$this->next(6)];
        $left = $this->tree($depth - 1);
        if ($operator === 'negate' || $operator === 'abs') { return [$operator, $left]; }
        $right = $operator === '/' ? ['literal', $this->next(2) === 0 ? 2 : 5, 1] : $this->tree($depth - 1);
        return [$operator, $left, $right];
    }

    public function boundedTree(): array
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $tree = $this->tree(3);
            try { ArithmeticOracle::evaluate($tree); return $tree; }
            catch (OverflowException $exception) { /* Generate another bounded tree. */ }
        }
        throw new RuntimeException('Failed to generate a bounded arithmetic tree');
    }
}
