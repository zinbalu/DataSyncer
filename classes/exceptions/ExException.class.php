<?php
class ExException extends \Exception {
	private 	$additionalData 	= null;
	protected 	$sDefaultMessage 	= 'Error during operation';
	protected 	$nDefaultCode 		= 1;
	protected 	$sMessage 			= '';
	protected 	$aInnerExceptions 	= [];
	public 		$bIsLogError 		= false;
	public 		$nLogID				= null;
	
	public function __construct($sMessage = null, $aInnerExceptions = []) {
		$aTrace = $this->getTrace();
		$this->bIsLogError = (strpos($this->getFile(), 'Log') !== false);
		//$bIsTranslationError = (strpos($this->getFile(), 'Language') !== false);
		foreach($aTrace as $aTraceItem) {
			if(isset($aTraceItem['file']) && strpos($aTraceItem['file'], 'Logs') !== false) $this->bIsLogError = true;
			//if(isset($aTraceItem['file']) && strpos($aTraceItem['file'], 'Language') !== false) $bIsTranslationError = true;
		}
		
		//if($sMessage === null) {
		//	try {
		//		if(!$bIsTranslationError) $this->sMessage = Language::getTranslationForText($this->sDefaultMessage, 'default');
		//		else $this->sMessage = $this->sDefaultMessage;
		//	} catch (\Exception $e) {
		//		$this->sMessage = $this->sDefaultMessage;
		//	}
		//} else {
		//	$this->sMessage = $sMessage;
		//}
		
		$this->sMessage = $sMessage !== null ? $sMessage : $this->sDefaultMessage;
		
		$this->addInnerExceptions($aInnerExceptions);
		$nCode = $this->nDefaultCode;
		
		parent::__construct($this->sMessage, $nCode);
	}
	
	public function getType(){ return end(explode('\\', get_class($this))); }
	
	public function getLogObject() {
		$oObj = (object)get_object_vars($this);
		unset($oObj->aInnerExceptions);
		$aTrace = $this->getTrace();
		foreach ($aTrace as $k => $aItem) unset($aTrace[$k]['args']);
		
		$oObj->trace = $aTrace;
		$oObj->inner = [];
		foreach($this->aInnerExceptions as $e) $oObj->inner[] = $e->getLogObject();
		
		return $oObj;
	}
	
	public function getLogID() {return $this->nLogID; }
	
	/**
	 * @return ExException[]
	 */
	public function getInnerExceptions(){ return $this->aInnerExceptions; }
	
	public function setInnerExceptions(ExException... $aExceptions){
		if(!is_array($aExceptions)) return;
		foreach($aExceptions as $e) if(!($e instanceof ExException)) return;
		$this->aInnerExceptions = $aExceptions;
	}
	
	public function addInnerExceptions(ExException... $aExceptions){
		if(!is_array($aExceptions)) $aExceptions = array($aExceptions);
		foreach($aExceptions as $e) if(!($e instanceof ExException)) return;
		$this->aInnerExceptions = array_merge($this->aInnerExceptions, $aExceptions);
	}
	
	public function getMessages(){
		if(empty($this->aInnerExceptions)) return array($this->getMessage());
		
		$aMessages = [];
		foreach($this->aInnerExceptions as $e){
			$aInnerMessage = $e->getMessages();
			if(!empty($aInnerMessage)) $aMessages = array_merge($aMessages, $aInnerMessage);
		}
		
		return $aMessages;
	}
	
	/**
	 * @return ExException[]
	 */
	public function getAllInnerExceptions(){
		$aExceptions = [];
		foreach($this->aInnerExceptions as $e){
			$aInner = $e->getAllInnerExceptions();
			if(!empty($aInner)) $aExceptions = array_merge($aExceptions, $aInner);
			else $aExceptions = array_merge($aExceptions, [$e]);
		}
		
		return $aExceptions;
	}
	
	public function getJSObject(){
		$aJSObj = [
			'type'           => $this->getType(),
			'message'        => $this->getMessage(),
			'code'           => $this->getCode(),
			'file'           => $this->getFile(),
			'line'           => $this->getLine(),
			'log_id'         => $this->getLogID(),
			'inner'          => [],
			'additionalData' => $this->getAdditionalData(),
		];
		
		foreach($this->aInnerExceptions as $ex) $aJSObj['inner'][] = $ex->getJSObject();
		
		return trim($aJSObj['message']) == '' && empty($aJSObj['additionalData']) && count($aJSObj['inner']) == 1 ? reset($aJSObj['inner']) : $aJSObj;
	}
	
	public static function convertToJSObject(\Exception $e){
		if($e instanceof ExException) return $e->getJSObject();
		$aJSObj = array(
			'type'    => end(explode('\\', get_class($e))),
			'message' => $e->getMessage(),
			'code'    => $e->getCode(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
			'log_id'  => $e->{'logID'},
			'inner'   => [],
		);
		if(is_array($e->{'inner'})) foreach($e->{'inner'} as $ee) if($ee instanceof \Exception) $aJSObj['inner'][] = self::convertToJSObject($ee);
		
		return $aJSObj;
	}
	
	public function getAdditionalData(){ return $this->additionalData; }
	
	public function setAdditionalData($additionalData){ $this->additionalData = $additionalData; }
	
	public function getFullMessage(){
		$sMessage = trim($this->getMessage(), ' :');
		$aInnerMessages = [];
		foreach($this->getInnerExceptions() as $ex) if($ex instanceof ExException) $aInnerMessages[] = $ex->getFullMessage();
		if(!empty($sMessage) && !empty($aInnerMessages)) $sMessage .= ": ";
		
		return $sMessage.implode("; ", $aInnerMessages);
	}
	
	//public static function checkThrowErrors($aErrors, $sMessage = ' ', $field = array())
	//{
	//	if (!empty($aErrors)) throw new ExInvalidParam($sMessage, $field, $aErrors);
	//}
}




