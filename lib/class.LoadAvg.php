<?php
/**
* LoadAvg - Server Monitoring & Analytics
* http://www.loadavg.com
*
* Main controller class for LoadAvg 2.0
*
* @link https://github.com/loadavg/loadavg
* @author Karsten Becker
* @copyright 2014 Sputnik7
*
* This file is licensed under the Affero General Public License version 3 or
* later.
*/

class LoadAvg
{
	public static $_settings; // storing standard settings and/or loaded modules settings
	public static $_classes; // storing loaded modules classes
	public static $_modules; // storing loaded modules
	public static $current_date; // current date
	private static $_timezones; // Cache of timezones

	// Periodas
	public static $period;
	public static $period_minDate;
	public static $period_maxDate;
	public static $settings_ini;
	public $timeStart = null, $timeFinish = null;


	/**
	 * setSettings
	 *
	 * Stores the standard settings
	 *
	 * @param string $module name of the module
	 * @param array $args array of module settings
	 */
	public function setSettings($module, $args)
	{
		@self::$_settings->$module = $args;
	}

	/**
	 * __construct
	 *
	 * Class constructor
	 *
	 */

	public function __construct()
	{

		date_default_timezone_set("UTC");
		self::$settings_ini = "settings.ini";

		$this->setSettings('general',
			parse_ini_file(APP_PATH . '/config/' . self::$settings_ini, true)
		);

		date_default_timezone_set(self::$_settings->general['timezone']);

		self::$current_date = (isset($_GET['logdate']) && !empty($_GET['logdate'])) ? $_GET['logdate'] : date("Y-m-d");

		foreach ( self::$_settings->general['modules'] as $key => &$value ) {
			if ( $value == "true" ) {
				try {
					require_once $key . DIRECTORY_SEPARATOR . 'class.' . $key . '.php';
					self::$_classes[$key] = new $key;
				} catch (Exception $e) {
					throw Exception( $e->getMessage() );
				}
			}
		}

		if (is_dir(HOME_PATH . '/lib/modules/')) {
			foreach (glob(HOME_PATH . "/lib/modules/*/*.php") as $filename) {
				$filename = explode(".", basename($filename));
				self::$_modules[$filename[1]] = strtolower($filename[1]);
			}
		}

	}

	/**
	 * is_dir_empty
	 *
	 * Checks if specified directory is empty or not.
	 *
	 * @param string $dir path to directory
	 */

	private function is_dir_empty($dir) {
		if (!is_readable($dir)) return NULL;
		return (count(scandir($dir)) == 2);
	}

	/**
	 * createFirstLogs
	 *
	 * Creates first log files for every loaded modules 
	 * only run once - after installation - and may not be needed as modules will do this themselves
	 * if the file isnt there they create it
	 *
	 */

	public function createFirstLogs()
	{

		echo "Create Logs  \n";

		//only does it if DIR is empty ?
		if ( $this->is_dir_empty(HOME_PATH . '/' . self::$_settings->general['logs_dir']) ) {

			$loaded = self::$_settings->general['modules'];
			$logdir = HOME_PATH . '/' . self::$_settings->general['logs_dir'];

			$test_nested = false;

			// Check for each module we have loaded
			foreach ( $loaded as $module => $value ) {
				if ( $value == "false" ) continue;

				$moduleSettings = self::$_settings->$module;

				// Check if loaded module needs loggable capabilities
				if ( $moduleSettings['module']['logable'] == "true" ) {
					foreach ( $moduleSettings['logging']['args'] as $args) {

						$args = json_decode($args);
						$class = self::$_classes[$module];
						
						$caller = $args->function;

						//skip network interfaces as they have nested logs and work differently
						//later need to skip all other nested logs as we check those below
						
						if ( $args->logfile == "network_%s_%s.log" )
						{
							$test_nested = true;
						}
						else
						{
							$caller = sprintf($args->function, sprintf("'". $args->logfile . "'", date('Y-m-d')));
							$caller = $args->function;
							
							//dont work for network ?
							$class->logfile = $logdir . sprintf($args->logfile, date('Y-m-d'));
							$class->logfile = $logdir . $args->logfile;

							$class->$caller();	
						}
					}
				}

				//network interface is off at install time so dont really matter at this point

				if ($test_nested == true) {

					//now do nested charts 
					foreach (LoadAvg::$_settings->general['network_interface'] as $interface => $value) {
																					
								$caller = sprintf($args->function, sprintf("'". $args->logfile . "'", date('Y-m-d') , $interface  ));
								$caller = $args->function;
								
								//dont work for network ?
								$class->logfile = $logdir . sprintf($args->logfile, date('Y-m-d') , $interface );
								$class->logfile = $logdir . $args->logfile;

								$class->$caller();	
					}
				}
			}
		}
	}

/*
 * used when we turn modules on and off
 * this needs to only build the log file for modules that have no log file in /logs
 * also be great to pass the module over if we know 
 * what module has changed or been enabled
 */

	public static function rebuildLogs()
	{

			echo "Rebuild Logs  \n";

			$loaded = self::$_settings->general['modules'];

			$logdir = HOME_PATH . '/' . self::$_settings->general['logs_dir'];

			// Check for each module we have loaded
			foreach ( $loaded as $module => $value ) {
				if ( $value == "false" ) continue;

				$moduleSettings = self::$_settings->$module;

				// Check if loaded module needs loggable capabilities
				if ( $moduleSettings['module']['logable'] == "true" ) {
					foreach ( $moduleSettings['logging']['args'] as $args) {
						$args = json_decode($args);
						$class = self::$_classes[$module];

						$caller = $args->function;

						$class->logfile = $logdir . $args->logfile;

						//what does this do ? run args function ?
						$class->$caller();
					}
				}
			}

	}

	/**
	 * genChart - move from modules into core ASAP
	 *
	 * Function witch passes the data formatted for the chart view
	 *
	 * @param array @moduleSettings settings of the module
	 * @param string @logdir path to logfiles folder
	 *
	 */
	
	/*
	public function genChart($moduleSettings, $logdir)
	{

		$charts = $moduleSettings['chart'];
		$module = __CLASS__;
		$i = 0;
		foreach ( $charts['args'] as $chart ) {
			$chart = json_decode($chart);

			//grab the log file and date
			$this->logfile = $logdir . sprintf($chart->logfile, self::$current_date);
			
			if ( file_exists( $this->logfile )) {
				$i++;				

				$caller = $chart->function;
				$stuff = $this->$caller( (isset($moduleSettings['module']['url_args']) && isset($_GET[$moduleSettings['module']['url_args']])) ? $_GET[$moduleSettings['module']['url_args']] : '2' );
				$no_logfile = false;
				
			} else {
				$no_logfile = true;
			}
			
			include APP_PATH . '/views/chart.php';
		}
	}
	*/

/*
 * used to test if log files are being created by logger
 * needs better testing currently a bit of a hack
 * as we just test if the log directory is empty or not
 */

 	function testLogs( $mode = true)
	{

			$loaded = self::$_settings->general['modules'];
			$logdir = HOME_PATH . '/' . self::$_settings->general['logs_dir'];

			$test_worked = false;
			$test_nested = false;

			if ( $this->is_dir_empty($logdir))
				return false;

			// Check for each module we have loaded
			foreach ( $loaded as $module => $value ) {
				if ( $value == "false" ) continue;

				$moduleSettings = self::$_settings->$module;

				// Check if loaded module needs loggable capabilities
				if ( $moduleSettings['module']['logable'] == "true" ) {
					
					foreach ( $moduleSettings['logging']['args'] as $args) {
						
						$args = json_decode($args);
						$class = self::$_classes[$module];
						
						$caller = $args->function;

						//skip network interfaces as they have nested logs and work differently
						//later need to skip all nested logs as we check those below
						
						if ( $args->logfile == "network_%s_%s.log" )
						{
							$test_nested = true;
						}
						else
						{
							$filename = ( $logdir . sprintf($args->logfile, date('Y-m-d')) );

							if (file_exists($filename)) {
						    	$test_worked = true;
							}

							if ($mode == true)
								echo "Log: $filename Status: $test_worked  \n";		
						}
					}
				}
			}

			if ($test_nested == true) {

				//now do nested charts 
				foreach (LoadAvg::$_settings->general['network_interface'] as $interface => $value) {

					if (  !( isset(LoadAvg::$_settings->general['network_interface'][$interface]) 
						&& LoadAvg::$_settings->general['network_interface'][$interface] == "true" ) )
						continue;

					$filename = ( $logdir . sprintf($args->logfile, date('Y-m-d') , $interface ) );
																				
					if (file_exists($filename)) {
				    	$test_worked = true;
					}

					if ($mode == true)
						echo "Log: $filename Status: $test_worked  \n";
				}
			}

			return $test_worked;
	}


	


	/**
	 * checkWritePermissions
	 *
	 * Checks if specified file has write permissions.
	 *
	 * @param string $file path to file
	 */

	public function checkWritePermissions( $file )
	{
		if ( is_writable( $file ) )
			return true;
		else
			return false;
	}

	/**
	 * checkInstaller
	 *
	 * Checks if installer is removed or not
	 *
	 */

	public function checkInstaller() {

		$install_loc = "../install/index.php";

		if ( file_exists($install_loc) )
			return false;
		else
			return true;
	}

	/**
	 * checkInstall
	 *
	 * Checks if is still installation progress and redirects if TRUE.
	 *
	 */

	public function checkInstall() {

		$install_loc = "../install/index.php";

		if ( file_exists($install_loc) )
			header("Location: ../install/index.php");
	}



	/*
	 * build the chart data array here and patch to check for downtime
	 * as current charts connect last point to next point
	 * so when we are down / offline for more than logging interval
	 * charts dont accuratly show downtime
	 *
	 * send over chartData and contents
	 * return data in chartData
	 */



	/**
	 * parseInfo
	 *
	 * Parses ini file data for a module
	 *
	 * @param array $info array with info lines from the classes INI file
	 * @param array $variables variables to format lines
	 * @param string $class class name of module
	 * @return array $return formatted info lines
	 */

	public function parseInfo( $info, $variables, $class )
	{
		$return = array();
		foreach ( $info as $line ) {
			$line = json_decode($line);

			if (isset($line->type) && isset($line->filename)) {
				$return['info']['line'][] = array("type" => "file", "file" => 'modules' . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . $line->filename);
				continue;
			}

			if ( strstr($line->args, "|")) {
				$lineArgs = explode("|", $line->args);
				$args = array();
				foreach ($lineArgs as $arg) {
					$args[] = $variables[$arg];
				}
				$line = vsprintf($line->format, $args);
			} else {
				$line = sprintf($line->format, $variables[$line->args]);
			}
			$return['info']['line'][] = array( "type" => "line", "formatted_line" => $line );
		}

		return $return;
	}

	/**
	 * write_php_ini
	 *
	 * Writes data into INI file
	 *
	 * @param array $array array with data to write into INI file.
	 * @param string $file filename to write.
	 */

	public static function write_php_ini($array, $file)
	{
	    $res = array();
		$bval = null;
	    foreach($array as $key => $val)
	    {
	        if(is_array($val))
	        {
	            $res[] = "[$key]";
	            foreach($val as $skey => $sval) {
			if (is_array($sval)) {
				for ($i = 0; $i < count($sval); $i++) {
					$res[] = $skey . '[] = \'' . $sval[$i] . '\'';
				}
				#echo '<pre>';var_dump($res);echo'</pre>';
			} else {
	        	    	if (strpos($sval, ";") === 0)
		            		$res[] = $sval;
		            	else
	            			$res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
			}
	            }
	        }
	        else {
	        	if (strpos($val, ";") === 0)
	        		$res[] = $val;
	        	else
	        		$res[] = "$key = ".(is_numeric($val) ? $val : (strstr($val, '{') !== false) ? '\''.$val.'\'' : '"'.$val.'"');
	        }
	    }

	    if ($fp = fopen($file, 'w') ) {
	    	fwrite($fp, implode("\r\n", $res));
	    	fclose($fp);
	    }
	    //LoadAvg::safefilerewrite($file, implode("\r\n", $res));
	}

	//modified to not clean numeric values
	/*
	function write_php_ini($array, $file)
	{
	    $res = array();
	    foreach($array as $key => $val)
	    {
	        if(is_array($val))
	        {
	            $res[] = "[$key]";

	            //foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
	            foreach($val as $skey => $sval) 
	            	$res[] = "$skey = ".'"'.$sval.'"';
	        }
	        //else $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
	        else $res[] = "$key = ".'"'.$val.'"';
	    }
	    safefilerewrite($file, implode("\r\n", $res));
	}
	*/



	public static function write_module_ini($newsettings, $module_name)
	{

		$module_config_file = HOME_PATH . '/lib/modules/' . $module_name . '/' . strtolower( $module_name ) . '.ini';

		//$this->write_php_ini($newsettings, $module_config_file);
		self::write_php_ini($newsettings, $module_config_file);

	}

	/**
	 * safefilewrite
	 *
	 * Writes data to INI file and locks the file
	 *
	 * @param string $fileName filename
	 * @param array $dataToSave data to save to file
	 */

	public function safefilerewrite($fileName, $dataToSave, $mode = "w", $logs = false )
	{    

		//if file is new and is a logfile then we need to make it chmod 777
		//or we have issues between flies create using app and ones using cron
		//cron gives root permissions and app gives appache permissions
		$exists = file_exists ( $fileName );

		if ($fp = fopen($fileName, $mode))
	    {
	        $startTime = microtime();
	        do
	        {
	        	$canWrite = flock($fp, LOCK_EX);
	        	// If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
	        	if(!$canWrite) usleep(round(rand(0, 100)*1000));
	        } while ((!$canWrite)and((microtime()-$startTime) < 1000));

	        //file was locked so now we can store information
	        if ($canWrite)
	        {
	        	fwrite($fp, $dataToSave);
	            //flock($fp, LOCK_UN);
	        }

	        fclose($fp);

	        //if its a new log file fix permissions
	        if (!$exists && $logs==true ) {
	        	//echo "fix logs";
				chmod($fileName, 0777);
			}

	        return true;
	    }
	    else
	    {
	    	return false;
	    }

	}


	/**
	 * ini_merge
	 *
	 * used in settings modules to merge changes inot settings files
	 * may be depreciated now in exchange for array_replace
	 *
	 * @param string $config_ini config file array
	 * @param string $custom_ini data config file array to merge with
	 */

	 public static function ini_merge ($config_ini, $custom_ini) 
	 {
	 	foreach ($custom_ini AS $k => $v):
	    	if (is_array($v)):
	      		$config_ini[$k] = self::ini_merge($config_ini[$k], $custom_ini[$k]);
	    	else:
	      		$config_ini[$k] = $v;
	    	endif;
	  	endforeach;
	 
	 	return $config_ini;
	 }
	/**
	 * getLoggerInterval
	 *
	 * User login, checks username and password from default settings to match.
	 *
	 * @param string $username the username
	 * @param string $password the password
	 */

	public function getLoggerInterval( ) 
	{

		$interval = LoadAvg::$_settings->general['logger_interval'];

		if  ( $interval ) {

			$interval = $interval * 60;
			return $interval;

		} else {

			return false;
		}

	}




	/**
	 * sendApiData
	 *
	 * If API is activated sends data to server
	 *
	 * @param array $data array of data to send to the server
	 * @return string $result message returned from the server
	 */

	public function sendApiData( $data ) {

		// for debugging
		//var_dump($data); //exit;

		$url = self::$_settings->general['api']['url'];

		$user_url = $url . '/users/';
		$server_url = $url . '/servers/';

		//validate API access here
		$ch =  curl_init($server_url . self::$_settings->general['api']['server_token'] . '/' . self::$_settings->general['api']['key'] . '/v');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$account_valid = curl_exec($ch);

		//get server id from server token
		$ch =  curl_init($server_url . self::$_settings->general['api']['server_token'] . '/t');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_exists = curl_exec($ch);


		//echo $server_url.json_decode($server_exists)->id.'/data';

		//validation needs to happen on the sever this is still insecure!
		//need to pass api token and server token over in data push

		if( $server_exists != 'false' && $account_valid != 'false' ) 
		{

			//file_put_contents("file.txt", json_encode($data)); test data
			$curl = curl_init();

			// Set some options - we are passing in a useragent too here
			curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $server_url.json_decode($server_exists)->id.'/data',
		    CURLOPT_USERAGENT => 'LoadAvg Client',
		    CURLOPT_POST => 1,
		    CURLOPT_POSTFIELDS => array(
		      'data' => json_encode($data),
		    )
			));

			// Send the request & save response to $resp
			$resp = curl_exec($curl);

			// Close request to clear up some resources
			curl_close($curl);

			//used for debugging to file
			//file_put_contents("file.txt",$resp);
			
			return true;
		}

		return null;
	}


	/**
	 * testApiConnection
	 *
	 * Test if API connection is working
	 *
	 * @return string $result message returned from the server
	 */

	public function testApiConnection( $echo = false ) {

		$url = self::$_settings->general['api']['url'];

		$user_url = $url . '/users/';
		$server_url = $url . '/servers/';
		
		//validate users api key
		$ch =  curl_init($user_url . self::$_settings->general['api']['key'] . '/va');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$user_exists = curl_exec($ch);

		//val;idate server token
		$ch =  curl_init($server_url . self::$_settings->general['api']['server_token'] . '/vs');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_exists = curl_exec($ch);

		//validate api key against server token
		$ch =  curl_init($server_url . self::$_settings->general['api']['server_token'] . '/' . self::$_settings->general['api']['key'] . '/v');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_valid = curl_exec($ch);


		if ($echo) {

			echo ($user_exists == 'false' ?  'API Key : INVALID ' :  'API Key : VALID ');

			echo ($server_exists == 'false' ?  'Server Token : INVALID ' :  'Server Token : VALID ');
			
			echo ($server_valid == 'false' ?  'Server Access : INVALID ' :  'Server Access : VALID ');

		}

		//return server valid status
		if($server_valid == 'false') 
			return false;
		else
			return true;
	}



	public static function getNetworkInterfaces()
	{
		// $interfaces = exec("/sbin/ifconfig | grep -oP '^[a-zA-Z0-9]*' | paste -d'|' -s");
		//$interfaces = exec('/sbin/ifconfig | expand | cut -c1-8 | sort | uniq -u | awk -F: \'{print $1;}\' | tr "\\n" "|" | tr -d \' \' | sed \'s/|*$//g\'');

		exec("/sbin/ifconfig", $content);
		$interfaces = array();

		#foreach (preg_split("/\n\n/", $content) as $int) {
		foreach ( $content as $int ) {
		    preg_match("/^(.*)\s+(flags|Link)/ims", $int, $regex);

		        if (!empty($regex)) {
		                $interface = array();
		                //$interface['name'] = $regex[1];

		                //added a trim to the return value as on centos 6.5 we had whitespace
		                $interface['name'] =  trim ( (substr(trim($regex[1]), strlen(trim($regex[1]))-1, strlen(trim($regex[1]))) == ":") ? substr(trim($regex[1]), 0 , strlen(trim($regex[1]))-1) : $regex[1] );

		                //echo ':' . $interface['name'] . ':';

		                $interfaces[] = $interface;
		        }
		}

		return $interfaces;
	}


	/**
	 * setStartTime
	 *
	 * Sets startTime of page load
	 *
	 */

	public function setStartTime()
	{
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$this->timeStart = $time;
	}

	/**
	 * setFinishTime
	 *
	 * Sets finish time of page load
	 *
	 */

	public function setFinishTime()
	{
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$this->timeFinish = $time;
	}

	/**
	 * getPageLoadTime
	 *
	 * Returns page load time
	 *
	 * @return string $time page load time
	 */

	public function getPageLoadTime()
	{
		return round( ( $this->timeFinish - $this->timeStart ), 4 );
	}

	/**
	 * getDates
	 *
	 * Gets date range from logfiles to populate the select box from topbar
	 * NOTE: Was changed to static
	 *
	 * @return array $return array with list of dates
	 */

	public static function getDates()
	{
		$dates = array();
		foreach ( glob(dirname(__FILE__) . "/../logs/*.log") as $file ) {
			preg_match("/([0-9-]+)/", basename($file), $output_array);
			if ( isset( $output_array[0] ) && !empty( $output_array[0] ) )
				$dates[] = $output_array[0];
		}
		return array_unique($dates);
	}

	/**
	 * logUpdateCheck
	 *
	 * Logs to file every check for update(s).
	 *
	 */

	public function logUpdateCheck( $response )
	{
		$fh = fopen(dirname(__FILE__) . '/../logs/update.log', 'a+');
		$logLine = "Update check at " . date("Y-m-d H:i:s a") . " ---- Response: " . $response . PHP_EOL;
		if ( $fh ) {
-			fwrite($fh, $logLine);
			fclose($fh);
		}

	}

	/**
	 * checkForUpdate
	 *
	 * Checks for new versions of LoadAvg
	 *
	 */

	public function checkForUpdate()
	{

		if ( !isset($_SESSION['download_url'])) {
			if ( ini_get("allow_url_fopen") == 1) {

				#$response = file_get_contents("http://updates.loadavg.com/version.php?site_url=" . $_SERVER['SERVER_ADDR']  . "&ip=" . $_SERVER['SERVER_ADDR'] . "&version=" . self::$_settings->general['version'] . "&key=1");
				// $response = json_decode($response);

				$response = file_get_contents("http://updates.loadavg.com/version.php?site_url=" 
					. $_SERVER['SERVER_ADDR']  . "&ip=" . $_SERVER['SERVER_ADDR'] . "&version=" . self::$_settings->general['version'] . "&key=1");

				//log the action locally
				$this->logUpdateCheck( $response );

				//var_dump("http://updates.loadavg.com/version.php?site_url=" . $_SERVER['SERVER_ADDR']  . "&ip=" . $_SERVER['SERVER_ADDR'] . "&version=" . self::$_settings->general['version'] . "&key=1");

				 	$_SESSION['download_url'] = "http://www.loadavg.com/download/";

				if ( $response > self::$_settings->general['version'] ) {
				 	$_SESSION['download_url'] = "http://www.loadavg.com/download/";
				}
			}
		}
	}



	/**
	 * getTimezones
	 *
	 * Get the (cached) list of all possible timezones
	 *
	 */

	public static function getTimezones()
	{
		if (is_array(LoadAvg::$_timezones)) {
			return LoadAvg::$_timezones;
		}

		LoadAvg::$_timezones = array();

		$regions = array(
		    'Africa' => DateTimeZone::AFRICA,
		    'America' => DateTimeZone::AMERICA,
		    'Antarctica' => DateTimeZone::ANTARCTICA,
		    'Aisa' => DateTimeZone::ASIA,
		    'Atlantic' => DateTimeZone::ATLANTIC,
		    'Europe' => DateTimeZone::EUROPE,
		    'Indian' => DateTimeZone::INDIAN,
		    'Pacific' => DateTimeZone::PACIFIC
		);

		foreach ($regions as $name => $mask)
		{
		    $zones = DateTimeZone::listIdentifiers($mask);
		    foreach($zones as $timezone)
		    {
				// Lets sample the time there right now
				$time = new DateTime(NULL, new DateTimeZone($timezone));

				// Us dumb Americans can't handle millitary time
				$ampm = $time->format('H') > 12 ? ' ('. $time->format('g:i a'). ')' : '';

				// Remove region name and add a sample time
				LoadAvg::$_timezones[$name][$timezone] = substr($timezone, strlen($name) + 1) . ' - ' . $time->format('H:i') . $ampm;
			}
		}

		return LoadAvg::$_timezones;

	}

}