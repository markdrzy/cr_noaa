<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cr_noaa {

	var $return_data = '';
	var $modname = 'Cr_noaa';
	var $short_modname = 'cr_noaa';
	
	function __construct()
	{
		$this->EE =& get_instance();
		
		// Load the OmniLogger class.
		if (file_exists(PATH_THIRD .'omnilog/classes/omnilogger' .EXT))
		{
			include_once PATH_THIRD .'omnilog/classes/omnilogger' .EXT;
		}
	}
	
	function current_conditions()
	{
		$this->_get_wx('c');
	}
	
	function forecast()
	{
		$this->_get_wx('f');
	}
	
	function _get_wx($type)
	{
		if ( ! isset($_COOKIE[$this->short_modname.'_wxs']) && $_COOKIE[$this->short_modname.'_wxs'] != '')
		{
			$zip = $this->EE->TMPL->fetch_param('zip');
			$ll = $this->EE->TMPL->fetch_param('lat_lon');
			if ($zip === FALSE && $ll === FALSE) return FALSE;
			
			if ($ll == FALSE && ($ll = $this->_get_cached_ll($zip)) === FALSE)
			{
				$g = 'http://maps.google.com/maps/api/geocode/xml?address='.$zip.'&sensor=false';
				if ( ($x = simplexml_load_file($g)) === FALSE)
				{
					$this->log_message('Geocode failed.',2);
				}
				$ll = $x->result->geometry->location->lat.','.$x->result->geometry->location->lng;
				
				$this->_cache_ll($zip,$x->result->geometry->location->lat,$x->result->geometry->location->lng);
			}
			
			$wxs = $this->_find_nearest_wxs($ll,$zip);
			setcookie($this->short_modname.'_wxs',$wxs,time()+3600,'/');
		}
		else
		{
			$wxs = $_COOKIE[$this->short_modname.'_wxs'];
		}
		
		$wxc = $this->_fetch_wx_data($wxs,$type);
	}
	
	function _fetch_wx_data($station,$type)
	{
		switch($type)
		{
			case 'f':
				$url = 'http://weather.gov/xml/current_obs/' . $station . '.xml';
				break;
			
			case 'c':
			default:
				$ll = $this->_get_station_ll($station);
				$url = 'http://www.weather.gov/forecasts/xml/sample_products/browser_interface/ndfdBrowserClientByDay.php?'
						. 'lat=' . $ll['lat'] . '&lon=' . $ll['lng']
						. '&format=24+hourly&startDate='.date('Y-m-d',time()).'&numDays=5';
						/* UNSUMMARIZED DATA
						'http://www.weather.gov/forecasts/xml/sample_products/browser_interface/ndfdXMLclient.php?'
						. 'lat=' . $ll['lat'] . '&lon=' . $ll['lng']
						. '&product=glance';
						*/
						/* EXTENDED DETAILS
						. '&begin=2004-01-01T00:00:00'
						. '&end=2013-04-20T00:00:00'
						. '&product=time-series&maxt=maxt&mint=mint&wx=wx&pop12=pop12&icons=icons';
						*/
				break;
		}
		$file = APPPATH . 'cache/' . $this->short_modname . '/' . $station . '-' . $type . '.json';
		
		if (file_exists($file) && (filemtime($file) > (time() - 60 * 15 ))) {
			$data = file_get_contents($file);
		} else {
			$o = array();
			if ( ($d = simplexml_load_file($url)) === FALSE)
			{
				$this->log_message('Unable to load wx data (' . $station . ', ' . $type . ')',2);
				return FALSE;
			}
			switch($type)
			{
				case 'f':
					$d = (array) $d->data;
					$dates = (array) $d['time-layout'][0];
					$params = (array) $d['parameters'];
					
					$o['temps'] = array();
					foreach ($params['temperature'] as $t)
					{
						$temp_item = (array) $t;
						$temp_attr = (array) $t->attributes();
						$temp_type = $temp_attr['@attributes']['type'];
						foreach ($temp_item['value'] as $td)
						{
							$o['temps'][$temp_type][] = $td;
						}
					}
					
					$o['precip'] = array();
					$prob_precip = (array) $params['probability-of-precipitation'];
					foreach ($prob_precip['value'] as $p)
					{
						$o['precip'][] = $p;
					}
					
					$o['conditions'] = array();
					$catt = 'weather-conditions';
					$weather = (array) $params['weather'];
					foreach ($weather['weather-conditions'] as $wxa)
					{
						$wxa = (array) $wxa;
						$o['conditions'][] = $wxa['@attributes']['weather-summary'];
					}
					
					$o['icons'] = array();
					$wx_icons = (array) $params['conditions-icon'];
					foreach ($wx_icons['icon-link'] as $i)
					{
						$o['icons'][] = $i;
					}
					
					$o['info_url'] = $d['moreWeatherInformation'];
				break;
				
				case 'c':
				default:
					foreach ($d as $line => $data)
					{
						if ($line != 'image')
						{
							$o[$line] = (string) $data;
						}
					}
				break;
			}
			
			$data = json_encode($o);
			file_put_contents($file, $data, LOCK_EX);
		}
		
		return $data;
	}
	
	function _cache_ll($z,$lt,$ln)
	{
		$q = $this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_zipcache` (`zip`,`ll`) 
									VALUES ({$z},PointFromWKB(POINT({$lt},{$ln})));");
	}
	
	function _get_cached_ll($z)
	{
		$q = $this->EE->db->query("SELECT X(ll) AS lat, Y(ll) AS lng 
									FROM `{$this->EE->db->dbprefix}{$this->short_modname}_zipcache`
									WHERE `zip` = {$z};");
									
		if ($q->num_rows() == 0) return FALSE;
		
		return $q->row('lat').','.$q->row('lng');
	}
	
	function _find_nearest_wxs($ll,$z=FALSE)
	{
		if ($z)
		{
			$r = $this->EE->db->query("SELECT station 
										FROM `{$this->EE->db->dbprefix}{$this->short_modname}_zip_stations` 
										WHERE `zip` = {$z};");
			if ($r->num_rows() > 0) return $r->row('station');
		}
		$ls = explode(',',$ll);
		$r = $this->EE->db->query("SELECT name, ( 3959 * acos( cos( radians('{$ls[0]}') ) * cos( radians( lat ) ) * cos( radians( lng ) - radians('{$ls[1]}') ) + sin( radians('{$ls[0]}') ) * sin( radians( lat ) ) ) ) AS distance 
									FROM {$this->EE->db->dbprefix}{$this->short_modname}_stations 
									HAVING distance < 150 ORDER BY distance LIMIT 1");
		if ($r->num_rows() == 0)
		{
			$this->log_message('Unable to locate weather station.',2);
			return FALSE;
		}
		
		return $r->row('name');
	}
	
	function _refresh_wxs()
	{
		if ( ($d = simplexml_load_file('http://www.weather.gov/xml/current_obs/index.xml')) === FALSE)
		{
			$this->log_message('Unable to load weather stations from NOAA.',3);
			return FALSE;
		}
		else
		{
			$this->log_message('Weather stations download sucessful.',1);
		}
		foreach ($d->station as $s)
		{
			$sql[] = "('{$s->station_id}',{$s->latitude},{$s->longitude})";
		}
		$this->EE->db->query("TRUNCATE TABLE `{$this->EE->db->dbprefix}{$this->short_modname}_stations`;");
		$this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_stations` (`name`, `lat`, `lng`) 
								VALUES ".implode(',',$sql).';');
		
		return TRUE;
	}
	
	function _get_station_ll($s)
	{
		$r = $this->EE->db->query("SELECT `lat`, `lng` 
									FROM `{$this->EE->db->dbprefix}{$this->short_modname}_stations` 
									WHERE `name` = '{$s}';");
		if ($r->num_rows() == 0)
		{
			$this->log_message('Unable to find station lat/lng.',2);
			return FALSE;
		}
		return array($r->row(),$r->row());
	}
	
	
	
	/**
	* Logs an error to OmniLog.
	*
	* @access  public
	* @param   string      $message        The log entry message.
	* @param   int         $severity       The log entry 'level'.
	* @return  void
	*/
	public function log_message($message, $severity = 1)
	{
		if (class_exists('Omnilog_entry') && class_exists('Omnilogger'))
		{
			switch ($severity)
			{
				case 3:
				$notify = TRUE;
				$type   = Omnilog_entry::ERROR;
				break;
				
				case 2:
				$notify = FALSE;
				$type   = Omnilog_entry::WARNING;
				break;
				
				case 1:
				default:
				$notify = FALSE;
				$type   = Omnilog_entry::NOTICE;
				break;
			}
			
			$omnilog_entry = new Omnilog_entry(array(
				'addon_name'    => 'Example Add-on',
				'date'          => time(),
				'message'       => $message,
				'notify_admin'  => $notify,
				'type'          => $type
			));
			
			OmniLogger::log($omnilog_entry);
		}
	}

}

// END