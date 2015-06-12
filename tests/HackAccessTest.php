<?php

class HackAccessTest extends PHPUnit_Framework_TestCase {
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
	
	public function testPhpFunction() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('print(1)');
	}

	public function testClassMethod() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('DateTime::getLastErrors()');
	}

	public function testClassConstant() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('DateTime::ISO8601');
	}

	public function testClassVariable() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('DateTime::$foo');
	}

	public function testObjectMethod() {
		
		$clazz = new DateTime;
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('$clazz->getTimestamp()');
	}

	public function testObjectVariable() {
		
		$clazz = new DateTime;
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('$clazz->foo');
	}
}