<?php

class test_hack_access extends UnitTestCase {
	function testPhpFunction() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('print(1)');
	}

	function testClassMethod() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('DateTime::getLastErrors()');
	}

	function testClassConstant() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('DateTime::ISO8601');
	}

	function testClassVariable() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('DateTime::$foo');
	}

	function testObjectMethod() {
		$E = new Expression;
		$clazz = new DateTime;
		$this->expectException('ExpressionException');
		$E->evaluate('$clazz->getTimestamp()');
	}

	function testObjectVariable() {
		$E = new Expression;
		$clazz = new DateTime;
		$this->expectException('ExpressionException');
		$E->evaluate('$clazz->foo');
	}
}