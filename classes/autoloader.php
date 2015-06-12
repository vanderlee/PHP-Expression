<?php

class ExpressionAutoloader {
	public function autoload($class) {
		if (is_file($file = __DIR__.'/'.$class.'.php')) {
			require_once $file;
		}
	}
}

spl_autoload_register(array(new ExpressionAutoloader(), 'autoload'));