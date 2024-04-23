<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class ParenthesisTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testBalanced()
    {

        $this->assertEquals(14, $this->object->evaluate('2+3*4'));
        $this->assertEquals(20, $this->object->evaluate('(2+3)*4'));
        $this->assertEquals(14, $this->object->evaluate('2+(3*4)'));
        $this->assertEquals(14, $this->object->evaluate('((2)+((3)*(4)))'));
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