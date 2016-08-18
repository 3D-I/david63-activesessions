<?php
/**
*
* @package Active Sessions Extension
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\activesessions\controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use david63\activesessions\ext;

/**
* Admin controller
*/
class admin_controller implements admin_interface
{
	const ACTIVE_SESSIONS_VERSION = '1.0.0';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \phpbb\user */
	protected $user;

	/** @var phpbb\language\language */
	protected $language;

	/** @var string Custom form action */
	protected $u_action;

	/**
	* Constructor for admin controller
	*
	* @param \phpbb\config\config				$config		Config object
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\request\request				$request	Request object
	* @param \phpbb\template\template			$template	Template object
	* @param \phpbb\pagination					$pagination
	* @param \phpbb\user						$user		User object
	* @param phpbb\language\language			$language
	*
	* @return \david63\activesessions\controller\admin_controller
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\pagination $pagination, \phpbb\user $user, \phpbb\language\language $language)
	{
		$this->config		= $config;
		$this->db  			= $db;
		$this->request		= $request;
		$this->template		= $template;
		$this->pagination	= $pagination;
		$this->user			= $user;
		$this->language		= $language;
	}

	/**
	* Display the output for this extension
	*
	* @return null
	* @access public
	*/
	public function display_output()
	{
		// Add the language file
		$this->language->add_lang('acp_activesessions', 'david63/activesessions');

		// Start initial var setup
		$action			= $this->request->variable('action', '');
		$start			= $this->request->variable('start', 0);
		$fc				= $this->request->variable('fc', '');
		$sort_key		= $this->request->variable('sk', 's');
		$sd = $sort_dir	= $this->request->variable('sd', 'd');

		$sort_dir		= ($sort_dir == 'd') ? ' DESC' : ' ASC';

		$order_ary = array(
			'i'	=> 's.session_ip' . $sort_dir. ', u.username_clean ASC',
			's'	=> 's.session_start' . $sort_dir. ', u.username_clean ASC',
			'u'	=> 'u.username_clean' . $sort_dir,
		);

		$filter_by = '';
		if ($fc == 'other')
		{
			for ($i = ord($this->language->lang('START_CHARACTER')); $i	<= ord($this->language->lang('END_CHARACTER')); $i++)
			{
				$filter_by .= ' AND u.username_clean ' . $this->db->sql_not_like_expression(utf8_clean_string(chr($i)) . $this->db->get_any_char());
			}
		}
		else if ($fc)
		{
			$filter_by .= ' AND u.username_clean ' . $this->db->sql_like_expression(utf8_clean_string(substr($fc, 0, 1)) . $this->db->get_any_char());
		}

	   	$sql = $this->db->sql_build_query('SELECT', array(
			'SELECT'	=> 'u.user_id, u.username, u.username_clean, u.user_colour, s.*, f.forum_id, f.forum_name',
			'FROM'		=> array(
				USERS_TABLE		=> 'u',
				SESSIONS_TABLE	=> 's',
			),
			'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(FORUMS_TABLE => 'f'),
						'ON'	=> 's.session_forum_id = f.forum_id',
					),
				),
			'WHERE'		=> 'u.user_id = s.session_user_id
				AND s.session_time >= ' . (time() - ($this->config['session_length'] * 60)) . $filter_by,
			'ORDER_BY'	=> ($sort_key == '') ? 'u.username_clean' : $order_ary[$sort_key],
		));

		$result = $this->db->sql_query_limit($sql, $this->config['topics_per_page'], $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->template->assign_block_vars('active_sessions', array(
				'ADMIN'				=> ($row['session_admin']) ? $this->language->lang('YES') : $this->language->lang('NO'),
				'AUTO_LOGIN'		=> ($row['session_autologin']) ? $this->language->lang('YES') : $this->language->lang('NO'),
				'BROWSER'			=> $row['session_browser'],
				'FORUM'				=> ($row['forum_id'] > 0) ? $row['forum_name'] : '',
				'LAST_VISIT'		=> $this->user->format_date($row['session_last_visit']),
				'SESSION_FORWARD'	=> $row['session_forwarded_for'],
				'SESSION_ID'		=> $row['session_id'],
				'SESSION_IP'		=> $row['session_ip'],
				'SESSION_KEY'		=> $row['session_id'] . $row['user_id'], // Create a unique key for the js script
				'SESSION_ONLINE'	=> ($row['session_viewonline']) ? $this->language->lang('YES') : $this->language->lang('NO'),
				'SESSION_PAGE'		=> $row['session_page'],
				'SESSION_START'		=> $this->user->format_date($row['session_start']),
				'SESSION_TIME'		=> $this->user->format_date($row['session_time']),
				'USERNAME'			=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
		   	));
		}
		$this->db->sql_freeresult($result);

		$sort_by_text	= array('u' => $this->language->lang('SORT_USERNAME'), 'i' => $this->language->lang('SESSION_IP'), 's' => $this->language->lang('SESSION_START'));
		$limit_days		= array();
		$s_sort_key		= $s_limit_days = $s_sort_dir = $u_sort_param = '';

		gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sd, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);

		// Get total session count for output
		$sql = $this->db->sql_build_query('SELECT', array(
			'SELECT'	=> 'COUNT(s.session_id) AS total_sessions',
			'FROM'		=> array(
				USERS_TABLE		=> 'u',
				SESSIONS_TABLE	=> 's',
			),
			'WHERE'		=> 'u.user_id = s.session_user_id' . $filter_by,
		));

		$result			= $this->db->sql_query($sql);
		$session_count	= (int) $this->db->sql_fetchfield('total_sessions');

		$this->db->sql_freeresult($result);

		$action = "{$this->u_action}&amp;sk=$sort_key&amp;sd=$sd";
		$link = ($session_count) ? adm_back_link($action . '&amp;start=' . $start) : '';

		if ($session_count == 0)
		{
			trigger_error($this->language->lang('NO_SESSION_DATA') . $link);
		}

		$start = $this->pagination->validate_start($start, $this->config['topics_per_page'], $session_count);
		$this->pagination->generate_template_pagination($action, 'pagination', 'start', $session_count, $this->config['topics_per_page'], $start);

		$first_characters		= array();
		$first_characters['']	= $this->language->lang('ALL');
		for ($i = ord($this->language->lang('START_CHARACTER')); $i	<= ord($this->language->lang('END_CHARACTER')); $i++)
		{
			$first_characters[chr($i)] = chr($i);
		}
		$first_characters['other'] = $this->language->lang('OTHER');

		foreach ($first_characters as $char => $desc)
		{
			$this->template->assign_block_vars('first_char', array(
				'DESC'		=> $desc,
				'U_SORT'	=> $action . '&amp;fc=' . $char,
			));
		}

		$this->template->assign_vars(array(
			'ACTIVE_SESSIONS_VERSION'	=> ext::ACTIVE_SESSIONS_VERSION,

			'S_SORT_DIR'				=> $s_sort_dir,
			'S_SORT_KEY'				=> $s_sort_key,
			'TOTAL_USERS'				=> $this->language->lang('TOTAL_SESSIONS', (int) $session_count),
			'U_ACTION'					=> $action,

		));
	}

	/**
	* Set page url
	*
	* @param string $u_action Custom form action
	* @return null
	* @access public
	*/
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
