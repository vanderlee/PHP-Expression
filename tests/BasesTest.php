<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class BasesTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testDecimal()
    {
        $this->assertEquals(0, $this->object->evaluate('0'));
        $this->assertEquals(1, $this->object->evaluate('1'));
        $this->assertEquals(10, $this->object->evaluate('10'));
        $this->assertEquals(100, $this->object->evaluate('100'));
        $this->assertEquals(-100, $this->object->evaluate('-100'));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testOctal()
    {

        $this->assertEquals(0, $this->object->evaluate('0'));
        $this->assertEquals(1, $this->object->evaluate('01'));
        $this->assertEquals(8, $this->object->evaluate('010'));
        $this->assertEquals(64, $this->object->evaluate('0100'));
        $this->assertEquals(-64, $this->object->evaluate('-0100'));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testHexadecimal()
    {

        $this->assertEquals(0, $this->object->evaluate('0x0'));
        $this->assertEquals(1, $this->object->evaluate('0x1'));
        $this->assertEquals(16, $this->object->evaluate('0x10'));
        $this->assertEquals(256, $this->object->evaluate('0x100'));
        $this->assertEquals(-256, $this->object->evaluate('-0x100'));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testBinary()
    {

        $this->assertEquals(0, $this->object->evaluate('0b0'));
        $this->assertEquals(1, $this->object->evaluate('0b1'));
        $this->assertEquals(2, $this->object->evaluate('0b10'));
        $this->assertEquals(4, $this->object->evaluate('0b100'));
        $this->assertEquals(-4, $this->object->evaluate('-0b100'));
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