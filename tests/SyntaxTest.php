<?php

class SyntaxTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Expression();
    }

    /**
     * @dataProvider dataSyntax
     *
     * @param $expression
     * @param $exception
     * @throws ExpressionException
     */
    public function testSyntax($expression)
    {
        $this->setExpectedException(ExpressionException::class);
        $this->object->evaluate($expression);
    }

    public function dataSyntax()
    {
        return [
            ['(1'],
            ['1)'],
            ['"3"'],
            ["'3'"],
            ['123[1]'],
            ['123{1}'],
            ['1,2'],
            ['1;2'],
            ['1:2'],
            ['1?2:3'],
            ['1?:2'],
            ['1??2'],
        ];
    }
}