<?php

/*
 Return the image by default so that, even if the script fails, the user isn't aware that
 anything has gone wrong (broken image symbol).
*/
header("Content-Type: image/png");
echo file_get_contents('lib/img.png');

if((isset($_GET['campaign']) && !empty($_GET['campaign'])) && (isset($_GET['email']) && !empty($_GET['email']))){
	error_reporting(~E_NOTICE);
	$email = $_GET['email'];
	$campaign = $_GET['campaign'];
	
	$db_addr = 'localhost';
	$db_user = 'username';
	$db_pass = 'password';
	$db_name = 'database';
	$db_tabl = 'table';
	
	$data = array();
	
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
					$data['client'] = ($value == "Default Browser") ? NULL : $value;
					break;
				case 'platform':
					$temp = (isset($data['platform']) && is_null($data['platform'])) ? NULL : $data['platform'];
					$data['platform'] = ($value == "unknown") ? $temp : $value;
					break;
				case 'platform_description':
					$temp = (isset($data['platform']) && is_null($data['platform'])) ? NULL : $data['platform'];
					$data['platform'] = ($value == "unknown") ? $temp : $value;
					break;
			}
		}
	} else {
		if(file_exists('lib/Browscap.php')){
			try{
				$cache_dir = 'lib/phpbc_cache';
				if(!is_dir($cache_dir)){
					mkdir($cache_dir, true);
				}
				@include 'lib/Browscap.php';
				$bc = new Browscap('lib/phpbc_cache');
				$browser = $bc->getBrowser();
				if(isset($browser->Comment)){
					$temp = (isset($data['client']) && is_null($data['client'])) ? NULL : $data['client'];
					$data['client'] = ($browser->Comment == "Default Browser") ? $temp : $browser->Comment;
				}
				if(isset($browser->Parent)){
					$temp = (isset($data['client']) && is_null($data['client'])) ? NULL : $data['client'];
					$data['client'] = ($browser->Parent == "Default Browser") ? $temp : $browser->Parent;
				}
				if(isset($browser->Platform)){
					$data['platform'] = ($browser->Platform == "Default Browser") ? NULL : $browser->Platform;
				}
				$skip = true;
			} catch(Browscap_Exception $e){
				$log = fopen('log', 'a');
				fwrite($log, date('Y/m/d - H:i:s')." >> \r\n");
				fwrite($log, ">>\t".$e->getMessage()."\r\n");
				$trace = $e->getTrace();
				fwrite($log, ">>\t".$trace[0]['file'].":".$trace[0]['line']."\r\n\r\n");
				fclose($log);
			}
		}
		if(file_exists('lib/categorizr.php') && !isset($skip)){
			@include("lib/categorizr.php");
			$device = categorizr();
			$data['platform'] = $device;
		}
	}

	/*
	 Prepare collected data for inserting into the database. By using an associative array, this
	 script is easily expandable for any data you want to collect about your email viewers.
	 Connect to the database and dump the data into it.
	*/
	$fields = "(`email`, `campaign`";
	$values = "('".$email."', '".$campaign."'";
	foreach ($data as $key => $value) {
		$fields .= ", `".$key."`";
		if(is_null($value)){
			$values .= ", NULL";
		} else {
			$values .= ", '".$value."'";
		}
	}
	$fields .= ")";
	$values .= ")";

	/*
	 Connect to the database, check if the table exists, create it with default settings if not, then
	 insert the data.
	*/
	$db = mysql_connect($db_addr, $db_user, $db_pass);
	mysql_select_db($db_name, $db);
	$table_exists = mysql_query("SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema`='".$db_name."' AND `table_name`='".$db_tabl."';", $db);
	$table_exists = mysql_fetch_array($table_exists);
	if($table_exists[0] < 1){
		mysql_query(
			"CREATE TABLE IF NOT EXIST `".$db_tabl."` (
				`ID` INT(10) NOT NULL AUTO_INCREMENT,
				`email` VARCHAR(50) NOT NULL,
				`campaign` VARCHAR(50) NOT NULL,
				`datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`client` VARCHAR(50) NULL DEFAULT NULL,
				`platform` VARCHAR(50) NULL DEFAULT NULL,
				PRIMARY KEY (`ID`)
			)
			COLLATE='latin1_swedish_ci'
			ENGINE=InnoDB
			AUTO_INCREMENT=0;"
		);
	}
	$results = mysql_query("INSERT INTO `".$db_tabl."` ".$fields." VALUES ".$values.";", $db);
}

?>