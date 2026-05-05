<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

function test_output_function(): int
{
    echo 'output';
    return 1;
}

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

    public function testExpressionLengthLimit()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('expression too long');
        $this->object->evaluate(str_repeat('1+', 2048) . '1');
    }

    public function testParenthesisDepthLimit()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('expression too deeply nested');
        $this->object->evaluate(str_repeat('(', 129) . '1' . str_repeat(')', 129));
    }

    public function testFunctionCallLimit()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('too many function calls');
        $this->object->evaluate(str_repeat('pi()+', 129) . '1');
    }

    public function testWhitespaceOnlyExpressionIsEmpty()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty expression');
        $this->object->evaluate(" \t\r\n ");
    }

    public function testFunctionOutputIsRejected()
    {
        $this->object->addFunction('test_output_function');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Syntax error');
        $this->object->evaluate('test_output_function()');
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
