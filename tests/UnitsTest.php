<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class UnitsTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws Exception
     */
    public function testUnit()
    {

        $this->object->addUnit('u', 10);
        $this->assertEquals(2, $this->object->evaluate('2'));
        $this->assertEquals(20, $this->object->evaluate('2u'));
        $this->assertEquals(-20, $this->object->evaluate('-2u'));
        $this->assertEquals(25, $this->object->evaluate('2.5u'));
        $this->assertEquals(-25, $this->object->evaluate('-2.5u'));
        $this->assertEquals(20, $this->object->evaluate('2.u'));
        $this->assertEquals(-20, $this->object->evaluate('-2.u'));
        $this->assertEquals(5, $this->object->evaluate('.5u'));
        $this->assertEquals(-5, $this->object->evaluate('-.5u'));
    }

    public function testUnitDot()
    {

        $this->object->addUnit('u', 10);
        $this->expectException(Exception::class);
        $this->object->evaluate('.u');
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
