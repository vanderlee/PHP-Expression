<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class ComparisonTest extends TestCase
{

    /**
     * @var Expression
     */
    protected $object;

    public static function dataComparison(): array
    {
        return [
            ['0 == 0', 1],
            ['1 == 0', 0],
            ['0 == 1', 0],
            ['1 == 1', 1],

            ['0 != 0', 0],
            ['1 != 0', 1],
            ['0 != 1', 1],
            ['1 != 1', 0],

            ['0 === 0', 1],
            ['1 === 0', 0],
            ['0 === 1', 0],
            ['1 === 1', 1],

            ['0 !== 0', 0],
            ['1 !== 0', 1],
            ['0 !== 1', 1],
            ['1 !== 1', 0],

            ['0 > 0', 0],
            ['1 > 0', 1],
            ['0 > 1', 0],
            ['1 > 1', 0],

            ['0 >= 0', 1],
            ['1 >= 0', 1],
            ['0 >= 1', 0],
            ['1 >= 1', 1],

            ['0 < 0', 0],
            ['1 < 0', 0],
            ['0 < 1', 1],
            ['1 < 1', 0],

            ['0 <= 0', 1],
            ['1 <= 0', 0],
            ['0 <= 1', 1],
            ['1 <= 1', 1],
        ];
    }

    public static function dataSpaceship(): array
    {
        return [
            ['0 <=> 0', 0],
            ['1 <=> 0', 1],
            ['0 <=> 1', -1],
            ['1 <=> 1', 0],
        ];
    }

    /**
     * @dataProvider dataComparison
     * @throws Exception
     */
    public function testComparison($expression, $expected)
    {
        $this->assertEquals($expected, $this->object->evaluate($expression));
    }

    /**
     * @dataProvider dataSpaceship
     * @requires PHP 7
     * @throws Exception
     */
    public function testSpaceship($expression, $expected)
    {
        $this->assertEquals($expected, $this->object->evaluate($expression));
    }

    protected function setUp(): void
    {
        $this->object = new Expression();
    }
}
