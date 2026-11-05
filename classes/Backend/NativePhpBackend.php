<?php

declare(strict_types=1);

namespace Vanderlee\Expression\Backend;

use Throwable;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\ResourceLimits;
use Vanderlee\Expression\Unit\Unit;

/** Native PHP normalization and eval execution, returning finite floats. */
final class NativePhpBackend extends AbstractNativeBackend
{
    private const BOOLEAN_LITERALS = ['false' => '0', 'true' => '1'];

    /** @var string|null */
    private $unitSuffixPattern;

    /** @var array */
    private $cachedSuffixes = [];

    public function evaluate(string $expression, array $functions, array $units): float
    {
        self::assertNativeSource($expression);

        // Normalize non-decimal and underscore decimal literals.
        $expression = preg_replace_callback(
            '~\b(?:0x[[:xdigit:]]+|0b[01]+|0[oO][0-7]+|[1-9]\d*(?:_\d+)+)\b~i',
            static function (array $match): string {
                return self::normalizeNumericLiteral($match[0]);
            },
            $expression
        );

        // Convert units
        if ($units !== []) {
            $pattern = '~((?<=[\w.)])\s*-\s*)?(?<![\w.])(-?(?:\d+\.?\d*|\.\d+))('
                . $this->getUnitSuffixPattern($units) . ')(?![\w.])~i';
            foreach ($units as $unit) {
                if ($unit instanceof Unit) {
                    // Check source syntax and allowlists without invoking host
                    // conversions. Numeric placeholders preserve operand shape.
                    $count = 0;
                    $validation = preg_replace_callback($pattern, static function (array $match): string {
                        return $match[1] . '(0.0)';
                    }, $expression, -1, $count);
                    if ($count > 0) {
                        self::assertSyntax($this->prepareExpression($validation, $functions));
                    }
                    break;
                }
            }
            $expression = preg_replace_callback(
                $pattern,
                static function (array $match) use ($units): string {
                    return self::convertUnit($match, $units);
                },
                $expression
            );
        }

        return self::runExpression($this->prepareExpression($expression, $functions));
    }

    private function getUnitSuffixPattern(array $units): string
    {
        if ($this->unitSuffixPattern !== null && $this->cachedSuffixes === array_keys($units)) {
            return $this->unitSuffixPattern;
        }

        $this->cachedSuffixes = array_keys($units);
        return $this->unitSuffixPattern = UnitSuffixes::pattern($units);
    }

    private static function assertSyntax(string $expression): void
    {
        try {
            // Compile an unreachable return: no function/unit executes. Unlike
            // evaluated closures, this does not retain closure compilation data
            // for the lifetime of a long-running PHP process.
            eval('if (false) { return(' . $expression . '); }');
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), 0, $throwable);
        }
    }

    private function prepareExpression(string $expression, array $functions): string
    {
        // Preserve token boundaries: deleting whitespace can turn "1 2" into "12".
        $expression = trim(
            strtolower(
                preg_replace(
                    ['~\band\b~i', '~\bor\b~i', '~\bnot\b~i', '~\bxor\b~i', '~\s+~'],
                    ['&&', '||', '!', '^^', ' '],
                    $expression
                )
            )
        );

        ResourceLimits::assertExpression($expression);

        // Empty expression
        if ($expression === '') {
            throw new Exception('Empty expression');
        }

        if (strpos($expression, '/*') !== false || strpos($expression, '//') !== false) {
            throw new Exception('PHP comments are not mathematical expressions');
        }

        // Map function
        $numberPattern = '(?:\b\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?';
        $expression = preg_replace_callback(
            '~' . $numberPattern . '(*SKIP)(*F)|\b[a-z_]\w*\b~i',
            static function (array $match) use ($functions): string {
                return self::mapFunction($match, $functions);
            },
            $expression
        );

        // Invalid function calls
        if (preg_match(
                '~' . $numberPattern . '(*SKIP)(*F)|[a-z_][\w:]*(?![\w:])(?!\s*\()~i',
                $expression,
                $match
            ) > 0) {
            throw new Exception(sprintf('Invalid function call `%s`', $match[0]));
        }

        // Illegal characters
        if (preg_match('~[^-^+/%*&|<>!=.()0-9a-z,_:\\\\ ]~i', $expression, $match) > 0) {
            throw new Exception(sprintf('Illegal character `%s`', $match[0]));
        }

        // A dot must belong to a numeric literal, never PHP string concatenation.
        if (preg_match('~' . $numberPattern . '(*SKIP)(*F)|\.~i', $expression) > 0) {
            throw new Exception('Invalid numeric literal');
        }

        // Replace boolean operator 'xor'
        $expression = str_replace('^^', ' xor ', $expression);

        return $expression;
    }

    /**
     * @param string[] $match
     * @return string
     * @throws Exception
     */
    private static function mapFunction(array $match, array $functions): string
    {
        $function = $match[0];

        if (isset(self::BOOLEAN_LITERALS[$function])) {
            return self::BOOLEAN_LITERALS[$function];
        }

        if (isset($functions[$function])) {
            return '\\' . $functions[$function];
        }

        throw new Exception(sprintf('Illegal function `%s`', $function));
    }

    /**
     * @param string[] $match
     * @throws Exception
     */
    private static function convertUnit(array $match, array $units): string
    {
        $unit = $units[$match[3]] ?? null;

        if (null === $unit) {
            throw new Exception(sprintf('Undefined unit `%s`', $match[3]));
        }

        $result = self::convertUnitAmount($match[2], $unit);

        // Keep subtraction separate from signed unit amounts, and make each
        // replacement one operand even when conversion changes its sign.
        return $match[1] . '(' . self::formatNumericLiteral($result) . ')';
    }
}
