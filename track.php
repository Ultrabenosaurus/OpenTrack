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

	/*
	 some hosts don't have PHP set to use browscap, but I wrote some code to take advantage
	 of it in case that fact changes, as browscap is a very powerful detection method
	*/
	$browscap = ini_get('browscap');
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
	/*
	 If browscap is not available, use a simple 3rd party (freely distributable under GNU Lesser
	 General Public License) which uses a series of regex checks on the user agent.
	 This script only returns either mobile, desktop, tv or tablet, but that's still better than
	 nothing. It assumes the device is 'mobile' and works up from there.
	*/
	} else {
		if(file_exists('categorizr.php')){
			@include("categorizr.php");
			$device = categorizr();
			$agent = "categorizr";
			$platform = ", '".$device."'";
		}
	}

	/*
	 Connect to the database and dump the data into the database.
	 If you add/change any browser detection methods set $agent to a unique string for all except
	 browscap so that it will always be the default.
	*/
	$db = mysql_connect("localhost", "username", "password");
	mysql_select_db("database", $db);
	if(isset($agent)){
		if($agent == 'categorizr'){
			$results = mysql_query("INSERT INTO `email_tracking` (`email`, `campaign`, `platform`) VALUES ('".$email."', '".$campaign."'".$platform.");");
		} else {
			$results = mysql_query("INSERT INTO `email_tracking` (`email`, `campaign`, `client`, `platform`) VALUES ('".$email."', '".$campaign."'".$client.$platform.");");
		}
	} else {
		$results = mysql_query("INSERT INTO `email_tracking` (`email`, `campaign`) VALUES ('".$email."', '".$campaign."');");
	}
}

?>