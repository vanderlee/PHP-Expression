<?php

class test_custom_methods_class {
	public static function test_custom_method($v) {
		return $v * 3;
	}
}

class test_custom_methods extends UnitTestCase {
	function testAddAndReset() {
		$E = new Expression;
		$E->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEqual($E->evaluate('test_custom_method(2)'), 6);
		$E->resetFunctions();
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_method(2)');
	}

	function testAddAndClear() {
		$E = new Expression;
		$E->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEqual($E->evaluate('test_custom_method(2)'), 6);
		$E->clearFunctions();
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_method(2)');
	}

	function testAddAlias() {
		$E = new Expression;
		$E->addFunction('tcf', 'test_custom_methods_class::test_custom_method');
		$this->assertEqual($E->evaluate('tcf(2)'), 6);
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_method(2)');
	}

	function testDefault() {
		$E = new Expression;
		$E->addFunction('test_custom_method', 'test_custom_methods_class::test_custom_method');
		$this->assertEqual($E->evaluate('test_custom_method(2)'), 6);
		
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('test_custom_method(2)');
	}
}