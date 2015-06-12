<?php

/**
 * ExpressionDecibelUnit
 *
 * @author Martijn
 */
class ExpressionDecibelUnit implements ExpressionUnitInterface {
	public function convert($amount) {
		return pow(10, $amount * .1);
	}
}

// PHP 5.3+ namespace alias
if (function_exists('class_alias')) {
	class_alias('ExpressionDecibelUnit', 'Expression\DecibelUnit');
}