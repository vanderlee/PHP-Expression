<?php

class test_whitespace extends UnitTestCase {
	function testSingle() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('1'), 1);
		$this->assertEqual($E->evaluate(' 1 '), 1);
		$this->assertEqual($E->evaluate('  1  '), 1);
		$this->assertEqual($E->evaluate('	1	'), 1);
		$this->assertEqual($E->evaluate('
			1
			'), 1);

		}
	function testMultiple() {
		$E = new Expression();
		$this->assertEqual($E->evaluate('1+1'), 2);
		$this->assertEqual($E->evaluate(' 1 + 1 '), 2);
		$this->assertEqual($E->evaluate('  1  +  1 '), 2);
		$this->assertEqual($E->evaluate('	1	+	1	'), 2);
		$this->assertEqual($E->evaluate('
			1
			+
			1
			'), 2);
	}
}