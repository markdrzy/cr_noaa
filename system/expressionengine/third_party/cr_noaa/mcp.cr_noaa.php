<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Include cr_noaa Core Mod
 */
require_once PATH_THIRD.'cr_noaa/mod.cr_noaa.php';

class Cr_noaa_mcp {

	var $cr_noaa;

	function __construct()
	{
		$this->EE =& get_instance();
		$this->cr_noaa = new Cr_noaa();
	}
	
	function index()
	{
	}

}

// END