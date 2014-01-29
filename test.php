<?php

require_once('simpletest/autorun.php');

function expression_autoload($classname) {
	include_once dirname(__FILE__) . '/Expression/'.preg_replace('~\W~', '', $classname).'.class.php';
}
spl_autoload_register('expression_autoload');

class AllTests extends TestSuite {
	function __construct() {
		parent::__construct();
		$this->collect(dirname(__FILE__).'/tests', new SimplePatternCollector('~\.php$~'));
	}
}

// @todo parenthesis
// @todo builtin functions
// @todo global functions
// @todo static class functions
// @todo hack-xss
// @todo hack-sql-injection
// @todo hack-php-execution
// @todo hack-system-execution
// @todo ...