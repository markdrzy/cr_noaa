<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Include cr_noaa Core Mod
 */
require_once PATH_THIRD . 'cr_noaa/mod.cr_noaa' . EXT;

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
		$mod_id = $this->EE->db->insert_id();
		
		// Fire up the forge
		$this->EE->load->dbforge();
		
		// Check for and Create ModuleMeta Table if Needed
		$r = $this->EE->db->query("SELECT COUNT(*) AS `table_exists` FROM `information_schema`.`tables` 
									WHERE `table_schema` = '{$this->EE->db->database}' 
									&& `table_name` = '{$this->EE->db->dbprefix}cr_module_meta';");
		if ($r->row('table_exists') == '0')
		{
			$modmeta_fields = array(
				'id'			=> array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'mod_id'		=> array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE),
				'mod_name'		=> array('type' => 'varchar', 'constraint' => '32'),
				'mod_settings'	=> array('type' => 'text')
			);
			$this->EE->dbforge->add_field($modmeta_fields);
			$this->EE->dbforge->add_key('id',TRUE);
			$this->EE->dbforge->create_table('cr_module_meta',TRUE);
			
			$mm_data = array(
				'id'			=> '',
				'mod_id'		=> $mod_id,
				'mod_name'		=> $this->modname,
				'mod_settings'	=> ''
			);
			$this->EE->db->insert('cr_module_meta',$mm_data);
		}
		
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
			'station_id'	=> array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'name'			=> array('type' => 'varchar', 'constraint' => '6'),
			'lat'			=> array('type' => 'float', 'constraint' => '10,6'),
			'lng'			=> array('type' => 'float', 'constraint' => '10,6')
		);
		$this->EE->dbforge->add_field($station_fields);
		$this->EE->dbforge->add_key('station_id',TRUE);
		$this->EE->dbforge->create_table($this->short_modname.'_stations');
		
		// Pre-populate Stations Table
		$this->cr_noaa->_refresh_wxs();
		
		// Create Zip2Stations Table
		$zip_stations_fields = array(
			'zip'			=> array('type' => 'int','constraint' => '10', 'unsigned' => TRUE),
			'station'		=> array('type' => 'varchar', 'constraint' => '6')
		);
		$this->EE->dbforge->add_field($zip_stations_fields);
		$this->EE->dbforge->add_key('zip',TRUE);
		$this->EE->dbforge->create_table($this->short_modname.'_zip_stations');
		$this->EE->db->query('CREATE INDEX `zip_station_index` 
								ON `'.$this->EE->db->dbprefix.$this->short_modname.'_zip_stations` (`zip`);');
		
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
		
		// Drop ModMeta Table (if it's not being used otherwise)
		$this->EE->db->where('mod_name',$this->modname);
		$this->EE->db->delete('cr_module_meta');
		$this->EE->db->select('mod_id');
		$q = $this->EE->db->get('cr_module_meta');
		if ($q->num_rows() == 0)
		{
			$this->EE->dbforge->drop_table('cr_module_meta');
		}
		
		// Drop Zipcache Table
		$this->EE->dbforge->drop_table($this->short_modname.'_zipcache');
		
		// Drop Stations Table
		$this->EE->dbforge->drop_table($this->short_modname.'_stations');
		
		// Drop Zip Stations Table
		$this->EE->dbforge->drop_table($this->short_modname.'_zip_stations');
		
		// Delete Cache Files
		foreach (glob(APPPATH . 'cache/' . $this->short_modname . '/*.json') as $f)
		{
			unlink($f);
		}
		// ... and Directory
		rmdir(APPPATH . 'cache/' . $this->short_modname);
		
		return TRUE;
	}

}

// END