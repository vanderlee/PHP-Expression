<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\NativePhpBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Unit;

class NativePhpBackendTest extends TestCase
{
    public function testExplicitNativeBackendMatchesDefault(): void
    {
        $native = new Expression(new NativePhpBackend());
        $default = new Expression();
        foreach (['0.1 + 0.2', '0.1 + 0.2 == 0.3', 'sin(1)', '0x10 + 0b10', 'true && !false'] as $input) {
            $this->assertSame($default->evaluate($input), $native->evaluate($input));
        }
    }

    public function testNativeBackendReturnsFloatDirectly(): void
    {
        $this->assertSame(3.0, (new NativePhpBackend())->evaluate('abs(-3)', ['abs' => 'abs'], []));
    }

    public function testSharedBackendDoesNotLeakRegistryValuesOrSuffixPatterns(): void
    {
        $backend = new NativePhpBackend();
        $first = new Expression($backend);
        $second = new Expression($backend);
        $first->addUnit('u', 2);
        $second->addUnit('u', 3);
        $first->addFunction('f', 'abs');
        $second->addFunction('f', 'sqrt');
        $this->assertSame(4.0, $first->evaluate('2u'));
        $this->assertSame(6.0, $second->evaluate('2u'));
        $this->assertSame(4.0, $first->evaluate('f(4)'));
        $this->assertSame(2.0, $second->evaluate('f(4)'));
        $first->addUnit('uu', 10);
        $this->assertSame(20.0, $first->evaluate('2uu'));
        $first->removeUnit('uu');
        $this->assertSame(4.0, $first->evaluate('2u'));
        $second->clearUnits();
        $this->assertSame(4.0, $first->evaluate('2u'));
        $this->expectException(Exception::class);
        $second->evaluate('2u');
    }

    public function testNestedUseOfSharedBackendKeepsUnitRegistryLocal(): void
    {
        $backend = new NativePhpBackend();
        $first = new Expression($backend);
        $second = new Expression($backend);
        $second->addUnit('u', 20);
        $first->addUnit('u', new class($second) implements Unit {
            private $second;
            public function __construct(Expression $second) { $this->second = $second; }
            public function convert(float $amount): float
            {
                $this->second->evaluate('1u');
                return $amount * 2;
            }
        });
        $this->assertSame(10.0, $first->evaluate('2u + 3u'));
    }

    public function testNativeUnitValidationRejectsInvalidReplacementImmediately(): void
    {
        $expression = new Expression(new NativePhpBackend());
        $expression->addUnit('u', '2');
        try {
            $expression->addUnit('u', '1e9999');
            $this->fail('Invalid factor was accepted');
        } catch (Exception $exception) {
            $this->assertSame(4.0, $expression->evaluate('2u'));
        }
    }

    public function testDecimalUnitIsRejectedByExplicitNativeBackend(): void
    {
        $expression = new Expression(new NativePhpBackend());
        $this->expectException(Exception::class);
        $expression->addUnit('u', new class implements DecimalUnit {
            public function convert(string $amount): string { return $amount; }
        });
    }

    public function testExplicitNativeBackendCannotClaimExactEvaluation(): void
    {
        $this->expectException(Exception::class);
        (new Expression(new NativePhpBackend()))->evaluateExact('0.1 + 0.2');
    }

    public function testNormalizationCannotExpandBeyondSharedDepthLimit(): void
    {
        $expression = new Expression(new NativePhpBackend());
        $expression->addUnit('u');
        $this->expectException(Exception::class);
        $expression->evaluate(str_repeat('(', 128) . '1u' . str_repeat(')', 128));
    }

    public function testRepeatedCustomUnitPreflightDoesNotRetainCompiledExpressions(): void
    {
        $unit = new class implements Unit {
            public $calls = 0;
            public function convert(float $amount): float
            {
                ++$this->calls;
                return $amount * 2;
            }
        };
        $expression = new Expression(new NativePhpBackend());
        $expression->addUnit('u', $unit);
        // Warm autoloading and regular expression caches before measuring.
        for ($index = 0; $index < 100; ++$index) {
            $expression->evaluate('2u + ' . $index);
        }
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $sum = 0.0;
        // Distinct sources also catch fixes that merely cache every expression.
        for ($index = 0; $index < 10000; ++$index) {
            $sum += $expression->evaluate('2u + ' . $index);
        }
        gc_collect_cycles();
        $growth = memory_get_usage(false) - $before;
        $this->assertSame(50035000.0, $sum);
        $this->assertSame(10100, $unit->calls);
        $this->assertLessThan(512 * 1024, $growth, 'Repeated syntax checks retained compilation memory');
    }
}
