<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class NumbersTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testPositiveInteger()
    {

        $this->assertEquals(0, $this->object->evaluate('0'));
        $this->assertEquals(2, $this->object->evaluate('2'));
        $this->assertEquals(PHP_INT_MAX, $this->object->evaluate(PHP_INT_MAX));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testNegativeInteger()
    {

        $this->assertEquals(0, $this->object->evaluate('-0'));
        $this->assertEquals(-2, $this->object->evaluate('-2'));
        $this->assertEquals(-PHP_INT_MAX, $this->object->evaluate(-PHP_INT_MAX));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testPositiveFloat()
    {

        $this->assertEquals(1.23, $this->object->evaluate('1.23'));
        $this->assertEquals(1.0, $this->object->evaluate('1.'));
        $this->assertEquals(0.1, $this->object->evaluate('.1'));
        $this->assertEquals(1.0, $this->object->evaluate('1.0'));
        $this->assertEquals(0.1, $this->object->evaluate('0.1'));
        $this->assertEquals(0.0, $this->object->evaluate('0.'));
        $this->assertEquals(0.0, $this->object->evaluate('.0'));
        $this->assertEquals(0.0, $this->object->evaluate('0.0'));
    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testNegativeFloat()
    {

        $this->assertEquals(-1.23, $this->object->evaluate('-1.23'));
        $this->assertEquals(-1.0, $this->object->evaluate('-1.'));
        $this->assertEquals(-0.1, $this->object->evaluate('-.1'));
        $this->assertEquals(-1.0, $this->object->evaluate('-1.0'));
        $this->assertEquals(-0.1, $this->object->evaluate('-0.1'));
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