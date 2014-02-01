<?php

class ExpressionAutoloader {
	public function autoload($class) {
		if (is_file($file = dirname(__FILE__).'/'.preg_replace('~\W~', '', $class).'.class.php')) {
			require_once $file;
		}
	}
}

spl_autoload_register(array(new ExpressionAutoloader(), 'autoload'));