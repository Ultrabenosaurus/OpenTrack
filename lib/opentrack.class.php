<?php

class OpenTrack{
	private $dirs;
	private $device;
	private $db;
	private $db_addr;
	private $db_user;
	private $db_pass;
	private $db_name;
	private $db_tabl;
	private $db_fiel;
	private $cache_dir;
	private $data;
	private $test;

	public function __construct($_device = true, $_cache_dir = 'phpbc_cache/', $_db_fiel = false){
		set_error_handler(array($this, "_handleErrors"));
		$this->dirs = array(
			'root'=>'lib/',
			'logs'=>'logs/'.date('Y').'/'.date('m').'/',
			'logs_organise'=>true,
			'browscap'=>'lib/Browscap.php',
			'categorizr'=>'lib/categorizr.php',
			'image'=>'lib/img.png'
		);
		$this->device = $_device;
		$this->db = null;
		$this->db_fiel = $_db_fiel;
		$this->dirs['cache'] = $this->dirs['root'].$_cache_dir;
		$this->data = array();
		$this->test = array();
	}
	
	public function __destruct(){
		$this->dbDisconnect();
		$dirs = null;
		$device = null;
		$db = null;
		$db_addr = null;
		$db_user = null;
		$db_pass = null;
		$db_name = null;
		$db_tabl = null;
		$db_fiel = null;
		$cache_dir = null;
		$data = null;
		$test = null;
	}
	
	public function logsDirOrganise($organise = true){
		if(!$organise){
			$this->dirs['logs_organise'] = false;
			$this->dirs['logs'] = 'logs/';
		} else {
			$this->dirs['logs_organise'] = true;
			$this->dirs['logs'] = 'logs/'.date('Y').'/'.date('m').'/';
		}
	}
	
	public function dbConnect($_db_addr, $_db_user, $_db_pass, $_db_name = null, $_db_tabl = null){
		if(!is_null($this->db)){
			$this->dbDisconnect(true);
		}
		$this->db_addr = $_db_addr;
		$this->db_user = $_db_user;
		$this->db_pass = $_db_pass;
		$this->db_tabl = $_db_tabl;
		$conn = mysql_connect($this->db_addr, $this->db_user, $this->db_pass);
		if(!is_resource($conn)){
			$count = 0;
			while(!is_resource($conn)){
				$conn = mysql_connect($this->db_addr, $this->db_user, $this->db_pass);
				if($count >= 10){
					$info = date('H:i:s')." - OpenTrack::dbConnect() >> \r\n";
					$info .= ">>\tDatabase connection could not be established properly.\r\n";
					$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
					$this->_log($info);
					break;
				}
				$count++;
			}
		}
		if(is_resource($conn)){
			$this->db = $conn;
			if(!is_null($_db_name)){
				if($this->dbSwitch($_db_name)){
					return true;
				} else {
					return false;
				}
			}
			return true;
		} else {
			return false;
		}
	}
	
	public function dbDisconnect($force = false){
		if($force){
			$this->db = null;
			return true;
		}
		if(!is_null($this->db)){
			$count = 0;
			$close = mysql_close($this->db);
			while(!$close){
				$close = mysql_close($this->db);
				if($count >= 10){
					$info = date('H:i:s')." - OpenTrack::dbDisconnect() >> \r\n";
					$info .= ">>\tDatabase connection could not be terminated properly.\r\n";
					$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
					$this->_log($info);
					break;
				}
				$count++;
			}
			if($close){
				$this->db = null;
				return true;
			} else {
				return false;
			}
		}
	}
	
	public function dbSwitch($_db_name){
		$active = mysql_select_db($_db_name, $this->db);
		if(!$active){
			$count = 0;
			while(!$active){
				$active = mysql_select_db($this->db_name, $this->db);
				if($count >= 10){
					$info = date('H:i:s')." - OpenTrack::dbSwitch() >> \r\n";
					$info .= ">>\tCould not set database '".$this->db."' as the active database. Please ensure it exists and you have permission to access it.\r\n";
					$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
					$this->_log($info);
					break;
				}
				$count++;
			}
		}
		if($active){
			$this->db_name = $_db_name;
			return true;
		} else {
			return false;
		}
	}
	
	public function track($_test = false){
		$params = $this->_getFromQueryString();
		if($params){
			$em_camp = explode(',', $params);
			if($this->device){
				$this->_getDeviceInfo();
			}
			$table_prep = $this->_prepareTable();
			if($table_prep){
				$this->_prepareData($em_camp[0], $em_camp[1]);
				if(!$_test){
					$this->_image();
					return $this->_insertData();
				} else {
					return $this->test;
				}
			}
		}
		return false;
	}
	
	private function _image(){
		header("Content-Type: image/png");
		echo file_get_contents($this->dirs['image']);
	}
	
	private function _getFromQueryString(){
		if((isset($_GET['email']) && !empty($_GET['email'])) && (isset($_GET['campaign']) && !empty($_GET['campaign']))){
			return $_GET['email'].",".$_GET['campaign'];
		} else {
			$info = date('H:i:s')." - OpenTrack::_getFromQueryString() >> \r\n";
			$info .= ">>\tThe script was not passed both an email address and a campaign name.\r\n";
			$info .= ">>\tThese are the main purpose of this script, if you do not have a need for them then this script may not be ideal for your current project.";
			$this->_log($info);
			return false;
		}
	}
	
	private function _getDeviceInfo(){
		$browscap = isset($_GET['browscap']) ? $_GET['browscap'] : ini_get('browscap');
		if(!empty($browscap) && !is_null($browscap) && $browscap !== false){
			$agent = get_browser($_SERVER['HTTP_USER_AGENT'], true);
			foreach ($agent as $key => $value) {
				switch ($key) {
					case 'comment':
						$this->data['client'] = ($value == "Default Browser") ? NULL : $value;
						break;
					case 'platform':
						$temp = (isset($this->data['platform']) && is_null($this->data['platform'])) ? NULL : $this->data['platform'];
						$this->data['platform'] = ($value == "unknown") ? $temp : $value;
						break;
					case 'platform_description':
						$temp = (isset($this->data['platform']) && is_null($this->data['platform'])) ? NULL : $this->data['platform'];
						$this->data['platform'] = ($value == "unknown") ? $temp : $value;
						break;
				}
			}
		} else {
			if(file_exists($this->dirs['browscap'])){
				try{
					if(!is_dir($this->dirs['cache'])){
						mkdir($this->dirs['cache'], true);
					}
					@include $this->dirs['browscap'];
					$bc = new Browscap($this->dirs['cache']);
					$agent = $bc->getBrowser();
					if(isset($agent)){
						$skip = true;
					}
					if(isset($agent->Comment)){
						$temp = (isset($this->data['client']) && is_null($this->data['client'])) ? NULL : $this->data['client'];
						$this->data['client'] = ($agent->Comment == "Default Browser") ? $temp : $agent->Comment;
					}
					if(isset($agent->Parent)){
						$temp = (isset($this->data['client']) && is_null($this->data['client'])) ? NULL : $this->data['client'];
						$this->data['client'] = ($agent->Parent == "Default Browser") ? $temp : $agent->Parent;
					}
					if(isset($agent->Platform)){
						$this->data['platform'] = ($agent->Platform == "Default Browser") ? NULL : $agent->Platform;
					}
				} catch(Exception $e){
					$this->_image();
					$info = date('H:i:s')." - PHPBrowscap Exception thrown >> \r\n";
					$info .= ">>\t".$e->getMessage()."\r\n";
					$trace = $e->getTrace();
					$info .= ">>\t".$trace[0]['file'].":".$trace[0]['line'];
					$this->_log($info);
				}
			}
			if(file_exists($this->dirs['categorizr']) && !isset($skip)){
				@include($this->dirs['categorizr']);
				$agent = categorizr();
				if(isset($agent)){
					$this->data['platform'] = $agent;
					$skip = true;
				}
			}
		}
		if(isset($agent)){
			$this->test['agent'] = $agent;
			$this->test['data_initial'] = $this->data;
		} else {
			$info = date('H:i:s')." - OpenTrack::_getDeviceInfo() >> \r\n";
			$info .= ">>\tNo device detection methods were successful. Please ensure you have implemented at least one of the possible methods.\r\n";
			$info .= ">>\tAlternatively, initiate this class with the first parameter as false to skip device detection.";
			$this->_log($info);
		}
	}
	
	private function _prepareTable(){
		$table = $this->_createTable();
		if($table){
			$field_exists = mysql_query("SHOW COLUMNS FROM `".$this->db_tabl."`;", $this->db);
			$found = array();
			while($row = mysql_fetch_assoc($field_exists)){
				$found[] = $row['Field'];
			}
			$diff = array_diff(array_keys($this->data), $found);
			if($this->db_fiel){
				$prep = $this->_addFields($diff);
			} else {
				$prep = $this->_removeFields($diff);
			}
			$this->test['found'] = $found;
			$this->test['diff'] = $diff;
			$this->test['data_final'] = $this->data;
			return $prep;
		} else {
			return false;
		}
	}
	
	private function _createTable(){
		$table_exists = mysql_query("SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema`='".$this->db_name."' AND `table_name`='".$this->db_tabl."';", $this->db);
		$table_exists = mysql_fetch_array($table_exists);
		if($table_exists[0] < 1){
			$create = mysql_query(
				"CREATE TABLE IF NOT EXIST `".$this->db_tabl."` (
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
			if(!$create){
				$count = 0;
				while(!$create){
					$create = mysql_query(
						"CREATE TABLE IF NOT EXIST `".$this->db_tabl."` (
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
					if($count >= 10){
						$info = date('H:i:s')." - OpenTrack::_createTable() >> \r\n";
						$info .= ">>\tTable '' did not exist and could not be created. This is probably either a connection or permissions error.\r\n";
						$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
						$this->_log($info);
						break;
					}
					$count++;
				}
			}
			if($create){
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	private function _addFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value){
				$type = gettype($this->data[$value]);
				$length = strlen($this->data[$value]);
				switch (strtolower($type)) {
					case 'boolean':
						$field = "ENUM('0','1') NOT NULL DEFAULT '0'";
						break;
					case 'integer':
						$field = "INT(".((int)$length*2).") NULL DEFAULT NULL";
						break;
					case 'null':
						$field = "VARCHAR(50) NULL DEFAULT NULL";
						break;
					case 'string':
					case 'double':
					default:
						$field = "VARCHAR(".((int)$length*2).") NULL DEFAULT NULL";
						break;
				}
				$add = mysql_query("ALTER TABLE `".$this->db_tabl."` ADD `".$value."` ".$field." COLLATE 'latin1_general_ci';", $this->db);
				if(!$add){
					$count = 0;
					while(!$add){
						$add = mysql_query("ALTER TABLE `".$this->db_tabl."` ADD `".$value."` ".$field." COLLATE 'latin1_general_ci';", $this->db);
						if($count >= 10){
							$info = date('H:i:s')." - OpenTrack::_addFields() >> \r\n";
							$info .= ">>\tCould not add field '".$value."' to table '".$this->db_tabl."'. Please ensure it exists and you have permission to access it.\r\n";
							$info .= ">>\tValue: ".$this->data[$value]."\r\n";
							$info .= ">>\tType: ".strtolower($type)."\r\n";
							$info .= ">>\tLength: ".$length."\r\n";
							$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
							$this->_log($info);
							break;
						}
						$count++;
					}
				}
				if($add){
					return true;
				} else {
					return false;
				}
			}
		}
		return true;
	}
	
	private function _removeFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value) {
				array_splice($this->data, array_search($value, array_keys($this->data)), 1);
			}
		}
		$size = count(array_diff($this->data, $diff));
		if(count($this->data) > $size){
			$info = date('H:i:s')." - OpenTrack::_removeFields() >> \r\n";
			$info .= ">>\tAdditional entries could not be removed from collected data.";
			$this->_log($info);
			return false;
		}
		return true;
	}
	
	private function _prepareData($email, $campaign){
		$fields = "(`email`, `campaign`";
		$values = "('".$email."', '".$campaign."'";
		foreach ($this->data as $key => $value) {
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
		$this->test['fields'] = $fields;
		$this->test['values'] = $values;
		$this->data['fields'] = $fields;
		$this->data['values'] = $values;
	}
	
	private function _insertData(){
		$response = mysql_query("INSERT INTO `".$this->db_tabl."` ".$this->data['fields']." VALUES ".$this->data['values'].";", $this->db);
		if(!$response){
			$count = 0;
			while(!$response){
				$response = mysql_query("INSERT INTO `".$this->db_tabl."` ".$this->data['fields']." VALUES ".$this->data['values'].";", $this->db);
				if($count >= 10){
					$info = date('H:i:s')." - OpenTrack::_insertData() >> \r\n";
					$info .= ">>\tCould not insert data into table.\r\n";
					$info .= ">>\tFields: ".$this->data['fields']."\r\n";
					$info .= ">>\tValues: ".$this->data['values']."\r\n";
					$info .= ">>\tMySQL Error: ".mysql_errno($this->db).":".mysql_error($this->db);
					$this->_log($info);
					break;
				}
				$count++;
			}
		}
		if($response){
			return true;
		} else {
			return false;
		}
	}
	
	private function _log($info, $php = false){
		if(!is_dir($this->dirs['logs'])){
			mkdir($this->dirs['logs'], true);
		}
		$filename = ($this->dirs['logs_organise']) ? date('d') : date('Y-m-d');
		$filename .= ($php) ? "_error" : "_log";
		$log = fopen($this->dirs['logs'].$filename, 'a');
		fwrite($log, $info."\r\n\r\n");
		fclose($log);
	}
	
	private function _handleErrors($errno, $errstr, $errfile, $errline, $errcontext){
		switch($errno){
			case 2:
				$errtype = " - Warning >>\r\n";
				break;
			case 8:
				return;
				break;
			case 256:
				$errtype = " - User Error >>\r\n";
				break;
			case 512:
				$errtype = " - User Warning >>\r\n";
				break;
			case 1024:
				$errtype = " - User Notice >>\r\n";
				break;
			case 2048:
				$errtype = " - Strict >>\r\n";
				break;
			case 4096:
				$errtype = " - Recoverable Error >>\r\n";
				break;
			case 8192:
				$errtype = " - Deprecated >>\r\n";
				break;
			case 16384:
				$errtype = " - User Deprecated >>\r\n";
				break;
		}
		$error = date('H:i:s').$errtype.">>\t".$errstr."\r\n>>\tFile: ".$errfile."\r\n>>\tLine: ".$errline;
		$this->_log($error, true);
		header("Content-Type: image/png");
		echo file_get_contents($this->dirs['image']);
		die();
	}
}

?>