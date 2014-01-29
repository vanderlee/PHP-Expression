<?php

/*
 * @version 0.0.2
 * @author Martijn W. van der Lee (martijn-at-vanderlee-dot-com)
 * @copyright Copyright (c) 2011, Martijn W. van der Lee
 * @license http://www.opensource.org/licenses/mit-license.php
 */

require_once 'ExpressionUnitInterface.class.php';

/**
 * Expression
 *
 * @author Martijn
 */
class Expression {
	private static $default_functions = array(
		'abs'			=> 'abs',
		'acos'			=> 'acos',
		'acosh'			=> 'acosh',
		'asin'			=> 'asin',
		'asinh'			=> 'asinh',
		'atan2'			=> 'atan2',
		'atan'			=> 'atan',
		'atanh'			=> 'atanh',
		'ceil'			=> 'ceil',
		'cos'			=> 'cos',
		'cosh'			=> 'cosh',
		'deg2rad'		=> 'deg2rad',
		'exp'			=> 'exp',
		'expm1'			=> 'expm1',
		'floor'			=> 'floor',
		'fmod'			=> 'fmod',
		'getrandmax'	=> 'getrandmax',
		'hypot'			=> 'hypot',
		'is_finite'		=> 'is_finite',
		'is_infinite'	=> 'is_infinite',
		'is_nan'		=> 'is_nan',
		'lcg_value'		=> 'lcg_value',
		'log10'			=> 'log10',
		'log1p'			=> 'log1p',
		'log'			=> 'log',
		'max'			=> 'max',
		'min'			=> 'min',
		'mt_getrandmax'	=> 'mt_getrandmax',
		'mt_rand'		=> 'mt_rand',
		'mt_srand'		=> 'mt_srand',
		'pi'			=> 'pi',
		'pow'			=> 'pow',
		'rad2deg'		=> 'rad2deg',
		'rand'			=> 'rand',
		'round'			=> 'round',
		'sin'			=> 'sin',
		'sinh'			=> 'sinh',
		'sqrt'			=> 'sqrt',
		'tan'			=> 'tan',
		'tanh'			=> 'tanh'
	);

	private $functions;

	/**
	 * units
	 * @var Array map of suffix and unitsize
	 */
	private $units = array();

	public function __construct() {
		$this->resetFunctions();
	}

	public function addUnit($suffix, $unitsize = 1) {
		$this->units[$suffix] = $unitsize;
	}

	public function removeUnit($suffix) {
		unset($this->units[$suffix]);
	}

	public function clearUnits() {
		$this->units = array();
	}

	public function addFunction($alias, $function = null) {
		$this->functions[$alias] = $function ?: $alias;
	}

	public function removeFunction($alias) {
		unset($this->functions[$alias]);
	}

	public function clearFunctions() {
		$this->functions = array();
	}

	public function resetFunctions() {
		$this->functions = self::$default_functions;
	}

	private function match_hexdec($match) {
		return hexdec($match[0]);
	}

	private function match_bindec($match) {
		return bindec($match[0]);
	}

	private function match_convert_unit($match) {
		$unit = $this->units[$match[2]];
		return ($unit instanceof ExpressionUnitInterface) ? $unit->convert($match[1]) : $match[1] * $unit;
	}

	private function match_map_function($match) {
		$function = $match[0];
		if (!isset($this->functions[$function])) {
			throw new Exception("Illegal function '{$match[0]}'");
		}
		return $this->functions[$function];
	}

	public function evaluate($expression) {
		// Convert hexadecimal to decimal
		$expression = preg_replace_callback('~\b0x[[:xdigit:]]+\b~', array($this, 'match_hexdec'), $expression);

		// Convert binary to decimal
		$expression = preg_replace_callback('~\b0b[01]+\b~', array($this, 'match_bindec'), $expression);

		// Convert units
		$units = &$this->units;
		if ($units) {
			$suffixes = array_keys($units);
			array_walk($suffixes, 'preg_quote');
			$suffix_string = join('|', $suffixes);

			$expression = preg_replace_callback('~\b([\d.]+)('.$suffix_string.')~i', array($this, 'match_convert_unit'), $expression);
		}

		// Remove any whitespace
		$expression = strtolower(preg_replace('~\s+~', '', $expression));

		// Empty expression
		if ($expression === '') {
			throw new Exception('Empty expression');
		}

		// Illegal colons
		if (strpos($expression, ':') !== FALSE) {
			throw new Exception("Illegal character ':'");
		}

		// Illegal function
		$functions = &$this->functions;
		$expression = preg_replace_callback('~\b[a-z_]\w*\b~i', array($this, 'match_map_function'), $expression);

		// Invalid function calls
		if (preg_match('~[a-z_][\w:]*(?![\(\w:])~i', $expression, $match) > 0) {
			throw new Exception("Invalid function call '{$match[0]}'");
		}

		// Illegal characters
		if (preg_match('~[^-^+/%*&|<>!=.()0-9a-z,_:]~i', $expression, $match) > 0) {
			throw new Exception("Illegal character '{$match[0]}'");
		}

		return floatval(eval("return({$expression});"));
	}
}

// PHP 5.3+ namespace alias
if (function_exists('class_alias')) {
	class_alias('Expression', 'Expression\Expression');
}