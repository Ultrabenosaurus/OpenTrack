<?php

/**
* OpenTrack - a simple class to gather information on the people who open your emails
* 
* This class is designed to be used in email newsletters or other group emails in order
* to collect information on the people who open the email. It cannot track how long the
* email was open for or whether or not the user clicked any links inside the email, but
* by default it will add a new record to a MySQL database containing the email address,
* email campaign, date/time and the device used to view the email.
* 
* This class requires access to a MySQL database in order to function.
* Device detection requires one of the following:
* * browscap.ini (http://de3.php.net/manual/en/function.get-browser.php)
* * GaretJax's PHPBrowscap class (https://github.com/GaretJax/phpbrowscap)
* * bjankord's Categorizr class (https://github.com/bjankord/Categorizr)
* 
*
* @package  OpenTrack
* @version  1.6
* @author   Dan Bennett <danielj.bennett@yahoo.com>
* @license  http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
* @link     https://github.com/Ultrabenosaurus/OpenTrack/
*/
class OpenTrack{
	/**
	 * $dirs - An array of directories needed for the class to run
	 *
	 * @var array
	 *
	 * @access private
	 */
	private $dirs;

	/**
	 * $debug - Whether or not the class is running in debug mode
	 *
	 * @var boolean
	 *
	 * @access private
	 */
	private $debug;

	/**
	 * $device - Whether or not to attempt device detection
	 *
	 * @var boolean
	 *
	 * @access private
	 */
	private $device;

	/**
	 * $db - The MySQL connection resource
	 *
	 * @var resource
	 *
	 * @access private
	 */
	private $db;

	/**
	 * $db_addr - MySQL URI to connect to
	 *
	 * @var string
	 *
	 * @access private
	 */
	private $db_addr;

	/**
	 * $db_user - The username with which to connect
	 *
	 * @var string
	 *
	 * @access private
	 */
	private $db_user;

	/**
	 * $db_pass - The password with which to connect
	 *
	 * @var string
	 *
	 * @access private
	 */
	private $db_pass;

	/**
	 * $db_name - The database to connect to
	 *
	 * @var string
	 *
	 * @access private
	 */
	private $db_name;

	/**
	 * $db_tabl - The table in which to store data
	 *
	 * @var string
	 *
	 * @access private
	 */
	private $db_tabl;

	/**
	 * $db_fiel - Whether new fields should be added to the table or removed from $this->data
	 *
	 * @var boolean
	 *
	 * @access private
	 */
	private $db_fiel;

	/**
	 * $data - An array containing all collected data
	 *
	 * @var array
	 *
	 * @access private
	 */
	private $data;

	/**
	 * $test - An array containing various debugging information
	 *
	 * @var array
	 *
	 * @access private
	 */
	private $test;

	/**
	 * Construction method - sets up the basic variables needed to start tracking
	 * 
	 * @param boolean $debug      Whether or not the class is running in debug mode.
	 * @param boolean $_device    Whether or not to attempt device detection.
	 * @param string  $_cache_dir PHPBrowscap's cache directory (will be appended to the root dir).
	 * @param mixed   $_db_fiel   Whether data targetting non-existing fields should be deleted
	 *                            or have the fields created.
	 *
	 * @access public
	 */
	public function __construct($_debug = false, $_device = true, $_cache_dir = 'phpbc_cache/', $_db_fiel = false){
		if(!$_debug){
			set_error_handler(array($this, "_handleErrors"));
		}
		$this->dirs = array(
			'root'=>'lib/',
			'logs'=>'logs/'.date('Y').'/'.date('m').'/',
			'logs_organize'=>true,
			'browscap'=>'lib/Browscap.php',
			'categorizr'=>'lib/categorizr.php',
			'image'=>'lib/img.png'
		);
		$this->debug = $_debug;
		$this->device = $_device;
		$this->db = null;
		$this->db_fiel = $_db_fiel;
		$this->dirs['cache'] = $this->dirs['root'].$_cache_dir;
		$this->data = array();
		$this->test = array(
			'dirs'=>$this->dirs,
			'errors'=>array(
				'count'=>0
			)
		);
	}
	

	/**
	 * Nullifies all class variables and disconnects from the database
	 * 
	 * @access public
	 */
	public function __destruct(){
		$this->dbDisconnect(true);
		$this->dirs = null;
		$this->debug = null;
		$this->device = null;
		$this->db = null;
		$this->db_addr = null;
		$this->db_user = null;
		$this->db_pass = null;
		$this->db_name = null;
		$this->db_tabl = null;
		$this->db_fiel = null;
		$this->data = null;
		$this->test = null;
	}
	

	/**
	 * The method to actually collect and store data
	 * 
	 * @access public
	 *
	 * @return mixed False or $this->test on failure, depending on debug mode;
	 *               True or $this->test on success, depending on debug mode.
	 */
	public function track(){
		$params = $this->_getFromQueryString();
		if($params){
			$em_camp = explode(',', $params);
			if($this->device){
				$this->_getDeviceInfo();
			}
			$table_prep = $this->_prepareTable();
			if($table_prep){
				$this->_prepareData($em_camp[0], $em_camp[1]);
				if($this->debug === false){
					$this->_image();
					return $this->_insertData();
				} else {
					return $this->test;
				}
			} else {
				if($this->debug === false){
					$this->_image();
					return false;
				} else {
					return $this->test;
				}
			}
		}
		if($this->debug === false){
			$this->_image();
			return false;
		} else {
			return $this->test;
		}
	}
	

	/**
	 * Toggles log directory structure
	 * 
	 * @param boolean $organize Whether to organize the log directory or name the log file by the date.
	 *
	 * @access public
	 */
	public function logsDirOrganize($organize = true){
		if(!$organize){
			$this->dirs['logs_organize'] = false;
			$this->dirs['logs'] = 'logs/';
		} else {
			$this->dirs['logs_organize'] = true;
			$this->dirs['logs'] = 'logs/'.date('Y').'/'.date('m').'/';
		}
		$this->test['dirs'] = $this->dirs;
	}
	

	/**
	 * Creates a MySQL database connection
	 * 
	 * If $_db_name and/or $_db_tabl are omitted, you will need to use dbSwitch() before
	 * you can start tracking emails.
	 * 
	 * @param string $_db_addr MySQL URI.
	 * @param string $_db_user Username to access the database.
	 * @param string $_db_pass Password to access the database.
	 * @param mixed  $_db_name Database to access (string or null).
	 * @param mixed  $_db_tabl Table to use in the database (string or null).
	 *
	 * @access public
	 * 
	 * @return boolean Indicates success of connecting to MySQL URI.
	 */
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
				if($this->dbSwitch($_db_name, $_db_tabl)){
					$this->test['db_info']['address'] = $this->db_addr;
					$this->test['db_info']['username'] = $this->db_user;
					$this->test['db_info']['password'] = $this->db_pass;
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
	

	/**
	 * Closes the database connection
	 * 
	 * Allow for easily tracking data to multiple tables/databases.
	 * 
	 * @param boolean $force Whether or not to drop the connection without attempting to close it properly.
	 * 
	 * @access public
	 * 
	 * @return boolean Indicates success of disconnecting from database.
	 */
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
	

	/**
	 * Switch the database/table used for storing data
	 * 
	 * Allows for easily tracking data to multiple tables/databases.
	 * 
	 * @param mixed $_db_name Database to access (string or null).
	 * @param mixed $_db_tabl Table to use in the database (string or null).
	 *
	 * @access public
	 * 
	 * @return boolean Indicates success of switching database
	 */
	public function dbSwitch($_db_name, $_db_tabl){
		$this->db_tabl = $_db_tabl;
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
			$this->test['db_info']['database'] = $this->db_name;
			return true;
		} else {
			return false;
		}
	}
	

	/**
	 * Display the image to the user
	 * 
	 * @access private
	 */
	private function _image(){
		header("Content-Type: image/png");
		echo file_get_contents($this->dirs['image']);
	}
	

	/**
	 * Get the email address and campaign name from the query string
	 * 
	 * @access private
	 *
	 * @return mixed Comma-separated combination of email address and campaign name on success, false on failure.
	 */
	private function _getFromQueryString(){
		if((isset($_GET['email']) && !empty($_GET['email'])) && (isset($_GET['campaign']) && !empty($_GET['campaign']))){
			if(preg_match('/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $_GET['email']) === 1){
				return $_GET['email'].",".$_GET['campaign'];
			} else {
				$info = date('H:i:s')." - OpenTrack::_getFromQueryString() >> \r\n";
				$info .= ">>\tThe email address provided was invalid.\r\n";
				$info .= ">>\tRegex: '/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i'\r\n";
				$info .= ">>\tEmail: ".$_GET['email'];
				$this->_log($info);
				return false;
			}
		} else {
			$info = date('H:i:s')." - OpenTrack::_getFromQueryString() >> \r\n";
			$info .= ">>\tThe script was not passed both an email address and a campaign name.\r\n";
			$info .= ">>\tThese are the main purpose of this script, if you do not have a need for them then this script may not be ideal for your current project.";
			$this->_log($info);
			return false;
		}
	}
	

	/**
	 * Determines which device detection method to use
	 * 
	 * A parameter 'device' can be added to the query string to simply skip browscap.ini (useful if
	 * your server has an outdated version you can't update) or to specify the exact method to use.
	 * 
	 * @access private
	 */
	private function _getDeviceInfo(){
		$method = isset($_GET['device']) ? $_GET['device'] : ini_get('browscap');
		switch($method){
			case 'browscapini':
				$agent = $this->_browscapini();
				break;
			case 'phpbrowscap':
				$agent = $this->_phpbrowscap();
				break;
			case 'categorizr':
				$agent = $this->_categorizer();
				break;
			default:
				if(!empty($method) && !is_null($method)){
					$agent = $this->_browscapini();
				} else {
					if(!isset($agent) && file_exists($this->dirs['browscap'])){
						$agent = $this->_phpbrowscap();
					}
					if(!isset($agent) && file_exists($this->dirs['categorizr'])){
						$agent = $this->_categorizer();
					}
				}
				break;
		}
		if(isset($agent)){
			$this->test['device_detection']['info'] = $agent;
			$this->test['data']['initial'] = $this->data;
		} else {
			$info = date('H:i:s')." - OpenTrack::_getDeviceInfo() >> \r\n";
			$info .= ">>\tNo device detection methods were successful. Please ensure you have implemented at least one of the possible methods.\r\n";
			$info .= ">>\tAlternatively, initiate this class with the first parameter as false to skip device detection.";
			$this->_log($info);
		}
	}
	

	/**
	 * Use server's browscap.ini and get_browser() to determine the device
	 * 
	 * @access private
	 *
	 * @return array An array of the device's information.
	 */
	private function _browscapini(){
		$agent = get_browser($_SERVER['HTTP_USER_AGENT'], true);
		$this->test['device_detection']['method'] = 'browscap.ini';
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
		return $agent;
	}
	

	/**
	 * Use GaretJax's PHPBrowscap class to determine the device
	 * 
	 * @access private
	 *
	 * @return mixed Object containing device information on success, or false on failure.
	 */
	private function _phpbrowscap(){
		try{
			if(!is_dir($this->dirs['cache'])){
				mkdir($this->dirs['cache'], 0777, true);
			}
			@include $this->dirs['browscap'];
			$bc = new Browscap($this->dirs['cache']);
			$agent = $bc->getBrowser();
			if(isset($agent)){
				$this->test['device_detection']['method'] = 'PHPBrowscap';
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
			return $agent;
		} catch(Exception $e){
			if(!$this->debug){
				$this->_image();
			}
			$info = date('H:i:s')." - PHPBrowscap Exception thrown >> \r\n";
			$info .= ">>\t".$e->getMessage()."\r\n";
			$trace = $e->getTrace();
			$info .= ">>\t".$trace[0]['file'].":".$trace[0]['line'];
			$this->_log($info);
			return false;
		}
	}
	

	/**
	 * Use bjankord's Categorizr class to determine the device
	 * 
	 * @access private
	 *
	 * @return mixed String containing very simplistic device type on success, false on failure.
	 */
	private function _categorizer(){
		@include($this->dirs['categorizr']);
		$agent = categorizr();
		if(isset($agent)){
			$this->test['device_detection']['method'] = 'categorizr';
			$this->data['platform'] = $agent;
			return $agent;
		}
		return false;
	}
	

	/**
	 * Checks the state of the table to be used against the data collected
	 * 
	 * If the table does not exist, it will be created in a state to store the default data collection.
	 * If data has been collected which targets a non-existing field in the table, it will either be
	 * removed from $this->data or the class will attempt to add new fields to the table based on the
	 * values in $this->data.
	 * 
	 * @access private
	 * 
	 * @return boolean Indicates success of preparing the table for insertion.
	 */
	private function _prepareTable(){
		$table = $this->_createTable();
		if($table){
			$this->test['db_info']['table'] = $this->db_tabl;
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
			$this->test['data']['final'] = $this->data;
			return $prep;
		} else {
			return false;
		}
	}
	

	/**
	 * Check for the tables existence, create in a default state if not found
	 * 
	 * @access private
	 * 
	 * @return boolean Indicates success of creating table or tables existence.
	 */
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
				$this->test['db_info']['creation'] = true;
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	

	/**
	 * Attempt to add new fields in the table if necessary, based on the collected data
	 * 
	 * @param array $diff An array of the target fields present in $this->data but not in the table.
	 *
	 * @access private
	 * 
	 * @return boolean Indicates success.
	 */
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
						if((int)$length*2 > 200){
							$field = "TEXT NULL DEFAULT NULL";
						} else {
							$field = "VARCHAR(".((int)$length*2).") NULL DEFAULT NULL";
						}
						break;
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
					$this->test['db_info']['fields'] = array('type'=>'addition', 'result'=>'success', 'columns'=>$diff);
					return true;
				} else {
					$this->test['db_info']['fields'] = array('type'=>'addition', 'result'=>'failure', 'columns'=>$diff);
					return false;
				}
			}
		}
		return true;
	}
	

	/**
	 * Remove information from $this->data which targets non-existing fields in the table.
	 * 
	 * @param array $diff An array of the target fields present in $this->data but not in the table.
	 *
	 * @access private
	 * 
	 * @return boolean Indicates success
	 */
	private function _removeFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value) {
				array_splice($this->data, array_search($value, array_keys($this->data)), 1);
			}
			$size = count(array_diff($this->data, $diff));
			if(count($this->data) > $size){
				$info = date('H:i:s')." - OpenTrack::_removeFields() >> \r\n";
				$info .= ">>\tAdditional entries could not be removed from collected data.";
				$this->_log($info);
				$this->test['db_info']['fields'] = array('type'=>'removal', 'result'=>'failure', 'columns'=>$diff);
				return false;
			}
			$this->test['db_info']['fields'] = array('type'=>'removal', 'result'=>'success', 'columns'=>$diff);
			return true;
		}
		return true;
	}
	

	/**
	 * Prepare the collected data in MySQL INSERT format.
	 * 
	 * @param string $email    The email address of the user who opened the email.
	 * @param string $campaign The campaign to which the opened email belongs.
	 *
	 * @access private
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
		$this->test['insert']['fields'] = $fields;
		$this->test['insert']['values'] = $values;
		$this->data['fields'] = $fields;
		$this->data['values'] = $values;
	}
	

	/**
	 * Insert the collected data into the database.
	 *
	 * @access private
	 * 
	 * @return boolean Indicates success
	 */
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
	

	/**
	 * Attempt to log errors so that the user never sees a "broken image" icon.
	 * 
	 * @param string $info The error to log.
	 *
	 * @access private
	 */
	private function _log($info, $php = false){
		if(!file_exists($this->dirs['logs'])){
			mkdir($this->dirs['logs'], 0777, true);
		}
		$filename = ($this->dirs['logs_organize']) ? date('d') : date('Y-m-d');
		$filename .= ($php) ? "_error" : "_log";
		$log = fopen($this->dirs['logs'].$filename, 'a');
		fwrite($log, $info."\r\n\r\n");
		fclose($log);
		$this->test['errors']['count']++;
		$this->test['errors'][] = "\r\n".$info;
	}
	

	/**
	 * Intercept non-fatal PHP errors
	 * 
	 * This prevents disruption to the users (eg broken image icon) unless something goes terribly wrong
	 * 
	 * @param integer $errno      The level of the error raised.
	 * @param string  $errstr     The error message.
	 * @param string  $errfile    The filename that the error was raised in.
	 * @param integer $errline    The line number the error was raised at.
	 * @param array   $errcontext Points to the active symbol table at the point the error occurred.
	 *
	 * @access private
	 *
	 * @return mixed Value.
	 */
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
		$this->_image();
		die();
	}
}

?>