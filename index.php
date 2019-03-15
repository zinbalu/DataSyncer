<?php
try {
    require_once('./config/setup.php');
    //Login::check();
    
    if(!empty($_GET)){
        $_SESSION['_tmp_get_params'] = $_GET;
        header('location: '.$_SERVER['PHP_SELF']);
        die();
    }
    
    if(!empty($_SESSION['_tmp_get_params'])){
        System::$aGetParams = $_SESSION['_tmp_get_params'];
        unset($_SESSION['_tmp_get_params']);
    }
    
    if(!empty($_SERVER['HTTP_REFERER']) && empty(System::$aGetParams['referer'])){
        System::$aGetParams['referer'] = $_SERVER['HTTP_REFERER'];
    }
    
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tauri API Client</title>

    <link type="text/css" href="css/main.css?r=<?=REVISION?>" rel="stylesheet"/>

    <script type="text/javascript">
        REVISION = <?=REVISION?>;
        BASE_URL = "<?=BASE_URL?>";
        //LANG = '<?//=Language::getCurrentLanguage()?>//';
        //IS_LOGGED_IN = <?//=System::isLoggedIn() ? 'true' : 'false'?>//;
    </script>

	<?php } catch (ExException $e) {
		Logs::logException($e);
		die($e->getMessage());
	} ?>
    <style>
    </style>
</head>
<body>
<div id="main-div" class="main-content">
	<?php
	try {
	    if(file_exists(BASE_PATH.'/pages/'.System::$aGetParams['target'].'.php')) require_once(BASE_PATH.'/pages/'.System::$aGetParams['target'].'.php');
		else switch(System::$aGetParams['target']){
            case 'google': header('location: http://google.com/');
            default: require_once(BASE_PATH.'/pages/homepage.php');
        }
	} catch (ExException $e) {
		Logs::logException($e);
		echo $e->getMessage();
	}
	?>
</div>
</body>
</html>