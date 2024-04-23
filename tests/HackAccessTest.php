<?php

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class HackAccessTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    public function testPhpFunction()
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('print(1)');
    }

    public function testClassMethod()
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::getLastErrors()');
    }

    public function testClassConstant()
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::ISO8601');
    }

    public function testClassVariable()
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::$foo');
    }

    public function testObjectMethod()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $clazz = new DateTime;
        $this->expectException(Exception::class);
        $this->object->evaluate('$clazz->getTimestamp()');
    }

    public function testObjectVariable()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $clazz = new DateTime;
        $this->expectException(Exception::class);
        $this->object->evaluate('$clazz->foo');
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