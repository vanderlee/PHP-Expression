<?php

	require_once 'Expression/autoloader.php';

//	require_once 'Expression.class.php';
//	require_once 'ExpressionPercentageUnit.class.php';
//	require_once 'ExpressionDecibelUnit.class.php';

	//@todo More default unit typs
	//@todo PHP5.2/5.3 autoswitching class loader (SPL-based)
	//@todo Support for custom (non-global) functions. i.e. temporary static on Expression self?
	//@todo Complete unittest coverage using SimpleTest framework
	//@todo Complete test of any and all SQL injection (how to simulate?)
	//@todo Complete test of any and all XSS hacks (how to simulate?)
	//@todo Complete test of any and all other hacks (how to simulate?)
	//@todo Do :: filtering for static functions on self?

//	echo expression(unit_convert('100% - 2px', 500)) . '<br/>';
//	echo expression('1 ^ 3') . '<br/>';
//	echo expression('pi()') . '<br/>';
//	echo expression(anydec('0x0f-(010+0b10)')) . '<br/>';
//	echo expression('ceil(4/3)') . '<br/>';
//	echo expression('floor(4/3)') . '<br/>';
//	echo expression('round(4/3)') . '<br/>';

	$E = new Expression();
	echo $E->evaluate('1 == 1').'<br/>';
	echo $E->evaluate('1 == 0').'<br/>';

	echo $E->evaluate('1+3').'<br/>';

	echo $E->evaluate('0x0f').'<br/>';

	$E->addUnit('px', 1);
	$E->addUnit('%', 1000/100);
	echo $E->evaluate('1%+3px').'<br/>';

	$P = new PercentageUnit(200);
	$E->addUnit('%', $P);
	echo $E->evaluate('50%').'<br/>';
	$P->set(1000);
	echo $E->evaluate('50%').'<br/>';
	$E->addUnit('vw', $P);
	echo $E->evaluate('50vw').'<br/>';

	function double($val) {	return $val * 2; }
	$E->addFunction('double');
	echo $E->evaluate('double(5)').'<br/>';

	echo $E->evaluate('min(1,2)').'<br/>';
	$E->removeFunction('min');
	try {
		echo $E->evaluate('min(1,2)').'<br/>';
	} catch(Exception $e) {
		echo $e->getMessage().' (okay)<br/>';
	}
	$E->resetFunctions();
	echo $E->evaluate('min(1,2)').'<br/>';

	// Decibel unit
	$E->addUnit('dB', new DecibelUnit);
	echo $E->evaluate('0dB').'<br/>';


	class FunctionCarrier {
		public static function half($v) {
			return $v * .5;
		}
	}

	try {
		echo $E->evaluate('FunctionCarrier::half').'<br/>';
	} catch(Exception $e) {
		echo $e->getMessage().' (okay)<br/>';
	}

	$E->addFunction('fc_half', 'FunctionCarrier::half');
	echo $E->evaluate('fc_half(9)').'<br/>';