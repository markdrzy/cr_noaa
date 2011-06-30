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
		$wxs = (isset($_COOKIE[$this->short_modname . '_wxs']) && $_COOKIE[$this->short_modname . '_wxs'] != '')? 
			$_COOKIE[$this->short_modname . '_wxs']: FALSE;
		$zip = $this->EE->TMPL->fetch_param('zip');
		$ll = $this->EE->TMPL->fetch_param('lat_lon');
		return $this->_get_wx('c',$wxs,$zip,$ll);
	}
	
	function forecast()
	{
		$wxs = (isset($_COOKIE[$this->short_modname . '_wxs']) && $_COOKIE[$this->short_modname . '_wxs'] != '')? 
			$_COOKIE[$this->short_modname . '_wxs']: FALSE;
		$zip = $this->EE->TMPL->fetch_param('zip');
		$ll = $this->EE->TMPL->fetch_param('lat_lon');
		return $this->_get_wx('f',$wxs,$zip,$ll);
	}
	
	function _get_wx($type,$wxs=FALSE,$zip=FALSE,$ll=FALSE)
	{
		
		/* In order of priority: */
		/* 1: WXS */
		if ($wxs !== FALSE)
		{
			return $this->_fetch_wx_data($wxs,$type);
		}
		
		/* 2: ZIP */
		if ($zip !== FALSE)
		{
			return $this->_fetch_wx_data($this->_find_nearest_wxs('zip',$zip),$type);
		}
		
		/* 3: LL */
		if ($ll !== FALSE)
		{
			return $this->_fetch_wx_data($this->_find_nearest_wxs('ll',$ll),$type);
		}
		
		/* Nothing else? Break. */
		return FALSE;
	}
	
	function _fetch_wx_data($station,$type)
	{
		switch($type)
		{
			case 'f':
				// Get Station LL
				$r = $this->EE->db->query("SELECT `lat`, `lng` 
									FROM `{$this->EE->db->dbprefix}{$this->short_modname}_stations` 
									WHERE `name` = '{$station}';");
				if ($r->num_rows() == 0)
				{
					$this->log_message('Unable to find station lat/lng.',2);
					return FALSE;
				}
				$ll = array('lat'=>$r->row('lat'),'lng'=>$r->row('lng'));
				
				// Generate URL
				$url = 'http://www.weather.gov/forecasts/xml/sample_products/browser_interface/ndfdXMLclient.php?'
						. 'lat=' . $ll['lat'] . '&lon=' . $ll['lng']
						. '&product=glance';
				break;
			
			case 'c':
			default:
				// Generate URL
				$url = 'http://weather.gov/xml/current_obs/' . $station . '.xml';
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
	
	function _find_nearest_wxs($t,$td)
	{
		switch($t)
		{
			case 'zip':
				// Look for cached zip stations.
				$cr = $this->EE->db->query("SELECT station 
											FROM `{$this->EE->db->dbprefix}{$this->short_modname}_zip_stations` 
											WHERE `zip` = {$td};");
				if ($cr->num_rows() > 0) return $cr->row('station');
				
				// Otherwise, look for cached zip ll...
				$q = $this->EE->db->query("SELECT X(ll) AS lat, Y(ll) AS lng 
									FROM `{$this->EE->db->dbprefix}{$this->short_modname}_zipcache`
									WHERE `zip` = {$td};");
				$ll = ($q->num_rows() > 0)? $q->row('lat') . ',' . $q->row('lng'): FALSE;
				if ($ll !== FALSE)
				{
					die($ll);
					$wxs = $this->_find_nearest_wxs('ll',$ll);
					$this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_zip_stations` 
											(`zip`,`station`) VALUES ({$td},'{$wxs}');"); // cache it for later
					return $wxs;
				}
				else
				{
					// ... or geocode the zip.
					$g = 'http://maps.google.com/maps/api/geocode/xml?address='.$td.'&sensor=false';
					if ( ($x = simplexml_load_file($g)) === FALSE)
					{
						$this->log_message('Geocode failed.',2);
					}
					$ll = array((string) $x->result->geometry->location->lat,(string) $x->result->geometry->location->lng);
					$this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_zipcache` (`zip`,`ll`) 
											VALUES ({$z},PointFromWKB(POINT({$ll[0]},{$ll[1]})));"); // cache it for later
					$wxs = $this->_find_nearest_wxs('ll',implode(',',$ll));
					$this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_zip_stations` 
											(`zip`,`station`) VALUES ({$td},'{$wxs}');"); // cache it for later
					return $wxs;
				}
				
				
				break;
			
			case 'll':
				// No choice but to crawl the entire Station DB. This is why LL is non-preferred.
				$ls = explode(',',$td);
				$r = $this->EE->db->query("SELECT name, ( 3959 * acos( cos( radians('{$ls[0]}') ) * cos( radians( lat ) ) * cos( radians( lng ) - radians('{$ls[1]}') ) + sin( radians('{$ls[0]}') ) * sin( radians( lat ) ) ) ) AS distance 
											FROM {$this->EE->db->dbprefix}{$this->short_modname}_stations 
											HAVING distance < 150 ORDER BY distance LIMIT 1");
				if ($r->num_rows() == 0)
				{
					$this->log_message('Unable to locate weather station.',2);
					return FALSE;
				}
				
				return $r->row('name');
				break;
		}
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
				'addon_name'    => $this->load->Lang->line('cr_noaa_module_name'),
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