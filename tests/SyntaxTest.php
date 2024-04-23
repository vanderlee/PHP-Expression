<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class SyntaxTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    public static function dataSyntax(): array
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

    /**
     * @dataProvider dataSyntax
     * @param $expression
     * @throws Exception
     */
    public function testSyntax($expression)
    {
        $this->expectException(Exception::class);
        $this->object->evaluate($expression);
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new Expression();
    }
}