<?php

/***************************************************************************
 *
 *   OUGC Signature Control plugin ()
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *   This plugin will add seven new options to extend users signature control in a group basis.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Run the hooks.
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_formcontainer_end', 'ougc_signcontrol_permission');
	$plugins->add_hook('admin_user_groups_edit_commit', 'ougc_signcontrol_permission_commit');
}
else
{
	$plugins->add_hook('usercp_start', 'ougc_signcontrol_usercp');
	$plugins->add_hook('member_profile_start', 'ougc_signcontrol_profile_control');
	$plugins->add_hook('member_profile_end', 'ougc_signcontrol_profile');
	$plugins->add_hook('postbit_pm', 'ougc_signcontrol_postbit');
	$plugins->add_hook('postbit', 'ougc_signcontrol_postbit');
	$plugins->add_hook('postbit_prev', 'ougc_signcontrol_postbit');
	$plugins->add_hook('postbit_announcement', 'ougc_signcontrol_postbit');
}

//Necessary plugin information for the ACP plugin manager.
function ougc_signcontrol_info()
{
	global $lang;
	$lang->load('ougc_signcontrol');

	return array(
		'name'			=> 'OUGC Signature Control',
		'description'	=> $lang->ougc_signcontrol_plugin_desc,
		'website'		=> 'http://udezain.com.ar/',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://udezain.com.ar/',
		'version'		=> '1.2',
		'compatibility'	=> '16*',
	);
}

//Install the plugin.
function ougc_signcontrol_install()
{
	global $db, $cache;
	ougc_signcontrol_uninstall(false);

	$db->add_column('usergroups', 'sc_sightml', "int(1) NOT NULL default '0'");
	$db->add_column('usergroups', 'sc_sigmycode', "int(1) NOT NULL default '1'");
	$db->add_column('usergroups', 'sc_sigsmilies', "int(1) NOT NULL default '1'");
	$db->add_column('usergroups', 'sc_sigimgcode', "int(1) NOT NULL default '1'");
	$db->add_column('usergroups', 'sc_sigcountmycode', "int(1) NOT NULL default '1'");
	$db->add_column('usergroups', 'sc_siglength', "int(3) NOT NULL default '200'");
	$db->add_column('usergroups', 'sc_maxsigimages', "int(2) NOT NULL default '2'");
	$db->add_column('usergroups', 'sc_hidetoguest', "int(1) NOT NULL default '1'");
	$db->add_column('usergroups', 'sc_maxsiglines', "int(2) NOT NULL default '0'");
	$db->add_column('usergroups', 'sc_maximgsize', "varchar(7) NOT NULL DEFAULT ''");

	$cache->update_usergroups();
}

//Is this plugin installed?
function ougc_signcontrol_is_installed()
{
	global $db;

	return ($db->field_exists('sc_hidetoguest', 'usergroups'));
}

//Uninstall the plugin.
function ougc_signcontrol_uninstall($hard=true)
{
	global $db, $cache;

	if($db->field_exists('sc_sightml', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_sightml');
	}
	if($db->field_exists('sc_sigmycode', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_sigmycode');
	}
	if($db->field_exists('sc_sigsmilies', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_sigsmilies');
	}
	if($db->field_exists('sc_sigimgcode', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_sigimgcode');
	}
	if($db->field_exists('sc_sigcountmycode', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_sigcountmycode');
	}
	if($db->field_exists('sc_siglength', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_siglength');
	}
	if($db->field_exists('sc_maxsigimages', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_maxsigimages');
	}
	if($db->field_exists('sc_hidetoguest', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_hidetoguest');
	}
	if($db->field_exists('sc_maxsiglines', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_maxsiglines');
	}
	if($db->field_exists('sc_maximgsize', 'usergroups'))
	{
		$db->drop_column('usergroups', 'sc_maximgsize');
	}

	if($hard)
	{
		$cache->update_usergroups();
	}
}

//Insert the require code in the group edit page.
function ougc_signcontrol_permission()
{
	global $run_module, $form_container, $lang;

	if($run_module == 'user' && !empty($form_container->_title) && !empty($lang->users_permissions) && $form_container->_title == $lang->users_permissions)
	{
		global $form, $mybb;
		$lang->load('ougc_signcontrol');

		$sc_options = array(
			$form->generate_check_box('sc_sightml', 1, $lang->ougc_signcontrol_sightml, array('checked' => $mybb->input['sc_sightml'])),
			$form->generate_check_box('sc_sigmycode', 1, $lang->ougc_signcontrol_sigmycode, array('checked' => $mybb->input['sc_sigmycode'])),
			$form->generate_check_box('sc_sigsmilies', 1, $lang->ougc_signcontrol_sigsmilies, array('checked' => $mybb->input['sc_sigsmilies'])),
			$form->generate_check_box('sc_sigimgcode', 1, $lang->ougc_signcontrol_sigimgcode, array('checked' => $mybb->input['sc_sigimgcode'])),
			$form->generate_check_box('sc_sigcountmycode', 1, $lang->ougc_signcontrol_sigcountmycode, array('checked' => $mybb->input['sc_sigcountmycode'])),
			$form->generate_check_box('sc_hidetoguest', 1, $lang->ougc_signcontrol_hidetoguest, array('checked' => $mybb->input['sc_hidetoguest'])),
			"<br />{$lang->ougc_signcontrol_maxsiglines}<br /><small>{$lang->ougc_signcontrol_maxsiglines_desc}</small><br />".$form->generate_text_box('sc_maxsiglines', $mybb->input['sc_maxsiglines'], array('id' => 'sc_maxsiglines', 'class' => 'field50')),
			"<br />{$lang->ougc_signcontrol_sc_maximgsize}<br /><small>{$lang->ougc_signcontrol_sc_maximgsize_desc}</small><br />".$form->generate_text_box('sc_maximgsize', $mybb->input['sc_maximgsize'], array('id' => 'sc_maximgsize', 'class' => 'field50')),
			"<br />{$lang->ougc_signcontrol_siglength}<br /><small>{$lang->ougc_signcontrol_siglength_desc}</small><br />".$form->generate_text_box('sc_siglength', $mybb->input['sc_siglength'], array('id' => 'sc_siglength', 'class' => 'field50')),
			"<br />{$lang->ougc_signcontrol_maxsigimages}<br /><small>{$lang->ougc_signcontrol_maxsigimages_desc}</small><br />".$form->generate_text_box('sc_maxsigimages', $mybb->input['sc_maxsigimages'], array('id' => 'sc_maxsigimages', 'class' => 'field50'))
		);
		$form_container->output_row($lang->ougc_signcontrol_plugin, '', '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $sc_options).'</div>');
	}
}

//Save the data.
function ougc_signcontrol_permission_commit()
{
	global $updated_group, $mybb;

	$sc_maximgsize = implode('x', array_map('intval', explode('x', my_strtolower($mybb->input['sc_maximgsize']))));
	$array_data = array(
		'sc_sightml'		=>	intval($mybb->input['sc_sightml']),
		'sc_sigmycode'		=>	intval($mybb->input['sc_sigmycode']),
		'sc_sigsmilies'		=>	intval($mybb->input['sc_sigsmilies']),
		'sc_sigimgcode'		=>	intval($mybb->input['sc_sigimgcode']),
		'sc_sigcountmycode'	=>	intval($mybb->input['sc_sigcountmycode']),
		'sc_hidetoguest'	=>	intval($mybb->input['sc_hidetoguest']),
		'sc_siglength'		=>	intval($mybb->input['sc_siglength']),
		'sc_maxsigimages'	=>	intval($mybb->input['sc_maxsigimages']),
		'sc_maxsiglines'	=>	intval($mybb->input['sc_maxsiglines']),
		'sc_maximgsize'		=>	($sc_maximgsize == '0x0' ? '' : $sc_maximgsize)
	);
	$updated_group = array_merge($updated_group, $array_data);
}

//Modify the settings for users editing their signatures.
function ougc_signcontrol_usercp()
{
	global $mybb;

	$mybb->settings['sightml'] = ($mybb->usergroup['sc_sightml'] == 1 ? 1 : 0);
	$mybb->settings['sigmycode'] = ($mybb->usergroup['sc_sigmycode'] == 1 ? 1 : 0);
	$mybb->settings['sigsmilies'] = ($mybb->usergroup['sc_sigsmilies'] == 1 ? 1 : 0);
	$mybb->settings['sigimgcode'] = ($mybb->usergroup['sc_sigimgcode'] == 1 ? 1 : 0);
	$mybb->settings['sigcountmycode'] = ($mybb->usergroup['sc_sigcountmycode'] == 1 ? 1 : 0);
	$mybb->settings['siglength'] = intval($mybb->usergroup['sc_siglength']);
	$mybb->settings['maxsigimages'] = intval($mybb->usergroup['sc_maxsigimages']);

	if(!($mybb->input['action'] == 'do_editsig' && $mybb->request_method == 'post' && $mybb->usergroup['sc_maxsiglines']))
	{
		return;
	}

	global $lang, $error;
	isset($lang->ougc_signcontrol_plugin) or $lang->load('ougc_signcontrol');

	if(count(explode("\n", $mybb->input['signature'])) > $mybb->usergroup['sc_maxsiglines'])
	{
		$error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsigimages, my_number_format($mybb->usergroup['sc_maxsiglines'])));
		return;
	}

	if(!function_exists('getimagesize') || !$mybb->usergroup['sc_maximgsize'])
	{
		return;
	}

	global $parser;

	$parser_options = array(
		'allow_html'		=> $mybb->settings['sightml'],
		'filter_badwords'	=> 1,
		'allow_mycode'		=> $mybb->settings['sigmycode'],
		'allow_smilies'		=> $mybb->settings['sigsmilies'],
		'allow_imgcode'		=> $mybb->settings['sigimgcode'],
		'filter_badwords'	=> 1
	);
	$parsed_sig = $parser->parse_message($mybb->input['signature'], $parser_options);

	preg_match_all('#<img(.+?)src=\"(.+?)\"(.+?)/>#i', $parsed_sig, $matches);

	$matches = array_unique($matches[2]);

	$invalid_found = $maxsized_found = false;
	list($maxwidth, $maxheight) = explode('x', my_strtolower($mybb->usergroup['sc_maximgsize']));
	foreach((array)$matches as $match)
	{
		$imginfo = @getimagesize($match);
		if(!$imginfo)
		{
			$file = fetch_remote_file($match);
			if($file)
			{
				$tmp_name = MYBB_ROOT.'cache/ougc_signcontrol/remote_'.md5(random_str());
				$fp = @fopen($tmp_name, 'wb');
				if($fp)
				{
					fwrite($fp, $file);
					fclose($fp);
					$imginfo = @getimagesize($tmp_name);
					@unlink($tmp_name);
				}
			}
		}
		if(!$imginfo)
		{
			$invalid_found = true;
			break;
		}

		// Check that this is a valid image type
		$invalid = false;
		switch(my_strtolower($imginfo['mime']))
		{
			case 'image/gif':
			case 'image/jpeg':
			case 'image/x-jpg':
			case 'image/x-jpeg':
			case 'image/pjpeg':
			case 'image/jpg':
			case 'image/png':
			case 'image/x-png':
				$invalid = false;
				break;
			default:
				$invalid = true;
				break;
		}
		if($invalid)
		{
			$invalid_found = true;
			break;
		}

		if((int)$imginfo[0] > (int)$maxwidth || (int)$imginfo[1] > (int)$maxheight)
		{
			$maxsized_found = true;
		}
	}

	// Invalid iamge >_>?
	if($invalid_found)
	{
		$error = inline_error($lang->ougc_signcontrol_sc_invalidimage);
		return;
	}

	// A image was found, error for this user!
	if($maxsized_found)
	{
		$error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsized, get_friendly_size($mybb->usergroup['sc_maximgsize']*1024)));
		return;
	}
}

// Extend the DB class
function ougc_signcontrol_profile_control()
{
	ougc_extend_object($GLOBALS['db'], '
		function query($string, $hide_errors=0, $write_query=0)
		{
			if(!$write_query && strpos($string, \'ELECT * FROM '.TABLE_PREFIX.'users WHERE\') && !strpos($string, \'signature_control\'))
			{
				$string = str_replace(\'*\', \'*, signature AS signature_control\', $string);
			}
			return parent::query($string, $hide_errors, $write_query);
		}
	');
}

//Modify the settings for users profiles.
function ougc_signcontrol_profile()
{
	global $mybb, $memprofile, $memperms, $signature, $parser, $templates;

	if(!$memprofile['signature'])
	{
		return;
	}

	if(!$mybb->user['uid'] && $memperms['sc_hidetoguest'])
	{
		$memprofile['signature'] = $signature = '';
		return;
	}

	$sig_parser = array(
		'allow_html' 		=> ($memperms['sc_sightml'] == 1 ? 1 : 0),
		'allow_mycode' 		=> ($memperms['sc_sigmycode'] == 1 ? 1 : 0),
		'allow_smilies'		=> ($memperms['sc_sigsmilies'] == 1 ? 1 : 0),
		'allow_imgcode'		=> ($memperms['sc_sigimgcode'] == 1 ? 1 : 0),
		'me_username' 		=> $memprofile['username'],
		'filter_badwords' 	=> 1
	);
	$memprofile['signature'] = $parser->parse_message($memprofile['signature_control'], $sig_parser);
	eval('$signature = "'.$templates->get('member_profile_signature').'";');
}

// Modify the settings for post.
function ougc_signcontrol_postbit(&$post)
{
	global $mybb, $memperms, $signature, $parser, $templates;

	if(!$post['signature'])
	{
		return;
	}

	$usergroup = usergroup_permissions($post['usergroup'].(!$post['additionalgroups'] ? '' : ','.$post['additionalgroups']));

	if(!$mybb->user['uid'] && $usergroup['sc_hidetoguest'])
	{
		$post['signature'] = '';
		return;
	}

	$sig_parser = array(
		'allow_html' 		=> ($usergroup['sc_sightml'] == 1 ? 1 : 0),
		'allow_mycode' 		=> ($usergroup['sc_sigmycode'] == 1 ? 1 : 0),
		'allow_smilies'		=> ($usergroup['sc_sigsmilies'] == 1 ? 1 : 0),
		'allow_imgcode'		=> ($usergroup['sc_sigimgcode'] == 1 ? 1 : 0),
		'me_username' 		=> $post['username'],
		'filter_badwords' 	=> 1
	);

	if(THIS_SCRIPT == 'private.php' && $mybb->input['action'] == 'read')
	{
		$post['signature'] = $parser->parse_message($GLOBALS['pm']['signature'], $sig_parser);
	}
	elseif(THIS_SCRIPT == 'announcements.php')
	{
		$post['signature'] = $parser->parse_message($GLOBALS['announcementarray']['signature'], $sig_parser);
	}
	else
	{
		$post['signature'] = $parser->parse_message($GLOBALS['post']['signature'], $sig_parser);
	}
}

// This function is a mere duplicate of Zinga Burga's code used in the Bump plugin, he applied a license that allowed me to do whatever I want to it. And since control_object is under GPL, this is the most I can do.
if(!function_exists('ougc_extend_object'))
{
	function ougc_extend_object(&$object, $strign)
	{
		if(!is_object($object))
		{
			return false;
		}

		static $ougc_extend_object_key = 0;
		$key = 'ougcExtendObject'.(++$ougc_extend_object_key);
		eval('class '.$key.' extends '.get_class($object).'
			{
				function '.$key.'(&$old)
				{
					$vars = get_object_vars($old);
					foreach((array)$vars as $var => $val)
					{
						$this->$var = $val;
					}
				}

				'./*str_replace("\\'", "'", addslashes($strign)*/$strign.'
			}
		');
		$object = new $key($object);
	}
}

// Run the hooks.
if(!defined('IN_ADMINCP'))
{
	if(defined('THIS_SCRIPT') && THIS_SCRIPT == 'member.php')
	{
		ougc_extend_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0)
			{
				if(!$write_query && strpos($string, \'ELECT u.*, f.*\') && strpos($string, \'users u\') && !strpos($string, \'signature_control\'))
				{
					$string = str_replace(\'u.*\', \'u.*, u.signature AS signature_control\', $string);
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}
	/*switch(THIS_SCRIPT)
	{
		case 'showthread.php':
			ougc_extend_object($GLOBALS['db'], '
				function query($string, $hide_errors=0, $write_query=0)
				{
					if(!$write_query && strpos($string, \'SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername\') && !strpos($string, \'signature_control\'))
					{
						$string = str_replace(\'u.*\', \'u.*, u.signature AS signature_control\', $string);
					}
					return parent::query($string, $hide_errors, $write_query);
				}
			');
			break;
		case 'private.php':
			ougc_extend_object($GLOBALS['db'], '
				function query($string, $hide_errors=0, $write_query=0)
				{
					if(!$write_query && strpos($string, \'u.*, f.*\') && !strpos($string, \'signature_control\'))
					{
						$string = str_replace(\'u.*\', \'u.*, u.signature AS signature_control\', $string);
					}
					return parent::query($string, $hide_errors, $write_query);
				}
			');
			break;
	}*/
}