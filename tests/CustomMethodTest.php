<?php

class test_custom_methods_class {

	public static function test_custom_method($v) {
		return $v * 3;
	}

}

class CustomMethodTest extends PHPUnit_Framework_TestCase {

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

	public function testAddAndReset() {
		$this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEquals($this->object->evaluate('test_custom_method(2)'), 6);
		$this->object->resetFunctions();
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('test_custom_method(2)');
	}

	public function testAddAndClear() {
		$this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEquals($this->object->evaluate('test_custom_method(2)'), 6);
		$this->object->clearFunctions();
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('test_custom_method(2)');
	}

	public function testAddAlias() {
		$this->object->addFunction('tcf', 'test_custom_methods_class::test_custom_method');
		$this->assertEquals($this->object->evaluate('tcf(2)'), 6);
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('test_custom_method(2)');
	}

	public function testDefault() {
		$this->object->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEquals($this->object->evaluate('test_custom_method(2)'), 6);
		$this->assertEquals($this->object->evaluate('test_custom_method(2)'), 6);
	}

}
