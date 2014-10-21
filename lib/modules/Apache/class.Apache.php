<?php
/**
* LoadAvg - Server Monitoring & Analytics
* http://www.loadavg.com
*
* Apache Module for LoadAvg
* 
* @version SVN: $Id$
* @link https://github.com/loadavg/loadavg
* @author Karsten Becker
* @copyright 2014 Sputnik7
*
* This file is licensed under the Affero General Public License version 3 or
* later.
*/

class Apache extends LoadAvg
{
	public $logfile; // Stores the logfile name & path

	/**
	 * __construct
	 *
	 * Class constructor, appends Module settings to default settings
	 *
	 */
	public function __construct()
	{
		$this->setSettings(__CLASS__, parse_ini_file(strtolower(__CLASS__) . '.ini', true));
	}

	/**
	 * logApacheUsageData
	 *
	 * Retrives data and logs it to file
	 *
	 * @param string $type type of logging default set to normal but it can be API too.
	 * @return string $string if type is API returns data as string
	 *
	 */

	public function logData( $type = false )
	{
		$class = __CLASS__;
		$settings = LoadAvg::$_settings->$class;


		$url = $settings['settings']['serverstatus'];
		//$url = "http://localhost/server-status";

		$parseUrl = $url . "/?auto";

		$locate = "CPULoad";

		$dataValue = $this->getApacheDataValue($parseUrl, $locate);

		if ($dataValue == null)
			$dataValue = 0;

	    $string = time() . '|' . $dataValue . "\n";

		if ( $type == "api") {
			return $string;
		} else {
			$filename = sprintf($this->logfile, date('Y-m-d'));
			$this->safefilerewrite($filename,$string,"a",true);
		}
	}


	/**
	 * getApacheDataValue
	 *
	 * Gets data from logfile, formats and parses it to pass it to the chart generating function
	 *
	 * @return array $dataValue data retrived from mod_status
	 *
	 */

	public function getApacheDataValue($parseurl, $locate) 
	{


		$f = implode(file($parseurl."?dat=".time()),"");

		$active = explode("\n", $f );

		$dataValue = false;

		foreach ($active as $i => $value) {

			$pieces = explode(": ", $active[$i]);

			if ($pieces[0]==$locate) {
				$dataValue = $pieces[1];
			}

		}

		return($dataValue);
    }
    
}




