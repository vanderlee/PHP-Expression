<?php
declare(strict_types=1);

namespace Vanderlee\Expression;

use Throwable;
use Vanderlee\Expression\Unit\Unit;

/**
 * @author Martijn
 */
class Expression
{
    private static $defaultFunctions = [
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
     * List of functions supported
     *
     * @var array
     */
    private $functions = [];

    /**
     * List of unit suffixes and unitsize for conversion
     *
     * @var Unit[] map of suffix and unitsize
     */
    private $units = [];

    public function __construct()
    {
        $this->resetFunctions();
    }

    public function resetFunctions()
    {
        $this->functions = self::$defaultFunctions;
    }

    public function addFunction(string $alias, $function = null): void
    {
        $this->functions[$alias] = $function ?: $alias;
    }

    public function removeFunction(string $alias): void
    {
        unset($this->functions[$alias]);
    }

    public function clearFunctions(): void
    {
        $this->functions = [];
    }

    public function addUnit(string $suffix, float $unitsize = 1.): void
    {
        $this->units[$suffix] = $unitsize;
    }

    public function removeUnit(string $suffix): void
    {
        unset($this->units[$suffix]);
    }

    public function clearUnits(): void
    {
        $this->units = [];
    }

    /**
     * @param string $expression
     * @return float
     * @throws Exception
     */
    public function evaluate(string $expression): float
    {
        // Convert hexadecimal to decimal
        $expression = preg_replace_callback('~\b0x[[:xdigit:]]+\b~', function (array $match): float {
            return hexdec($match[0]);
        }, $expression);

        // Convert binary to decimal
        $expression = preg_replace_callback('~\b0b[01]+\b~', function (array $match): float {
            return bindec($match[0]);
        }, $expression);

        // Convert octal to decimal
        $expression = preg_replace_callback('~\b0[oO][0-7]+\b~', function (array $match): float {
            return octdec($match[0]);
        }, $expression);

        // Underscore decimal to decimal (since PHP 7.4)
        $expression = preg_replace_callback('~\b[1-9]\d*(?:_\d+)+\b~', function (array $match): float {
            return floatval(str_replace('_', '', $match[0]));
        }, $expression);

        // Convert units
        if ($this->units !== []) {
            $suffixes = array_keys($this->units);
            array_walk($suffixes, 'preg_quote');
            $suffixJoin = join('|', $suffixes);

            $expression = preg_replace_callback(
                '~(-?(?:\d+\\.?\d*|\\.\d+))(' . $suffixJoin . ')~i',
                [$this, 'convertUnit'],
                $expression
            );
        }

        // boolean logic keywords
        $expression = preg_replace('~\band\b~', '&&', $expression);
        $expression = preg_replace('~\bor\b~', '||', $expression);
        $expression = preg_replace('~\bnot\b~', '!', $expression);
        $expression = preg_replace('~\bxor\b~', '^^', $expression);

        // Remove any whitespace
        $expression = strtolower(preg_replace('~\s+~', '', $expression));

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
    private static function runExpression(string $expression): float
    {
        ob_start();

        try {
            $result = floatval(eval(sprintf('return(%s);', $expression)));
        } catch (Throwable $throwable) {
            ob_end_clean();
            throw new Exception($throwable->getMessage(), 0, $throwable);
        }

        if (ob_get_clean() !== '') {
            throw new Exception('Syntax error');
        }

        return $result;
    }

    /**
     * @param string[] $match
     * @return float|int
     * @throws Exception
     */
    private function convertUnit(array $match)
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
