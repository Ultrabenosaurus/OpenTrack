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
* @version  1.4
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
	 * @param boolean $_device    Whether or not to attempt device detection.
	 * @param string  $_cache_dir PHPBrowscap's cache directory (will be appended to the root dir).
	 * @param mixed   $_db_fiel   Whether data targetting non-existing fields should be deleted
	 *                            or have the fields created.
	 *
	 * @access public
	 */
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
	 */
	public function dbConnect($_db_addr, $_db_user, $_db_pass, $_db_name = null, $_db_tabl = null){
		$this->db_addr = $_db_addr;
		$this->db_user = $_db_user;
		$this->db_pass = $_db_pass;
		$this->db = mysql_connect($this->db_addr, $this->db_user, $this->db_pass);
		
		if((!is_null($_db_name) && !empty($_db_name)) && (!is_null($_db_tabl) && !empty($_db_tabl))){
			$this->dbSwitch($_db_name, $_db_tabl);
		}
	}
	

	/**
	 * Closes the database connection
	 * 
	 * Allow for easily tracking data to multiple tables/databases.
	 * 
	 * @access public
	 */
	public function dbDisconnect(){
		if(!is_null($this->db)){
			$this->db = mysql_close($this->db);

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
	 */
	public function dbSwitch($_db_name, $_db_tabl){
		$this->db_name = $_db_name;
		$this->db_tabl = $_db_tabl;
		mysql_select_db($this->db_name, $this->db);
	}
	

	/**
	 * The method to actually collect and store data
	 * 
	 * @param boolean $_test Whether or not to enable debugging mode.
	 *
	 * @access public
	 *
	 * @return mixed $this->test A multi-dimensional associative array of the information collected.
	 *                           Prevents data being stored in the database.
	 */
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
	

	/**
	 * Extracts the email address and email campaign from the query string.
	 * 
	 * @access private
	 *
	 * @return string A comma-separated combination of the email address and email campaign.
	 */
	private function _getFromQueryString(){
		return $_GET['email'].",".$_GET['campaign'];
	}
	

	/**
	 * Attempt to determine the device used to open the email.
	 * 
	 * @access private
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
	

	/**
	 * Checks the state of the table to be used against the data collected.
	 * 
	 * If the table does not exist, it will be created in a state to store the default data collection.
	 * If data has been collected which targets a non-existing field in the table, it will either be
	 * removed from $this->data or the class will attempt to add new fields to the table based on the
	 * values in $this->data.
	 * 
	 * @access private
	 */
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
	

	/**
	 * Check for the tables existence, create in a default state if not found.
	 * 
	 * @access private
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
	

	/**
	 * Attempt to add new fields in the table if necessary, based on the collected data.
	 * 
	 * @param array $diff An array of the target fields present in $this->data but not in the table.
	 *
	 * @access private
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
	

	/**
	 * Remove information from $this->data which targets non-existing fields in the table.
	 * 
	 * @param array $diff An array of the target fields present in $this->data but not in the table.
	 *
	 * @access private
	 */
	private function _removeFields($diff){
		if(count($diff) > 0){
			foreach ($diff as $key => $value) {
				array_splice($this->data, array_search($value, array_keys($this->data)), 1);
			}
		}
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

		$this->test['fields'] = $fields;
		$this->test['values'] = $values;
		$this->data['fields'] = $fields;
		$this->data['values'] = $values;
	}
	

	/**
	 * Insert the collected data into the database.
	 * 
	 * @param string $fields The fields into which data is to be inserted.
	 * @param string $values The data to be inserted.
	 *
	 * @access private
	 */
	private function _insertData($fields, $values){
		$response = mysql_query("INSERT INTO `".$this->db_tabl."` ".$this->data['fields']." VALUES ".$this->data['values'].";", $this->db);
	}
	

	/**
	 * Attempt to log errors so that the user never sees a "broken image" icon.
	 * 
	 * @param string $info The error to log.
	 *
	 * @access private
	 */
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