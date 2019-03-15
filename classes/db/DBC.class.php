<?php
class DBC {
	
	/**
	 * Connection for `tauri` database
	 * @var DBSmartConnection
	 */
	public static $tauri;
	
	/**
	 * Connection for `system` database
	 * @var DBSmartConnection
	 */
	public static $system;
	
	/**
	 * Array with all connections
	 * @var DBSmartConnection[]
	 */
	protected static $aConnections = [];
	
	public static function setTimeZone($sZoneOffset) {
		foreach (self::$aConnections as $oConnection) {
			$oConnection->setTimeZone($sZoneOffset);
		}
	}
	
	public static function rollbackTransactions() {
		foreach(self::$aConnections as $oConnection) $oConnection->resetTrans();
	}
	
	public static function closeAllConnections() {
		foreach(self::$aConnections as $oConnection) if($oConnection->isConnected()) $oConnection->Close();
	}
	
}
foreach(array_keys(get_class_vars('DBC')) as $sVarName) DBC::$$sVarName = new DBSmartConnection($sGlobalDSN);
