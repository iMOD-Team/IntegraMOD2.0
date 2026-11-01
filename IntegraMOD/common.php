<?php
/***************************************************************************
 *                                common.php
 *                            -------------------
 *   begin                : Saturday, Feb 23, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

if ( !defined('IN_PHPBB') )
{
	die("Hacking attempt");
}

// Page generation time
$starttime = 0;
$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

//ini_set('display_startup_errors',1); 
//ini_set('display_errors',1);
//error_reporting (E_ALL); // This report all errors
error_reporting  (E_ERROR | E_WARNING | E_PARSE); // This will NOT report uninitialized variables

//if (function_exists('set_magic_quotes_runtime'))
//{
//  @set_magic_quotes_runtime(0); // Disable magic_quotes_runtime
//}
date_default_timezone_set(@date_default_timezone_get());

// CrackerTracker v5.x
include($phpbb_root_path . 'ctracker/engines/ct_security.' . $phpEx);

// Protect against GLOBALS tricks
if (isset($_POST['GLOBALS']) || isset($_FILES['GLOBALS']) || isset($_GET['GLOBALS']) || isset($_COOKIE['GLOBALS']))
{
	die("Hacking attempt");
}

// Protect against HTTP_SESSION_VARS tricks
if (isset($_SESSION) && !is_array($_SESSION))
{
	die("Hacking attempt");
}

//
// Slash request data. phpBB 2 SQL still expects this, and PHP 8.5 has no magic quotes.
//
if (!function_exists('im_addslashes_deep'))
{
	function im_addslashes_deep($value)
	{
		if (is_array($value))
		{
			foreach ($value as $k => $v)
			{
				$value[$k] = im_addslashes_deep($v);
			}
			return $value;
		}

		if (is_string($value))
		{
			return addslashes($value);
		}

		return $value;
	}
}

if (is_array($_GET))
	{
		$_GET = im_addslashes_deep($_GET);
		@reset($_GET);
	}

	if( is_array($_POST) )
	{
		$_POST = im_addslashes_deep($_POST);
		@reset($_POST);
	}

	if( is_array($_COOKIE) )
	{
		$_COOKIE = im_addslashes_deep($_COOKIE);
		@reset($_COOKIE);
	}

//
// Define some basic configuration arrays this also prevents
// malicious rewriting of language and otherarray values via
// URI params
//
$board_config = array();
$userdata = array();
$theme = array();
$images = array();
$current_template_path = '';
$lang = array();
$nav_links = array();
$dss_seeded = false;
$gen_simple_header = FALSE;

include($phpbb_root_path . 'config.'.$phpEx);

if( !defined("PHPBB_INSTALLED") )
{
	header('Location: ' . $phpbb_root_path . 'install/chmod.' . $phpEx);
	exit;
}

include($phpbb_root_path . 'includes/constants.'.$phpEx);
include($phpbb_root_path . 'includes/template.'.$phpEx);
include($phpbb_root_path . 'includes/sessions.'.$phpEx);
include($phpbb_root_path . 'includes/auth.'.$phpEx);
include($phpbb_root_path . 'includes/functions.'.$phpEx);
include($phpbb_root_path . 'includes/db.'.$phpEx);
if ( defined('IN_CASHMOD') )
{
	include($phpbb_root_path . 'includes/functions_cash.'.$phpEx);
}
// We do not need this any longer, unset for safety purposes
unset($dbpasswd);

//
// Obtain and encode users IP
//
//if( getenv('HTTP_X_FORWARDED_FOR') != '' )
//{
//	$client_ip = ( !empty($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : ( ( !empty($_ENV['REMOTE_ADDR']) ) ? $_ENV['REMOTE_ADDR'] : $REMOTE_ADDR );
//
//	$entries = explode(',', getenv('HTTP_X_FORWARDED_FOR'));
//	reset($entries);
//	while (list(, $entry) = each($entries)) 
//	{
//		$entry = trim($entry);
//		if ( preg_match("/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/", $entry, $ip_list) )
//		{
//			$private_ip = array('/^0\./', '/^127\.0\.0\.1/', '/^192\.168\..*/', '/^172\.((1[6-9])|(2[0-9])|(3[0-1]))\..*/', '/^10\..*/', '/^224\..*/', '/^240\..*/');
//			$found_ip = preg_replace($private_ip, $client_ip, $ip_list[1]);
//
//			if ($client_ip != $found_ip)
//			{
//				$client_ip = $found_ip;
//				break;
//			}
//		}
//	}
//}
//else
//{
//	$client_ip = ( !empty($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : ( ( !empty($_ENV['REMOTE_ADDR']) ) ? $_ENV['REMOTE_ADDR'] : $REMOTE_ADDR );
//}
$client_ip = ( !empty($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : ( ( !empty($_ENV['REMOTE_ADDR']) ) ? $_ENV['REMOTE_ADDR'] : $REMOTE_ADDR );
$user_ip = encode_ip($client_ip);

// CrackerTracker v5.x
include($phpbb_root_path . 'ctracker/engines/ct_varsetter.' . $phpEx);
include($phpbb_root_path . 'ctracker/engines/ct_ipblocker.' . $phpEx);

//
// Setup forum wide options, if this fails
// then we output a CRITICAL_ERROR since
// basic forum information is not available
//
$sql = "SELECT *
	FROM " . CONFIG_TABLE;
if( !($result = $db->sql_query($sql, false, 'board_config')) )
{
	message_die(CRITICAL_ERROR, "Could not query config information", "", __LINE__, __FILE__, $sql);
}

while ( $row = $db->sql_fetchrow($result) )
{
	$board_config[$row['config_name']] = $row['config_value'];
}
$db->sql_freeresult($result);
if ((empty($board_config['summer_time']) || $board_config['summer_time'] != date('I')) && !empty($board_config['summer_time_auto'])) 
{ 
	$board_config['summer_time'] = date('I'); 
} 

// Auto lang mod start
// If someone spoofs the language setting, then init_userprefs() will use the default language, as the spoofed result can't be found
$language = '';
$supported_languages = array();
$accept_language = strtolower (getenv ('HTTP_ACCEPT_LANGUAGE'));
if (!empty ($accept_language))
{
	reset ($board_config);
	$needle = 'auto_lang_';
	$needle_length = strlen($needle);
  foreach ($board_config as $key => $value)
	{
		if ((strstr($key, $needle)))
		{
			$supported_languages[substr ($key, $needle_length)] = $value;
		}
	}
	reset ($board_config); // Avoid nasty surprises for other coders

	if (((($__imc0 = $supported_languages) instanceof Countable || is_array($__imc0)) ? count($__imc0) : 0) > 0)
	{
		$accepted_languages = explode (',', $accept_language);
		reset ($accepted_languages);
    foreach ($accepted_languages as $lng)
		{
			$pos = strpos ($lng, ';');
			if ($pos > 0) // The ; never occurs on position 0 in this case (unless spoofed)
			{
				$lng = substr ($lng, 0, $pos);
			}
			$lng = trim ($lng);
			if (!empty($lng))
			{
				if (isset($supported_languages[$lng])
						&& file_exists($phpbb_root_path . 'language/lang_' .  $supported_languages[$lng] . '/lang_main.'.$phpEx))
				{
					$language = $supported_languages[$lng];
					break;
				}
				else if (strstr($lng,'-')) // A user can have entered '-' at pos 0, so strpos is out for PHP 3 compliance
				{
					// break it up at the '-'
					$lng = substr($lng, 0, strpos($lng, '-'));
					if (!empty($lng) && isset($supported_languages[$lng])
							&& file_exists($phpbb_root_path . 'language/lang_' .  $supported_languages[$lng] . '/lang_main.'.$phpEx))
					{
						$language = $supported_languages[$lng];
						break;
					}
				}
			}
		}
	}
}
if (!empty ($language))
{
	$board_config['default_lang'] = $language;
}
// Auto lang mod end

///added by crxgames to optimize the Integramod Beast
	$sql = 'SELECT * FROM ' . ACRONYMS_TABLE;
	if( !$result = $db->sql_query($sql, false, 'acronyms') )
	{
		message_die(GENERAL_ERROR, "Couldn't obtain acronyms data", "", __LINE__, __FILE__, $sql);
	}
			
	$_acronyms = $db->sql_fetchrowset($result);
	$db->sql_freeresult($result);

//-- mod : today at   yesterday at ------------------------------------------------------------------------ 
//-- add 
// 
// PARSE DATEFORMAT TO GET TIME FORMAT 
// 
$time_reg = '/([gh][[:punct:][:space:]]{1,2}[i][[:punct:][:space:]]{0,2}[a]?[[:punct:][:space:]]{0,2}[S]?)/i'; 
preg_match($time_reg, $board_config['default_dateformat'], $regs); 
$board_config['default_timeformat'] = $regs[1]; 
unset($time_reg); 
unset($regs); 

include($phpbb_root_path . 'attach_mod/attachment_mod.'.$phpEx);
//-- mod : Advanced Group Color Management -------------------------------------
//-- add
include($phpbb_root_path . 'includes/class_color.' . $phpEx);
//-- fin mod : Advanced Group Color Management ---------------------------------

if(!defined("IN_LOGIN") && !defined('DEV_MODE')){
	if ((file_exists('install')) && ( !file_exists('prill_install') )) {
	message_die(GENERAL_MESSAGE, 'Please_remove_install');
}

	if ((file_exists('prill_install')) && ( !file_exists('install') )) {
	message_die(GENERAL_MESSAGE, 'Please_remove_prill');
	}
	
	if (file_exists('install') && file_exists('prill_install')) {
	message_die(GENERAL_MESSAGE, 'Please_remove_both');
	}

	if (file_exists('contrib'))
	{
		message_die(GENERAL_MESSAGE, 'Please ensure the contrib/ directory is deleted');
	}

	if (file_exists('chmod.php'))
	{
		message_die(GENERAL_MESSAGE, 'Please ensure root/chmod.php is deleted');
	}
}
//
// Show 'Board is disabled' message if needed.
//
$is_called = FALSE;
if( $board_config['board_disable'] && !defined("IN_ADMIN") && !defined("IN_LOGIN") )
{
	$userdata = session_pagestart( $user_ip, PAGE_INDEX );
	init_userprefs( $userdata );
	$is_called = TRUE;
	if($userdata['user_level'] != ADMIN)
	{
		if ( $board_config['board_disable_msg'] != "" )
		{
			message_die(GENERAL_MESSAGE, $board_config['board_disable_msg'], 'Information');
		}
		else
		{
			message_die(GENERAL_MESSAGE, $lang['Board_disable'], 'Information');
		}
	}
	else
	{
		$template->assign_block_vars('switch_board_disabled', array());
		if ( $board_config['board_disable_msg'] != "" )
		{
			$template->assign_vars(array('L_BOARD_DISABLE' => $board_config['board_disable_msg']));
		}
		else
		{
			$template->assign_vars(array('L_BOARD_DISABLE' => $lang['Board_disable']));
		}
	}
}

if ( is_robot() ) define('IS_ROBOT', true);
include($phpbb_root_path . 'includes/optimize_database_cron.'.$phpEx);

#======================================================================= |
#==== Start: == phpBB Security ========================================= |
#==== v1.0.3 =========================================================== |
#====					
include_once($phpbb_root_path .'includes/phpbb_security.'. $phpEx);
$ps_check = phpBBSecurity_Blocks();
	if ($ps_check)
		phpBBSecurity_Ban(phpBBSecurity_IP(), $board_config['phpBBSecurity_auto_ban'], $ps_check);
phpBBSecurity_MaxSessions($board_config['phpBBSecurity_allowed_sessions']);
phpBBSecurity_Guests();
#====
#==== Author: aUsTiN [austin@phpbb-amod.com] [http://phpbb-tweaks.com] = |
#==== End: ==== phpBB Security ========================================= |	
#======================================================================= |

//Begin Lo-Fi Mod
if (!empty($_GET['lofi']) || !empty($_COOKIE['lofi']))
{
	$lofi = 1;
}
//End Lo-Fi Mod 
