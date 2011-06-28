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
		$ll = $this->EE->TMPL->fetch_param('lat_lon')
		if ($zip === FALSE && $ll === FALSE) return FALSE;
		
		if ($ll === FALSE)
		{
			$g = 'http://maps.google.com/maps/api/geocode/xml?address='.$zip.'&sensor=false';
			$x = simplexml_load_file($g);
			$ll = $x->result->geometry->location->lat.','.$x->result->geometry->location->lng;
		}
		
		
	}
	
	function forecast()
	{
	}

}

// END