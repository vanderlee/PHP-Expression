<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\CompiledExpression;
use Vanderlee\Expression\DiagnosticException;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Unit;

class NativeFormulaCacheTest extends TestCase
{
    public function testPlansAreReusedAndLeastRecentlyUsedPlanIsEvicted(): void
    {
        foreach ([new Expression(), new Expression(new NativeGrammarBackend())] as $expression) {
            $formulas = [];
            for ($index = 0; $index < 8; ++$index) {
                $formulas[] = $expression->compile('value + ' . $index);
            }
            $recent = $expression->compile('value + 0');
            $this->assertNotSame($formulas[0], $recent);
            $this->assertSame($this->plan($formulas[0]), $this->plan($recent));
            $expression->compile('value + 8');
            $this->assertSame($this->plan($recent), $this->plan($expression->compile('value + 0')));
            $this->assertNotSame($this->plan($formulas[1]), $this->plan($expression->compile('value + 1')));
            // Eviction releases the cache entry, not a caller's saved formula.
            $this->assertSame(5.0, $formulas[1]->evaluate(['value' => 4]));
        }
    }

    public function testAlternatingFormulasKeepBindingsAndUnitEffectsLive(): void
    {
        foreach ([new Expression(), new Expression(new NativeGrammarBackend())] as $expression) {
            $unit = new class implements Unit {
                public $calls = 0;

                public function convert(float $amount): float
                {
                    return $amount * ++$this->calls;
                }
            };
            $expression->addUnit('u', $unit);
            $first = 'if(flag, value + 2u, 0)';
            $second = 'if(flag, value * 2 + 2u, 0)';
            $this->assertSame(5.0, $expression->evaluate($first, ['flag' => 1, 'value' => 3]));
            $this->assertSame(10.0, $expression->evaluate($second, ['flag' => 1, 'value' => 3]));
            $this->assertSame(0.0, $expression->evaluate($first, ['flag' => 0, 'value' => 9]));
            $this->assertSame(14.0, $expression->evaluate($second, ['flag' => 1, 'value' => 4]));
            $this->assertSame(13.0, $expression->evaluate($first, ['flag' => 1, 'value' => 5]));
            $this->assertSame(4, $unit->calls);
        }
    }

    public function testConfigurationChangesRevokeEveryCachedPlan(): void
    {
        foreach ([new Expression(), new Expression(new NativeGrammarBackend())] as $expression) {
            $expression->addFunction('f', 'abs');
            $expression->addUnit('u', 2);
            $sources = ['f(value) + 1u', 'f(value) + 2u'];
            $saved = [$expression->compile($sources[0]), $expression->compile($sources[1])];
            $expression->addFunction('f', 'sqrt');
            $expression->addUnit('u', 3);
            foreach ($saved as $index => $formula) {
                $this->assertNotSame($this->plan($formula), $this->plan($expression->compile($sources[$index])));
                $this->assertSame(2.0 + 3 * ($index + 1), $expression->evaluate($sources[$index], ['value' => 4]));
                try {
                    $formula->evaluate(['value' => 4]);
                    $this->fail('A stale formula was executed');
                } catch (DiagnosticException $error) {
                    $this->assertSame('stale_configuration', $error->getErrorCode());
                }
            }
            $expression->removeFunction('f');
            foreach ($sources as $source) {
                try {
                    $expression->compile($source);
                    $this->fail('A removed function was accepted from the cache');
                } catch (DiagnosticException $error) {
                    $this->assertSame('unknown_function', $error->getErrorCode());
                }
            }
        }
    }

    private function plan(CompiledExpression $formula): callable
    {
        // Compare preparation identity rather than asserting unstable timings.
        $property = new ReflectionProperty(CompiledExpression::class, 'nativeExecution');
        $property->setAccessible(true);
        return $property->getValue($formula);
    }
}
