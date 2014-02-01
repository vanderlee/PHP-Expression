<?php

require_once 'simpletest/autorun.php';
require_once 'Expression/autoloader.php';

class AllTests extends TestSuite {
	function __construct() {
		parent::__construct();
		$this->collect(dirname(__FILE__).'/tests', new SimplePatternCollector('~\.php$~'));
	}
}

// @todo hack-xss
// @todo hack-sql-injection
// @todo hack-system-execution
// @todo comparison
// @todo boolean
// @todo bitwise
// @todo catch exception messages