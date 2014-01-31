<?php

class test_syntax extends UnitTestCase {
	function testUnbalancedParenthesisOpen() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('(1');
	}

	function testUnbalancedParenthesisClose() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('1)');
	}

	function testStringQuotes() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('"3"');
	}

	function testStringApostrophes() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate("'3'");
	}

	function testBrackets() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('123[1]');
	}

	function testAccolades() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('123{1}');
	}

	function testComma() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('1,2');
	}

	function testSemicolon() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('1;2');
	}

	function testColon() {
		$E = new Expression();
		$this->expectException('ExpressionException');
		$E->evaluate('1:2');
	}
}