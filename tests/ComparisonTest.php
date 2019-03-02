<?php

class ComparisonTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var Expression
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new Expression();
    }

    /**
     * @dataProvider dataComparison
     * @throws ExpressionException
     */
    public function testComparison($expression, $expected)
    {
        $this->assertEquals($expected, $this->object->evaluate($expression));
    }

    public function dataComparison()
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

    /**
     * @requires PHP 7
     * @dataProvider dataSpaceship
     * @throws ExpressionException
     */
    public function testSpaceship($expression, $expected)
    {
        $this->assertEquals($expected, $this->object->evaluate($expression));
    }

    public function dataSpaceship()
    {
        return [
            ['0 <=> 0', 0],
            ['1 <=> 0', 1],
            ['0 <=> 1', -1],
            ['1 <=> 1', 0],
        ];
    }
}
