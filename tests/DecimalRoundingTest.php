<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class DecimalRoundingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
    }

    public static function roundedExpressions(): array
    {
        return [
            ['1/3', 20, BcMathBackend::HALF_UP, '0.33333333333333333333'],
            ['sqrt(2)', 20, BcMathBackend::HALF_UP, '1.4142135623730950488'],
            ['round(1/3, 2)', 20, BcMathBackend::HALF_UP, '0.33'],
            ['1/8', 2, BcMathBackend::HALF_UP, '0.13'],
            ['-1/8', 2, BcMathBackend::HALF_UP, '-0.13'],
            ['1/8', 2, BcMathBackend::HALF_EVEN, '0.12'],
            ['3/8', 2, BcMathBackend::HALF_EVEN, '0.38'],
            ['-1/8', 2, BcMathBackend::HALF_EVEN, '-0.12'],
            ['1.2500001/10', 2, BcMathBackend::HALF_EVEN, '0.13'],
            ['1.2499999/10', 2, BcMathBackend::HALF_EVEN, '0.12'],
            ['9.995/1', 2, BcMathBackend::HALF_UP, '10'],
            ['-2/3', 2, BcMathBackend::TOWARD_ZERO, '-0.66'],
            ['1/3', 0, BcMathBackend::HALF_UP, '0'],
            ['-1/1000000', 2, BcMathBackend::HALF_UP, '0'],
            ['5/2', 0, BcMathBackend::HALF_EVEN, '2'],
            ['7/2', 0, BcMathBackend::HALF_EVEN, '4'],
            ['sqrt(0.0625)', 1, BcMathBackend::HALF_EVEN, '0.2'],
            ['sqrt(0.0625)', 1, BcMathBackend::HALF_UP, '0.3'],
            ['sqrt(0.0625000001)', 1, BcMathBackend::HALF_EVEN, '0.3'],
            ['sqrt(0.0624999999)', 1, BcMathBackend::HALF_EVEN, '0.2'],
            ['sqrt(2)', 3, BcMathBackend::TOWARD_ZERO, '1.414'],
            ['sqrt(0)', 3, BcMathBackend::HALF_UP, '0'],
            ['3**-1', 4, BcMathBackend::HALF_UP, '0.3333'],
            ['pow(-3,-1)', 4, BcMathBackend::HALF_UP, '-0.3333'],
            ['0.1 + 0.2 == 0.3', 2, BcMathBackend::HALF_UP, '1'],
            ['1.23456789 * 10', 2, BcMathBackend::HALF_UP, '12.3456789'],
            ['round(1.255,2)', 0, BcMathBackend::HALF_UP, '1.26'],
            ['round(1.255,2)', 0, BcMathBackend::TOWARD_ZERO, '1.26'],
            ['false && (1/0)', 2, BcMathBackend::HALF_UP, '0'],
        ];
    }

    /** @dataProvider roundedExpressions */
    public function testExplicitDecimalRounding(string $input, int $scale, string $rounding, string $expected): void
    {
        $expression = new Expression(new BcMathBackend($scale, $rounding));
        $this->assertSame($expected, $expression->evaluateDecimal($input));
    }

    public function testExactApiBypassesRoundedPolicy(): void
    {
        $expression = new Expression(new BcMathBackend(2));
        $this->assertSame('0.13', $expression->evaluateDecimal('1/8'));
        $this->assertSame('0.125', $expression->evaluateExact('1/8'));
        $this->assertSame(0.13, $expression->evaluate('1/8'));
        $this->assertSame('0.13', $expression->evaluateDecimal('1/8'));
        $this->expectException(Exception::class);
        $expression->evaluateExact('1/3');
    }

    public function testExactRootStillRejectsIrrationalResultsInRoundedBackend(): void
    {
        $expression = new Expression(new BcMathBackend(10));
        $this->assertSame('0.25', $expression->evaluateExact('sqrt(0.0625)'));
        $this->expectException(Exception::class);
        $expression->evaluateExact('sqrt(2)');
    }

    public function testDefaultBackendRetainsStrictDecimalPolicy(): void
    {
        $this->expectException(Exception::class);
        (new Expression(new BcMathBackend()))->evaluateDecimal('1/3');
    }

    public function testNativeBackendCannotClaimDecimalResults(): void
    {
        $this->expectException(Exception::class);
        (new Expression())->evaluateDecimal('1/3');
    }

    public static function invalidPolicies(): array
    {
        return [[-1, BcMathBackend::HALF_UP], [1000, BcMathBackend::HALF_UP], [2, 'unknown']];
    }

    /** @dataProvider invalidPolicies */
    public function testInvalidPoliciesAreRejected(int $scale, string $rounding): void
    {
        $this->expectException(Exception::class);
        new BcMathBackend($scale, $rounding);
    }

    public function testRoundingAtMaximumScaleIsBounded(): void
    {
        $expression = new Expression(new BcMathBackend(999));
        $this->assertSame('0.' . str_repeat('3', 999), $expression->evaluateDecimal('1/3'));
    }

    public static function invalidRoundedOperations(): array
    {
        return [['1/0'], ['sqrt(-1)'], ['0**-1'], ['1e999/0.1'], ['sin(1)'], ['2**0.5']];
    }

    /** @dataProvider invalidRoundedOperations */
    public function testRoundingDoesNotBypassDomainsUnsupportedFunctionsOrLimits(string $input): void
    {
        $this->expectException(Exception::class);
        (new Expression(new BcMathBackend(2)))->evaluateDecimal($input);
    }

    public function testRoundedEvaluationDoesNotChangeGlobalPrecision(): void
    {
        $scale = ini_get('bcmath.scale');
        $precision = ini_get('serialize_precision');
        $expression = new Expression(new BcMathBackend(3, BcMathBackend::HALF_EVEN));
        $this->assertSame('0.333', $expression->evaluateDecimal('1/3'));
        $this->assertSame($scale, ini_get('bcmath.scale'));
        $this->assertSame($precision, ini_get('serialize_precision'));
    }

    public function testRoundedDivisionMatchesIndependentIntegerRemainderOracle(): void
    {
        foreach ([0, 1, 2] as $scale) {
            $factor = 10 ** $scale;
            foreach ([BcMathBackend::HALF_UP, BcMathBackend::HALF_EVEN, BcMathBackend::TOWARD_ZERO] as $policy) {
                $expression = new Expression(new BcMathBackend($scale, $policy));
                foreach ([-101, -25, -1, 0, 1, 25, 101] as $numerator) {
                    foreach ([2, 3, 7, 8, -2, -7] as $denominator) {
                        $magnitude = abs($numerator) * $factor;
                        $divisor = abs($denominator);
                        $integer = intdiv($magnitude, $divisor);
                        $remainder = $magnitude % $divisor;
                        if ($policy !== BcMathBackend::TOWARD_ZERO && (2 * $remainder > $divisor
                            || (2 * $remainder === $divisor && ($policy === BcMathBackend::HALF_UP || $integer % 2 !== 0)))) {
                            ++$integer;
                        }
                        $expected = (string) intdiv($integer, $factor);
                        if ($scale > 0) {
                            $fraction = rtrim(str_pad((string) ($integer % $factor), $scale, '0', STR_PAD_LEFT), '0');
                            if ($fraction !== '') { $expected .= '.' . $fraction; }
                        }
                        if (($numerator < 0) !== ($denominator < 0) && $integer !== 0) { $expected = '-' . $expected; }
                        $input = $numerator . '/' . $denominator;
                        $this->assertSame($expected, $expression->evaluateDecimal($input), $input . ' scale=' . $scale . ' policy=' . $policy);
                    }
                }
            }
        }
    }
}
