<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Unit;

class NativeGrammarBackendTest extends TestCase
{
    public static $calls = 0;
    public static function value($value): float { ++self::$calls; return (float) $value; }

    public function testNativeTypesPrecisionAndFunctionSupport(): void
    {
        $grammar = new Expression(new NativeGrammarBackend());
        $native = new Expression();
        foreach (['0.1+0.2', '0.1+0.2==0.3', '1.0===1', '(1<2)<3',
            'sin(1)+cos(2)', '1/3+sqrt(2)', '010+0O10',
            '0x123456789abcdef012345', '9_007_199_254_740_993-9_007_199_254_740_992',
            '1<<2+1', '3&2==2', '1|2^3&4', '5%2', '1<>2'] as $source) {
            $this->assertSame($native->evaluate($source), $grammar->evaluate($source), $source);
        }
    }

    public function testMalformedSyntaxAndUntrustedTargetsRejectBeforeConversion(): void
    {
        $backend = new NativeGrammarBackend();
        $unit = new class implements Unit {
            public $calls = 0;
            public function convert(float $amount): float { ++$this->calls; return $amount; }
        };
        foreach (['1u+1+', '1u+08', '1u+0xgg', '1u+2++3', '1u+--2',
            '1u+1/*comment*/', '1u+true()', '1u+abs', '1u+abs(1,)', '1u+(1<2<3)',
            '1u+$convertedUnits[0]', '1u+1;die()', "1u\0", '1u+1e3u'] as $source) {
            try { $backend->evaluate($source, ['abs' => 'abs'], ['u' => $unit]); $this->fail($source); }
            catch (Exception $error) { $this->assertSame(0, $unit->calls, $source); }
        }
        foreach (['abs);die();//', 'system("echo x")', '\\abs', 'Class::method;', 'isset', 'include', 'missing_target'] as $target) {
            try { $backend->evaluate('1u+f(1)', ['f' => $target], ['u' => $unit]); $this->fail($target); }
            catch (Exception $error) { $this->assertSame(0, $unit->calls, $target); }
        }
    }

    public function testRegistriesAndPortableValidationRemainLive(): void
    {
        $backend = new NativeGrammarBackend();
        $first = new Expression($backend, true);
        $second = new Expression($backend, true);
        $first->addUnit('u', 2);
        $second->addUnit('u', 3);
        $first->addFunction('f', 'abs');
        $second->addFunction('f', 'sqrt');
        $this->assertSame(4.0, $first->evaluate('2u'));
        $this->assertSame(6.0, $second->evaluate('2u'));
        $this->assertSame(4.0, $first->evaluate('f(4)'));
        $this->assertSame(2.0, $second->evaluate('f(4)'));
        $first->removeFunction('f');
        foreach (['f(4)', '1===1', '1<<2'] as $source) {
            try { $first->evaluate($source); $this->fail($source); }
            catch (Exception $error) { $this->assertNotSame('', $error->getMessage()); }
        }
    }

    public function testDecimalMethodsRejectWithoutRunningHostCode(): void
    {
        $expression = new Expression(new NativeGrammarBackend());
        $expression->addFunction('probe', 'NativeGrammarBackendTest::value');
        self::$calls = 0;
        foreach (['evaluateExact', 'evaluateDecimal'] as $method) {
            try { $expression->$method('probe(1)'); $this->fail($method); }
            catch (Exception $error) { $this->assertSame(0, self::$calls); }
        }
    }

    public function testRepeatedDistinctSourcesDoNotRetainMemory(): void
    {
        $expression = new Expression(new NativeGrammarBackend());
        $unit = new class implements Unit {
            public function convert(float $amount): float { return $amount * 2; }
        };
        $expression->addUnit('u', $unit);
        for ($i = 0; $i < 100; ++$i) { $expression->evaluate('2u+' . $i); }
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $sum = 0.0;
        for ($i = 0; $i < 10000; ++$i) { $sum += $expression->evaluate('2u+' . $i); }
        gc_collect_cycles();
        $growth = memory_get_usage(false) - $before;
        $this->assertSame(50035000.0, $sum);
        $this->assertLessThan(512 * 1024, $growth);
    }
}
