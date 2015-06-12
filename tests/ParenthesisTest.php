<?php

class ParenthesisTest extends PHPUnit_Framework_TestCase {
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
	
	public function testBalanced() {
		
		$this->assertEquals($this->object->evaluate('2+3*4'), 14);
		$this->assertEquals($this->object->evaluate('(2+3)*4'), 20);
		$this->assertEquals($this->object->evaluate('2+(3*4)'), 14);
		$this->assertEquals($this->object->evaluate('((2)+((3)*(4)))'), 14);
	}
}