<?php

class test_parenthesis extends UnitTestCase {
	function testBalanced() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('2+3*4'), 14);
		$this->assertEqual($E->evaluate('(2+3)*4'), 20);
		$this->assertEqual($E->evaluate('2+(3*4)'), 14);
		$this->assertEqual($E->evaluate('((2)+((3)*(4)))'), 14);
	}
}