<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cr_noaa_upd {

	var $version = '0.1';
	var $modname = 'Cr_noaa';
	var $short_modname = 'cr_noaa';
	
	function __construct()
	{
		$this->EE =& get_instance();
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
		
		// Delete Cache Files
		foreach (glob('system/expressionengine/cache/cr_noaa/*.xml') as $f)
		{
			unlink($f);
		}
		// ... and Directory
		rmdir(APPPATH . 'cache/' . $this->short_modname);
		
		return TRUE;
	}

}

// END