<?php

class test_numbers extends UnitTestCase {
	function testPositiveInteger() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('0'), 0);
		$this->assertEqual($E->evaluate('2'), 2);
		$this->assertEqual($E->evaluate(PHP_INT_MAX), PHP_INT_MAX);
	}

	function testNegativeInteger() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('-0'), 0);
		$this->assertEqual($E->evaluate('-2'), -2);
		$this->assertEqual($E->evaluate(-PHP_INT_MAX), -PHP_INT_MAX);
	}

	function testPositiveFloat() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('1.23'), 1.23);
		$this->assertEqual($E->evaluate('1.'), 1.0);
		$this->assertEqual($E->evaluate('.1'), 0.1);
		$this->assertEqual($E->evaluate('1.0'), 1.0);
		$this->assertEqual($E->evaluate('0.1'), 0.1);
		$this->assertEqual($E->evaluate('0.'), 0.0);
		$this->assertEqual($E->evaluate('.0'), 0.0);
		$this->assertEqual($E->evaluate('0.0'), 0.0);
	}

	function testNegativeFloat() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('-1.23'), -1.23);
		$this->assertEqual($E->evaluate('-1.'), -1.0);
		$this->assertEqual($E->evaluate('-.1'), -0.1);
		$this->assertEqual($E->evaluate('-1.0'), -1.0);
		$this->assertEqual($E->evaluate('-0.1'), -0.1);
	}

	function testAdditon() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('2 + 3'), 5);
		$this->assertEqual($E->evaluate('2+3'), 5);
		$this->assertEqual($E->evaluate('2
			+
			3'), 5);
		$this->assertEqual($E->evaluate('2	+	3'), 5);
		$this->assertEqual($E->evaluate('2+3+4'), 9);
		$this->assertEqual($E->evaluate('0+0'), 0);
		$this->assertEqual($E->evaluate('1+-1'), 0);
	}

	function testSubtraction() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('2 + 3'), 5);
		$this->assertEqual($E->evaluate('2+3'), 5);
		$this->assertEqual($E->evaluate('2
			+
			3'), 5);
		$this->assertEqual($E->evaluate('2	+	3'), 5);
		$this->assertEqual($E->evaluate('2+3+4'), 9);
		$this->assertEqual($E->evaluate('0+0'), 0);
		$this->assertEqual($E->evaluate('1+-1'), 0);
	}

	function testMultiplication() {
		// @todo
	}

	function testDivision() {
		// @todo
	}

	function testModulo() {
		// @todo
	}
}