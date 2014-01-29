<?php

class test_bases extends UnitTestCase {
	function testDecimal() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('0'), 0);
		$this->assertEqual($E->evaluate('1'), 1);
		$this->assertEqual($E->evaluate('10'), 10);
		$this->assertEqual($E->evaluate('100'), 100);
		$this->assertEqual($E->evaluate('-100'), -100);
	}

	function testOctal() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('0'), 0);
		$this->assertEqual($E->evaluate('01'), 1);
		$this->assertEqual($E->evaluate('010'), 8);
		$this->assertEqual($E->evaluate('0100'), 64);
		$this->assertEqual($E->evaluate('-0100'), -64);
	}

	function testHexadecimal() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('0x0'), 0);
		$this->assertEqual($E->evaluate('0x1'), 1);
		$this->assertEqual($E->evaluate('0x10'), 16);
		$this->assertEqual($E->evaluate('0x100'), 256);
		$this->assertEqual($E->evaluate('-0x100'), -256);
	}

	function testBinary() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('0b0'), 0);
		$this->assertEqual($E->evaluate('0b1'), 1);
		$this->assertEqual($E->evaluate('0b10'), 2);
		$this->assertEqual($E->evaluate('0b100'), 4);
		$this->assertEqual($E->evaluate('-0b100'), -4);
	}
}