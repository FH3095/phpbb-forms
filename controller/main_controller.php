<?php

namespace fh3095\forms\controller;

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
require_once ($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
require_once ($phpbb_root_path . 'includes/message_parser.' . $phpEx);

use Symfony\Component\DependencyInjection\ContainerInterface;

class main_controller extends \phpbb\pages\controller\main_controller implements 
		main_interface
{

	const FORM_NAME = 'fh3095_forms';

	const VAR_TAG_NAME = 'fvar';

	const SETTINGS_NAME = 'FH3095_FORMS_SETTINGS';

	protected $config;

	protected $request;

	protected $request_vars;

	protected $settings;

	public function __construct(\phpbb\config\config $config, 
			\phpbb\symfony_request $request, 
			\phpbb\request\request $request_vars, \phpbb\auth\auth $auth, 
			ContainerInterface $container, \phpbb\controller\helper $helper, 
			\phpbb\template\template $template, \phpbb\user $user)
	{
		$this->config = $config;
		$this->request = $request;
		$this->request_vars = $request_vars;
		parent::__construct($auth, $container, $helper, $template, $user);
	}

	public function display($route)
	{
		$form_target = append_sid(
				$this->request->getSchemeAndHttpHost() .
						 $this->request->getBaseUrl() .
						 $this->request->getPathInfo());
		$this->template->assign_var('S_FORM_TARGET', $form_target);
		
		if ($this->config['enable_post_confirm'] &&
				 ! $this->user->data['is_registered'])
		{
			$captcha = $this->container->get('captcha.factory')->get_instance(
					$this->config['captcha_plugin']);
			$captcha->init(CONFIRM_POST);
			if ($captcha->is_solved() === false)
			{
				$this->template->assign_vars(
						array(
							'S_CONFIRM_CODE' => true,
							'CAPTCHA_TEMPLATE' => $captcha->get_template()
						));
			}
			else
			{
				$this->template->assign_var('S_HIDDEN_FIELDS', 
						build_hidden_fields($captcha->get_hidden_fields()));
			}
		}
		
		add_form_key(self::FORM_NAME);
		
		return parent::display($route);
	}

	public function submit($route)
	{
		// Captcha Check
		$captcha = null;
		if ($this->config['enable_post_confirm'] &&
				 ! $this->user->data['is_registered'])
		{
			$captcha = $this->container->get('captcha.factory')->get_instance(
					$this->config['captcha_plugin']);
			$captcha->init(CONFIRM_POST);
			$captcha_result = $captcha->validate();
			if (false !== $captcha_result)
			{
				trigger_error($captcha_result);
			}
		}
		
		if (! check_form_key(self::FORM_NAME))
		{
			trigger_error('FORM_INVALID');
			return;
		}
		
		/*
		 * if ($this->config['flood_interval'] &&
		 * ! $this->auth->acl_get('f_ignoreflood', $forum_id))
		 * {
		 * // Flood check
		 * $last_post_time = 0;
		 *
		 * if ($user->data['is_registered'])
		 * {
		 * $last_post_time = $user->data['user_lastpost_time'];
		 * }
		 * else
		 * {
		 * $sql = 'SELECT post_time AS last_post_time
		 * FROM ' . POSTS_TABLE . "
		 * WHERE poster_ip = '" . $user->ip . "'
		 * AND post_time > " . ($current_time - $config['flood_interval']);
		 * $result = $db->sql_query_limit($sql, 1);
		 * if ($row = $db->sql_fetchrow($result))
		 * {
		 * $last_post_time = $row['last_post_time'];
		 * }
		 * $db->sql_freeresult($result);
		 * }
		 *
		 * if ($last_post_time && ($current_time - $last_post_time) <
		 * intval($config['flood_interval']))
		 * {
		 * $error[] = $user->lang['FLOOD_ERROR'];
		 * }
		 * }
		 */
		

		$this->user->add_lang_ext('fh3095/forms', 'forms_controller');
		$display = $this->load_page_data($route);
		if (! $display)
		{
			trigger_error('FORMS_FORMATING_NOT_SET');
			return;
		}
		
		$content = $display->get_content_for_display();echo $content;
		
		$settings_start = strpos($content, '[' . self::SETTINGS_NAME . ' ');
		$settings_end = strpos($content, ']', $settings_start);
		if (false === $settings_start || false === $settings_end)
		{
			trigger_error('FORMS_SETTINGS_MISSING');
			return;
		}
		
		$settings_options_start = $settings_start +
				 strlen('[' . self::SETTINGS_NAME . ' ');
		$settings_tmp = explode(' ', 
				trim(
						substr($content, $settings_options_start, 
								$settings_end - $settings_options_start)));
		
		$content = substr_replace($content, '', $settings_start, 
				$settings_end - $settings_start + 1);
		
		// Extract settings from array
		$settings = array();
		foreach ($settings_tmp as $setting)
		{
			list ($key, $val) = explode('=', $setting, 2);
			$settings[trim($key)] = trim($val);
		}
		
		$var_tag_name = self::VAR_TAG_NAME;
		$content = preg_replace_callback(
				"@\\[$var_tag_name\\]([a-zA-Z0-9\-_]+)\\[/$var_tag_name\\]@", 
				array(
					$this,
					preg_callback
				), $content);
		
		$message_parser = new \parse_message();
		$message_parser->message = $content;
		
		$data = array(
			'message_md5' => md5($message_parser->message),
			'forum_id' => (int) $settings['FORUM_ID'],
			// Defaults
			'enable_bbcode' => 0,
			'enable_smilies' => 1,
			'enable_urls' => 1,
			'enable_sig' => 0,
			// Required values
			'icon_id' => 0,
			'post_edit_locked'=>0,
		);

		$message_parser->parse(0, 
				($this->config['allow_post_links']) ? true : false, true, false, 
				false, false, $this->config['allow_post_links']);
		$data['bbcode_bitfield'] = $message_parser->bbcode_bitfield;
		$data['bbcode_uid'] = $message_parser->bbcode_uid;
		$data['message'] = $message_parser->message;
		
		$poll = array();
		$redirect_url = submit_post('post', 'Test Subject', '', POST_NORMAL, 
				$poll, $data, '', false);		
		redirect($redirect_url);
	}

	public function preg_callback($match)
	{
		return $this->request_vars->variable($match[1], $match[0], true, 
				\phpbb\request\request_interface::POST);
	}
}
