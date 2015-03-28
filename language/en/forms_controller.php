<?php
if (! defined('IN_PHPBB'))
{
	exit();
}

if (empty($lang) || ! is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, 
		array(
			'FORMS_FORMATING_NOT_SET' => 'No Formatting is defined for this page. Can\'t generate a thread from your input.',
			'FORMS_SETTINGS_MISSING' => 'Form settings missing.'
		));
