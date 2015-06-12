<?php

class BasesTest extends PHPUnit_Framework_TestCase {
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
	
	public function testDecimal() {		
		$this->assertEquals($this->object->evaluate('0'), 0);
		$this->assertEquals($this->object->evaluate('1'), 1);
		$this->assertEquals($this->object->evaluate('10'), 10);
		$this->assertEquals($this->object->evaluate('100'), 100);
		$this->assertEquals($this->object->evaluate('-100'), -100);
	}

	public function testOctal() {
		
		$this->assertEquals($this->object->evaluate('0'), 0);
		$this->assertEquals($this->object->evaluate('01'), 1);
		$this->assertEquals($this->object->evaluate('010'), 8);
		$this->assertEquals($this->object->evaluate('0100'), 64);
		$this->assertEquals($this->object->evaluate('-0100'), -64);
	}

	public function testHexadecimal() {
		
		$this->assertEquals($this->object->evaluate('0x0'), 0);
		$this->assertEquals($this->object->evaluate('0x1'), 1);
		$this->assertEquals($this->object->evaluate('0x10'), 16);
		$this->assertEquals($this->object->evaluate('0x100'), 256);
		$this->assertEquals($this->object->evaluate('-0x100'), -256);
	}

	public function testBinary() {
		
		$this->assertEquals($this->object->evaluate('0b0'), 0);
		$this->assertEquals($this->object->evaluate('0b1'), 1);
		$this->assertEquals($this->object->evaluate('0b10'), 2);
		$this->assertEquals($this->object->evaluate('0b100'), 4);
		$this->assertEquals($this->object->evaluate('-0b100'), -4);
	}
}