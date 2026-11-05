<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\Backend;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Unit;

class PortableValidationTest extends TestCase
{
    public static $calls = 0;
    public static function probeCallback($value): int { ++self::$calls; return 1; }

    public static function backendModes(): array
    {
        return [['native'], ['exact'], ['rounded']];
    }

    private function expression(string $mode): Expression
    {
        if ($mode !== 'native' && !extension_loaded('bcmath')) {
            $this->markTestSkipped('Requires optional ext-bcmath');
        }
        return new Expression($mode === 'native' ? null : new BcMathBackend($mode === 'rounded' ? 8 : null), true);
    }

    /** @dataProvider backendModes */
    public function testSharedGrammar(string $mode): void
    {
        $expression = $this->expression($mode);
        $expression->addUnit('u', '2');
        $expression->addFunction('magnitude', 'abs');
        foreach (['2+3*4' => 14, '-2**2' => -4, '2**-3' => .125,
            '1e-3*1000' => 1, '.25+0.5' => .75, 'MAGNITUDE(-3)' => 3,
            'min(1,2)' => 1, 'max(1,2)' => 2, 'ceil(.1)+floor(.9)' => 1,
            'round(-1.255,2)' => -1.26, 'pow(2,3)+sqrt(4)' => 10,
            'round(123,-1)' => 120, '-2u**2' => 16, '10-2u' => 6,
            '(1<2) && !(3==4)' => 1, '1 xor 1 || 1' => 0,
            'false && (1/0)' => 0, '(1<=>2)+2' => 1, '2- -1' => 3] as $source => $expected) {
            $this->assertEqualsWithDelta((float) $expected, $expression->evaluate($source), 1e-12, $source);
        }
    }

    /** @dataProvider backendModes */
    public function testNonPortableAndMalformedSourcesReject(string $mode): void
    {
        $expression = $this->expression($mode);
        $expression->addUnit('u', '2');
        $expression->addUnit('U', '2');
        foreach (['1.0===1', '1!==2', '5%2', '1<<2', '1^3', 'sin(1)', 'fmod(5,2)',
            'pi()', '(1<2)<3', '(1<2)==true', '(1<2)+1', 'abs(1<2)', '-(1<2)',
            '0x10', '0b10', '0o10', '010', '00.5', '1_000', '1e3u', '2U',
            '2**.5', '2**(1+2)', 'pow(2,1001)', 'round(1,1000)', 'round(1,1.0)',
            'round(1,2,1)', 'min(1)', 'abs()', '1 2', '1+', '1<2<3',
            '1/*comment*/', "1\0", '2--1', '2++1', '--2', '++2'] as $source) {
            try {
                $expression->evaluate($source);
                $this->fail('Accepted non-portable source: ' . $source);
            } catch (Exception $exception) {
                $this->assertNotSame('', $exception->getMessage(), $source);
            }
        }
    }

    public function testOptInAndTogglePreserveDefaultBehavior(): void
    {
        $expression = new Expression();
        $this->assertSame(1.0, $expression->evaluate('5.5%2'));
        $expression->setPortableValidation(true);
        try { $expression->evaluate('5.5%2'); $this->fail('Portable mode was not enabled'); }
        catch (Exception $exception) { $this->assertStringContainsString('Non-portable', $exception->getMessage()); }
        $expression->setPortableValidation(false);
        $this->assertSame(1.0, $expression->evaluate('5.5%2'));
    }

    public function testValidationNeverCallsCustomCode(): void
    {
        self::$calls = 0;
        $expression = new Expression(null, true);
        $expression->addFunction('probe', 'PortableValidationTest::probeCallback');
        $expression->addUnit('u', new class implements Unit {
            public function convert(float $amount): float { ++PortableValidationTest::$calls; return $amount; }
        });
        foreach (['probe(1)', '1u', 'false && probe(1)', 'false && 1u'] as $source) {
            try { $expression->evaluate($source); $this->fail('Custom code accepted'); }
            catch (Exception $exception) { $this->assertSame(0, self::$calls); }
        }
    }

    public function testValidationAppliesToAllEvaluationMethodsBeforeBackendExecution(): void
    {
        $backend = new class implements Backend {
            public $calls = 0;
            public function evaluate(string $expression, array $functions, array $units): string
            {
                ++$this->calls;
                return '1';
            }
        };
        $expression = new Expression($backend, true);
        foreach (['evaluate', 'evaluateDecimal', 'evaluateExact'] as $method) {
            try { $expression->$method('1===1'); $this->fail('Validation bypassed'); }
            catch (Exception $exception) { $this->assertSame(0, $backend->calls); }
        }
        $this->assertSame('1', $expression->evaluateExact('1'));
        $this->assertSame(1, $backend->calls);
    }

    public function testPrecisionAndExactnessRemainBackendPolicies(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
        $native = new Expression(null, true);
        $decimal = new Expression(new BcMathBackend(), true);
        $this->assertSame(0.0, $native->evaluate('0.1+0.2==0.3'));
        $this->assertSame('1', $decimal->evaluateExact('0.1+0.2==0.3'));
        $this->assertEqualsWithDelta(1 / 3, $native->evaluate('1/3'), 1e-12);
        $this->expectException(Exception::class);
        $decimal->evaluateExact('1/3');
    }
}
