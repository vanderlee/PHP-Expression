<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\DiagnosticException;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Unit\Unit;

class BusinessFormulaTest extends TestCase
{
    public function testDistinctCompiledNativeFormulasHaveBoundedRetainedMemory(): void
    {
        foreach ([new Expression(), new Expression(new NativeGrammarBackend())] as $expression) {
            for ($index = 0; $index < 50; ++$index) {
                $expression->compile('value + ' . $index)->evaluate(['value' => 1]);
            }
            gc_collect_cycles();
            $before = memory_get_usage(false);
            $sum = 0.0;
            for ($index = 0; $index < 2000; ++$index) {
                $sum += $expression->compile('value + ' . $index)->evaluate(['value' => 1]);
            }
            gc_collect_cycles();
            $this->assertSame(2001000.0, $sum);
            $this->assertLessThan(512 * 1024, memory_get_usage(false) - $before);
        }
    }

    public function testNativeParseCacheIsRevokedWithItsConfiguration(): void
    {
        $expression = new Expression();
        $expression->addFunction('f', 'abs');
        $first = $expression->compile('f(value)');
        $second = $expression->compile('f(value)');
        $this->assertNotSame($first, $second);
        $this->assertSame($first->inspect(), $second->inspect());
        $this->assertSame(4.0, $second->evaluate(['value' => 4]));
        $expression->addFunction('f', 'sqrt');
        $this->assertSame(2.0, $expression->compile('f(value)')->evaluate(['value' => 4]));
        try {
            $first->evaluate(['value' => 4]);
            $this->fail('A stale formula was executed');
        } catch (DiagnosticException $error) {
            $this->assertSame('stale_configuration', $error->getErrorCode());
        }
        $expression->removeFunction('f');
        try {
            $expression->compile('f(value)');
            $this->fail('Removed function was accepted');
        } catch (DiagnosticException $error) {
            $this->assertSame('unknown_function', $error->getErrorCode());
        }
    }

    public function testRepeatedCompiledNativeFormulaKeepsBindingsAndEffectsLive(): void
    {
        foreach ([new Expression(), new Expression(new NativeGrammarBackend())] as $expression) {
            $calls = 0;
            $unit = new class implements Unit {
                public $calls = 0;

                public function convert(float $amount): float
                {
                    return $amount * ++$this->calls;
                }
            };
            $expression->addUnit('u', $unit);
            $expression->addFunction('probe', static function ($value) use (&$calls) {
                return $value + ++$calls;
            });
            $formula = $expression->compile('if(flag, probe(value) + 2u, 0)');
            $this->assertSame(0, $calls);
            $this->assertSame(0, $unit->calls);
            $this->assertSame(0.0, $formula->evaluate(['flag' => 0, 'value' => 3]));
            $this->assertSame(6.0, $formula->evaluate(['flag' => 1, 'value' => 3]));
            $this->assertSame(10.0, $formula->evaluate(['flag' => 1, 'value' => 4]));
            try {
                $formula->evaluate(['flag' => 0]);
                $this->fail('Missing binding in an inactive branch was accepted');
            } catch (DiagnosticException $error) {
                $this->assertSame('missing_variable', $error->getErrorCode());
                $this->assertSame(15, $error->getOffset());
            }
            $this->assertSame(2, $calls);
            $this->assertSame(2, $unit->calls);
        }
    }

    private function evaluators(): array
    {
        $evaluators = [new Expression(), new Expression(new NativeGrammarBackend())];
        if (extension_loaded('bcmath')) { $evaluators[] = new Expression(new BcMathBackend(8)); }
        return $evaluators;
    }

    public function testReusablePricingAndInspection(): void
    {
        foreach ($this->evaluators() as $e) {
            $e->addUnit('cm', '0.01');
            $formula = $e->compile('if(quantity >= 10, round_half_even(price * quantity * 0.9, 2), price * quantity) + 2cm');
            $this->assertEqualsWithDelta(90.02, $formula->evaluate(['price' => '10', 'quantity' => 10]), 1e-12);
            $this->assertEqualsWithDelta(20.02, $formula->evaluate(['price' => '10', 'quantity' => 2]), 1e-12);
            $this->assertSame(['price', 'quantity'], $formula->inspect()['variables']);
            $this->assertSame(['if', 'round_half_even'], $formula->inspect()['functions']);
            $this->assertSame(['cm'], $formula->inspect()['units']);
        }
    }

    public function testValidationNeverRunsHostCodeAndConditionalIsLazy(): void
    {
        foreach ($this->evaluators() as $e) {
            $calls = 0;
            $e->addFunction('probe', function ($value) use (&$calls) { ++$calls; return $value; });
            $e->validate('if(flag, probe(2), 1 / 0)');
            $this->assertSame(0, $calls);
            $this->assertSame(2.0, $e->evaluate('if(flag, probe(2), 1 / 0)', ['flag' => 1]));
            $this->assertSame(1, $calls);
            $this->assertSame(7.0, $e->evaluate('if(0, probe(2), 7)'));
            $this->assertSame(1, $calls);
            try { $e->validate('probe(2) + missing(1)'); $this->fail(); }
            catch (DiagnosticException $error) { $this->assertSame('unknown_function', $error->getErrorCode()); }
            $this->assertSame(1, $calls);
        }
    }

    public function testUnusedConditionalUnitDoesNotRun(): void
    {
        $e = new Expression();
        $unit = new class implements Unit {
            public $calls = 0;
            public function convert(float $amount): float { ++$this->calls; throw new RuntimeException('Must not run'); }
        };
        $e->addUnit('u', $unit);
        $e->validate('if(1, 5, 2u)');
        $this->assertSame(5.0, $e->evaluate('if(1, 5, 2u)'));
        $this->assertSame(0, $unit->calls);
    }

    public function testDiagnosticsAndConfigurationRevocation(): void
    {
        $e = new Expression();
        $formula = $e->compile('price * quantity');
        try { $formula->evaluate(['price' => 2]); $this->fail(); }
        catch (DiagnosticException $error) {
            $this->assertSame('missing_variable', $error->getErrorCode());
            $this->assertSame(8, $error->getOffset());
        }
        try { $e->validate('price * (2 + 1'); $this->fail(); }
        catch (DiagnosticException $error) {
            $this->assertSame('expected_token', $error->getErrorCode());
            $this->assertSame(14, $error->getOffset());
        }
        $e->clearFunctions();
        try { $formula->evaluate(['price' => 2, 'quantity' => 3]); $this->fail(); }
        catch (DiagnosticException $error) { $this->assertSame('stale_configuration', $error->getErrorCode()); }
    }

    public function testBindingsCannotInjectCodeAndRejectInvalidTypes(): void
    {
        foreach ($this->evaluators() as $e) {
            foreach (['1);die();', true, [], new stdClass(), INF, NAN] as $value) {
                try { $e->evaluate('value + 1', ['value' => $value]); $this->fail(); }
                catch (DiagnosticException $error) { $this->assertSame('invalid_variable', $error->getErrorCode()); }
            }
            $this->assertSame(3.0, $e->evaluate('VALUE + 1', ['Value' => 2]));
        }
    }

    public function testInstanceCallablesAndRecursiveExecutionAreIsolated(): void
    {
        foreach ($this->evaluators() as $first) {
            $second = new Expression();
            $first->addFunction('fee', function ($v) { return $v; });
            $second->addFunction('fee', new class { public function __invoke($v) { return $v * 2; } });
            $this->assertSame(3.0, $first->evaluate('fee(3)'));
            $this->assertSame(6.0, $second->evaluate('fee(3)'));
            $inner = $first->compile('x + 1');
            $first->addFunction('nested', function ($v) use ($first) { return (string) $first->evaluate('x + 1', ['x' => $v]); });
            $this->assertSame(8.0, $first->evaluate('nested(2) + x', ['x' => 5]));
        }
    }

    public function testExplicitRoundingPolicies(): void
    {
        foreach ($this->evaluators() as $e) {
            foreach (['round_half_even(1.225, 2)' => 1.22, 'round_half_even(-1.235, 2)' => -1.24,
                'round_half_up(-1.225, 2)' => -1.23, 'truncate(-1.239, 2)' => -1.23,
                'round_increment(1.23, 0.05)' => 1.25, 'round_half_even(1250, -2)' => 1200] as $source => $expected) {
                $this->assertSame((float) $expected, $e->evaluate($source), $source);
            }
        }
    }

    public function testDecimalPrecisionStrictnessAndBackendValidation(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('BCMath required'); }
        $e = new Expression(new BcMathBackend(2));
        $formula = $e->compile('amount + 1');
        $this->assertSame('9007199254740994', $formula->evaluateExact(['amount' => '9007199254740993']));
        $this->assertSame('0.33', $e->compile('1/3')->evaluateDecimal());
        $this->assertSame('0.125', $e->compile('1/8')->evaluateExact());
        $this->assertSame('1.25', $e->compile('round_increment(1.23, 0.05)')->evaluateExact());
        $this->assertSame('1.22', $e->compile('round_half_even(1.225, 2)')->evaluateExact());
        try { $e->validate('sin(amount)'); $this->fail(); }
        catch (DiagnosticException $error) { $this->assertSame('unsupported_function', $error->getErrorCode()); }
        try { $e->compile('1/3')->evaluateExact(); $this->fail(); }
        catch (DiagnosticException $error) { $this->assertSame('evaluation_error', $error->getErrorCode()); }
    }

    public function testPortableBusinessFormula(): void
    {
        $e = new Expression(null, true);
        $this->assertSame(1.22, $e->compile('if(amount > 0, round_half_even(amount, 2), 0)')->evaluate(['amount' => '1.225']));
    }

    public function testCompatibilityAndArgumentErrors(): void
    {
        $e = new Expression();
        $checks = $e->inspect('sin(angle)')['compatibility'];
        $this->assertTrue($checks['native_grammar']);
        $this->assertFalse($checks['portable']);
        $this->assertSame(extension_loaded('bcmath') ? false : null, $checks['decimal']);
        $e->addFunction('fee', function ($weight, $rate) { return $weight * $rate; });
        foreach (['fee(2)', 'if(1, 2)', 'round_half_even(1, 2, 3)'] as $source) {
            try { $e->validate($source); $this->fail($source); }
            catch (DiagnosticException $error) { $this->assertSame('invalid_argument_count', $error->getErrorCode()); }
        }
        $e->addFunction('native_rounding', 'Vanderlee\\Expression\\NativeRounding::halfEven');
        $this->assertSame(1.22, $e->evaluate('native_rounding(1.225, 2)'));
    }
}
