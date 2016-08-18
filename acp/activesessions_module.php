<?php
/**
*
* @package Active Sessions Extension
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\activesessions\acp;

class activesessions_module
{
	public $u_action;

	function main($id, $mode)
	{
		global $phpbb_container, $user;

		$this->tpl_name		= 'active_sessions';
		$this->page_title	= $user->lang('ACTIVE_SESSIONS');

		// Get an instance of the admin controller
		$admin_controller = $phpbb_container->get('david63.activesessions.admin.controller');

		// Make the $u_action url available in the admin controller
		$admin_controller->set_page_url($this->u_action);

		$admin_controller->display_output();
	}
}