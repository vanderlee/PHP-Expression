<?php

class test_units extends UnitTestCase {
	function testUnit() {
		$E = new Expression();
		$E->addUnit('u', 10);
		$this->assertEqual($E->evaluate('2'), 2);
		$this->assertEqual($E->evaluate('2u'), 20);
		$this->assertEqual($E->evaluate('-2u'), -20);
		$this->assertEqual($E->evaluate('2.5u'), 25);
		$this->assertEqual($E->evaluate('-2.5u'), -25);
		$this->assertEqual($E->evaluate('2.u'), 20);
		$this->assertEqual($E->evaluate('-2.u'), -20);
		$this->assertEqual($E->evaluate('.5u'), 5);
		$this->assertEqual($E->evaluate('-.5u'), -5);
	}
}