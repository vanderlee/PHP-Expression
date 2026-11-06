<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Decibel;

class ArithmeticNormalizationTest extends TestCase
{
    public static function unitArithmetic(): array
    {
        return [
            ['10-2dB', 10 - 10 ** 0.2],
            ['10 - 2dB', 10 - 10 ** 0.2],
            ['10 -2dB', 10 - 10 ** 0.2],
            ['10- 2dB', 10 - 10 ** 0.2],
            ['10+(-2dB)', 10 + 10 ** -0.2],
            ['10--2dB', 10 - 10 ** -0.2],
            ['20dB-2dB', 100 - 10 ** 0.2],
            ['(10)-2dB', 10 - 10 ** 0.2],
            ['-2dB', 10 ** -0.2],
            ['2u**2', 400.0],
            ['2u*3u', 600.0],
            ['10-2v', 30.0],
            ['2v**2', 400.0],
            ['10/-2u', -0.5],
            ['min(-2dB,2dB)', 10 ** -0.2],
        ];
    }

    /** @dataProvider unitArithmetic */
    public function testUnitReplacementPreservesArithmetic(string $input, float $expected): void
    {
        $expression = new Expression();
        $expression->addUnit('dB', new Decibel());
        $expression->addUnit('u', 10);
        $expression->addUnit('v', -10);

        $this->assertEqualsWithDelta($expected, $expression->evaluate($input), 1e-12);
    }

    public static function invalidTokenBoundaries(): array
    {
        return [
            ['1 2'], ['1 . 2'], ['1\t2'], ["1\t2"], ["1\n2"],
            ['1u2'], ['1u.2'], ['1 u'], ['1e3u'], ['0x10u'],
            ['1 < < 2'], ['1 * * 2'], ['1 & & 2'],
        ];
    }

    /** @dataProvider invalidTokenBoundaries */
    public function testMalformedTokensAreRejected(string $input): void
    {
        $expression = new Expression();
        $expression->addUnit('u', 10);

        $this->expectException(Exception::class);
        $expression->evaluate($input);
    }

    public function testWhitespaceBetweenFunctionAndParenthesisIsAccepted(): void
    {
        $this->assertSame(3.0, (new Expression())->evaluate("abs \t ( -3 )"));
    }

    public function testDecimalUnderscoresDoNotRoundIntegerOperandsBeforeArithmetic(): void
    {
        $this->assertSame(1.0, (new Expression())->evaluate('9_007_199_254_740_993 - 9_007_199_254_740_992'));
    }

    public function testUnitReplacementRetainsFloatPrecision(): void
    {
        $expression = new Expression();
        $expression->addUnit('u', 1.2345678901234567);

        $this->assertSame(1.2345678901234567, $expression->evaluate('1u'));
    }

    public function testUnitReplacementSupportsScientificNotation(): void
    {
        $expression = new Expression();
        $expression->addUnit('u', 1e-20);

        $this->assertSame(2e-20, $expression->evaluate('2u'));
    }

    public function testOverflowingBaseLiteralThrowsLibraryException(): void
    {
        $this->expectException(Exception::class);
        (new Expression())->evaluate('0x' . str_repeat('f', 300));
    }
}
