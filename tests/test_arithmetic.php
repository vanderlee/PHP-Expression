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
	}
}