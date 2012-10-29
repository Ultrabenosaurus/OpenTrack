<?php

/*
 Return the image by default so that, even if the script fails, the user isn't aware that
 anything has gone wrong (broken image symbol).
*/
header("Content-Type: image/png");
echo file_get_contents('img.png');

if((isset($_GET['campaign']) && !empty($_GET['campaign'])) && (isset($_GET['email']) && !empty($_GET['email']))){
	$email = $_GET['email'];
	$campaign = $_GET['campaign'];
	$db_addr = 'localhost';
	$db_user = 'username';
	$db_pass = 'password';
	$db_name = 'database';
	$db_tabl = 'table';

	/*
	 Attempt to use PHP's built-in browscap detection method to get information on the user's device
	 and client. However, not all hosts support browscap, and those that do may be using an outdated
	 version, so GaretJax's PHPBrowscap and bjankord's Categorizr classes are supported.
	 You can force the default browscap to be skipped by including &browscap at the end of the query
	 string in the image source attribute when using this script.
	 
	 PHPBrowscap - https://github.com/GaretJax/phpbrowscap
	 Categorizr - https://github.com/bjankord/Categorizr
	*/
	$browscap = isset($_GET['browscap']) ? $_GET['browscap'] : ini_get('browscap');
	if(!empty($browscap) && !is_null($browscap) && $browscap !== false){
		$agent = get_browser($_SERVER['HTTP_USER_AGENT'], true);
		foreach ($agent as $key => $value) {
			switch ($key) {
				case 'comment':
					$client = ($value == "Default Browser") ? NULL : ", '".$value."'";
					break;
				case 'platform':
					$platform = ($value == "unknown") ? NULL : ", '".$value."'";
					break;
				case 'platform_description':
					$platform = ($value == "unknown") ? NULL : ", '".$value."'";
					break;
			}
		}
	} else {
		if(file_exists('Browscap.php')){
			$cache_dir = 'phpbc_cache';
			if(!is_dir($cache_dir)){
				mkdir($cache_dir);
			}
			@include 'Browscap.php';
			$bc = new Browscap('phpbc_cache');
			$browser = $bc->getBrowser();
			if(isset($browser->Comment)){
				$client = ($browser->Comment == "Default Browser") ? NULL : ", '".$browser->Comment."'";
			}
			if(isset($browser->Parent)){
				$client = ($browser->Parent == "Default Browser") ? NULL : ", '".$browser->Parent."'";
			}
			if(isset($browser->Platform)){
				$platform = ($browser->Platform == "Default Browser") ? NULL : ", '".$browser->Platform."'";
			}
			$agent = "phpbc";
			$skip = true;
		}
		if(file_exists('categorizr.php') && !isset($skip)){
			@include("categorizr.php");
			$device = categorizr();
			$platform = ", '".$device."'";
			$agent = "categorizr";
		}
	}

	/*
	 Connect to the database (switch the user/pass info above depending on whether you're testing
	 or live) and dump the data into the database.
	 If you add/change any browser detection methods set $agent to a unique string for all except
	 browscap so that it will always be the default.
	*/
	$db = mysql_connect($db_addr, $db_user, $db_pass);
	mysql_select_db($db_name, $db);
	if(isset($agent)){
		if($agent == 'categorizr'){
			$results = mysql_query("INSERT INTO `".$db_tabl."` (`email`, `campaign`, `platform`) VALUES ('".$email."', '".$campaign."'".$platform.");");
		} else {
			$results = mysql_query("INSERT INTO `".$db_tabl."` (`email`, `campaign`, `client`, `platform`) VALUES ('".$email."', '".$campaign."'".$client.$platform.");");
		}
	} else {
		$results = mysql_query("INSERT INTO `".$db_tabl."` (`email`, `campaign`) VALUES ('".$email."', '".$campaign."');");
	}
}

?>