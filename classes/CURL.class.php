<?php
class CURL {
	public static function postRequest($sURL, $aPostData = [], $nTimeout = 4, &$aHeaders = [], &$nStatus = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 			 $sURL);
		curl_setopt($ch, CURLOPT_HEADER, 		 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, !empty($nTimeout) && intval($nTimeout) ? $nTimeout : 4);
		if(!empty($aPostData)) curl_setopt($ch, CURLOPT_POSTFIELDS,$aPostData);
		
		$oResponse = curl_exec($ch);
		
		if(!$oResponse) return $oResponse;
		
		$nHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$nStatus     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$aHeaders  	 = explode("\r\n", substr($oResponse, 0, $nHeaderSize));
		
		return substr($oResponse, $nHeaderSize);
	}
}