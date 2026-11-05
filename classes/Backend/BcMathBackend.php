<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Unit\DecimalUnit;

/** Decimal arithmetic with strict exact or explicitly rounded division/roots. */
final class BcMathBackend implements ExactBackend, TreeBackend
{
    private const MAX_DIGITS = 1000;

    public const HALF_UP = 'half_up';
    public const HALF_EVEN = 'half_even';
    public const TOWARD_ZERO = 'toward_zero';

    /** @var int|null Decimal places for division/roots; null requires exactness. */
    private $scale;

    /** @var string */
    private $rounding;
    private $variables = [];

    public function __construct(?int $scale = null, string $rounding = self::HALF_UP)
    {
        if (!extension_loaded('bcmath')) {
            throw new Exception('BcMathBackend requires ext-bcmath');
        }
        if ($scale !== null && ($scale < 0 || $scale >= self::MAX_DIGITS)) {
            throw new Exception('Decimal scale must be between 0 and 999');
        }
        if (!in_array($rounding, [self::HALF_UP, self::HALF_EVEN, self::TOWARD_ZERO], true)) {
            throw new Exception('Unsupported decimal rounding policy');
        }
        $this->scale = $scale;
        $this->rounding = $rounding;
    }

    public function evaluateExact(string $expression, array $functions, array $units): string
    {
        return $this->scale === null
            ? $this->evaluate($expression, $functions, $units)
            : (new self())->evaluate($expression, $functions, $units);
    }

    public function evaluate(string $expression, array $functions, array $units): string
    {
        $tree = (new DecimalParser($expression, $functions, $units))->parse();
        return $this->execute($tree, $units, 0);
    }

    private function execute(array $node, array $units, int $depth): string
    {
        if ($depth > 128) {
            throw new Exception('decimal expression too deeply nested');
        }
        if ($node[0] === 'variable') {
            return $this->externalDecimal($this->variables[$node[1]]);
        }
        if ($node[0] === 'conditional') {
            $condition = $this->execute($node[2][0], $units, $depth + 1);
            return $this->execute($node[2][$condition === '0' ? 2 : 1], $units, $depth + 1);
        }
        if ($node[0] === 'number') {
            return $this->literal($node[1]);
        }
        if ($node[0] === 'unit') {
            $amount = $this->literal($node[1]);
            $factor = $units[$node[2]];
            if ($factor instanceof DecimalUnit) {
                return $this->externalDecimal($factor->convert($amount));
            }
            return $this->multiply($amount, $this->externalDecimal($factor));
        }
        if ($node[0] === 'unary') {
            $value = $this->execute($node[2], $units, $depth + 1);
            if ($node[1] === '!' || $node[1] === 'not') {
                return $value === '0' ? '1' : '0';
            }
            return $node[1] === '-' ? $this->normalize(bcsub('0', $value, $this->scale($value))) : $value;
        }
        if ($node[0] === 'call') {
            $arguments = [];
            foreach ($node[2] as $argument) {
                $arguments[] = $this->execute($argument, $units, $depth + 1);
            }
            return $this->call($node[1], $arguments);
        }
        $left = $this->execute($node[2], $units, $depth + 1);
        $operator = $node[1];
        if (in_array($operator, ['&&', 'and'], true) && $left === '0') {
            return '0';
        }
        if (in_array($operator, ['||', 'or'], true) && $left !== '0') {
            return '1';
        }
        $right = $this->execute($node[3], $units, $depth + 1);
        $scale = max($this->scale($left), $this->scale($right));
        switch ($operator) {
            case '+':
                return $this->normalize(bcadd($left, $right, $scale));
            case '-':
                return $this->normalize(bcsub($left, $right, $scale));
            case '*':
                return $this->multiply($left, $right);
            case '/':
                return $this->divide($left, $right);
            case '**':
                return $this->power($left, $right);
            case '%':
                if ($scale !== 0) {
                    throw new Exception('Decimal modulo requires integer operands; use fmod() for decimals');
                }
                return $this->remainder($left, $right);
            case '&&':
            case 'and':
            case '||':
            case 'or':
                return $right === '0' ? '0' : '1';
            case '^^':
            case 'xor':
                return ($left === '0') !== ($right === '0') ? '1' : '0';
        }
        $comparison = bccomp($left, $right, $scale);
        switch ($operator) {
            case '<=>':
                return (string)$comparison;
            case '==':
            case '===':
                return $comparison === 0 ? '1' : '0';
            case '!=':
            case '!==':
                return $comparison !== 0 ? '1' : '0';
            case '<':
                return $comparison < 0 ? '1' : '0';
            case '<=':
                return $comparison <= 0 ? '1' : '0';
            case '>':
                return $comparison > 0 ? '1' : '0';
            case '>=':
                return $comparison >= 0 ? '1' : '0';
        }
        throw new Exception('Unsupported decimal operator');
    }

    private function externalDecimal($value): string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new Exception(
                'Exact decimal values must be strings or integers; float values and float-only units are unsupported'
            );
        }
        return $this->decimal((string)$value);
    }

    private function decimal(string $value): string
    {
        if (strlen($value) > 4096 || !preg_match(
                '~^([+-]?)(\d+(?:\.\d*)?|\.\d+)(?:[eE]([+-]?\d+))?$~D',
                $value,
                $parts
            )) {
            throw new Exception('Invalid decimal value');
        }
        $mantissa = $parts[2];
        $exponentText = $parts[3] ?? '0';
        if (strlen(ltrim($exponentText, '+-0')) > 4 || abs((int)$exponentText) > self::MAX_DIGITS) {
            throw new Exception('decimal exponent limit exceeded');
        }
        $exponent = (int)$exponentText;
        $point = strpos($mantissa, '.');
        $point = ($point === false ? strlen($mantissa) : $point) + $exponent;
        $digits = str_replace('.', '', $mantissa);
        if ($point <= 0) {
            $result = '0.' . str_repeat('0', -$point) . $digits;
        } elseif ($point >= strlen($digits)) {
            $result = $digits . str_repeat('0', $point - strlen($digits));
        } else {
            $result = substr($digits, 0, $point) . '.' . substr($digits, $point);
        }
        return $this->normalize($parts[1] . $result);
    }

    private function normalize(string $value): string
    {
        $negative = isset($value[0]) && $value[0] === '-';
        $value = ltrim($value, '+-');
        if (strpos($value, '.') !== false) {
            $value = rtrim(rtrim($value, '0'), '.');
        }
        $value = ltrim($value, '0');
        if ($value === '' || $value[0] === '.') {
            $value = '0' . $value;
        }
        if (strlen(str_replace('.', '', $value)) > self::MAX_DIGITS) {
            throw new Exception('decimal digit limit exceeded');
        }
        return $negative && $value !== '0' ? '-' . $value : $value;
    }

    private function literal(string $literal): string
    {
        $negative = isset($literal[0]) && $literal[0] === '-';
        $literal = ltrim($literal, '+-');
        $literal = str_replace('_', '', strtolower($literal));
        $base = 10;
        if (substr($literal, 0, 2) === '0x') {
            $base = 16;
        } elseif (substr($literal, 0, 2) === '0b') {
            $base = 2;
        } elseif (substr($literal, 0, 2) === '0o') {
            $base = 8;
        } elseif (preg_match('~^0\d+$~D', $literal)) {
            $base = 8;
        }
        if ($base !== 10) {
            $digits = substr(
                $literal,
                substr($literal, 0, 2) === '0x' || substr($literal, 0, 2) === '0b' || substr(
                    $literal,
                    0,
                    2
                ) === '0o' ? 2 : 1
            );
            $value = '0';
            foreach (str_split($digits) as $digit) {
                $number = strpos('0123456789abcdef', $digit);
                if ($number === false || $number >= $base) {
                    throw new Exception('Invalid base literal');
                }
                $value = $this->normalize(bcadd(bcmul($value, (string)$base, 0), (string)$number, 0));
            }
            return $negative && $value !== '0' ? '-' . $value : $value;
        }
        return $this->decimal(($negative ? '-' : '') . $literal);
    }

    private function multiply(string $left, string $right): string
    {
        return $this->normalize(bcmul($left, $right, $this->scale($left) + $this->scale($right)));
    }

    private function scale(string $value): int
    {
        $point = strpos($value, '.');
        return $point === false ? 0 : strlen($value) - $point - 1;
    }

    private function call($target, array $arguments): string
    {
        if (!is_string($target)) {
            return $this->externalDecimal(call_user_func_array($target, $arguments));
        }
        $name = strtolower($target);
        $roundingHelpers = [
            'vanderlee\\expression\\nativerounding::halfeven' => self::HALF_EVEN,
            'vanderlee\\expression\\nativerounding::halfup' => self::HALF_UP,
            'vanderlee\\expression\\nativerounding::towardzero' => self::TOWARD_ZERO,
        ];
        if (isset($roundingHelpers[$name])) {
            if (count($arguments) < 1 || count($arguments) > 2) {
                throw new Exception('Rounding accepts one or two arguments');
            }
            $precision = $arguments[1] ?? '0';
            if ($this->scale($precision) !== 0 || abs((float)$precision) > 999) {
                throw new Exception('Invalid rounding precision');
            }
            $factor = $this->decimal('1e' . $precision);
            $shifted = $this->multiply($arguments[0], $factor);
            $context = new self(0, $roundingHelpers[$name]);
            $guarded = bcdiv($shifted, '1', 1);
            $sticky = bccomp($guarded, $shifted, $this->scale($shifted)) !== 0;
            return $this->divideExact($context->roundGuarded($guarded, $sticky), $factor);
        }
        if ($name === 'vanderlee\\expression\\nativerounding::increment') {
            if (count($arguments) !== 2 || bccomp($arguments[1], '0', $this->scale($arguments[1])) <= 0) {
                throw new Exception('round_increment requires a positive increment');
            }
            // Round the quotient directly with an exact remainder, even in strict mode.
            $context = new self(0, self::HALF_UP);
            return $this->multiply($context->divide($arguments[0], $arguments[1]), $arguments[1]);
        }
        $counts = ['abs' => 1, 'ceil' => 1, 'floor' => 1, 'sqrt' => 1, 'pow' => 2, 'fmod' => 2];
        if (isset($counts[$name]) && count($arguments) !== $counts[$name]) {
            throw new Exception('Invalid decimal function argument count');
        }
        if ($name === 'abs') {
            return ltrim($arguments[0], '-');
        }
        if ($name === 'pow') {
            return $this->power($arguments[0], $arguments[1]);
        }
        if ($name === 'fmod') {
            return $this->remainder($arguments[0], $arguments[1]);
        }
        if ($name === 'sqrt') {
            if ($arguments[0][0] === '-') {
                throw new Exception('Square root of a negative decimal');
            }
            if ($this->scale !== null) {
                $guarded = bcsqrt($arguments[0], $this->scale + 1);
                $square = bcmul($guarded, $guarded, 2 * ($this->scale + 1));
                $sticky = bccomp($square, $arguments[0], max($this->scale($square), $this->scale($arguments[0]))) !== 0;
                return $this->roundGuarded($guarded, $sticky);
            }
            $root = $this->normalize(bcsqrt($arguments[0], self::MAX_DIGITS - 1));
            if ($this->multiply($root, $root) !== $arguments[0]) {
                throw new Exception('Square root has no exact finite decimal result within the digit limit');
            }
            return $root;
        }
        if ($name === 'round') {
            if (count($arguments) < 1 || count($arguments) > 2) {
                throw new Exception('round accepts one or two decimal arguments');
            }
            $precision = $arguments[1] ?? '0';
            if ($this->scale($precision) !== 0 || strlen(ltrim($precision, '-')) > 3 || abs(
                    (int)$precision
                ) >= self::MAX_DIGITS) {
                throw new Exception('Decimal rounding precision must be an integer between -999 and 999');
            }
            $factor = $this->decimal('1e' . $precision);
            $shifted = $this->multiply(ltrim($arguments[0], '-'), $factor);
            $rounded = $this->normalize(bcdiv(bcadd($shifted, '0.5', max(1, $this->scale($shifted))), '1', 0));
            $result = $this->divideExact($rounded, $factor);
            return $arguments[0][0] === '-' && $result !== '0' ? '-' . $result : $result;
        }
        if ($name === 'ceil' || $name === 'floor') {
            $value = $arguments[0];
            $integer = bcdiv($value, '1', 0);
            if ($this->scale($value) > 0) {
                if ($name === 'ceil' && $value[0] !== '-') {
                    return bcadd($integer, '1', 0);
                }
                if ($name === 'floor' && $value[0] === '-') {
                    return bcsub($integer, '1', 0);
                }
            }
            return $this->normalize($integer);
        }
        if ($name === 'min' || $name === 'max') {
            if (count($arguments) < 2) {
                throw new Exception('min/max require at least two decimal arguments');
            }
            $result = $arguments[0];
            foreach ($arguments as $value) {
                $comparison = bccomp($value, $result, max($this->scale($value), $this->scale($result)));
                if (($name === 'min' && $comparison < 0) || ($name === 'max' && $comparison > 0)) {
                    $result = $value;
                }
            }
            return $result;
        }
        if (strpos($target, '::') !== false) {
            list($class, $method) = explode('::', $target, 2);
            $reflection = new \ReflectionMethod($class, $method);
        } else {
            $reflection = new \ReflectionFunction($target);
        }
        if ($reflection->isInternal()) {
            throw new Exception('Function `' . $target . '` is unsupported by the exact decimal backend');
        }
        return $this->externalDecimal(call_user_func_array($target, $arguments));
    }

    private function divideExact(string $left, string $right): string
    {
        if ($right === '0') {
            throw new Exception('Division by zero');
        }
        $result = $this->normalize(bcdiv($left, $right, self::MAX_DIGITS - 1));
        if ($this->multiply($result, $right) !== $left) {
            throw new Exception('Division has no exact finite decimal result within the digit limit');
        }
        return $result;
    }

    /** Round a value with one guard digit; sticky marks further nonzero digits. */
    private function roundGuarded(string $value, bool $sticky): string
    {
        $negative = $value[0] === '-';
        $parts = explode('.', ltrim($value, '-'));
        $fraction = str_pad($parts[1] ?? '', $this->scale + 1, '0');
        $kept = $parts[0] . ($this->scale === 0 ? '' : '.' . substr($fraction, 0, $this->scale));
        $guard = (int)$fraction[$this->scale];
        $last = (int)substr($kept, -1);
        $increment = $this->rounding !== self::TOWARD_ZERO && ($guard > 5
                || ($guard === 5 && ($this->rounding === self::HALF_UP || $sticky || $last % 2 !== 0)));
        if ($increment) {
            $step = $this->scale === 0 ? '1' : '0.' . str_repeat('0', $this->scale - 1) . '1';
            $kept = bcadd($kept, $step, $this->scale);
        }
        return $this->normalize(($negative ? '-' : '') . $kept);
    }

    private function divide(string $left, string $right): string
    {
        if ($this->scale === null) {
            return $this->divideExact($left, $right);
        }
        if ($right === '0') {
            throw new Exception('Division by zero');
        }
        $guarded = bcdiv($left, $right, $this->scale + 1);
        $product = bcmul($guarded, $right, $this->scale($guarded) + $this->scale($right));
        $sticky = bccomp($product, $left, max($this->scale($product), $this->scale($left))) !== 0;
        return $this->roundGuarded($guarded, $sticky);
    }

    private function power(string $value, string $exponent): string
    {
        if ($this->scale($exponent) !== 0 || strlen(ltrim($exponent, '-')) > 4 || abs(
                (int)$exponent
            ) > self::MAX_DIGITS) {
            throw new Exception('Decimal powers require an integer exponent between -1000 and 1000');
        }
        $power = (int)$exponent;
        if ($this->scale($value) * abs($power) > self::MAX_DIGITS
            || strlen(explode('.', ltrim($value, '-'))[0]) * abs($power) > self::MAX_DIGITS) {
            throw new Exception('decimal power digit limit exceeded');
        }
        $result = $this->normalize(bcpow($value, (string)abs($power), $this->scale($value) * abs($power)));
        return $power < 0 ? $this->divide('1', $result) : $result;
    }

    private function remainder(string $left, string $right): string
    {
        if ($right === '0') {
            throw new Exception('Modulo by zero');
        }
        $quotient = bcdiv($left, $right, 0);
        return $this->normalize(
            bcsub($left, $this->multiply($quotient, $right), max($this->scale($left), $this->scale($right)))
        );
    }

    public function evaluateTree(
        array $tree,
        array $functions,
        array $units,
        array $variables,
        bool $exact = false
    ): string {
        // A separate context keeps recursive callbacks and concurrent instances isolated.
        $context = $exact ? new self() : clone $this;
        $context->variables = $variables;
        return $context->execute($tree, $units, 0);
    }

    /** Check literals, unit representations, and function support without callbacks. */
    public function validateTree(array $node, array $units, int $depth = 0): void
    {
        if ($depth > 128) {
            throw new Exception('Decimal expression too deeply nested');
        }
        if ($node[0] === 'number' || $node[0] === 'unit') {
            $this->literal($node[1]);
        }
        if ($node[0] === 'unit' && !$units[$node[2]] instanceof DecimalUnit) {
            $this->externalDecimal($units[$node[2]]);
        }
        if ($node[0] === 'call') {
            $target = $node[1];
            $arity = [
                'abs' => [1, 1],
                'ceil' => [1, 1],
                'floor' => [1, 1],
                'sqrt' => [1, 1],
                'pow' => [2, 2],
                'fmod' => [2, 2],
                'round' => [1, 2],
                'min' => [2, 128],
                'max' => [2, 128],
                'vanderlee\\expression\\nativerounding::halfeven' => [1, 2],
                'vanderlee\\expression\\nativerounding::halfup' => [1, 2],
                'vanderlee\\expression\\nativerounding::towardzero' => [1, 2],
                'vanderlee\\expression\\nativerounding::increment' => [2, 2]
            ];
            $name = is_string($target) ? strtolower($target) : '';
            if (isset($arity[$name])) {
                $count = count($node[2]);
                if ($count < $arity[$name][0] || $count > $arity[$name][1]) {
                    throw new \Vanderlee\Expression\DiagnosticException(
                        'invalid_argument_count',
                        'Invalid argument count for `' . $node[3] . '`',
                        $node[4]
                    );
                }
            } else {
                $reflection = new \ReflectionFunction(\Closure::fromCallable($target));
                if ($reflection->isInternal()) {
                    throw new \Vanderlee\Expression\DiagnosticException(
                        'unsupported_function',
                        'Function `' . $node[3] . '` is unsupported by the decimal backend',
                        $node[4]
                    );
                }
            }
        }
        if ($node[0] === 'call' || $node[0] === 'conditional') {
            foreach ($node[2] as $child) {
                $this->validateTree($child, $units, $depth + 1);
            }
        } elseif ($node[0] === 'unary') {
            $this->validateTree($node[2], $units, $depth + 1);
        } elseif ($node[0] === 'binary') {
            $this->validateTree($node[2], $units, $depth + 1);
            $this->validateTree($node[3], $units, $depth + 1);
        }
    }
}
