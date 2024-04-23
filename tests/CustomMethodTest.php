<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

/** @noinspection PhpUnused */

class test_custom_methods_class
{
    /** @noinspection PhpUnused */
    public static function test_custom_method($v)
    {
        return $v * 3;
    }
}

class CustomMethodTest extends TestCase
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
        $this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
        $this->assertEquals(6, $this->object->evaluate('test_custom_method(2)'));
        $this->object->resetFunctions();
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_method(2)');
    }

    /**
     * @throws Exception
     */
    public function testAddAndClear()
    {
        $this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
        $this->assertEquals(6, $this->object->evaluate('test_custom_method(2)'));
        $this->object->clearFunctions();
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_method(2)');
    }

    /**
     * @throws Exception
     */
    public function testAddAlias()
    {
        $this->object->addFunction('tcf', 'test_custom_methods_class::test_custom_method');
        $this->assertEquals(6, $this->object->evaluate('tcf(2)'));
        $this->expectException(Exception::class);
        $this->object->evaluate('test_custom_method(2)');
    }

    /**
     * @throws Exception
     */
    public function testDefault()
    {
        $this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
        $this->assertEquals(6, $this->object->evaluate('test_custom_method(2)'));
        $this->assertEquals(6, $this->object->evaluate('test_custom_method(2)'));
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
