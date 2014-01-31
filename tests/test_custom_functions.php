<?php

function test_custom_function($v) {
	return $v * 3;
}

class test_custom_functions extends UnitTestCase {
	function testAddAndReset() {
		$E = new Expression;
		$E->addFunction('test_custom_function');
		$this->assertEqual($E->evaluate('test_custom_function(2)'), 6);
		$E->resetFunctions();
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_function(2)');
	}

	function testAddAndClear() {
		$E = new Expression;
		$E->addFunction('test_custom_function');
		$this->assertEqual($E->evaluate('test_custom_function(2)'), 6);
		$E->clearFunctions();
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_function(2)');
	}

	function testAddAlias() {
		$E = new Expression;
		$E->addFunction('tcf', 'test_custom_function');
		$this->assertEqual($E->evaluate('tcf(2)'), 6);
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_function(2)');
	}

	function testDefault() {
		$E = new Expression;
		$E->addFunction('test_custom_function');
		$this->assertEqual($E->evaluate('test_custom_function(2)'), 6);
		
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_function(2)');
	}
}