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

    public function testPhpFunction(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('print(1)');
    }

    public function testClassMethod(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::getLastErrors()');
    }

    public function testClassConstant(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::ISO8601');
    }

    public function testClassVariable(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::$foo');
    }

    public function testObjectMethod(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $clazz = new DateTime;
        $this->expectException(Exception::class);
        $this->object->evaluate('$clazz->getTimestamp()');
    }

    public function testObjectVariable(): void
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