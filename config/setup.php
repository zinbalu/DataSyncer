<?php
//config
define('BASE_PATH', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.BASE_PATH.'/');
mb_internal_encoding("UTF-8");

require_once(BASE_PATH.'/config/config.php');

class Autoloader {
	public static $aPaths = [
		'' => [
			BASE_PATH.'/classes',
			BASE_PATH.'/classes/db',
			BASE_PATH.'/classes/exceptions',
			BASE_PATH.'/classes/daemons',
		]
	];
	
	public static function autoload($sClassName){
		if(!preg_match("/^[a-z0-9_]+$/i", $sClassName)) return false;
		
		foreach(self::$aPaths[''] as $sPath){
			if(file_exists($sPath.'/'.$sClassName.'.class.php')){
				require_once($sPath.'/'.$sClassName.'.class.php');
				return true;
			}
		}
		
		return false;
	}
}
spl_autoload_register(['Autoloader', 'autoload']);
set_exception_handler('Logs::logUncaughtException');

if(!defined('BASE_URL')){
	$sURL = Login::getURL();
	$sURL .= dirname($_SERVER['PHP_SELF']) == '.' ? '' : dirname($_SERVER['PHP_SELF']);
	$sURL = rtrim($sURL, '\\/');
	define('BASE_URL', $sURL);
}

$_SERVER['SCRIPT_NAME_DIR'] = dirname($_SERVER['SCRIPT_NAME']);
if(strlen($_SERVER['SCRIPT_NAME_DIR']) !== 1) $_SERVER['REQUEST_PATH'] = reset(explode('?', str_replace($_SERVER['SCRIPT_NAME_DIR'], '', $_SERVER['REQUEST_URI'])));
else $_SERVER['REQUEST_PATH'] = $_SERVER['REQUEST_URI'];
unset($_SERVER['SCRIPT_NAME_DIR']);

//connect
require_once(BASE_PATH."/classes/db/adodb/adodb-exceptions.inc.php");
require_once(BASE_PATH."/classes/db/adodb/adodb.inc.php");
define('CLIENT_MULTI_STATEMENTS', 0x00010000);
$ADODB_EXCEPTION = 'ExDBSQLError';
$ADODB_FORCE_TYPE = ADODB_FORCE_EMPTY;
$ADODB_QUOTE_FIELDNAMES = true;
if(defined('ADODB_CACHE_DIR')) $ADODB_CACHE_DIR = ADODB_CACHE_DIR;

DBCConnect::connect($sDBSystemDSN,'DSN');
register_shutdown_function(function() {
	DBC::rollbackTransactions();
});

//session
session_start();
