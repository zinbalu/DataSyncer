<?php

#use namespace? as something;

define('BASE_PATH', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.BASE_PATH.'/');
mb_internal_encoding("UTF-8");

require_once('config/config.php');

if(!defined('BASE_URL')){
	
	$sURL = 'http';
	if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") $sURL .= "s";
	
	$sURL .= "://";
	if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") $sURL .= $_SERVER["HTTP_HOST"].":".$_SERVER["SERVER_PORT"];
	else $sURL .= $_SERVER["HTTP_HOST"];
	
	$sURL .= dirname($_SERVER['PHP_SELF']) == '.' ? '' : dirname($_SERVER['PHP_SELF']);
	$sURL = rtrim($sURL, '\\/');
	define('BASE_URL', $sURL);
}

class Autoloader {
	public static $aPaths = [];
	
	public static function autoload($sClassName){
		if(strpos($sClassName, 'tauri\\classes\\') === 0){
			$sClassName = substr($sClassName, 14);
			$aPaths = self::$aPaths['tauri\\classes'];
		} else $aPaths = self::$aPaths[''];
		
		if(!preg_match("/^[a-z0-9_]+$/i", $sClassName)) return false;
		
		foreach($aPaths as $sPath){
			if(file_exists($sPath.'/'.$sClassName.'.class.php')){
				require_once($sPath.'/'.$sClassName.'.class.php');
				return true;
			}
		}
		
		//if(strpos($sClassName, 'Swift_') === 0){
		//	require_once BASE_PATH.'/include/lib/swift/swift_required.php';
		//
		//	return true;
		//}
		
		return false;
	}
}

Autoloader::$aPaths = [
	'' => [
		//BASE_PATH.'/api',
		//BASE_PATH.'/templates',
		//BASE_PATH.'/include',
		BASE_PATH.'',
	],
	'tauri\\classes' => [
		BASE_PATH.'/classes',
		//BASE_PATH.'/classes/exceptions',
		//BASE_PATH.'/classes/db_include',
		//BASE_PATH.'/classes/daemons',
		//BASE_PATH.'/classes/SOAP',
	]
];
spl_autoload_register(array('Autoloader', 'autoload'));
set_exception_handler('tauri\classes\Logs::logUncaughtException');

$_SERVER['SCRIPT_NAME_DIR'] = dirname($_SERVER['SCRIPT_NAME']);
if(strlen($_SERVER['SCRIPT_NAME_DIR']) !== 1) $_SERVER['REQUEST_PATH'] = reset(explode('?', str_replace($_SERVER['SCRIPT_NAME_DIR'], '', $_SERVER['REQUEST_URI'])));
else $_SERVER['REQUEST_PATH'] = $_SERVER['REQUEST_URI'];
unset($_SERVER['SCRIPT_NAME_DIR']);

//require_once(BASE_PATH."/include/classes/db_include/db_include.inc.php");

