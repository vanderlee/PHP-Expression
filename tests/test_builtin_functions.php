<?php

class test_builtin_functions extends UnitTestCase {
	/**
	 * clearFunctions method removes all builtins
	 */
	function testClearFunctions() {
		$E = new Expression;
		$this->assertEqual($E->evaluate('min(1,2)'), 1);
		$E->clearFunctions();
		$this->expectException('ExpressionException');
		$E->evaluate('min(1,2)');
	}

	/**
	 * resetFunctions method restores builtins
	 */
	function testResetFunctions() {
		$E = new Expression;
		$this->assertEqual($E->evaluate('min(1,2)'), 1);
		$E->resetFunctions();
		$this->assertEqual($E->evaluate('min(1,2)'), 1);
	}

	function testMin() {
		$E = new Expression;
		$this->assertEqual($E->evaluate('min(1,2)'), 1);
		$this->assertEqual($E->evaluate('min(2,1)'), 1);
	}

	function testMax() {
		$E = new Expression;
		$this->assertEqual($E->evaluate('max(1,2)'), 2);
		$this->assertEqual($E->evaluate('max(2,1)'), 2);
	}

	// @todo more builtins
}