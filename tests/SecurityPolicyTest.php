<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Unit;

class SecurityPolicyTest extends TestCase
{
    public static function nonFiniteExpressions(): array
    {
        return [['sqrt(-1)'], ['acos(2)'], ['log(0)'], ['pow(9,99999999)']];
    }

    /** @dataProvider nonFiniteExpressions */
    public function testNonFiniteResultsAreRejected(string $input): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('expression result must be finite');
        (new Expression())->evaluate($input);
    }

    public static function invalidUnitValues(): array
    {
        return [[null], [true], [false], ['invalid'], [[]], [new stdClass()], [NAN], [INF], [-INF], ['1e9999']];
    }

    /** @dataProvider invalidUnitValues */
    public function testInvalidUnitRegistrationPreservesExistingUnit($value): void
    {
        $expression = new Expression();
        $expression->addUnit('u', 2);
        try {
            $expression->addUnit('u', $value);
            $this->fail('Invalid unit value was accepted');
        } catch (Exception $exception) {
            $this->assertSame(6.0, $expression->evaluate('3u'));
        }
    }

    public function testNumericUnitStringsAndZeroAreAccepted(): void
    {
        $expression = new Expression();
        $expression->addUnit('u', '2.5');
        $this->assertSame(5.0, $expression->evaluate('2u'));
        $expression->addUnit('u', 0);
        $this->assertSame(0.0, $expression->evaluate('2u'));
    }

    public function testUnitSuffixLengthBoundary(): void
    {
        $expression = new Expression();
        $suffix = str_repeat('u', 64);
        $expression->addUnit($suffix, 2);
        $this->assertSame(6.0, $expression->evaluate('3' . $suffix));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unit suffix too long');
        $expression->addUnit($suffix . 'u');
    }

    public function testUnitCountLimitAllowsReplacementAndRemoval(): void
    {
        $expression = new Expression();
        for ($index = 0; $index < 128; ++$index) {
            $expression->addUnit('u' . $index);
        }
        $expression->addUnit('u0', 2);
        $this->assertSame(6.0, $expression->evaluate('3u0'));
        try {
            $expression->addUnit('extra');
            $this->fail('Unit count limit was not enforced');
        } catch (Exception $exception) {
            $this->assertSame('too many registered units', $exception->getMessage());
        }
        $expression->removeUnit('u0');
        $expression->addUnit('extra', 4);
        $this->assertSame(12.0, $expression->evaluate('3extra'));
        $expression->clearUnits();
        $expression->addUnit('fresh', 5);
        $this->assertSame(15.0, $expression->evaluate('3fresh'));
    }

    public static function nonFiniteUnitResults(): array
    {
        return [[NAN], [INF], [-INF]];
    }

    /** @dataProvider nonFiniteUnitResults */
    public function testCustomUnitNonFiniteResultIsRejected(float $result): void
    {
        $expression = new Expression();
        $expression->addUnit('u', new class($result) implements Unit {
            private $result;
            public function __construct(float $result) { $this->result = $result; }
            public function convert(float $amount): float { return $this->result; }
        });
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unit conversion result must be finite');
        $expression->evaluate('2u');
    }

    public function testUnitMultiplicationOverflowIsRejected(): void
    {
        $expression = new Expression();
        $expression->addUnit('u', PHP_FLOAT_MAX);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unit conversion result must be finite');
        $expression->evaluate('2u');
    }

    public function testCustomUnitErrorsAreWrapped(): void
    {
        $expression = new Expression();
        $expression->addUnit('u', new class implements Unit {
            public function convert(float $amount): float { throw new TypeError('conversion failed'); }
        });
        try {
            $expression->evaluate('2u');
            $this->fail('Unit error was not thrown');
        } catch (Exception $exception) {
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
        }
    }

    public static function randomFunctions(): array
    {
        return [['mt_srand', '123'], ['mt_rand', '1,1'], ['rand', '1,1'], ['lcg_value', '']];
    }

    /** @dataProvider randomFunctions */
    public function testRandomFunctionsRequireOptInAndResetRevokesIt(string $function, string $arguments): void
    {
        $expression = new Expression();
        $input = $function . '(' . $arguments . ')';
        try {
            $expression->evaluate($input);
            $this->fail('Random function was enabled by default');
        } catch (Exception $exception) {
            $this->assertStringContainsString('Illegal function', $exception->getMessage());
        }
        $expression->addFunction($function);
        $this->assertTrue(is_finite($expression->evaluate($input)));
        $expression->resetFunctions();
        $this->expectException(Exception::class);
        $expression->evaluate($input);
    }
}
