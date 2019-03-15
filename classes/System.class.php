<?php
class System {
	public static $aGetParams = [];
	
	private static $aIPs = [
		'127.0.0.0/24',
		'192.168.0.0/24',
	];
	
	//public static function isLocalIP($sIP = NULL) {
	//	$sIP = $sIP === NULL ? $_SERVER['REMOTE_ADDR'] : $sIP;
	//	return checkIP($sIP, self::$aIPs);
	//}
	//
	//public static function isLoggedIn() {
	//	return Access::isLoggedIn();
	//}
	
	public static function getHMACKey() {
		return 'a098vdvmau-a8mask,jkva0vw9v929v85.8888883orjwlklekj';
	}
	
	public static function genHMAC($aData) {
		if(empty($aData)) throw new ExInvalidParam();
		foreach ($aData as $k => $value) $aData[$k] = (string)$value;
		ksort($aData);
		return hash_hmac('sha1', serialize($aData), self::getHMACKey());
	}
	
	public static function addHMAC(&$aData) {
		$aData['_hmac'] = self::genHMAC($aData);
	}
	
	public static function verifyHMAC($aData, $aKeysToIgnore = []) {
		foreach ($aKeysToIgnore as $sKey) unset($aData[$sKey]);
		$sHMAC = $aData['_hmac'];
		unset($aData['_hmac']);
		return $sHMAC === self::genHMAC($aData);
	}
	
	public static function createProtectedURLParams($aParams) {
		$aParams['_key'] = self::genHMAC($aParams);
		return http_build_query($aParams, '', '&');
	}
	
	public static function checkProtectedURLParams($aParams, $aKeysToIgnore = []) {
		$aParams_ = $aParams;
		$aKeysToIgnore_ = $aKeysToIgnore;
		
		foreach ($aKeysToIgnore as $sKey) unset($aParams[$sKey]);
		$sHashKey = $aParams['_key'];
		unset($aParams['_key']);
		
		return (self::genHMAC($aParams) === $sHashKey ? true : self::checkProtectedURLParams_old($aParams_, $aKeysToIgnore_));
	}
	
	private static function checkProtectedURLParams_old($aParams, $aKeysToIgnore = []) {
		foreach ($aKeysToIgnore as $sKey) unset($aParams[$sKey]);
		$sHashKey = $aParams['_key'];
		unset($aParams['_key']);
		ksort($aParams);
		return sha1(self::getHMACKey() . serialize($aParams)) === $sHashKey;
	}
	
	public static function getTempDir() {
		$sDir = sys_get_temp_dir();
		if(substr($sDir, -1, 1) != '\\' && substr($sDir, -1, 1) != '/') {
			$sDir .= '/';
		}
		return $sDir;
	}
}
