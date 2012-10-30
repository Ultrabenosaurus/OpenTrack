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

	public function __construct($_device = true, $_cache_dir = 'phpbc_cache/', $_db_fiel = true){
		error_reporting(~E_NOTICE);
		$this->dirs = array(
			'root'=>'lib/',
			'logs'=>'lib/logs/',
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
	
	public function dbConnect($_db_addr, $_db_user, $_db_pass, $_db_name = null, $_db_tabl = null){
		$this->db_addr = $_db_addr;
		$this->db_user = $_db_user;
		$this->db_pass = $_db_pass;
		$this->db = mysql_connect($this->db_addr, $this->db_user, $this->db_pass);
		
		if(!is_null($_db_name) && !is_null($_db_tabl)){
			$this->dbSwitch($_db_name, $_db_tabl);
		}
	}
	
	public function dbDisconnect(){
		
	}
	
	public function dbSwitch($_db_name, $_db_tabl){
		$this->db_name = $_db_name;
		$this->db_tabl = $_db_tabl;
		mysql_select_db($this->db_name, $this->db);
	}
	
	public function track($_test = false){
		$em_camp = explode(',', $this->_getFromQueryString());
		if($this->device){
			$this->_getDeviceInfo();
		}
		$this->_prepareTable();
		$this->_prepareData($em_camp[0], $em_camp[1]);
		if(!$_test){
			header("Content-Type: image/png");
			echo file_get_contents($this->dirs['image']);
			$this->_insertData();
		} else {
			return $this->test;
		}
	}
	
	private function _getFromQueryString(){
		return $_GET['email'].",".$_GET['campaign'];
	}
	
	/*
	 Attempt to use PHP's built-in browscap detection method to get information on the user's device
	 and client. However, not all hosts support browscap, and those that do may be using an outdated
	 version, so GaretJax's PHPBrowscap and bjankord's Categorizr classes are supported.
	 You can force the default browscap to be skipped by including &browscap at the end of the query
	 string in the image source attribute when using this script.
	 
	 PHPBrowscap - https://github.com/GaretJax/phpbrowscap
	 Categorizr - https://github.com/bjankord/Categorizr
	*/
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
					$info = date('H:i:s')." - PHPBrowscap >> \r\n";
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
			$info = date('H:i:s')." - Device Detection >> \r\n";
			$info .= ">>\tNo device detection methods were successful. Please ensure you have implemented at least one of the possible methods.\r\n";
			$info .= ">>\tAlternatively, initiate this class with the first parameter as false to skip device detection.";
			$this->_log($info);
		}
	}
	
	private function _prepareTable(){
		$this->_createTable();
		$field_exists = mysql_query("SHOW COLUMNS FROM `".$this->db_tabl."`;", $this->db);
		$found = array();
		while($row = mysql_fetch_assoc($field_exists)){
			$found[] = $row['Field'];
		}
		$diff = array_diff(array_keys($this->data), $found);
		if($this->db_fiel){
			$this->_addFields($diff);
		} else {
			$this->_removeFields($diff);
		}
		$this->test['found'] = $found;
		$this->test['diff'] = $diff;
		$this->test['data_final'] = $this->data;
	}
	
	/*
	 Check if the table exists. If it doesn't, create it with default fields.
	*/
	private function _createTable(){
		$table_exists = mysql_query("SELECT COUNT(*) FROM `information_schema`.`tables` WHERE `table_schema`='".$this->db_name."' AND `table_name`='".$this->db_tabl."';", $this->db);
		$table_exists = mysql_fetch_array($table_exists);
		if($table_exists[0] < 1){
			mysql_query(
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
		}
	}
	
	/*
	 Loop through all fields in the table, check against keys in $data. If an entry in $data doesn't
	 have a matching field, attempt to create it based on the type and size of $data's value.
	*/
	private function _addFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value){
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
				mysql_query("ALTER TABLE `".$this->db_tabl."` ADD `".$value."` ".$field." COLLATE 'latin1_general_ci';", $this->db);
			}
		}
	}
	
	/*
	 Loop through all fields in the table, check against keys in $data. If an entry in $data doesn't
	 have a matching field, remove it from the array.
	*/
	private function _removeFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value) {
				array_splice($this->data, array_search($value, array_keys($this->data)), 1);
			}
		}
	}
	
	/*
	 Prepare collected data for inserting into the database. By using an associative array, this
	 script is easily expandable for any data you want to collect about your email viewers.
	 Email and Campaign are excluded from $data as they are the main point of this script.
	*/
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
	
	private function _insertData($fields, $values){
		$response = mysql_query("INSERT INTO `".$this->db_tabl."` ".$this->data['fields']." VALUES ".$this->data['values'].";", $this->db);
	}
	
	private function _log($info){
		if(!is_dir($this->dirs['logs'])){
			mkdir($this->dirs['logs'], true);
		}
		$log = fopen($this->dirs['logs'].date('Y-m-d').'_log', 'a');
		fwrite($log, $info."\r\n\r\n");
		fclose($log);
	}
}

?>