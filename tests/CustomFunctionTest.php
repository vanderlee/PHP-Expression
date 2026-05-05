<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

/** @noinspection PhpUnused */
function test_custom_function($v)
{
    return $v * 3;
}

class CustomFunctionTest extends TestCase
{

    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws Exception
     */
    public function testAddAndReset()
    {
        $this->object->addFunction('test_custom_function');
        $this->assertEquals(6, $this->object->evaluate('test_custom_function(2)'));
        $this->object->resetFunctions();
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_function(2)');
    }

    /**
     * @throws Exception
     */
    public function testAddAndClear()
    {
        $this->object->addFunction('test_custom_function');
        $this->assertEquals(6, $this->object->evaluate('test_custom_function(2)'));
        $this->object->clearFunctions();
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_function(2)');
    }

    /**
     * @throws Exception
     */
    public function testAddAlias()
    {
        $this->object->addFunction('tcf', 'test_custom_function');
        $this->assertEquals(6, $this->object->evaluate('tcf(2)'));
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_function(2)');
    }

    /**
     * @throws Exception
     */
    public function testDefault()
    {
        $this->object->addFunction('test_custom_function');
        $this->assertEquals(6, $this->object->evaluate('test_custom_function(2)'));
        $this->assertEquals(6, $this->object->evaluate('test_custom_function(2)'));
    }

    public function testInvalidAlias()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid function alias');
        $this->object->addFunction('test_custom_function();');
    }

    public function testInvalidFunctionTarget()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid function target');
        $this->object->addFunction('tcf', 'test_custom_function()');
    }

    public function testNonCallableFunctionTarget()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not callable');
        $this->object->addFunction('tcf', 'missing_custom_function');
    }

    public function testNonStringFunctionTarget()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid function target');
        $this->object->addFunction('tcf', ['test_custom_function']);
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
