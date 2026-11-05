<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Unit;

/** Bounded exhaustive matrices, with each failing source printed by assertions. */
class NativePipelineTest extends TestCase
{
    protected function expression(): Expression
    {
        return new Expression();
    }
    public static $functionCalls = 0;

    public static function probeValue($value): float
    {
        ++self::$functionCalls;
        return (float) $value;
    }

    public static function noisyValue($value): float
    {
        echo 'callback output';
        return (float) $value;
    }

    public static function throwingValue($value): float
    {
        throw new RuntimeException('pipeline callback failed');
    }

    private function unit(bool $nonlinear = false): Unit
    {
        return new class($nonlinear) implements Unit {
            public $calls = 0;
            private $nonlinear;
            public function __construct(bool $nonlinear) { $this->nonlinear = $nonlinear; }
            public function convert(float $amount): float
            {
                ++$this->calls;
                return $this->nonlinear ? $amount + 10 : $amount;
            }
        };
    }

    public function testEverySmallBaseLiteralAcrossNormalizationContexts(): void
    {
        $expression = $this->expression();
        $expression->addFunction('magnitude', 'abs');
        for ($value = 0; $value <= 255; ++$value) {
            foreach (['0x' . dechex($value), '0X' . strtoupper(dechex($value)),
                '0b' . decbin($value), '0B' . decbin($value),
                '0o' . decoct($value), '0O' . decoct($value)] as $literal) {
                foreach ([$literal => $value, '(' . $literal . ')+1' => $value + 1,
                    'MAGNITUDE(-' . $literal . ')' => $value,
                    $literal . ' == ' . $value . ' and true' => 1] as $source => $expected) {
                    $this->assertSame((float) $expected, $expression->evaluate($source), $source);
                }
            }
        }
        for ($value = 1000; $value <= 1255; ++$value) {
            $source = substr((string) $value, 0, 1) . '_' . substr((string) $value, 1) . '-1';
            $this->assertSame((float) ($value - 1), $expression->evaluate($source), $source);
        }
    }

    public function testEveryOperatorPairAgainstPhpPrecedenceAndTypes(): void
    {
        // PHP itself is the reference for its intended native semantics. Only
        // these fixed operators/literals reach reference eval, never user input.
        // This is complementary to the independent rational arithmetic oracle.
        $operators = ['+', '-', '*', '**', '<', '<=', '>', '>=', '==', '!=',
            '===', '!==', '<=>', '&&', '||', 'xor', '&', '|', '^', '<<', '>>', '<>'];
        foreach (['plain', 'factor', 'callback'] as $mode) {
            $expression = $this->expression();
            $unit = $this->unit();
            if ($mode !== 'plain') { $expression->addUnit('u', $mode === 'factor' ? 1 : $unit); }
            $operands = $mode === 'plain' ? ['2', '3', '1'] : ['2u', '3u', '1u'];
            $referenceOperands = $mode === 'plain' ? ['2', '3', '1'] : ['(2.0)', '(3.0)', '(1.0)'];
            foreach ($operators as $first) {
                foreach ($operators as $second) {
                    foreach (['flat', 'left', 'right'] as $grouping) {
                        foreach ([' ', "\t"] as $space) {
                            $source = self::formula($operands, $first, $second, $grouping, $space);
                            $reference = self::formula($referenceOperands, $first, $second, $grouping, ' ');
                            $syntaxError = false;
                            try { $expected = (float) eval('return(' . $reference . ');'); }
                            catch (ParseError $error) { $syntaxError = true; }
                            $unit->calls = 0;
                            if ($syntaxError) {
                                $this->reject($expression, $source);
                                $this->assertSame(0, $unit->calls, $source);
                            } else {
                                $this->assertSame($expected, $expression->evaluate($source), $source . ' mode=' . $mode);
                                if ($mode === 'callback') { $this->assertSame(3, $unit->calls, $source); }
                            }
                        }
                    }
                }
            }
        }
    }

    private static function formula(array $values, string $first, string $second, string $grouping, string $space): string
    {
        $left = $values[0] . $space . $first . $space . $values[1];
        $right = $values[1] . $space . $second . $space . $values[2];
        if ($grouping === 'left') { return '(' . $left . ')' . $space . $second . $space . $values[2]; }
        if ($grouping === 'right') { return $values[0] . $space . $first . $space . '(' . $right . ')'; }
        return $left . $space . $second . $space . $values[2];
    }

    public function testSmallSignedUnitArithmeticAndEscapedSuffixes(): void
    {
        $operations = [
            '+' => static function (float $a, float $b): float { return $a + $b; },
            '-' => static function (float $a, float $b): float { return $a - $b; },
            '*' => static function (float $a, float $b): float { return $a * $b; },
            '/' => static function (float $a, float $b): float { return $a / $b; },
        ];
        foreach (['u', 'uu', 'u+', 'u|', 'u.', 'u/', 'u/*', 'u;$'] as $suffix) {
            foreach (['positive', 'negative', 'nonlinear'] as $mode) {
                $expression = $this->expression();
                $expression->addUnit($suffix, $mode === 'nonlinear' ? $this->unit(true) : ($mode === 'negative' ? -2 : 2));
                for ($left = -3; $left <= 3; ++$left) {
                    for ($right = -3; $right <= 3; ++$right) {
                        $a = $mode === 'nonlinear' ? $left + 10.0 : $left * ($mode === 'negative' ? -2.0 : 2.0);
                        $b = $mode === 'nonlinear' ? $right + 10.0 : $right * ($mode === 'negative' ? -2.0 : 2.0);
                        foreach ($operations as $operator => $operation) {
                            if ($operator === '/' && $b === 0.0) { continue; }
                            $source = '(' . $left . $suffix . ')' . $operator . '(' . $right . $suffix . ')';
                            $this->assertSame($operation($a, $b), $expression->evaluate($source), $source . ' mode=' . $mode);
                        }
                    }
                }
            }
        }
    }

    public function testAllDisallowedBytesAtEverySourcePositionRejectBeforeCallbacks(): void
    {
        $expression = $this->expression();
        $unit = $this->unit();
        $expression->addUnit('u', $unit);
        $expression->addFunction('probe', 'NativePipelineTest::probeValue');
        $source = 'probe(1)+2u';
        $allowed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-^+/%*&|<>!=.(),_:\\ \t\n\r\v\f";
        for ($byte = 0; $byte <= 255; ++$byte) {
            if (strpos($allowed, chr($byte)) !== false) { continue; }
            for ($offset = 0; $offset <= strlen($source); ++$offset) {
                self::$functionCalls = $unit->calls = 0;
                $mutated = substr($source, 0, $offset) . chr($byte) . substr($source, $offset);
                $label = 'byte=' . $byte . ' offset=' . $offset;
                $this->reject($expression, $mutated, $label);
                $this->assertSame(0, self::$functionCalls, $label);
                $this->assertSame(0, $unit->calls, $label);
            }
        }
    }

    public function testWhitespaceAndKeywordSpellingsAcrossAllBooleanValues(): void
    {
        $expression = $this->expression();
        foreach ([' ', "\t", "\n", "\r", "\v", "\f", " \t\r\n "] as $space) {
            foreach (['and', 'AND', 'AnD', 'or', 'OR', 'Or', 'xor', 'XOR', 'XoR', '&&', '||', '^^'] as $operator) {
                for ($left = 0; $left <= 1; ++$left) {
                    for ($right = 0; $right <= 1; ++$right) {
                        $name = strtolower($operator);
                        $expected = in_array($name, ['and', '&&'], true) ? ($left && $right)
                            : (in_array($name, ['or', '||'], true) ? ($left || $right) : ($left !== $right));
                        $source = ($left ? 'TrUe' : 'FaLsE') . $space . $operator . $space . ($right ? 'TRUE' : 'FALSE');
                        $this->assertSame((float) $expected, $expression->evaluate($source), $source);
                    }
                }
            }
            foreach (['not', 'NOT', 'NoT', '!'] as $operator) {
                $this->assertSame(1.0, $expression->evaluate($operator . $space . 'false'));
                $this->assertSame(0.0, $expression->evaluate($operator . $space . 'true'));
            }
            foreach (['1' . $space . '2', '1' . $space . '.' . $space . '2', '2' . $space . 'u'] as $source) {
                $expression->addUnit('u', 2);
                $this->reject($expression, $source);
            }
        }
    }

    public function testOverlappingSuffixCacheAcrossEveryRegistrationOrderAndReplacement(): void
    {
        $expression = $this->expression();
        $orders = [['u', 'uu', 'uuu'], ['u', 'uuu', 'uu'], ['uu', 'u', 'uuu'],
            ['uu', 'uuu', 'u'], ['uuu', 'u', 'uu'], ['uuu', 'uu', 'u']];
        foreach ($orders as $order) {
            $expression->clearUnits();
            foreach ($order as $suffix) { $expression->addUnit($suffix, strlen($suffix)); }
            $this->assertSame(12.0, $expression->evaluate('2u+2uu+2uuu'));
            $expression->addUnit('uu', -4);
            $this->assertSame(0.0, $expression->evaluate('2u+2uu+2uuu'));
            $unit = $this->unit(true);
            $expression->addUnit('uu', $unit);
            $this->assertSame(20.0, $expression->evaluate('2u+2uu+2uuu'));
            $this->assertSame(1, $unit->calls);
            $expression->removeUnit('uuu');
            $this->reject($expression, '2uuu');
            $expression->addUnit('uuu', 5);
            $this->assertSame(24.0, $expression->evaluate('2u+2uu+2uuu'));
        }
    }

    public function testSignedNonlinearAmountsAtEveryWhitespaceBoundary(): void
    {
        $expression = $this->expression();
        $expression->addUnit('u', $this->unit(true));
        foreach (['', ' ', "\t", "\n", "\r\n"] as $before) {
            foreach (['', ' ', "\t", "\n", "\r\n"] as $after) {
                $this->assertSame(-2.0, $expression->evaluate('10' . $before . '-' . $after . '2u'));
                $this->assertSame($after === '' ? 8.0 : -12.0, $expression->evaluate($before . '-' . $after . '2u'));
                $this->assertSame($after === '' ? 64.0 : -144.0, $expression->evaluate($before . '-' . $after . '2u**2'));
            }
        }
    }

    public function testFunctionMappingDoesNotConsumeScientificDigitsOrUnitKeywords(): void
    {
        $expression = $this->expression();
        foreach (['e', 'e2', 'magnitude', '_probe'] as $alias) {
            $expression->addFunction($alias, 'NativePipelineTest::probeValue');
        }
        self::$functionCalls = 0;
        foreach (['1e2' => 100, '1E+2' => 100, '.1e-2' => .001, '2.e2' => 200] as $source => $expected) {
            $this->assertSame((float) $expected, $expression->evaluate($source));
        }
        $this->assertSame(0, self::$functionCalls);
        foreach (['e', 'E2', 'MAGNITUDE', '_PrObE'] as $alias) {
            $this->assertSame(3.0, $expression->evaluate($alias . " \t(-3)*-1"));
        }
        $this->assertSame(4, self::$functionCalls);
        foreach (['e', 'e2', 'magnitude', '_probe', 'unknown', 'absx', 'xabs', 'abs::other', '\\abs(1)'] as $source) {
            $this->reject($expression, $source);
        }
        $expression->clearFunctions();
        foreach (['and', 'or', 'xor', 'not', 'true', 'false', 'abs'] as $suffix) {
            $expression->addUnit($suffix, 2);
            $this->assertSame(6.0, $expression->evaluate('3' . $suffix), $suffix);
        }
        $this->reject($expression, 'abs(1)');
        $expression->resetFunctions();
        $this->assertSame(3.0, $expression->evaluate('abs(-3)'));
    }

    public function testFloatBitPatternsSurviveEveryPrecisionConfiguration(): void
    {
        $precision = ini_get('precision');
        $serializePrecision = ini_get('serialize_precision');
        $values = [0.0, -0.0, 1.0, -1.0, 1.2345678901234567, -1.2345678901234567,
            5e-324, -5e-324, 2.2250738585072014e-308, 1e-200, 1e200, 1.7976931348623157e308];
        try {
            foreach (['3', '6', '17'] as $digits) {
                foreach (['3', '6', '-1'] as $serialization) {
                    ini_set('precision', $digits);
                    ini_set('serialize_precision', $serialization);
                    foreach ($values as $value) {
                        foreach (['factor', 'callback'] as $mode) {
                            $expression = $this->expression();
                            $unit = new class($value) implements Unit {
                                private $value;
                                public function __construct(float $value) { $this->value = $value; }
                                public function convert(float $amount): float { return $this->value; }
                            };
                            $expression->addUnit('u', $mode === 'factor' ? $value : $unit);
                            $this->assertSame(pack('d', $value), pack('d', $expression->evaluate('1u')),
                                'precision=' . $digits . ' serialization=' . $serialization . ' mode=' . $mode);
                        }
                    }
                }
            }
        } finally {
            ini_set('precision', $precision);
            ini_set('serialize_precision', $serializePrecision);
        }
    }

    public function testResourceBoundariesRejectBeforeUnitAndFunctionCallbacks(): void
    {
        $expression = $this->expression();
        $unit = $this->unit();
        $expression->addUnit('u', $unit);
        $expression->addFunction('probe', 'NativePipelineTest::probeValue');
        $this->assertSame(1.0, $expression->evaluate(str_repeat(' ', 4094) . '1u'));
        $this->assertSame(1.0, $expression->evaluate(str_repeat('(', 127) . '1u' . str_repeat(')', 127)));
        $this->assertSame(129.0, $expression->evaluate(str_repeat('probe(1)+', 128) . '1u'));
        foreach ([str_repeat(' ', 4095) . '1u',
            str_repeat('(', 128) . '1u' . str_repeat(')', 128),
            str_repeat('probe(1)+', 129) . '1u'] as $source) {
            self::$functionCalls = $unit->calls = 0;
            $this->reject($expression, $source);
            $this->assertSame(0, self::$functionCalls);
            $this->assertSame(0, $unit->calls);
        }
    }

    public function testEveryExecutionOutcomePreservesHostOutputBuffer(): void
    {
        $expression = $this->expression();
        $unit = $this->unit();
        $expression->addUnit('u', $unit);
        $expression->addFunction('probe', 'NativePipelineTest::probeValue');
        $expression->addFunction('noisy', 'NativePipelineTest::noisyValue');
        $expression->addFunction('throwing', 'NativePipelineTest::throwingValue');
        $level = ob_get_level();
        ob_start();
        try {
            echo 'host prefix';
            foreach (['1u+probe(2)' => 3.0, 'false && probe(2)+1u' => 0.0,
                'true || probe(2)+1u' => 1.0] as $source => $expected) {
                self::$functionCalls = $unit->calls = 0;
                $this->assertSame($expected, $expression->evaluate($source));
                $this->assertSame(1, $unit->calls, 'Native units convert eagerly: ' . $source);
                $this->assertSame($expected === 3.0 ? 1 : 0, self::$functionCalls);
                $this->assertSame($level + 1, ob_get_level());
                $this->assertSame('host prefix', ob_get_contents());
            }
            foreach (['1u+', '1u+noisy(2)', '1u+throwing(2)', '1u+sqrt(-1)', '1u+1/0'] as $source) {
                $this->reject($expression, $source);
                $this->assertSame($level + 1, ob_get_level(), $source);
                $this->assertSame('host prefix', ob_get_contents(), $source);
            }
        } finally {
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }

    public function testSmallIntegerDivisionRemainderAndBitwiseDomains(): void
    {
        $operations = [
            '/' => static function (int $a, int $b): float { return $a / $b; },
            '%' => static function (int $a, int $b): int { return $a % $b; },
            '&' => static function (int $a, int $b): int { return $a & $b; },
            '|' => static function (int $a, int $b): int { return $a | $b; },
            '^' => static function (int $a, int $b): int { return $a ^ $b; },
            '<<' => static function (int $a, int $b): int { return $a << $b; },
            '>>' => static function (int $a, int $b): int { return $a >> $b; },
        ];
        foreach (['plain', 'factor', 'callback'] as $mode) {
            $expression = $this->expression();
            if ($mode !== 'plain') { $expression->addUnit('u', $mode === 'factor' ? 1 : $this->unit()); }
            $suffix = $mode === 'plain' ? '' : 'u';
            for ($left = -3; $left <= 3; ++$left) {
                for ($right = -3; $right <= 3; ++$right) {
                    foreach ($operations as $operator => $operation) {
                        $source = '(' . $left . $suffix . ')' . $operator . '(' . $right . $suffix . ')';
                        if ((in_array($operator, ['/', '%'], true) && $right === 0)
                            || (in_array($operator, ['<<', '>>'], true) && $right < 0)) {
                            $this->reject($expression, $source);
                        } else {
                            $this->assertSame((float) $operation($left, $right), $expression->evaluate($source), $source);
                        }
                    }
                }
            }
        }
    }

    private function reject(Expression $expression, string $source, string $label = ''): void
    {
        try {
            $expression->evaluate($source);
            $this->fail('Invalid source accepted: ' . ($label === '' ? $source : $label));
        } catch (Exception $exception) {
            $this->assertNotSame('', $exception->getMessage(), $label === '' ? $source : $label);
        }
    }
}
