<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

use Throwable;
use Vanderlee\Expression\Unit\Unit;

class Expression
{
    private const MAX_EXPRESSION_LENGTH = 4096;
    private const MAX_PARENTHESIS_DEPTH = 128;
    private const MAX_FUNCTION_CALLS = 128;

    private const DEFAULT_FUNCTIONS = [
        'abs' => 'abs',
        'acos' => 'acos',
        'acosh' => 'acosh',
        'asin' => 'asin',
        'asinh' => 'asinh',
        'atan2' => 'atan2',
        'atan' => 'atan',
        'atanh' => 'atanh',
        'ceil' => 'ceil',
        'cos' => 'cos',
        'cosh' => 'cosh',
        'deg2rad' => 'deg2rad',
        'exp' => 'exp',
        'expm1' => 'expm1',
        'floor' => 'floor',
        'fmod' => 'fmod',
        'getrandmax' => 'getrandmax',
        'hypot' => 'hypot',
        'is_finite' => 'is_finite',
        'is_infinite' => 'is_infinite',
        'is_nan' => 'is_nan',
        'lcg_value' => 'lcg_value',
        'log10' => 'log10',
        'log1p' => 'log1p',
        'log' => 'log',
        'max' => 'max',
        'min' => 'min',
        'mt_getrandmax' => 'mt_getrandmax',
        'mt_rand' => 'mt_rand',
        'mt_srand' => 'mt_srand',
        'pi' => 'pi',
        'pow' => 'pow',
        'rad2deg' => 'rad2deg',
        'rand' => 'rand',
        'round' => 'round',
        'sin' => 'sin',
        'sinh' => 'sinh',
        'sqrt' => 'sqrt',
        'tan' => 'tan',
        'tanh' => 'tanh',
    ];

    /**
     * @var array<string, string>
     */
    private $functions = [];

    /**
     * @var array<string, float|Unit>
     */
    private $units = [];

    /**
     * @var string|null
     */
    private $unitSuffixPattern;

    public function __construct()
    {
        $this->resetFunctions();
    }

    public function resetFunctions(): void
    {
        $this->functions = self::DEFAULT_FUNCTIONS;
    }

    /**
     * @param mixed $function
     * @throws Exception
     */
    public function addFunction(string $alias, $function = null): void
    {
        $alias = $this->validateFunctionAlias($alias);
        $function = $this->validateFunctionTarget($function ?: $alias);

        $this->functions[$alias] = $function;
    }

    /**
     * @throws Exception
     */
    private function validateFunctionAlias(string $alias): string
    {
        if (preg_match('~^[a-z_]\w*$~i', $alias) !== 1) {
            throw new Exception(sprintf('Invalid function alias `%s`', $alias));
        }

        return strtolower($alias);
    }

    /**
     * @param mixed $function
     * @throws Exception
     */
    private function validateFunctionTarget($function): string
    {
        if (!is_string($function)) {
            throw new Exception('Invalid function target');
        }

        if (preg_match('~^[a-z_]\w*$~i', $function) !== 1
            && preg_match('~^(?:[a-z_]\w*\\\\)*[a-z_]\w*::[a-z_]\w*$~i', $function) !== 1
        ) {
            throw new Exception(sprintf('Invalid function target `%s`', $function));
        }

        if (!is_callable($function)) {
            throw new Exception(sprintf('Function target `%s` is not callable', $function));
        }

        return $function;
    }

    public function removeFunction(string $alias): void
    {
        unset($this->functions[$alias]);
    }

    public function clearFunctions(): void
    {
        $this->functions = [];
    }

    /**
     * @param float|Unit $unitsize
     * @throws Exception
     */
    public function addUnit(string $suffix, $unitsize = 1.): void
    {
        if ($suffix === '') {
            throw new Exception('empty unit suffix');
        }

        $this->units[$suffix] = $unitsize;
        $this->unitSuffixPattern = null;
    }

    public function removeUnit(string $suffix): void
    {
        unset($this->units[$suffix]);
        $this->unitSuffixPattern = null;
    }

    public function clearUnits(): void
    {
        $this->units = [];
        $this->unitSuffixPattern = null;
    }

    public function evaluate(string $expression): float
    {
        $this->assertWithinExpressionLength($expression);

        // Normalize non-decimal and underscore decimal literals.
        $expression = preg_replace_callback(
            '~\b(?:0x[[:xdigit:]]+|0b[01]+|0[oO][0-7]+|[1-9]\d*(?:_\d+)+)\b~',
            static function (array $match): string {
                $literal = $match[0];
                $prefix = strtolower(substr($literal, 0, 2));

                if ($prefix === '0x') {
                    return (string) hexdec($literal);
                }

                if ($prefix === '0b') {
                    return (string) bindec($literal);
                }

                if ($prefix === '0o') {
                    return (string) octdec($literal);
                }

                return (string) floatval(str_replace('_', '', $literal));
            },
            $expression
        );

        // Convert units
        if ($this->units !== []) {
            $expression = preg_replace_callback(
                '~(-?(?:\d+\\.?\d*|\\.\d+))(' . $this->getUnitSuffixPattern() . ')~i',
                [$this, 'convertUnit'],
                $expression
            );
        }

        // Normalize boolean logic keywords and remove whitespace.
        $expression = strtolower(preg_replace(
            ['~\band\b~', '~\bor\b~', '~\bnot\b~', '~\bxor\b~', '~\s+~'],
            ['&&', '||', '!', '^^', ''],
            $expression
        ));

        $this->assertWithinResourceLimits($expression);

        // Empty expression
        if ($expression === '') {
            throw new Exception('Empty expression');
        }

        // Map function
        $expression = preg_replace_callback('~\b[a-z_]\w*\b~i', [$this, 'mapFunction'], $expression);

        // Invalid function calls
        if (preg_match('~[a-z_][\w:]*(?![(\w:])~i', $expression, $match) > 0) {
            throw new Exception(sprintf('Invalid function call `%s`', $match[0]));
        }

        // Illegal characters
        if (preg_match('~[^-^+/%*&|<>!=.()0-9a-z,_:\\\\]~i', $expression, $match) > 0) {
            throw new Exception(sprintf('Illegal character `%s`', $match[0]));
        }

        // Replace boolean operator 'xor'
        $expression = str_replace('^^', ' xor ', $expression);

        return self::runExpression($expression);
    }

    /**
     * @throws Exception
     */
    private function assertWithinExpressionLength(string $expression): void
    {
        if (strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new Exception('expression too long');
        }
    }

    /**
     * @throws Exception
     */
    private function assertWithinResourceLimits(string $expression): void
    {
        $this->assertWithinExpressionLength($expression);

        $depth = 0;
        $maxDepth = 0;
        $length = strlen($expression);

        for ($index = 0; $index < $length; ++$index) {
            if ($expression[$index] === '(') {
                ++$depth;
                $maxDepth = max($maxDepth, $depth);
            } elseif ($expression[$index] === ')') {
                --$depth;
            }
        }

        if ($maxDepth > self::MAX_PARENTHESIS_DEPTH) {
            throw new Exception('expression too deeply nested');
        }

        if (preg_match_all('~\b[a-z_]\w*\s*\(~i', $expression) > self::MAX_FUNCTION_CALLS) {
            throw new Exception('too many function calls');
        }
    }

    /**
     * @throws Exception
     */
    private static function runExpression(string $expression): float
    {
        ob_start();

        try {
            $result = floatval(eval('return(' . $expression . ');'));
        } catch (Throwable $throwable) {
            ob_end_clean();
            throw new Exception($throwable->getMessage(), 0, $throwable);
        }

        if (ob_get_clean() !== '') {
            throw new Exception('Syntax error');
        }

        return $result;
    }

    private function getUnitSuffixPattern(): string
    {
        if ($this->unitSuffixPattern !== null) {
            return $this->unitSuffixPattern;
        }

        $suffixes = array_keys($this->units);
        usort($suffixes, static function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });
        $suffixes = array_map(static function (string $suffix): string {
            return preg_quote($suffix, '~');
        }, $suffixes);

        return $this->unitSuffixPattern = implode('|', $suffixes);
    }

    /**
     * @param string[] $match
     * @throws Exception
     */
    private function convertUnit(array $match): float
    {
        $unit = $this->units[$match[2]] ?? null;

        if (null === $unit) {
            throw new Exception(sprintf('Undefined unit `%s`', $match[2]));
        }

        return ($unit instanceof Unit)
            ? $unit->convert(floatval($match[1]))
            : $match[1] * $unit;
    }

    /**
     * @param string[] $match
     * @return string
     * @throws Exception
     */
    private function mapFunction(array $match): string
    {
        $function = $match[0];

        if (isset($this->functions[$function])) {
            return '\\' . $this->functions[$function];
        }

        throw new Exception(sprintf('Illegal function `%s`', $function));
    }
}
