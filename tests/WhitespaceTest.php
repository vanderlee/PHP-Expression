<?php

class WhitespaceTest extends PHPUnit_Framework_TestCase {
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
	
	public function testSingle() {
		
		$this->assertEquals($this->object->evaluate('1'), 1);
		$this->assertEquals($this->object->evaluate(' 1 '), 1);
		$this->assertEquals($this->object->evaluate('  1  '), 1);
		$this->assertEquals($this->object->evaluate('	1	'), 1);
		$this->assertEquals($this->object->evaluate('
			1
			'), 1);

		}
		
	public function testMultiple() {
		
		$this->assertEquals($this->object->evaluate('1+1'), 2);
		$this->assertEquals($this->object->evaluate(' 1 + 1 '), 2);
		$this->assertEquals($this->object->evaluate('  1  +  1 '), 2);
		$this->assertEquals($this->object->evaluate('	1	+	1	'), 2);
		$this->assertEquals($this->object->evaluate('
			1
			+
			1
			'), 2);
	}
}