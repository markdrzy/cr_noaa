<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Include cr_noaa Core Mod
 */
require_once PATH_THIRD.'cr_noaa/mod.cr_noaa.php';

class Cr_noaa_upd {

	var $version = '0.1';
	var $modname = 'Cr_noaa';
	var $short_modname = 'cr_noaa';
	var $cr_noaa;
	
	function __construct()
	{
		$this->EE =& get_instance();
		$this->cr_noaa = new Cr_noaa();
	}
	
	function install()
	{
		$data = array(
			'module_name'			=> $this->modname,
			'module_version'		=> $this->version,
			'has_cp_backend'		=> 'n',
			'has_publish_fields'	=> 'n'
		);
		$this->EE->db->insert('modules',$data);
		
		// Fire up the forge
		$this->EE->load->dbforge();
		
		// Create Zipcache Table
		$zipcache_fields = array(
			'zip'			=> array('type' => 'int','constraint' => '10', 'unsigned' => TRUE),
			'll'			=> array('type' => 'POINT')
		);
		$this->EE->dbforge->add_field($zipcache_fields);
		$this->EE->dbforge->add_key('zip',TRUE);
		$this->EE->dbforge->create_table($this->short_modname.'_zipcache');
		
		// Create Stations Table
		$station_fields = array(
			'station_id'	=> array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'autoincrement' => TRUE),
			'name'			=> array('type' => 'varchar', 'constraint' => '6'),
			'lat'			=> array('type' => 'decimal', 'constraint' => '3,6'),
			'lng'			=> array('type' => 'decimal', 'constraint' => '3,6')
		);
		$this->EE->dbforge->add_field($station_fields);
		$this->EE->dbforge->add_key('station_id',TRUE);
		$this->EE->dbforge->create_table($this->short_modname.'_stations');
		
		// Pre-populate Stations Table
		$this->cr_noaa->_refresh_wxs();
		
		// Create Cache Directory
		if ( ! mkdir(APPPATH . 'cache/' . $this->short_modname)) return FALSE;
		
		return TRUE;
	}
	
	function update($current = '')
	{
		return FALSE;
	}
	
	function uninstall()
	{
		$this->EE->db->select('module_id');
		$query = $this->EE->db->get_where('modules',array('module_name'=>$this->modname));
		$this->EE->db->where('module_id',$query->row('module_id'));
		$this->EE->db->delete('module_member_groups');
		$this->EE->db->where('module_name',$this->modname);
		$this->EE->db->delete('modules');
		
		// Fire up the forge
		$this->EE->load->dbforge();
		
		// Drop Zipcache Table
		$this->EE->dbforge->drop_table($this->short_modname.'_zipcache');
		
		// Drop Stations Table
		$this->EE->dbforge->drop_table($this->short_modname.'_stations');
		
		// Delete Cache Files
		foreach (glob(APPPATH . 'cache/' . $this->short_modname . '/*.xml') as $f)
		{
			unlink($f);
		}
		// ... and Directory
		rmdir(APPPATH . 'cache/' . $this->short_modname);
		
		return TRUE;
	}

}

// END