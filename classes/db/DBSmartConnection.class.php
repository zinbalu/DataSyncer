<?php
class DBSmartConnection {
	private $sDSN;
	private $aDSN;
	
	/**
	 * @var \ADOConnection
	 */
	private $oDB = null;
	private $sDBName = null;
	private $sHost = null;
	private $sTimeZoneOffset = '';
	private $bForceSeparateConnection;
	
	/**
	 * @var ExException
	 */
	private $connError = null;
	private $bFailedTrans = false;
	private $nTransCount = 0;
	//public $transOff = 0;//unused
	//public $transCnt = 0;//unused
	
	
	public function __construct($sDSN, $bForceSeparateConnection = false) {
		if($sDSN !== null) {
			$sDSN = str_replace('memcache', 'b', $sDSN);
			$sDSN = str_replace('mysqlt', 'mysqli', $sDSN);
			$this->sDSN = $sDSN;
			$aMatches = [];
			$this->aDSN = parse_url($this->sDSN);
			$this->sHost = $this->aDSN['host'];
			if(!empty($aDSN['port'])) $this->sHost .= ':'.$this->aDSN['port'];
			preg_match("/.*\\/([a-zA-Z\\d_-]*).*/", $this->aDSN['path'], $aMatches);
			$this->sDBName = $aMatches[1];
		}
		$this->bForceSeparateConnection = $bForceSeparateConnection;
	}
	
	/**
	 * @throws ExDBError
	 * @throws ExException
	 */
	private function connect() {
		if($this->oDB) return;
		//$that = $this->getSameServerConenction();
		//if($that !== $this) {
		//	$that->connect();
		//	$this->oDB = $that->oDB;
		//	return;
		//}
		if($this->connError) throw $this->connError;
		try {
			if(!empty($this->sDSN)) {
				
				ini_set('mysql.connect_timeout', 4);
				
				$this->oDB = ADONewConnection($this->sDSN.(strpos($this->sDSN,'?')!==false ? '&' : '?').'new=1');
				
				$this->oDB->SetFetchMode(ADODB_FETCH_ASSOC);
				
				
				if($this->oDB->_isPersistentConnection) {
					$this->resetTrans();
				}
				
				$this->oDB->Execute('SET NAMES utf8');
				
				$this->oDB->Execute('SET SESSION group_concat_max_len = 400000');
				$this->oDB->Execute('SET SESSION innodb_table_locks = FALSE');
				$this->oDB->Execute('SET SESSION sql_mode = FALSE');
				
				if(!empty($this->sTimeZoneOffset)) {
					$this->oDB->Execute("SET time_zone = '{$this->sTimeZoneOffset}'");
				}
				
			} else {
				throw new ExDBError('DSN is empty.');
			}
		} catch (ExDBError $e) {
			$this->connError = $e;
			throw $this->connError;
		}
	}
	
	public function getHost() {
		return $this->sHost;
	}
	
	public function resetTrans() {
		if($this->isConnected()) $this->_resetTrans();
	}
	private function _resetTrans() {
		$this->_FailTrans();
		while($this->nTransCount) $this->_CompleteTrans();
	}
	
	public function isConnected() {
		return !empty($this->oDB) && $this->oDB->_connectionID;
	}
	
	public function setTimeZone($sZoneOffset) {
		$this->sTimeZoneOffset = $sZoneOffset;
		if($this->oDB) {
			$this->oDB->Execute("SET time_zone = '{$this->sTimeZoneOffset}'");
		}
	}
	
	public function setDBName($sDBName) {
		$this->sDBName = $sDBName;
	}
	
	private $queryPlaceholderRegex = "/<<[a-z_]+>>/i";
	private function replaceQueryPlaceholders($aMatches) {
		$sMatch = trim(strtolower($aMatches[0]),'<>');
		/*if($sMatch == 'field_suffix' || $sMatch == 'field_sufix') {
			return Language::getLangSuffix();
		} else*/ if(!empty(DBC::$$sMatch)) {
			return DBC::$$sMatch->database;
		} else {
			return $aMatches[0];
		}
	}
	
	/**
	 * @param string $sQuery
	 * @param int    $nAssocDepth
	 *
	 * @return array
	 * @throws ExDBError
	 * @throws ExException
	 */
	public function select($sQuery, $nAssocDepth = 0 ) {
		if(empty($sQuery)) throw new ExDBError("Query is empty.");
		$sQuery = preg_replace_callback($this->queryPlaceholderRegex,array($this,'replaceQueryPlaceholders'),$sQuery);
		$oRs = $this->Execute($sQuery);
		$aResult = $oRs instanceof \ADORecordSet_empty ? array() : $oRs->GetArray();
		$aResult = self::array_assocify($aResult, $nAssocDepth);
		
		return $aResult;
	}
	
	/**
	 * @param string $sQuery
	 *
	 * @return array
	 * @throws ExDBError
	 * @throws ExException
	 */
	public function selectAssoc($sQuery) {
		if(empty($sQuery)) throw new ExDBError("Query is empty.");
		$sQuery = preg_replace_callback($this->queryPlaceholderRegex,array($this,'replaceQueryPlaceholders'),$sQuery);
		$oRs = $this->Execute($sQuery);
		$aResult = $oRs instanceof \ADORecordSet_empty ? array() : $oRs->GetAssoc();
		
		return $aResult;
	}
	
	private static function array_assocify(&$aData, $nDepth) {
		if(empty($aData) || $nDepth <= 0) return $aData;
		$sFirstKey = reset(array_keys(reset($aData)));
		if(empty($sFirstKey)) return $aData;
		$aResult = array();
		foreach($aData as $aRow) {
			$sKey = $aRow[$sFirstKey];
			unset($aRow[$sFirstKey]);
			if($nDepth > 1) {
				if(!array_key_exists($sKey,$aResult)) $aResult[$sKey] = array();
				$aResult[$sKey][] = $aRow;
			} else {
				$aResult[$sKey] = $aRow;
			}
		}
		if($nDepth > 1) foreach($aResult as $k => $aRow) {
			$aResult[$k] = self::array_assocify($aRow,$nDepth - 1);
		}
		return $aResult;
	}
	
	/**
	 * @param string $sQuery
	 * @param int    $nCacheSecs
	 *
	 * @return array
	 * @throws ExDBError
	 * @throws ExException
	 */
	public function selectRow($sQuery, $nCacheSecs = 0) {
		$res = $this->select($sQuery,$nCacheSecs);
		return empty($res) ? array() : reset($res);
	}
	
	public function selectColumn($sQuery, $sColumn = null, $nCacheSecs = 0) {
		$aQueryResult = $this->select($sQuery, $nCacheSecs);
		$aResult = array();
		foreach($aQueryResult as $aRow) {
			$aResult[] = empty($sColumn) ? reset($aRow) : $aRow[$sColumn];
		}
		return $aResult;
	}
	
	public function selectField($sQuery, $sColumn = null, $nCacheSecs = 0) {
		$aColumn = $this->selectColumn($sQuery, $sColumn, $nCacheSecs);
		return reset($aColumn);
	}
	
	public function Affected_Rows() {
		$this->connect();
		return $this->oDB->Affected_Rows();
	}
	
	public function Insert_ID() {
		return $this->isConnected() ? $this->selectField('SELECT LAST_INSERT_ID()') : null;
	}
	
	public function multiInsert($sTableName, $aData, $sMode = 'update', $bUpdateFlag = true) {
		if(empty($aData)) return 0;
		if( strpos($sTableName, '.') === FALSE )
			$sTableName = $this->database.'.'.$sTableName;
		
		$this->connect();
		
		$aFields = $this->getTableFields($sTableName);
		
		$aDataByColumns = array();
		
		if($sMode == 'update') {
			foreach($aData as $aRow) {
				$aColumnNames = array();
				foreach($aRow as $sColName => $mVal) {
					$sColNameLower = strtolower($sColName);
					if(empty($aFields[$sColNameLower]) || ($mVal === null && !$aFields[$sColNameLower]['allow_null'])) continue;
					$aColumnNames[] = $sColNameLower;
				}
				sort($aColumnNames);
				$sColumnNamesKey = implode(':',$aColumnNames);
				if(!array_key_exists($sColumnNamesKey, $aDataByColumns)) $aDataByColumns[$sColumnNamesKey] = array();
				$aDataByColumns[$sColumnNamesKey][] = $aRow;
			}
		} else {
			$aDataByColumns['all'] = $aData;
		}
		
		$nAffected = 0;
		foreach($aDataByColumns as $aDataUnchunked) foreach(array_chunk($aDataUnchunked,1000) as $aData) {
			if($bUpdateFlag) {
				$sTime = date('Y-m-d H:i:s');
				$nUser = intval($_SESSION['userdata']['id']) ? intval($_SESSION['userdata']['id']) : (defined('IS_ROBOT') && IS_ROBOT ? 1 : 0);
				foreach($aData as $k => $aRow) {
					$aData[$k]['created_time'] = $aData[$k]['updated_time'] = $sTime;
					$aData[$k]['created_user'] = $aData[$k]['updated_user'] = $nUser;
				}
			}
			$aInsertFieldNamesQuoted = array();
			$aRowsQuoted = array();
			foreach($aData as $aRow) {
				$aRowValuesQuoted = array();
				foreach($aRow as $sFieldName => $mValue) {
					$sFieldNameLower = strtolower($sFieldName);
					if(empty($aFields[$sFieldNameLower])) continue;
					if(empty($aInsertFieldNamesQuoted[$sFieldNameLower])) $aInsertFieldNamesQuoted[$sFieldNameLower] = '`'.$aFields[$sFieldNameLower]['name'].'`';
					if($mValue === NULL) {
						$aRowValuesQuoted[$sFieldNameLower] = NULL;
					} else {
						if(!is_scalar($mValue)) continue;
						switch($aFields[$sFieldNameLower]['type']) {
							case 'tinyint':
							case 'bit':
							case 'smallint':
							case 'mediumint':
							case 'bigint':
							case 'int':
							case 'float':
							case 'double':
							case 'decimal':
								if(is_numeric($mValue)) $aRowValuesQuoted[$sFieldNameLower] = $mValue;
								else if(is_bool($mValue)) $aRowValuesQuoted[$sFieldNameLower] = $mValue ? 1 : 0;
								else $aRowValuesQuoted[$sFieldNameLower] = $this->oDB->Quote($mValue);
								break;
							
							case 'set':
							case 'enum':
							case 'char':
							case 'varchar':
							case 'tinytext':
							case 'text':
							case 'mediumtext':
							case 'longtext':
							case 'year':
								$aRowValuesQuoted[$sFieldNameLower] = $this->oDB->Quote($mValue);
								break;
							
							case 'date':
								$aRowValuesQuoted[$sFieldNameLower] = $this->oDB->Quote( is_numeric($mValue) ? date("Y-m-d",$mValue) : $mValue);
								break;
							
							case 'time':
								$aRowValuesQuoted[$sFieldNameLower] = $this->oDB->Quote( is_numeric($mValue) ? date("H:i:s",$mValue) : $mValue);
								break;
							
							case 'datetime':
							case 'timestamp':
								$aRowValuesQuoted[$sFieldNameLower] = $this->oDB->Quote( is_numeric($mValue) ? date("Y-m-d H:i:s",$mValue) : $mValue);
								break;
							
							case 'tinyblob':
							case 'blob':
							case 'mediumblob' :
							case 'longblob':
							case 'binary' :
							case 'varbinary':
								$aRowValuesQuoted[$sFieldNameLower] = "UNHEX('".bin2hex($mValue)."')";
								break;
							
							default: throw new ExInvalidParam(sprintf("Unknown field type (%s).", $aFields[$sFieldNameLower]['type']));
						}
					}
				}
				$aRowsQuoted[] = $aRowValuesQuoted;
			}
			$aSQLRows = array();
			foreach($aRowsQuoted as $aRowQuoted) {
				if(empty($aRowQuoted)) continue;
				$aTmpRow = array();
				foreach($aInsertFieldNamesQuoted as $sFieldNameLower => $sSQLFieldName) {
					if(!array_key_exists($sFieldNameLower, $aRowQuoted)) {
						$aTmpRow[] = 'default';
					}
					elseif($aRowQuoted[$sFieldNameLower] === NULL) {
						$aTmpRow[] = $aFields[$sFieldNameLower]['allow_null'] ? 'null' : 'default';
					} else {
						$aTmpRow[] = $aRowQuoted[$sFieldNameLower];
					}
				}
				if(empty($aTmpRow)) continue;
				$aSQLRows[] = '('.implode(',',$aTmpRow).')';
			}
			
			if(empty($aSQLRows)) return 0;
			
			if($sMode == 'replace')  $sQuery = "REPLACE INTO";
			elseif($sMode == 'ignore') $sQuery = "INSERT IGNORE INTO";
			elseif($sMode == 'delayed') $sQuery = "INSERT DELAYED INTO";
			else $sQuery = "INSERT INTO";
			
			$sQuery .= " ".$sTableName." ";
			$sQuery .= " (".implode(',',$aInsertFieldNamesQuoted).") VALUES \n";
			$sQuery .= implode(",\n",$aSQLRows) ."\n";
			
			if($sMode == 'update') {
				$aUpdateFields = array();
				foreach($aInsertFieldNamesQuoted as $sFieldNameLower => $sFieldName) {
					if($bUpdateFlag && ($sFieldNameLower == 'created_time' || $sFieldNameLower == 'created_user')) continue;
					$aUpdateFields[] = "$sFieldName = VALUES($sFieldName)";
				}
				$sQuery .= "ON DUPLICATE KEY UPDATE ".implode(',', $aUpdateFields)." \n";
			}
			
			$this->Execute($sQuery);
			$nAffected += $this->oDB->Affected_Rows();
		}
		return $nAffected;
	}
	
	private $aTableFieldsCache = array();
	public function getTableFields($sTableName) {
		$this->connect();
		if(!preg_match("/^[a-zA-Z\\d_\\.]+$/", $sTableName)) throw new ExInvalidParam(L("��������� ��� �� �������."));
		if(empty($this->aTableFieldsCache[$sTableName])) {
			$cacheKey = $this->getHost().'-'.$sTableName;
			$aFields = $this->cacheGet(__METHOD__,$cacheKey);
			if(!$aFields) {
				$aSQLFields = $this->select("SHOW FIELDS FROM $sTableName");
				$aFields = array();
				foreach($aSQLFields as $aSQLField) {
					$aField = array();
					$aMatches = array();
					preg_match("/^([a-zA-Z]+).*/", $aSQLField['Type'], $aMatches);
					$aField['type'] = strtolower($aMatches[1]);
					$aField['allow_null'] = $aSQLField['Null'] == 'YES';
					$aField['has_default'] = $aSQLField['Default'] === NULL;
					$aField['name'] = $aSQLField['Field'];
					$aFields[strtolower($aField['name'])] = $aField;
				}
				$this->cacheSet(__METHOD__,$cacheKey,$aFields,600);
			}
			$this->aTableFieldsCache[$sTableName] = $aFields;
		}
		
		return $this->aTableFieldsCache[$sTableName];
	}
	
	private $aTableEngineCache = array();
	public function getTableEngine($sTableName, $sDBName = null) {
		if(empty($sTableName)) return false;
		if(empty($sDBName)) $sDBName = $this->database;
		if(empty($this->aTableEngineCache[$sDBName.'.'.$sTableName])) {
			$this->aTableEngineCache[$sDBName.'.'.$sTableName] = $this->selectField("SELECT ENGINE FROM information_schema.TABLES where TABLE_SCHEMA = '{$sDBName}' AND TABLE_NAME='{$sTableName}'");
		}
		return $this->aTableEngineCache[$sDBName.'.'.$sTableName];
	}
	
	private function addDebugInfoToQuery($sQuery) {
		$sApi = '';
		if(!empty($_REQUEST['action_script'])) {
			$sApi = preg_replace("/[^a-zA-Z0-9._\\-]/",'',substr(substr($_REQUEST['action_script'],4),0,-4)) .'.'. preg_replace("/[^a-zA-Z0-9._\\-]/",'',$_REQUEST['api_action']);
		}
		$sApi = substr($sApi,0,200);
		$nSessionID = !empty($_SESSION['userdata']['id']) ? $_SESSION['userdata']['id'] : 0;
		$sQuery = '#'.$sApi.' ('.intval($nSessionID).") \n".$sQuery."\n";
		
		$sQuery.="#time:".date('Y-m-d H:i:s')."\n";
		if(defined('DEBUG_BACKTRACE_IGNORE_ARGS'))
			foreach(array_slice(@debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),1) as $aTraceItem) {
				if(!empty($aTraceItem['file'])) {
					$sQuery.="#".preg_replace("/[^a-zA-Z0-9.\\-_\\/\\\\]/",'',$aTraceItem['file']).':'.$aTraceItem['line']."\n";
				}
			}
		
		return $sQuery;
	}
	
	public function escape($sValue) {
		$this->connect();
		if($this->oDB->databaseType == 'mysqli') return mysqli_escape_string($this->oDB->_connectionID,$sValue);
		else return mysql_real_escape_string($sValue, $this->oDB->_connectionID );
	}
	
	public function quote($sValue) {
		return "'".$this->escape($sValue)."'";
	}
	
	/**
	 * @param string $sql      SQL statement to execute, or possibly an array holding prepared statement ($sql[0] will hold sql text)
	 * @param bool   $inputarr holds the input data to bind to. Null elements will be set to null.
	 *
	 * @return \ADORecordSet
	 * @throws ExDBError
	 * @throws ExException
	 */
	public function Execute($sql, $inputarr=false){
		$this->connect();
		if(!$this->oDB->SelectDB($this->database)) throw new ExDBError();
		$sql = $this->addDebugInfoToQuery($sql);
		$r = $this->oDB->Execute($sql, $inputarr);
		return $r;
	}
	
	/**
	 * @param int  $secs2cache seconds to cache data, set to 0 to force query.
	 * @param bool $sql        SQL statement to execute
	 * @param bool $inputarr   holds the input data  to bind to
	 *
	 * @return RecordSet
	 * @throws ExDBError
	 * @throws ExException
	 */
	public function CacheExecute($secs2cache,$sql=false,$inputarr=false) {
		$this->connect();
		if(!$this->oDB->SelectDB($this->database)) throw new ExDBError();
		$sql = $this->addDebugInfoToQuery($sql);
		return $this->oDB->CacheExecute($secs2cache, $sql, $inputarr);
	}
	
	public function GetCol($sql, $inputarr = false, $trim = false) {
		$this->connect();
		if(!$this->oDB->SelectDB($this->database)) throw new ExDBError();
		$sql = $this->addDebugInfoToQuery($sql);
		return $this->oDB->GetCol($sql, $inputarr, $trim);
	}
	
	public function CacheGetCol($secs, $sql = false, $inputarr = false,$trim=false) {
		$this->connect();
		if(!$this->oDB->SelectDB($this->database)) throw new ExDBError();
		$sql = $this->addDebugInfoToQuery($sql);
		return $this->oDB->CacheGetCol($secs, $sql, $inputarr, $trim);
	}
	
	public function StartTrans() {
		$this->connect();
		return $this->getSameServerConenction()->_StartTrans();
	}
	public function CompleteTrans() {
		$this->connect();
		return $this->getSameServerConenction()->_CompleteTrans();
	}
	public function FailTrans() {
		$this->connect();
		return $this->getSameServerConenction()->_FailTrans();
	}
	public function RollbackTrans() {
		$this->connect();
		return $this->getSameServerConenction()->_RollbackTrans();
	}
	public function CommitTrans() {}
	
	public function withDeadlockRetrying($func, $maxTime = 3200000, $retryDelay = 100000) {
		$this->connect();
		$rc = $this->getSameServerConenction();
		if($rc->nTransCount) throw new ExDBError("Deadlock retrying can't be used with nested transactions");
		
		retry:try {
			return $rc->_withTrans($func, array());
		} catch (ExDBSQLError $e) {
			if(strpos($e->getSQLError(),'try restarting transaction') !== false && $retryDelay <= $maxTime) {
				usleep($retryDelay);
				$retryDelay *= 2;
				goto retry;
			}
		}
		throw $e;
	}
	
	/**
	 * @param callable 	$func
	 * @param array 	$args
	 *
	 * @return mixed
	 * @throws ExDBError
	 * @throws ExException
	 * @throws \Exception
	 */
	public function withTrans($func, $args = array()) {
		$this->connect();
		return $this->getSameServerConenction()->_withTrans($func,$args);
	}
	
	private function _withTrans($func,$args) {
		if(!is_callable($func)) throw new ExException('$func must be a callable.');
		$this->StartTrans();
		$c = $this->nTransCount;
		try {
			$r = call_user_func_array($func,$args);
			$this->_CompleteTrans();
			return $r;
		} catch (\Exception $e) {
			while($c <= $this->nTransCount) $this->_RollbackTrans();
			throw $e;
		}
	}
	
	private function _StartTrans() {
		$this->nTransCount++;
		try {
			if($this->nTransCount == 1) {
				$this->Execute("SET autocommit=0");
			}
			$this->Execute("SAVEPOINT t".($this->nTransCount));
		} catch (\Exception $e) {
			$this->bFailedTrans = true;
			throw $e;
		}
		return true;
	}
	
	private function _CompleteTrans() {
		if(!$this->nTransCount) return true;
		$this->nTransCount--;
		if(!$this->nTransCount) {
			if($this->bFailedTrans) {
				$this->Execute("ROLLBACK");
			} else {
				$this->Execute("COMMIT");
			}
			$this->Execute("SET autocommit = 1");
			$this->bFailedTrans = false;
		}
		$this->_mergeCache($this->nTransCount);
		return true;
	}
	
	private function _FailTrans() {
		if($this->nTransCount) $this->bFailedTrans = true;
		return true;
	}
	
	private function _RollbackTrans() {
		if(!$this->nTransCount) return true;
		if($this->nTransCount == 1) {
			$this->bFailedTrans = true;
			$this->_CompleteTrans();
		} else {
			unset($this->aCache[$this->nTransCount]);//cache schemi
			$this->nTransCount--;
			try {
				$this->Execute("ROLLBACK TO SAVEPOINT t".($this->nTransCount + 1));
				$this->Execute("RELEASE SAVEPOINT t".($this->nTransCount + 1));
			} catch (\Exception $e) {
				$this->bFailedTrans = true;
				throw $e;
			}
		}
		return true;
	}
	
	public function __get( $sVarName ) {
		switch ($sVarName) {
			case 'database':
				return $this->sDBName;
				break;
			default:
				$this->connect();
				return $this->oDB->$sVarName;
		}
	}
	
	public function __set($sVarName, $mValue) {
		switch ($sVarName) {
			case 'database':
				return $this->sDBName;
				break;
			default:
				$this->connect();
				return $this->oDB->$sVarName = $mValue;
		}
	}
	
	public function __call($sMethodName, $aArguments) {
		$this->connect();
		if(!$this->oDB->SelectDB($this->database)) throw new ExDBError();
		return call_user_func_array(array($this->oDB, $sMethodName), $aArguments);
	}
}