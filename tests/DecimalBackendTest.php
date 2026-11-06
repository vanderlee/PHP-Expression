<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\Backend;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Percentage;

function decimal_backend_double(string $value): string
{
    return bcadd($value, $value, strpos($value, '.') === false ? 0 : strlen($value) - strpos($value, '.') - 1);
}

function decimal_backend_float(string $value): float
{
    return 0.1;
}

class DecimalBackendTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
    }

    public static function exactExpressions(): array
    {
        return [
            ['0.1 + 0.2', '0.3'], ['0.1 + 0.2 == 0.3', '1'],
            ['9007199254740993 + 1', '9007199254740994'],
            ['9999999999999999999999999999999999 * 10', '99999999999999999999999999999999990'],
            ['1e-20 + 2e-20', '0.00000000000000000003'],
            ['1_000.25 + .75', '1001'],
            ['0xFFFFFFFFFFFFFFFF + 1', '18446744073709551616'],
            ['0b1010 + 0o10 + 010', '26'],
            ['0xFF_FF + 0b1_0 + 0o1_0', '65545'],
            ['1 / 8', '0.125'], ['1.2 / 0.3', '4'],
            ['0.1 * 0.2', '0.02'], ['-0.00', '0'], ['2**3**2', '512'],
            ['-2**2', '-4'], ['(-2)**2', '4'], ['2**-3', '0.125'],
            ['10 % 3', '1'], ['-10 % 3', '-1'], ['fmod(1.25, 0.4)', '0.05'],
            ['abs(-0.1)', '0.1'], ['min(0.2, 0.1)', '0.1'], ['max(0.2, 0.1)', '0.2'],
            ['ceil(-0.1)', '0'], ['floor(-0.1)', '-1'], ['ceil(1.1)', '2'],
            ['floor(1.1)', '1'], ['sqrt(0.04)', '0.2'], ['pow(0.1, 2)', '0.01'],
            ['round(1.255, 2)', '1.26'], ['round(-1.255, 2)', '-1.26'],
            ['round(125, -1)', '130'], ['round(0.1 + 0.2)', '0'],
            ['1 < 2 && 2 >= 2', '1'], ['2 <=> 1', '1'], ['1.0 === 1', '1'],
            ['false && (1/0)', '0'], ['true || (1/0)', '1'],
            ['TRUE AND NOT FALSE', '1'], ['true ^^ true', '0'], ['false xor true', '1'],
        ];
    }

    /** @dataProvider exactExpressions */
    public function testExactArithmetic(string $input, string $expected): void
    {
        $this->assertSame($expected, (new Expression(new BcMathBackend()))->evaluateExact($input));
    }

    public static function rejectedExpressions(): array
    {
        return [
            ['1 / 3'], ['1 / 0'], ['sqrt(2)'], ['sqrt(-1)'], ['sin(1)'], ['pi()'],
            ['1 2'], ['1 . 2'], ['1;2'], ['system(1)'], ['$x'], ['true()'],
            ['2**0.5'], ['2**1000000'], ['1e999999'], ['1e1000'],
            ['0x' . str_repeat('f', 1000)], ['0.1 % 1'], ['1 % 0'],
            ['round(1, 2, 1)'], ['min(1)'], ['08'], ['1e'], ['abs('], ['1 +'],
            ['1._2'], ['1<2<3'], ['1==2==3'],
        ];
    }

    /** @dataProvider rejectedExpressions */
    public function testUnsupportedOrInexactExpressionsAreRejected(string $input): void
    {
        $this->expectException(Exception::class);
        (new Expression(new BcMathBackend()))->evaluateExact($input);
    }

    public function testStringUnitFactorsRetainAllDigits(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addUnit('u', '0.12345678901234567890123456789');
        $this->assertSame('0.24691357802469135780246913578', $expression->evaluateExact('2u'));
        $expression->addUnit('v', '-10');
        $this->assertSame('30', $expression->evaluateExact('10-2v'));
        $expression->removeUnit('u');
        $this->expectException(Exception::class);
        $expression->evaluateExact('1u');
    }

    public function testDecimalUnitReceivesSignedStrings(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addUnit('u', new class implements DecimalUnit {
            public function convert(string $amount): string { return bcadd($amount, '10', 1); }
        });
        $this->assertSame('8', $expression->evaluateExact('-2u'));
        $this->assertSame('-2', $expression->evaluateExact('10-2u'));
        $this->assertSame('18', $expression->evaluateExact('10+(-2u)'));
        $this->assertSame('64', $expression->evaluateExact('-2u**2'));
    }

    public function testFloatUnitsDoNotSilentlyEnterExactArithmetic(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addUnit('u', 0.1);
        $this->expectException(Exception::class);
        $expression->evaluateExact('1u');
    }

    public function testLegacyFloatUnitObjectsAreRejected(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addUnit('%', new Percentage());
        $this->expectException(Exception::class);
        $expression->evaluateExact('1%');
    }

    public function testCustomFunctionsUseDecimalStringsAndRespectRegistries(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addFunction('double', 'decimal_backend_double');
        $expression->addFunction('magnitude', 'abs');
        $this->assertSame('0.2', $expression->evaluateExact('double(0.1)'));
        $this->assertSame('0.1', $expression->evaluateExact('magnitude(-0.1)'));
        $expression->clearFunctions();
        $this->expectException(Exception::class);
        $expression->evaluateExact('double(0.1)');
    }

    public function testFloatReturningCustomFunctionsAreRejected(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addFunction('bad', 'decimal_backend_float');
        $this->expectException(Exception::class);
        $expression->evaluateExact('bad(1)');
    }

    public function testFloatApiRemainsAvailableButExactResultIsNotCappedByFloatRange(): void
    {
        $expression = new Expression(new BcMathBackend());
        $this->assertSame(0.3, $expression->evaluate('0.1 + 0.2'));
        $this->assertSame('1' . str_repeat('0', 400), $expression->evaluateExact('1e400'));
        $this->expectException(Exception::class);
        $expression->evaluate('1e400');
    }

    public function testNoGlobalBcMathScaleOrPhpPrecisionChanges(): void
    {
        $scale = ini_get('bcmath.scale');
        $precision = ini_get('serialize_precision');
        try {
            bcscale(0);
            ini_set('serialize_precision', '3');
            $this->assertSame('0.3', (new Expression(new BcMathBackend()))->evaluateExact('0.1 + 0.2'));
            $this->assertSame('0', ini_get('bcmath.scale'));
            $this->assertSame('3', ini_get('serialize_precision'));
        } finally {
            bcscale((int) $scale);
            ini_set('serialize_precision', $precision);
        }
    }

    public function testCustomBackendReceivesOriginalExpressionAndUnitValues(): void
    {
        $backend = new class implements Backend {
            public function evaluate(string $expression, array $functions, array $units): string
            {
                if ($expression !== '0.1 + 0.2' || $units['u'] !== '0.1234567890123456789' || $functions['abs'] !== 'abs') {
                    throw new Exception('Backend input was normalized');
                }
                return '0.3';
            }
        };
        $expression = new Expression($backend);
        $expression->addUnit('u', '0.1234567890123456789');
        $this->assertSame('0.3', $expression->evaluateExact('0.1 + 0.2'));
    }

    public function testDefaultBackendRequiresExplicitOptInForExactResults(): void
    {
        $this->assertSame(0.1 + 0.2, (new Expression())->evaluate('0.1 + 0.2'));
        $this->expectException(Exception::class);
        (new Expression())->evaluateExact('0.1 + 0.2');
    }

    public function testUnitRemovalClearingAndDefaultFactor(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addUnit('u');
        $this->assertSame('0.1', $expression->evaluateExact('0.1u'));
        $expression->addUnit('u', '1e400');
        $this->assertSame('1' . str_repeat('0', 400), $expression->evaluateExact('1u'));
        $expression->clearUnits();
        $this->expectException(Exception::class);
        $expression->evaluateExact('1u');
    }

    public function testNonTerminatingDivisionIsNotSilentlyTruncated(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no exact finite decimal result');
        (new Expression(new BcMathBackend()))->evaluateExact('1/3');
    }

    public function testDecimalDepthIsBoundedForUnaryAndPowerChains(): void
    {
        $expression = new Expression(new BcMathBackend());
        foreach ([str_repeat('!', 130) . '1', '1' . str_repeat('**1', 130)] as $input) {
            try {
                $expression->evaluateExact($input);
                $this->fail('Decimal depth limit was not enforced');
            } catch (Exception $exception) {
                $this->assertStringContainsString('deeply nested', $exception->getMessage());
            }
        }
    }

    public function testStaticDecimalFunctionTargets(): void
    {
        $expression = new Expression(new BcMathBackend());
        $expression->addFunction('identity', 'DecimalBackendTest::decimalIdentity');
        $this->assertSame('0.1234567890123456789', $expression->evaluateExact('identity(0.1234567890123456789)'));
        $expression->resetFunctions();
        $this->expectException(Exception::class);
        $expression->evaluateExact('identity(1)');
    }

    public static function decimalIdentity(string $value): string
    {
        return $value;
    }
}
