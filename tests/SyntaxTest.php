<?php

class SyntaxTest extends PHPUnit_Framework_TestCase {
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
	
	public function testUnbalancedParenthesisOpen() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('(1');
	}

	public function testUnbalancedParenthesisClose() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('1)');
	}

	public function testStringQuotes() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('"3"');
	}

	public function testStringApostrophes() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate("'3'");
	}

	public function testBrackets() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('123[1]');
	}

	public function testAccolades() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('123{1}');
	}

	public function testComma() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('1,2');
	}

	public function testSemicolon() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('1;2');
	}

	public function testColon() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('1:2');
	}
}