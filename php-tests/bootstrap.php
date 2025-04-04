<?php

if (@!include __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php') {
    echo 'Install Nette Tester using `composer update --dev`';
    exit(1);
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TestCase.php';

// configure environment
date_default_timezone_set('Europe/Prague');

// create temporary directory
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . (isset($_SERVER['argv']) ? md5(serialize($_SERVER['argv'])) : getmypid()));
Tester\Helpers::purge(TEMP_DIR);

$_SERVER = array_intersect_key($_SERVER, array_flip(['PHP_SELF', 'SCRIPT_NAME', 'SERVER_ADDR', 'SERVER_SOFTWARE', 'HTTP_HOST', 'DOCUMENT_ROOT', 'OS', 'argc', 'argv']));
$_SERVER['REQUEST_TIME'] = 1234567890;
$_ENV = $_GET = $_POST = [];

// !!! beware !!! PhpStan and Nette/Tester have problems with each other!
if (boolval(intval(strval(getenv('WITH_TEST_ENV'))))) {
    Tester\Environment::setup();
}
