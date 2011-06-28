<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cr_noaa {

	var $return_data = '';
	var $modname = 'Cr_noaa';
	var $short_modname = 'cr_noaa';
	
	function __construct()
	{
		$this->EE =& get_instance();
	}
	
	function current_conditions()
	{
		$zip = $this->EE->TMPL->fetch_param('zip');
		$ll = $this->EE->TMPL->fetch_param('lat_lon');
		if ($zip === FALSE && $ll === FALSE) return FALSE;
		
		if ($ll == FALSE && ($ll = $this->_get_cached_ll($zip)) === FALSE)
		{
			$g = 'http://maps.google.com/maps/api/geocode/xml?address='.$zip.'&sensor=false';
			$x = simplexml_load_file($g);
			$ll = $x->result->geometry->location->lat.','.$x->result->geometry->location->lng;
			
			$this->_cache_ll($zip,$x->result->geometry->location->lat,$x->result->geometry->location->lng);
		}
		
		die($this->_find_nearest_wxs($ll));
	}
	
	function forecast()
	{
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
	
	function _find_nearest_wxs($ll)
	{
		$ls = explode(',',$ll);
		$r = $this->EE->db->query("SELECT name, ( 3959 * acos( cos( radians('{$ls[0]}') ) * cos( radians( lat ) ) * cos( radians( lng ) - radians('{$ls[1]}') ) + sin( radians('{$ls[0]}') ) * sin( radians( lat ) ) ) ) AS distance 
									FROM {$this->EE->db->dbprefix}{$this->short_modname}_stations 
									HAVING distance < 150 ORDER BY distance LIMIT 1");
		if ($r->num_rows() == 0) return FALSE;
		
		return $r->row('name');
	}
	
	function _refresh_wxs()
	{
		$d = simplexml_load_file('http://www.weather.gov/xml/current_obs/index.xml');
		foreach ($d->station as $s)
		{
			$sql[] = "('{$s->station_id}',{$s->latitude},{$s->longitude})";
		}
		$this->EE->db->query("TRUNCATE TABLE `{$this->EE->db->dbprefix}{$this->short_modname}_stations`;");
		$this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}{$this->short_modname}_stations` (`name`, `lat`, `lng`) 
								VALUES ".implode(',',$sql).';');
		
		return TRUE;
	}

}

// END