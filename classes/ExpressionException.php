<?php

/**
 * ExpressionException
 *
 * @author Martijn
 */
class ExpressionException extends Exception {}

// PHP 5.3+ namespace alias
if (function_exists('class_alias')) {
	class_alias('ExpressionException', 'Expression\Exception');
}