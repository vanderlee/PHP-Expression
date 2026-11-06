<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Unit;

class BackendCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
    }

    public static function sharedExpressions(): array
    {
        return [
            ['2+3*4', '14'], ['(2+3)*4', '20'], ['10-3-2', '5'], ['24/4/3', '2'],
            ['2**3**2', '512'], ['-2**2', '-4'], ['(-2)**2', '4'], ['2**-3', '0.125'],
            ['!1==0', '1'], ['not (1==0)', '1'], ['1 || 0 and 0', '1'],
            ['1 xor 1 || 1', '0'], ['0 OR 1 AND 0', '0'], ['true === 1', '1'],
            ['(1 <=> 2) == -1', '1'], ['3>2 == true', '1'],
            ['0x10 + 0b10 + 0o10 + 010', '34'], ['1_000 + .25', '1000.25'],
            ['1e-3 * 1000', '1'], ['abs (-3)', '3'], ['min(3,2,1)', '1'],
            ['max(1,2,3)', '3'], ['ceil(-1.25)', '-1'], ['floor(-1.25)', '-2'],
            ['round(-1.255,2)', '-1.26'], ['sqrt(.0625)', '0.25'], ['pow(.5,3)', '0.125'],
            ['fmod(1.25,.4)', '0.05'], ['-10%3', '-1'],
            ['false && (1/0)', '0'], ['true || (1/0)', '1'],
        ];
    }

    /** @dataProvider sharedExpressions */
    public function testSharedSyntaxAndPrecedence(string $input, string $expected): void
    {
        $decimal = new Expression(new BcMathBackend());
        $this->assertSame($expected, $decimal->evaluateExact($input), $input);
        $native = (new Expression())->evaluate($input);
        $this->assertEqualsWithDelta((float) $expected, $native, 1e-12 * max(1, abs((float) $expected)), $input);
    }

    public function testSignedUnitsAndRegistryLifecycleHaveMatchingBehavior(): void
    {
        $native = new Expression();
        $decimal = new Expression(new BcMathBackend());
        $native->addUnit('u', new class implements Unit {
            public function convert(float $amount): float { return $amount + 10; }
        });
        $decimal->addUnit('u', new class implements DecimalUnit {
            public function convert(string $amount): string { return bcadd($amount, '10', 2); }
        });
        foreach (['-2u' => 8, '- 2u' => -12, '10-2u' => -2, '10- 2u' => -2, '10+(-2u)' => 18, '-2u**2' => 64] as $input => $expected) {
            $this->assertSame((float) $expected, $native->evaluate($input), $input);
            $this->assertSame((string) $expected, $decimal->evaluateExact($input), $input);
        }
        foreach ([$native, $decimal] as $expression) {
            $expression->addUnit('u', '2');
            $expression->addUnit('uu', '10');
            $this->assertSame(24.0, $expression->evaluate('2uu+2u'));
            $expression->addFunction('magnitude', 'abs');
            $this->assertSame(3.0, $expression->evaluate('MAGNITUDE(-3)'));
            $expression->removeFunction('MAGNITUDE');
            $this->assertRejected($expression, 'magnitude(1)');
            $expression->removeUnit('uu');
            $this->assertRejected($expression, '1uu');
            $expression->clearUnits();
            $this->assertRejected($expression, '1u');
            $expression->clearFunctions();
            $this->assertRejected($expression, 'abs(1)');
            $expression->resetFunctions();
            $this->assertSame(1.0, $expression->evaluate('abs(-1)'));
        }
    }

    public static function sharedErrors(): array
    {
        return [['1 2'], ['1 . 2'], ['(1'], ['1)'], ['1+'], ['1<2<3'], ['1==2==3'],
            ['1/0'], ['sqrt(-1)'], ['1%0'], ['abs()'], ['unknown(1)'], ['true()'], ['mt_rand()'],
            ['1 <=> 2 == -1']];
    }

    /** @dataProvider sharedErrors */
    public function testCommonRejectionContract(string $input): void
    {
        foreach ([new Expression(), new Expression(new BcMathBackend())] as $expression) {
            $this->assertRejected($expression, $input);
        }
    }

    public function testDocumentedBackendDifferencesRemainExplicit(): void
    {
        $native = new Expression();
        $decimal = new Expression(new BcMathBackend());
        $this->assertSame(0.0, $native->evaluate('0.1+0.2==0.3'));
        $this->assertSame('1', $decimal->evaluateExact('0.1+0.2==0.3'));
        $this->assertSame(1.0, $native->evaluate('1.0 === 1.0'));
        $this->assertSame(0.0, $native->evaluate('1.0 === 1'));
        $this->assertSame('1', $decimal->evaluateExact('1.0 === 1'));
        // PHP retains intermediate booleans; the decimal grammar has one numeric type.
        $this->assertSame(0.0, $native->evaluate('(1<2)<3'));
        $this->assertSame('1', $decimal->evaluateExact('(1<2)<3'));
        $this->assertSame(1.0, $native->evaluate('5.5 % 2'));
        $this->assertRejected($decimal, '5.5 % 2');
        foreach (['1/3', 'sqrt(2)', 'sin(1)', 'pi()', '1<<2', '1^3'] as $input) {
            $this->assertTrue(is_finite($native->evaluate($input)));
            $this->assertRejected($decimal, $input);
        }
        $native->addUnit('u', '2');
        $decimal->addUnit('u', '2');
        $this->assertRejected($native, '1e3u');
        $this->assertSame('2000', $decimal->evaluateExact('1e3u'));
        $this->assertSame('18446744073709551616', $decimal->evaluateExact('0xFFFFFFFFFFFFFFFF+1'));
        $this->assertSame((float) hexdec('FFFFFFFFFFFFFFFF'), $native->evaluate('0xFFFFFFFFFFFFFFFF+1'));
    }

    private function assertRejected(Expression $expression, string $input): void
    {
        try {
            $expression->evaluate($input);
            $this->fail('Accepted invalid/unsupported expression: ' . $input);
        } catch (Exception $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
