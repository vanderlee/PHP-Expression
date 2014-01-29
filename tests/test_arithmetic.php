<?php

class test_arithmetic extends UnitTestCase {
	function testAdditon() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('2+3'), 5);
		$this->assertEqual($E->evaluate('2+3+4'), 9);
		$this->assertEqual($E->evaluate('0+0'), 0);
		$this->assertEqual($E->evaluate('1+-1'), 0);
	}

	function testSubtraction() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('3-2'), 1);
		$this->assertEqual($E->evaluate('2-3'), -1);
		$this->assertEqual($E->evaluate('2-3-4'), -5);
		$this->assertEqual($E->evaluate('0-0'), 0);
		$this->assertEqual($E->evaluate('1-(-1)'), 2);
	}

	function testMultiplication() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('2*3'), 6);
		$this->assertEqual($E->evaluate('2*3*4'), 24);
		$this->assertEqual($E->evaluate('0*0'), 0);
		$this->assertEqual($E->evaluate('1*-1'), -1);
	}

	function testDivision() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('6/3'), 2);
		$this->assertEqual($E->evaluate('24/4/3'), 2);
		$this->assertEqual($E->evaluate('0/1'), 0);
	}

	function TestDivisionByZero() {
		$E = new Expression();
		$this->expectError('Division by zero');
		$E->evaluate('0/0');
	}

	function testModulo() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('12%1'), 0);
		$this->assertEqual($E->evaluate('12%2'), 0);
		$this->assertEqual($E->evaluate('12%3'), 0);
		$this->assertEqual($E->evaluate('12%4'), 0);
		$this->assertEqual($E->evaluate('12%5'), 2);
		$this->assertEqual($E->evaluate('12%6'), 0);
		$this->assertEqual($E->evaluate('12%7'), 5);
		$this->assertEqual($E->evaluate('12%8'), 4);
	}

	function TestModuleByZero() {
		$E = new Expression();
		$this->expectError('Division by zero');
		$E->evaluate('12%0');
	}
}