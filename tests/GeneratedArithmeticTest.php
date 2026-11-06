<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Expression;

require_once __DIR__ . '/Support/ArithmeticOracle.php';

class GeneratedArithmeticTest extends TestCase
{
    public function testGeneratedNativeExpressionsAgainstRationalOracle(): void
    {
        $expressions = [new Expression(), new Expression(new NativeGrammarBackend())];
        foreach (ArithmeticGenerator::seeds() as $seed) {
            $generator = new ArithmeticGenerator($seed);
            for ($case = 0; $case < 200; ++$case) {
                $tree = $generator->boundedTree();
                list($n, $d) = ArithmeticOracle::evaluate($tree);
                $source = ArithmeticOracle::render($tree);
                $expected = $n / $d;
                foreach ($expressions as $expression) {
                    $this->assertEqualsWithDelta($expected, $expression->evaluate($source), 1e-12 * max(1, abs($expected)), "seed=$seed case=$case source=$source");
                }
            }
        }
    }

    public function testGeneratedDecimalExpressionsAgainstRationalOracle(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
        $expression = new Expression(new BcMathBackend());
        foreach (ArithmeticGenerator::seeds() as $seed) {
            $generator = new ArithmeticGenerator($seed);
            for ($case = 0; $case < 200; ++$case) {
                $tree = $generator->boundedTree();
                list($n, $d) = ArithmeticOracle::evaluate($tree);
                $source = ArithmeticOracle::render($tree);
                $this->assertSame(ArithmeticOracle::decimal($n, $d), $expression->evaluateExact($source), "seed=$seed case=$case source=$source");
            }
        }
    }

    public function testGeneratedUnparenthesizedPrecedence(): void
    {
        $native = new Expression();
        $decimal = extension_loaded('bcmath') ? new Expression(new BcMathBackend()) : null;
        foreach (ArithmeticGenerator::seeds() as $seed) {
            $generator = new ArithmeticGenerator($seed);
            for ($case = 0; $case < 100; ++$case) {
                $a = $generator->next(20);
                $b = $generator->next(20);
                $c = $generator->next(20);
                $d = $generator->next(2) === 0 ? 2 : 5;
                $literal = static function (int $value): array { return ['literal', $value, 1]; };
                $tree = ['-', ['+', $literal($a), ['*', $literal($b), $literal($c)]], ['/', $literal($c), $literal($d)]];
                list($n, $denominator) = ArithmeticOracle::evaluate($tree);
                $source = "$a + $b * $c - $c / $d";
                $message = "seed=$seed case=$case source=$source";
                $this->assertEqualsWithDelta($n / $denominator, $native->evaluate($source), 1e-12, $message);
                if ($decimal !== null) { $this->assertSame(ArithmeticOracle::decimal($n, $denominator), $decimal->evaluateExact($source), $message); }
            }
        }
    }

    public function testGeneratedDivisionRoundingAgainstIntegerOracle(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
        foreach (ArithmeticGenerator::seeds() as $seed) {
            $generator = new ArithmeticGenerator($seed);
            for ($case = 0; $case < 100; ++$case) {
                $n = $generator->next(2001) - 1000;
                $d = $generator->next(997) + 1;
                if ($generator->next(2) === 0) { $d = -$d; }
                $scale = $generator->next(5);
                foreach ([BcMathBackend::HALF_UP, BcMathBackend::HALF_EVEN, BcMathBackend::TOWARD_ZERO] as $policy) {
                    $expression = new Expression(new BcMathBackend($scale, $policy));
                    $source = "$n/$d";
                    $this->assertSame(ArithmeticOracle::rounded($n, $d, $scale, $policy), $expression->evaluateDecimal($source), "seed=$seed case=$case scale=$scale policy=$policy source=$source");
                }
            }
        }
    }

    public function testSquareRootRoundingAgainstExactSquaredBoundaries(): void
    {
        if (!extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
        foreach ([0, 1, 2, 3] as $scale) {
            $factor = 10 ** $scale;
            foreach ([BcMathBackend::HALF_UP, BcMathBackend::HALF_EVEN, BcMathBackend::TOWARD_ZERO] as $policy) {
                $expression = new Expression(new BcMathBackend($scale, $policy));
                for ($kept = 0; $kept < 30; ++$kept) {
                    foreach ([-1, 0, 1] as $epsilon) {
                        // Square of the exact halfway root, +/- a small rational.
                        $n = (2 * $kept + 1) ** 2 * 25 + $epsilon;
                        $d = 100 * $factor * $factor;
                        $increment = $policy !== BcMathBackend::TOWARD_ZERO && ($epsilon > 0
                            || ($epsilon === 0 && ($policy === BcMathBackend::HALF_UP || $kept % 2 !== 0)));
                        $expected = ArithmeticOracle::decimal($kept + ($increment ? 1 : 0), $factor);
                        $source = 'sqrt(' . ArithmeticOracle::decimal($n, $d) . ')';
                        $this->assertSame($expected, $expression->evaluateDecimal($source), "scale=$scale policy=$policy epsilon=$epsilon source=$source");
                    }
                }
            }
        }
    }
}
