<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\Decibel;
use Vanderlee\Expression\Unit\Percentage;

class UnitsTest extends TestCase
{
    /**
     * @var Expression
     */
    protected $object;

    /**
     * @throws Exception
     */
    public function testUnit(): void
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

    public function testUnitDot(): void
    {

        $this->object->addUnit('u', 10);
        $this->expectException(Exception::class);
        $this->object->evaluate('.u');
    }

    public function testEmptyUnitSuffix(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('empty unit suffix');
        $this->object->addUnit('', 10);
    }

    public function testUnitSuffixIsMatchedLiterally(): void
    {
        $this->object->addUnit('u+', 10);
        $this->assertEquals(20, $this->object->evaluate('2u+'));
    }

    public function testRegexUnitSuffixDoesNotOvermatch(): void
    {
        $this->object->addUnit('.*', 10);
        $this->expectException(Exception::class);
        $this->object->evaluate('3abc');
    }

    public function testCaseMismatchedUnitSuffixIsRejected(): void
    {
        $this->object->addUnit('dB', 10);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Undefined unit `db`');
        $this->object->evaluate('1db');
    }

    public function testLongestUnitSuffixIsMatchedFirst(): void
    {
        $this->object->addUnit('m', 10);
        $this->object->addUnit('mm', 100);
        $this->assertEquals(220, $this->object->evaluate('2mm+2m'));
    }

    public function testRemoveUnit(): void
    {
        $this->object->addUnit('u', 10);
        $this->assertEquals(20, $this->object->evaluate('2u'));

        $this->object->removeUnit('u');

        $this->expectException(Exception::class);
        $this->object->evaluate('2u');
    }

    public function testClearUnits(): void
    {
        $this->object->addUnit('u', 10);
        $this->object->addUnit('v', 20);
        $this->assertEquals(60, $this->object->evaluate('2u+2v'));

        $this->object->clearUnits();

        $this->expectException(Exception::class);
        $this->object->evaluate('2u');
    }

    public function testPercentageUnit(): void
    {
        $percentage = new Percentage(200);

        $this->assertEquals(50, $percentage->convert(25));

        $percentage->set(400);

        $this->assertEquals(100, $percentage->convert(25));
    }

    public function testPercentageUnitIntegration(): void
    {
        $this->object->addUnit('%', new Percentage(200));

        $this->assertEquals(100, $this->object->evaluate('50%'));
        $this->assertEquals(125, $this->object->evaluate('50%+25'));
    }

    public function testDecibelUnit(): void
    {
        $decibel = new Decibel();

        $this->assertEquals(1, $decibel->convert(0));
        $this->assertEquals(10, $decibel->convert(10));
        $this->assertEquals(100, $decibel->convert(20));
    }

    public function testDecibelUnitIntegration(): void
    {
        $this->object->addUnit('dB', new Decibel());

        $this->assertEquals(1, $this->object->evaluate('0dB'));
        $this->assertEquals(10, $this->object->evaluate('10dB'));
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
