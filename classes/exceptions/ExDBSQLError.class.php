<?php
class ExDBSQLError extends ExDBError {
	protected $dbms;
	protected $fn;
	protected $sQuery = '';
	protected $params = '';
	protected $host = '';
	protected $database = '';
	protected $sError = '';
	
	function __construct($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection){
		$sMessage = null;
		
		switch($fn){
			case 'EXECUTE':
				$this->sQuery = $p1;
				$this->params = $p2;
				break;
			case 'PCONNECT':
			case 'CONNECT':
			default:
				break;
		}
		
		$this->dbms = $dbms;
		if($thisConnection){
			$this->host = $thisConnection->host;
			$this->database = $thisConnection->database;
		}
		
		$this->sError = $errmsg;
		if($errno == 1644) $sMessage = L($errmsg);
		parent::__construct($sMessage);
	}
	
	public function getSQLError(){
		return $this->sError;
	}
}
