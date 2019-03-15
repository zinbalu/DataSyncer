<?php
class Login {
	public static function check() {
		if(empty($_SESSION['userdata']['id'])) {
			if(isset($_GET['session_expired'])) $sURL = BASE_URL."/login.php?session_expired";
			else {
				$sTargetURL = self::getURL();
				
				if(!empty($_SERVER['HTTP_REFERER'])) $sTargetURL .= (strpos($sTargetURL, '?') === false ? '?' : '&').'referer='.urlencode($_SERVER['HTTP_REFERER']);
				
				$sURL = BASE_URL."/login.php?redirect_to=".urlencode($sTargetURL);
				if(isset($_GET['mediator'])) $sURL.="&mediator=".$_GET['mediator'];
				if(isset($_GET['target'])) 	 $sURL.="&target=".$_GET['target'];
			}
			
			if($_SERVER["HTTPS"] == "on") $sURL = str_replace('http://', 'https://', $sURL);
			header("Location: {$sURL}");
			die();
		}
	}
	
	public static function getURL() {
		//$sPageURL = 'http';
		//if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") $sPageURL .= "s";
		//$sPageURL .= "://";
		$sPageURL = 'http'.((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? "s" : "")."://";
		
		if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") $sPageURL .= $_SERVER["HTTP_HOST"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		else $sPageURL .= $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		
		return $sPageURL;
	}
}