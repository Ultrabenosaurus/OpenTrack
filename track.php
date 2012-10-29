<?php

/*
 Return the image by default so that, even if the script fails, the user isn't aware that
 anything has gone wrong (broken image symbol).
 Override this in testing mode.
*/
if(!isset($_GET['test'])){
	header("Content-Type: image/png");
	echo file_get_contents('lib/img.png');
}

if((isset($_GET['campaign']) && !empty($_GET['campaign'])) && (isset($_GET['email']) && !empty($_GET['email']))){
	error_reporting(~E_NOTICE);
	$email = $_GET['email'];
	$campaign = $_GET['campaign'];
	
	$db_addr = 'localhost';
	$db_user = 'username';
	$db_pass = 'password';
	$db_name = 'database';
	$db_tabl = 'table';
	$db_fiel = true;
	
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
				$agent = $bc->getBrowser();
				if(isset($agent->Comment)){
					$temp = (isset($data['client']) && is_null($data['client'])) ? NULL : $data['client'];
					$data['client'] = ($agent->Comment == "Default Browser") ? $temp : $agent->Comment;
				}
				if(isset($agent->Parent)){
					$temp = (isset($data['client']) && is_null($data['client'])) ? NULL : $data['client'];
					$data['client'] = ($agent->Parent == "Default Browser") ? $temp : $agent->Parent;
				}
				if(isset($agent->Platform)){
					$data['platform'] = ($agent->Platform == "Default Browser") ? NULL : $agent->Platform;
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
			$agent = categorizr();
			$data['platform'] = $agent;
		}
	}

	/*
	 Prepare collected data for inserting into the database. By using an associative array, this
	 script is easily expandable for any data you want to collect about your email viewers.
	 Email and Campaign are excluded from $data as they are the main point of this script.
	 Connect to the database ready for inserting the data.
	*/
	$fields = "(`email`, `campaign`";
	$values = "('".$email."', '".$campaign."'";
	foreach ($data as $key => $value) {
		$fields .= ", `".$key."`";
		if(is_null($value)){
			$values .= ", NULL";
		} else {
			if(is_bool($value)){
				$values .= ($value == true) ? ", '1'" : ", '0'";
			} else {
				$values .= ", '".$value."'";
			}
		}
	}
	$fields .= ")";
	$values .= ")";
	$db = mysql_connect($db_addr, $db_user, $db_pass);
	mysql_select_db($db_name, $db);
	
	/*
	 Check if the table exists. If it doesn't, create it with default fields.
	*/
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

	/*
	 Loop through all fields in the table, check against keys in $data. If an entry in $data doesn't
	 have a matching field, attempt to create it based on the type and size of $data's value;
	*/
	$field_exists = mysql_query("SHOW COLUMNS FROM `".$db_tabl."`;", $db);
	$found = array();
	while($row = mysql_fetch_assoc($field_exists)){
		$found[] = $row['Field'];
	}
	$diff = array_diff(array_keys($data), $found);
	if(isset($_GET['test'])){
		echo "<pre>" . print_r($data, true) . "</pre>";
		echo "<pre>" . print_r($found, true) . "</pre>";
		echo "<pre>" . print_r($diff, true) . "</pre>";
	}
	if(count($diff) > 0){
		foreach ($diff as $key => $value) {
			$type = gettype($data[$value]);
			$length = strlen($data[$value]);
			switch (strtolower($type)) {
				case 'string':
				case 'double':
					$field = "VARCHAR(".((int)$length*2).") NULL DEFAULT NULL";
					break;
				case 'boolean':
					$field = "ENUM('0','1') NOT NULL DEFAULT '0'";
					break;
				case 'integer':
					$field = "INT(".((int)$length*2).") NULL DEFAULT NULL";
					break;
				case 'null':
					$field = "VARCHAR(50) NULL DEFAULT NULL";
					break;
			}
			mysql_query("ALTER TABLE `".$db_tabl."` ADD `".$value."` ".$field." COLLATE 'latin1_general_ci';", $db);
		}
	}

	/*
	 Finally, once all data is gathered and the table is prepared, insert the data.
	*/
	$response = mysql_query("INSERT INTO `".$db_tabl."` ".$fields." VALUES ".$values.";", $db);
	if(isset($_GET['test'])){
		echo "<pre>" . print_r($agent, true) . "</pre>";
		echo "<pre>" . print_r($fields, true) . "</pre>";
		echo "<pre>" . print_r($values, true) . "</pre>";
		echo "<pre>" . print_r($response, true) . "</pre>";
	}
}

?>