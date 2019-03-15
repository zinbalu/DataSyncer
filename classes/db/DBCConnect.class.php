<?php
class DBCConnect extends DBC {
	public static function connect($sSystemDSN, $sType) {
		self::$system = new DBSmartConnection($sSystemDSN);
		$sEscapedType = self::$system->escape($sType);
		$oResult = self::$system->Execute("SELECT * FROM settings WHERE type = '$sEscapedType'");
		self::$aConnections['system'] = self::$system;
		$aRows = $oResult->GetArray();
		$aDSNRowsByName = array();
		foreach($aRows as $aRow) {
			if($aRow['name'] == 'system') continue;
			$aDSNRowsByName[$aRow['name']][] = $aRow;
		}
		
		foreach ($aDSNRowsByName as $sVarName => $aDSNRows) {
			if(isset(self::$$sVarName)) {
				$nTotalWeight = 0;
				foreach($aDSNRows as $aDSNRow) {
					if(is_numeric($aDSNRow['description'])) $nTotalWeight += abs(intval($aDSNRow['description']));
				}
				
				$sDSN = null;
				if($nTotalWeight == 0) {
					$aDSNRow = $aDSNRows[array_rand($aDSNRows)];
					$sDSN = $aDSNRow['value'];
				} else {
					$r = mt_rand(1,$nTotalWeight);
					$s = 0;
					foreach($aDSNRows as $aDSNRow) {
						$w = abs(intval($aDSNRow['description']));
						if(!$w) continue;
						$s += $w;
						if($s >= $r) {
							$sDSN = $aDSNRow['value'];
							break;
						}
					}
				}
				
				self::$$sVarName = new DBSmartConnection($sDSN);
				self::$aConnections[$sVarName] = self::$$sVarName;
			}
		}
	}
}
