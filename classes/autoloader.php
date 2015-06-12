<?php

function Expression_autoloader($class) {
	if (is_file(dirname(__FILE__). '/' . $class . '.php')) {
		require dirname(__FILE__). '/' . $class . '.php';
	}
}

spl_autoload_register('Expression_autoloader');