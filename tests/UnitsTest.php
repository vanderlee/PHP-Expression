<?php

class UnitsTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var Expression
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {		
		$this->object = new Expression();
	}

	public function testUnit() {
		
		$this->object->addUnit('u', 10);
		$this->assertEquals($this->object->evaluate('2'), 2);
		$this->assertEquals($this->object->evaluate('2u'), 20);
		$this->assertEquals($this->object->evaluate('-2u'), -20);
		$this->assertEquals($this->object->evaluate('2.5u'), 25);
		$this->assertEquals($this->object->evaluate('-2.5u'), -25);
		$this->assertEquals($this->object->evaluate('2.u'), 20);
		$this->assertEquals($this->object->evaluate('-2.u'), -20);
		$this->assertEquals($this->object->evaluate('.5u'), 5);
		$this->assertEquals($this->object->evaluate('-.5u'), -5);
	}

	public function testUnitDot() {
		
		$this->object->addUnit('u', 10);
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('.u');
	}

}
