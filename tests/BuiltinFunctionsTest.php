<?php

class BuiltinFunctionsTest extends PHPUnit_Framework_TestCase {
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
	
	/**
	 * clearFunctions method removes all builtins
	 */
	public function testClearFunctions() {
		
		$this->assertEquals($this->object->evaluate('min(1,2)'), 1);
		$this->object->clearFunctions();
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('min(1,2)');
	}

	/**
	 * resetFunctions method restores builtins
	 */
	public function testResetFunctions() {
		
		$this->assertEquals($this->object->evaluate('min(1,2)'), 1);
		$this->object->resetFunctions();
		$this->assertEquals($this->object->evaluate('min(1,2)'), 1);
	}

	public function testMin() {
		
		$this->assertEquals($this->object->evaluate('min(1,2)'), 1);
		$this->assertEquals($this->object->evaluate('min(2,1)'), 1);
	}

	public function testMax() {
		
		$this->assertEquals($this->object->evaluate('max(1,2)'), 2);
		$this->assertEquals($this->object->evaluate('max(2,1)'), 2);
	}

	// @todo more builtins
}