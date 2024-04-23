<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Expression;

class WhitespaceTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testSingle()
    {

        $this->assertEquals(1, $this->object->evaluate('1'));
        $this->assertEquals(1, $this->object->evaluate(' 1 '));
        $this->assertEquals(1, $this->object->evaluate('  1  '));
        $this->assertEquals(1, $this->object->evaluate('	1	'));
        $this->assertEquals(1, $this->object->evaluate('
			1
			'));

    }

    /**
     * @throws \Vanderlee\Expression\Exception
     */
    public function testMultiple()
    {

        $this->assertEquals(2, $this->object->evaluate('1+1'));
        $this->assertEquals(2, $this->object->evaluate(' 1 + 1 '));
        $this->assertEquals(2, $this->object->evaluate('  1  +  1 '));
        $this->assertEquals(2, $this->object->evaluate('	1	+	1	'));
        $this->assertEquals(2, $this->object->evaluate('
			1
			+
			1
			'));
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