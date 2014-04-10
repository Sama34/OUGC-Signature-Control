<?php

/***************************************************************************
 *
 *	OUGC Signature Control plugin (/inc/plugins/ougc_signcontrol.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012-2014 Omar Gonzalez
 *   
 *   Website: http://omarg.me
 *
 *   Manage signatures in group basis, to extend groups functionality.
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
defined('IN_MYBB') or die('Direct initialization of this file is not allowed.');

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
	# Apparently signatures aren't show in announcements or post previews... i may need a nap..
	#$plugins->add_hook('postbit_prev', 'ougc_signcontrol_postbit');
	#$plugins->add_hook('postbit_announcement', 'ougc_signcontrol_postbit');
}

// Plugin API
function ougc_signcontrol_info()
{
	global $lang;
	$lang->load('ougc_signcontrol');

	return array(
		'name'			=> 'OUGC Signature Control',
		'description'	=> $lang->ougc_signcontrol_plugin_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '1.2',
		'versioncode'	=> 1200,
		'compatibility'	=> '16*',
		'guid'			=> ''
	);
}

// _activate() routine
function ougc_signcontrol_activate()
{
	global $db, $cache;

	$fields = ougc_signcontrol_fields();
	foreach($fields as $field => $definition)
	{
		if(!$db->field_exists($field, 'usergroups'))
		{
			$db->add_column('usergroups', $field, $definition);
		}
	}

	$cache->update_usergroups();

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_signcontrol_info();

	if(!isset($plugins['signcontrol']))
	{
		$plugins['signcontrol'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['signcontrol'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _install() routine
function ougc_signcontrol_install()
{
	global $db, $cache;
	ougc_signcontrol_uninstall(false);

	$fields = ougc_signcontrol_fields();
	foreach($fields as $field => $definition)
	{
		$db->add_column('usergroups', $field, $definition);
	}

	$cache->update_usergroups();
}

// _is_installed() routine
function ougc_signcontrol_is_installed()
{
	global $db;

	$_is_installed = false;
	$fields = ougc_signcontrol_fields();
	foreach($fields as $field => $definition)
	{
		if($db->field_exists($field, 'usergroups'))
		{
			$_is_installed = true;
			break;
		}
	}
	return $_is_installed;
}

// _uninstall() routine
function ougc_signcontrol_uninstall($hard=true)
{
	global $db, $cache;

	$fields = ougc_signcontrol_fields();
	foreach($fields as $field => $definition)
	{
		if($db->field_exists($field, 'usergroups'))
		{
			$db->drop_column('usergroups', $field);
		}
	}

	if($hard)
	{
		$cache->update_usergroups();
	}

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['signcontrol']))
	{
		unset($plugins['signcontrol']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$db->delete_query('datacache', 'title=\'ougc_plugins\'');
		!is_object($cache->handler) or $cache->handler->delete('ougc_plugins');
	}
}

function ougc_signcontrol_fields()
{
	return array(
		'sc_sightml'		=> 'int(1) NOT NULL default \'0\'',
		'sc_sigmycode'		=> 'int(1) NOT NULL default \'1\'',
		'sc_sigsmilies'		=> 'int(1) NOT NULL default \'1\'',
		'sc_sigimgcode'		=> 'int(1) NOT NULL default \'1\'',
		'sc_sigcountmycode'	=> 'int(1) NOT NULL default \'1\'',
		'sc_siglength'		=> 'int(3) NOT NULL default \'200\'',
		'sc_maxsigimages'	=> 'int(2) NOT NULL default \'2\'',
		'sc_hidetoguest'	=> 'int(1) NOT NULL default \'1\'',
		'sc_maxsiglines'	=> 'int(2) NOT NULL default \'0\'',
		'sc_maximgsize'		=> 'varchar(7) NOT NULL DEFAULT \'\'',
	);
}

// Duplicate the current logged-in user signature field
function ougc_signcontrol_init()
{
	global $db;

	switch(THIS_SCRIPT)
	{
		case 'member.php':
			control_object($db, '
				function query($string, $hide_errors=0, $write_query=0)
				{
					if(!$write_query && strpos($string, \'ELECT u.*, f.*\') && strpos($string, \'users u\') && !strpos($string, \'signature_control\'))
					{
						$string = str_replace(\'u.*\', \'u.*, u.signature AS signature_control\', $string);
					}
					return parent::query($string, $hide_errors, $write_query);
				}
			');
			break;
		case 'showthread.php':
			control_object($db, '
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
			control_object($db, '
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

		if(function_exists('getimagesize'))
		{
			$sc_maximgsize = $form->generate_text_box('sc_maximgsize', $mybb->input['sc_maximgsize'], array('id' => 'sc_maximgsize', 'class' => 'field50'));
		}
		else
		{
			$sc_maximgsize = '<br />&nbsp;&nbsp;&nbsp;<strong style="color: red;">'.$lang->ougc_signcontrol_sc_maximgsize_unavailable.'</strong>';
		}

		$sc_options = array(
			$form->generate_check_box('sc_sightml', 1, $lang->ougc_signcontrol_sightml, array('checked' => $mybb->input['sc_sightml'])),
			$form->generate_check_box('sc_sigmycode', 1, $lang->ougc_signcontrol_sigmycode, array('checked' => $mybb->input['sc_sigmycode'])),
			$form->generate_check_box('sc_sigsmilies', 1, $lang->ougc_signcontrol_sigsmilies, array('checked' => $mybb->input['sc_sigsmilies'])),
			$form->generate_check_box('sc_sigimgcode', 1, $lang->ougc_signcontrol_sigimgcode, array('checked' => $mybb->input['sc_sigimgcode'])),
			$form->generate_check_box('sc_sigcountmycode', 1, $lang->ougc_signcontrol_sigcountmycode, array('checked' => $mybb->input['sc_sigcountmycode'])),
			$form->generate_check_box('sc_hidetoguest', 1, $lang->ougc_signcontrol_hidetoguest, array('checked' => $mybb->input['sc_hidetoguest'])),
			"<br />{$lang->ougc_signcontrol_maxsiglines}<br /><small>{$lang->ougc_signcontrol_maxsiglines_desc}</small><br />".$form->generate_text_box('sc_maxsiglines', $mybb->input['sc_maxsiglines'], array('id' => 'sc_maxsiglines', 'class' => 'field50')),
			"<br />{$lang->ougc_signcontrol_sc_maximgsize}<br /><small>{$lang->ougc_signcontrol_sc_maximgsize_desc}</small><br />".$sc_maximgsize,
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
		'sc_sightml'		=>	(int)$mybb->input['sc_sightml'],
		'sc_sigmycode'		=>	(int)$mybb->input['sc_sigmycode'],
		'sc_sigsmilies'		=>	(int)$mybb->input['sc_sigsmilies'],
		'sc_sigimgcode'		=>	(int)$mybb->input['sc_sigimgcode'],
		'sc_sigcountmycode'	=>	(int)$mybb->input['sc_sigcountmycode'],
		'sc_hidetoguest'	=>	(int)$mybb->input['sc_hidetoguest'],
		'sc_siglength'		=>	(int)$mybb->input['sc_siglength'],
		'sc_maxsigimages'	=>	(int)$mybb->input['sc_maxsigimages'],
		'sc_maxsiglines'	=>	(int)$mybb->input['sc_maxsiglines'],
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
	$mybb->settings['siglength'] = (int)$mybb->usergroup['sc_siglength'];
	$mybb->settings['maxsigimages'] = (int)$mybb->usergroup['sc_maxsigimages'];

	if($mybb->input['action'] != 'do_editsig' || $mybb->request_method != 'post')
	{
		return;
	}

	global $lang, $error;
	isset($lang->ougc_signcontrol_plugin) or $lang->load('ougc_signcontrol');

	if($mybb->usergroup['sc_maxsiglines'])
	{
		if(count(explode("\n", $mybb->input['signature'])) > $mybb->usergroup['sc_maxsiglines'])
		{
			$error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsigimages, my_number_format($mybb->usergroup['sc_maxsiglines'])));
			return;
		}
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

	$matches = array_unique((array)$matches[2]);

	$invalid_found = $maxsized_found = false;
	list($maxwidth, $maxheight) = explode('x', my_strtolower($mybb->usergroup['sc_maximgsize']));
	foreach($matches as $match)
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
				$invalid_found = false;
				break;
			default:
				$invalid_found = true;
				break;
		}

		if($invalid_found)
		{
			break;
		}

		if((int)$imginfo[0] > (int)$maxwidth || (int)$imginfo[1] > (int)$maxheight)
		{
			$maxsized_found = true;
			break;
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
		$error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsized, implode('x', array($maxwidth, $maxheight))));
		return;
	}
}

// Extend the DB class
function ougc_signcontrol_profile_control()
{
	control_object($GLOBALS['db'], '
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
	global $memprofile;

	if(!$memprofile['signature'])
	{
		return;
	}

	global $mybb, $memperms, $signature;

	if(!$mybb->user['uid'] && $memperms['sc_hidetoguest'])
	{
		$memprofile['signature'] = $signature = '';
		return;
	}

	global $parser, $templates, $lang, $theme;

	$memprofile['signature'] = $parser->parse_message($memprofile['signature_control'], array(
		'allow_html' 		=> ($memperms['sc_sightml'] == 1 ? 1 : 0),
		'allow_mycode' 		=> ($memperms['sc_sigmycode'] == 1 ? 1 : 0),
		'allow_smilies'		=> ($memperms['sc_sigsmilies'] == 1 ? 1 : 0),
		'allow_imgcode'		=> ($memperms['sc_sigimgcode'] == 1 ? 1 : 0),
		'me_username' 		=> $memprofile['username'],
		'filter_badwords' 	=> 1
	));

	eval('$signature = "'.$templates->get('member_profile_signature').'";');
}

// Modify the settings for post.
function ougc_signcontrol_postbit(&$post)
{
	/*global $announcementarray;

	if(!empty($announcementarray))
	{
		_dump($announcementarray);
	}*/

	if(!$post['signature'])
	{
		return;
	}

	global $mybb;

	$usergroup = usergroup_permissions($post['usergroup'].(!$post['additionalgroups'] ? '' : ','.$post['additionalgroups']));

	if(!$mybb->user['uid'] && $usergroup['sc_hidetoguest'])
	{
		$post['signature'] = '';
		return;
	}

	global $parser, $plugins;

	$var = 'post';
	/*if(THIS_SCRIPT == 'announcements.php')
	{
		$var = 'announcementarray';
	}*/

	$post['signature'] = $parser->parse_message(${$var}['signature_control'], array(
		'allow_html' 		=> ($usergroup['sc_sightml'] == 1 ? 1 : 0),
		'allow_mycode' 		=> ($usergroup['sc_sigmycode'] == 1 ? 1 : 0),
		'allow_smilies'		=> ($usergroup['sc_sigsmilies'] == 1 ? 1 : 0),
		'allow_imgcode'		=> ($usergroup['sc_sigimgcode'] == 1 ? 1 : 0),
		'me_username' 		=> $post['username'],
		'filter_badwords' 	=> 1
	));
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
	{
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
		{
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v)
			{
				if($p = strrpos($k, "\0"))
				{
					$k = substr($k, $p+1);
				}
				$vars[$k] = $v;
			}
			if(!empty($vars))
			{
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			}
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
			{
				$obj->___setvars($vars);
			}
		}
		// else not a valid object or PHP serialize has changed
	}
}

ougc_signcontrol_init();