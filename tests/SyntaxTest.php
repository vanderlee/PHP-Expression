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
		$this->setExpectedException(ExpressionException::class, "syntax error, unexpected ';'");
		$this->object->evaluate('(1');
	}

	public function testUnbalancedParenthesisClose() {
		$this->setExpectedException(ExpressionException::class, "syntax error, unexpected ')', expecting ';'");
		$this->object->evaluate('1)');
	}

	public function testStringQuotes() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character '\"'");
		$this->object->evaluate('"3"');
	}

	public function testStringApostrophes() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character '''");
		$this->object->evaluate("'3'");
	}

	public function testBrackets() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character '['");
		$this->object->evaluate('123[1]');
	}

	public function testAccolades() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character '{'");
		$this->object->evaluate('123{1}');
	}

	public function testComma() {
		$this->setExpectedException(ExpressionException::class, "syntax error, unexpected ','");
		$this->object->evaluate('1,2');
	}

	public function testSemicolon() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character ';'");
		$this->object->evaluate('1;2');
	}

	public function testColon() {
		
		$this->setExpectedException(ExpressionException::class, "illegal character ':'");
		$this->object->evaluate('1:2');
	}
}