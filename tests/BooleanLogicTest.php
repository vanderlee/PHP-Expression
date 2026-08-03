<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class BooleanLogicTest extends TestCase
{

    /**
     * @var Expression
     */
    protected $object;

    public static function dataBooleanLogic(): array
    {
        return [
            ['0 && 0', false],
            ['0 && 1', false],
            ['1 && 0', false],
            ['1 && 1', true],
            ['11 && 0', false],
            ['11 && 3', true],
            ['0 and 0', false],
            ['0 and 1', false],
            ['1 and 0', false],
            ['1 and 1', true],

            ['0 || 0', false],
            ['0 || 1', true],
            ['1 || 0', true],
            ['1 || 1', true],
            ['11 || 0', true],
            ['11 || 3', true],
            ['0 or 0', false],
            ['0 or 1', true],
            ['1 or 0', true],
            ['1 or 1', true],

            ['!0', true],
            ['!1', false],
            ['!3', false],
            ['not 0', true],
            ['not 1', false],
            ['not 3', false],

            ['0 ^^ 0', false],
            ['0 ^^ 1', true],
            ['1 ^^ 0', true],
            ['1 ^^ 1', false],
            ['11 ^^ 0', true],
            ['11 ^^ 3', false],
            ['0 xor 0', false],
            ['0 xor 1', true],
            ['1 xor 0', true],
            ['1 xor 1', false],
        ];
    }

    /**
     * @dataProvider dataBooleanLogic
     * @throws Exception
     */
    public function testBooleanLogic($expression, $expected): void
    {
        $this->assertEquals($expected, (bool)$this->object->evaluate($expression));
    }

    public static function dataBooleanLiterals(): array
    {
        return [
            ['false', 0.0],
            ['true', 1.0],
            ['FALSE', 0.0],
            ['TRUE', 1.0],
            ['FaLsE', 0.0],
            ['TrUe', 1.0],
            ['!false', 1.0],
            ['not true', 0.0],
            ['true && false', 0.0],
            ['true and true', 1.0],
            ['false || true', 1.0],
            ['false or false', 0.0],
            ['true ^^ false', 1.0],
            ['true xor true', 0.0],
            ['(true or false) and not false', 1.0],
            ['true == 1', 1.0],
            ['false == 0', 1.0],
        ];
    }

    /**
     * @dataProvider dataBooleanLiterals
     * @throws Exception
     */
    public function testBooleanLiterals(string $expression, float $expected): void
    {
        $this->assertSame($expected, $this->object->evaluate($expression));
    }

    public static function dataInvalidBooleanLiteralUsage(): array
    {
        return [
            ['truevalue'],
            ['false_value'],
            ['1true'],
            ['false1'],
            ['true()'],
            ['false()'],
        ];
    }

    /**
     * @dataProvider dataInvalidBooleanLiteralUsage
     */
    public function testBooleanLiteralsAreOnlyAcceptedAsWholeValueTokens(string $expression): void
    {
        $this->expectException(Exception::class);
        $this->object->evaluate($expression);
    }

    protected function setUp(): void
    {
        $this->object = new Expression();
    }
}
