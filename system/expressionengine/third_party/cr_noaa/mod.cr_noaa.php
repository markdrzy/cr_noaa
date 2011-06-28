<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cr_noaa {

	var $return_data = '';
	
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
		
		die($ll);
	}
	
	function forecast()
	{
	}
	
	function _cache_ll($z,$lt,$ln)
	{
		$q = $this->EE->db->query("INSERT INTO `{$this->EE->db->dbprefix}cr_noaa_zipcache` (`zip`,`ll`) 
									VALUES ({$z},PointFromWKB(POINT({$lt},{$ln})));");
	}
	
	function _get_cached_ll($z)
	{
		$q = $this->EE->db->query("SELECT X(ll) AS lat, Y(ll) AS lng 
									FROM `{$this->EE->db->dbprefix}cr_noaa_zipcache`
									WHERE `zip` = {$z};");
									
		if ($q->num_rows() == 0) return FALSE;
		
		return $q->row('lat').','.$q->row('lng');
	}
	
	function _find_nearest_wxs($ll)
	{
		
	}
	
	function _refresh_wxs()
	{
		
	}

}

// END