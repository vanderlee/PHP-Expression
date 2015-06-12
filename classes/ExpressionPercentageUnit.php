<?php

/**
 * ExpressionPercentageUnit
 *
 * @author Martijn
 */
class ExpressionPercentageUnit implements ExpressionUnitInterface {
	private $range = 1;

	public function __construct($range = 1) {
		$this->range = $range;
	}

	public function set($range = 1) {
		$this->range = $range;
	}

	public function convert($amount) {
		return $amount * $this->range / 100;
	}
}

// PHP 5.3+ namespace alias
if (function_exists('class_alias')) {
	class_alias('ExpressionPercentageUnit', 'Expression\PercentageUnit');
}