<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class FloatingPointTest extends TestCase
{
    public static function normalizedValues(): array
    {
        return [
            [1.2345678901234567], [1e-200], [1e200],
            [1.7976931348623157e308], [2.2250738585072014e-308],
            [5e-324], [-1.2345678901234567],
        ];
    }

    /** @dataProvider normalizedValues */
    public function testUnitsRoundTripDespiteLowPhpPrecision(float $value): void
    {
        $precision = ini_get('precision');
        $serializePrecision = ini_get('serialize_precision');
        try {
            ini_set('precision', '6');
            ini_set('serialize_precision', '6');
            $expression = new Expression();
            $expression->addUnit('u', $value);

            $this->assertSame($value, $expression->evaluate('1u'));
        } finally {
            ini_set('precision', $precision);
            ini_set('serialize_precision', $serializePrecision);
        }
    }

    public function testOverflowingIntegerBaseLiteralRetainsItsFloatValue(): void
    {
        $precision = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '6');
            $literal = '0x123456789abcdef012345';
            $this->assertSame(hexdec($literal), (new Expression())->evaluate($literal));
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    public function testDecimalArithmeticUsesNativePhpFloatSemantics(): void
    {
        $expression = new Expression();
        $this->assertSame(0.1 + 0.2, $expression->evaluate('0.1 + 0.2'));
        $this->assertSame(0.0, $expression->evaluate('0.1 + 0.2 == 0.3'));
        $this->assertSame(1.0, $expression->evaluate('abs((0.1 + 0.2) - 0.3) < 1e-12'));
        $this->assertSame(0.3, $expression->evaluate('round(0.1 + 0.2, 2)'));
    }

    public function testUnitReplacementIgnoresLocaleDecimalSeparator(): void
    {
        $locale = setlocale(LC_NUMERIC, '0');
        try {
            $selected = setlocale(
                LC_NUMERIC,
                'nl_NL.UTF-8', 'de_DE.UTF-8',
                'Dutch_Netherlands.1252', 'German_Germany.1252'
            );
            if ($selected === false || localeconv()['decimal_point'] === '.') {
                $this->markTestSkipped('No locale with a different decimal separator is installed');
            }

            $expression = new Expression();
            $expression->addUnit('u', 1.2345678901234567);
            $this->assertSame(1.2345678901234567, $expression->evaluate('1u'));
        } finally {
            setlocale(LC_NUMERIC, $locale);
        }
    }
}
