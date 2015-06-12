<?php

/**
 *
 * @author Martijn
 */
interface ExpressionUnitInterface {
	public function convert($amount);
}

// PHP 5.3+ namespace alias
if (function_exists('class_alias')) {
	class_alias('ExpressionUnitInterface', 'Expression\UnitInterface');
}