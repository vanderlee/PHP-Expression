<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Backend\Backend;
use Vanderlee\Expression\Backend\FloatBackend;
use Vanderlee\Expression\Backend\TreeBackend;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class BackendContractTest extends TestCase
{
    public function testCompiledCustomFloatBackendKeepsItsExecutionPolicy(): void
    {
        $backend = new class implements FloatBackend, TreeBackend {
            public $calls = 0;

            public function evaluate(string $expression, array $functions, array $units): float
            {
                return 42.0;
            }

            public function evaluateTree(array $tree, array $functions, array $units, array $variables, bool $exact = false): float
            {
                return 42.0 + ++$this->calls;
            }
        };
        $formula = (new Expression($backend))->compile('value + 1');
        $this->assertSame(0, $backend->calls);
        $this->assertSame(43.0, $formula->evaluate(['value' => 2]));
        $this->assertSame(44.0, $formula->evaluate(['value' => 3]));
    }

    public function testFloatBackendCapabilityRejectsDecimalRequestsBeforeExecution(): void
    {
        $backend = new class implements FloatBackend {
            public $calls = 0;
            public function evaluate(string $expression, array $functions, array $units): float
            {
                ++$this->calls;
                return 1.0;
            }
        };
        $expression = new Expression($backend);
        foreach (['evaluateExact', 'evaluateDecimal'] as $method) {
            try { $expression->$method('1'); $this->fail('Float capability ignored'); }
            catch (Exception $error) { $this->assertSame(0, $backend->calls); }
        }
        $this->assertSame(1.0, $expression->evaluate('1'));
        $this->assertSame(1, $backend->calls);
    }

    public static function invalidResults(): array
    {
        return [['NAN'], ['INF'], ['1e3'], [''], ['0;system(1)'], [' 0 ']];
    }

    /** @dataProvider invalidResults */
    public function testBackendMustReturnDecimalStrings(string $result): void
    {
        $backend = new class($result) implements Backend {
            private $result;
            public function __construct(string $result) { $this->result = $result; }
            public function evaluate(string $expression, array $functions, array $units): string { return $this->result; }
        };
        $this->expectException(Exception::class);
        (new Expression($backend))->evaluateExact('1');
    }

    public function testBackendExceptionsRetainTheirCause(): void
    {
        $backend = new class implements Backend {
            public function evaluate(string $expression, array $functions, array $units): string { throw new TypeError('backend failure'); }
        };
        try {
            (new Expression($backend))->evaluateExact('1');
            $this->fail('Backend exception was lost');
        } catch (Exception $exception) {
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
        }
    }

    public function testResourceLimitsApplyBeforeCallingPluggableBackends(): void
    {
        $backend = new class implements Backend {
            public function evaluate(string $expression, array $functions, array $units): string { throw new LogicException('Backend should not run'); }
        };
        $expression = new Expression($backend);
        foreach ([str_repeat('1', 4097), str_repeat('(', 129) . '1' . str_repeat(')', 129), str_repeat('abs(1)+', 129) . '1'] as $input) {
            try {
                $expression->evaluateExact($input);
                $this->fail('Limit did not apply');
            } catch (Exception $exception) {
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function testFloatBackendResultIsAcceptedWithoutDecimalSerialization(): void
    {
        $backend = new class implements Backend {
            public function evaluate(string $expression, array $functions, array $units): float { return 0.1 + 0.2; }
        };
        $expression = new Expression($backend);
        $this->assertSame(0.1 + 0.2, $expression->evaluate('0.1 + 0.2'));
        $this->expectException(Exception::class);
        $expression->evaluateExact('0.1 + 0.2');
    }

    public static function invalidNativeResults(): array
    {
        return [[NAN], [INF], [-INF], [1], [true], [null], [[]]];
    }

    /** @dataProvider invalidNativeResults */
    public function testNonFiniteFloatsAndOtherReturnTypesAreRejected($result): void
    {
        $backend = new class($result) implements Backend {
            private $result;
            public function __construct($result) { $this->result = $result; }
            public function evaluate(string $expression, array $functions, array $units) { return $this->result; }
        };
        $this->expectException(Exception::class);
        (new Expression($backend))->evaluate('1');
    }
}
