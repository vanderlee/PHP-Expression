<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class BuiltinFunctionsTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * clearFunctions method removes all builtins
     * @throws Exception
     */
    public function testClearFunctions()
    {
        $this->assertEquals(1, $this->object->evaluate('min(1,2)'));
        $this->object->clearFunctions();
        $this->expectException(Exception::class);
        $this->object->evaluate('min(1,2)');
    }

    /**
     * resetFunctions method restores builtins
     * @throws Exception
     */
    public function testResetFunctions()
    {
        $this->assertEquals(1, $this->object->evaluate('min(1,2)'));
        $this->object->resetFunctions();
        $this->assertEquals(1, $this->object->evaluate('min(1,2)'));
    }

    /**
     * @throws Exception
     */
    public function testMin()
    {
        $this->assertEquals(1, $this->object->evaluate('min(1,2)'));
        $this->assertEquals(1, $this->object->evaluate('min(2,1)'));
    }

    /**
     * @throws Exception
     */
    public function testMax()
    {
        $this->assertEquals(2, $this->object->evaluate('max(1,2)'));
        $this->assertEquals(2, $this->object->evaluate('max(2,1)'));
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new Expression();
    }

    // @todo more builtins
}