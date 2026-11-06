<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Unit;

require_once __DIR__ . '/Support/ArithmeticOracle.php';

class SecurityBoundaryTest extends TestCase
{
    public static $functionCalls = 0;
    public static $unitCalls = 0;

    public static function probe($value): int { ++self::$functionCalls; return 1; }
    public static function failCallback($value): int { throw new RuntimeException('trusted callback failed'); }

    private function expression(string $backend): Expression
    {
        self::$functionCalls = self::$unitCalls = 0;
        if ($backend !== 'native' && !extension_loaded('bcmath')) { $this->markTestSkipped('Requires optional ext-bcmath'); }
        $expression = $backend === 'native' ? new Expression() : new Expression(new BcMathBackend($backend === 'rounded' ? 8 : null));
        $expression->addFunction('probe', 'SecurityBoundaryTest::probe');
        $expression->addUnit('u', $backend === 'native' ? new class implements Unit {
            public function convert(float $amount): float { ++SecurityBoundaryTest::$unitCalls; return $amount; }
        } : new class implements DecimalUnit {
            public function convert(string $amount): string { ++SecurityBoundaryTest::$unitCalls; return $amount; }
        });
        return $expression;
    }

    public static function rejectedSources(): array
    {
        $sources = [
            'probe(1);probe(2)', 'probe(1)+$x', 'probe(1)+1[0]', 'probe(1)+1{0}',
            'probe(1)+"1"', "probe(1)+'1'", 'probe(1)+`1`', 'probe(1)+strlen(123)',
            'probe(1)+PHP_VERSION_ID', 'probe(1)+SecurityBoundaryTest::probe(1)',
            'probe(1)+new stdClass()', 'probe(1)+1?2:3', 'probe(1)+1??2',
            'probe(1)+\\probe(1)', 'probe(1)+1 . 2', 'probe(1)+1 2',
            'probe(1)/*1*/', 'probe(1)/**/+2', 'probe(1)+', '(probe(1)',
            '1u + probe(1) + missing(1)', '1u + probe(1) + $x', '1u + probe(1) +',
            '1u + probe(1) + 1[0]', '1u + probe(1) + 1 2',
            'probe(1)+1u+' . str_repeat('1', 4096),
            str_repeat('(', 129) . 'probe(1)+1u' . str_repeat(')', 129),
            str_repeat('probe(1)+', 129) . '1u',
        ];
        $cases = [];
        foreach (['native', 'exact', 'rounded'] as $backend) {
            foreach ($sources as $index => $source) { $cases[$backend . '-' . $index] = [$backend, $source]; }
        }
        return $cases;
    }

    /** @dataProvider rejectedSources */
    public function testInvalidSourceIsRejectedBeforeTrustedCallbacks(string $backend, string $source): void
    {
        $expression = $this->expression($backend);
        $bufferLevel = ob_get_level();
        try {
            $expression->evaluate($source);
            $this->fail('Rejected source executed: ' . $source);
        } catch (Exception $exception) {
            $this->assertSame(0, self::$functionCalls, $source);
            $this->assertSame(0, self::$unitCalls, $source);
            $this->assertSame($bufferLevel, ob_get_level(), $source);
        }
    }

    public static function backends(): array { return [['native'], ['exact'], ['rounded']]; }

    /** @dataProvider backends */
    public function testRegistryChangesRevokeAccessBeforeExecution(string $backend): void
    {
        $expression = $this->expression($backend);
        $this->assertSame(1.0, $expression->evaluate('probe(1)'));
        $expression->removeFunction('PROBE');
        self::$functionCalls = 0;
        try { $expression->evaluate('probe(1)'); $this->fail('Removed function ran'); }
        catch (Exception $exception) { $this->assertSame(0, self::$functionCalls); }
        $expression->removeUnit('u');
        try { $expression->evaluate('1u'); $this->fail('Removed unit ran'); }
        catch (Exception $exception) { $this->assertSame(0, self::$unitCalls); }
        $expression->addFunction('probe', 'SecurityBoundaryTest::probe');
        $expression->resetFunctions();
        try { $expression->evaluate('probe(1)'); $this->fail('Reset function ran'); }
        catch (Exception $exception) { $this->assertSame(0, self::$functionCalls); }
    }

    /** @dataProvider backends */
    public function testTrustedRuntimeFailuresRetainTheirCauseAndHostBuffer(string $backend): void
    {
        $expression = $this->expression($backend);
        $expression->addFunction('fail_callback', 'SecurityBoundaryTest::failCallback');
        $level = ob_get_level();
        ob_start();
        try {
            echo 'host output';
            try { $expression->evaluate('fail_callback(1)'); $this->fail('Callback failure disappeared'); }
            catch (Exception $exception) {
                $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
                $this->assertSame('host output', ob_get_contents());
                $this->assertSame($level + 1, ob_get_level());
            }
        } finally { while (ob_get_level() > $level) { ob_end_clean(); } }
    }

    /** @dataProvider backends */
    public function testShortCircuitAvoidsTrustedCallbackExecution(string $backend): void
    {
        $expression = $this->expression($backend);
        $this->assertSame(0.0, $expression->evaluate('false && probe(1)'));
        $this->assertSame(1.0, $expression->evaluate('true || probe(1)'));
        $this->assertSame(0, self::$functionCalls);
    }

    /** @dataProvider backends */
    public function testUnitAndFunctionRegistrationBoundaries(string $backend): void
    {
        $expression = $this->expression($backend);
        foreach (['probe();', 'probe\\other', '1probe', 'probe::other'] as $alias) {
            try { $expression->addFunction($alias, 'SecurityBoundaryTest::probe'); $this->fail('Invalid alias accepted'); }
            catch (Exception $exception) { $this->assertSame(0, self::$functionCalls); }
        }
        $expression->clearUnits();
        for ($i = 0; $i < 128; ++$i) { $expression->addUnit('u' . $i, '1'); }
        $expression->addUnit('u0', '2');
        $this->assertSame(4.0, $expression->evaluate('2u0'));
        try { $expression->addUnit('extra', '1'); $this->fail('Unit capacity bypassed'); }
        catch (Exception $exception) { $this->assertSame('too many registered units', $exception->getMessage()); }
        $expression->removeUnit('u0');
        $expression->addUnit('extra', '2');
        $this->assertSame(4.0, $expression->evaluate('2extra'));
        $expression->clearUnits();
        try { $expression->addUnit(str_repeat('u', 65)); $this->fail('Suffix length bypassed'); }
        catch (Exception $exception) { $this->assertSame('unit suffix too long', $exception->getMessage()); }
    }

    /** @dataProvider backends */
    public function testSeededForbiddenTokenMutationsCannotInvokeCallbacks(string $backend): void
    {
        $expression = $this->expression($backend);
        $source = '1u + probe(1) + abs(-3)';
        $forbidden = ['$', '[', ']', '{', '}', ';', '?', '"', "'", '`', "\0"];
        foreach (ArithmeticGenerator::seeds() as $seed) {
            $generator = new ArithmeticGenerator($seed);
            for ($case = 0; $case < 80; ++$case) {
                $offset = $generator->next(strlen($source) + 1);
                $token = $forbidden[$generator->next(count($forbidden))];
                $mutated = substr($source, 0, $offset) . $token . substr($source, $offset);
                try { $expression->evaluate($mutated); $this->fail("Mutation accepted: seed=$seed case=$case offset=$offset"); }
                catch (Exception $exception) {
                    $this->assertSame(0, self::$functionCalls, "seed=$seed case=$case");
                    $this->assertSame(0, self::$unitCalls, "seed=$seed case=$case");
                }
            }
        }
    }
}
