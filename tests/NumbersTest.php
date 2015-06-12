<?php

class NumbersTest extends PHPUnit_Framework_TestCase {
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
	
	public function testPositiveInteger() {
		
		$this->assertEquals($this->object->evaluate('0'), 0);
		$this->assertEquals($this->object->evaluate('2'), 2);
		$this->assertEquals($this->object->evaluate(PHP_INT_MAX), PHP_INT_MAX);
	}

	public function testNegativeInteger() {
		
		$this->assertEquals($this->object->evaluate('-0'), 0);
		$this->assertEquals($this->object->evaluate('-2'), -2);
		$this->assertEquals($this->object->evaluate(-PHP_INT_MAX), -PHP_INT_MAX);
	}

	public function testPositiveFloat() {
		
		$this->assertEquals($this->object->evaluate('1.23'), 1.23);
		$this->assertEquals($this->object->evaluate('1.'), 1.0);
		$this->assertEquals($this->object->evaluate('.1'), 0.1);
		$this->assertEquals($this->object->evaluate('1.0'), 1.0);
		$this->assertEquals($this->object->evaluate('0.1'), 0.1);
		$this->assertEquals($this->object->evaluate('0.'), 0.0);
		$this->assertEquals($this->object->evaluate('.0'), 0.0);
		$this->assertEquals($this->object->evaluate('0.0'), 0.0);
	}

	public function testNegativeFloat() {
		
		$this->assertEquals($this->object->evaluate('-1.23'), -1.23);
		$this->assertEquals($this->object->evaluate('-1.'), -1.0);
		$this->assertEquals($this->object->evaluate('-.1'), -0.1);
		$this->assertEquals($this->object->evaluate('-1.0'), -1.0);
		$this->assertEquals($this->object->evaluate('-0.1'), -0.1);
	}
}