<?php

/***************************************************************************
 *
 *	OUGC Signature Control plugin (/inc/plugins/ougc_signcontrol.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012-2014 Omar Gonzalez
 *
 *	Website: http://omarg.me
 *
 *	Control user signatures on a per-group basis with new options.
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

// Run/Add Hooks
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

// Plugin API
function ougc_signcontrol_info()
{
	global $lang;
	isset($lang->setting_group_ougc_signcontrol) or $lang->load('ougc_signcontrol');

	return array(
		'name'			=> 'OUGC Signature Control',
		'description'	=> $lang->setting_group_ougc_signcontrol_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '1.8',
		'versioncode'	=> 1800,
		'compatibility'	=> '18*'
	);
}

// _activate() routine
function ougc_signcontrol_activate()
{
	global $cache;

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

	ougc_signcontrol_install();

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['signcontrol'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _install() routine
function ougc_signcontrol_install()
{
	global $db, $cache;

	$fields = ougc_signcontrol_fields();

	foreach($fields as $name => $definition)
	{
		$db->field_exists($name, 'usergroups') or $db->add_column('usergroups', $name, $definition);
	}

	$cache->update_usergroups();
}

// _is_installed() routine
function ougc_signcontrol_is_installed()
{
	global $db;

	$fields = ougc_signcontrol_fields();

	$_is_installed = false;

	foreach($fields as $name => $definition)
	{
		$_is_installed = $db->field_exists($name, 'usergroups');
		break;
	}

	return $_is_installed;
}

// _uninstall() routine
function ougc_signcontrol_uninstall($hard=true)
{
	global $db, $cache;

	$fields = ougc_signcontrol_fields();

	foreach($fields as $name => $definition)
	{
		!$db->field_exists($name, 'usergroups') or $db->drop_column('usergroups', $name);
	}

	$cache->update_usergroups();

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
		$cache->delete('ougc_plugins');
	}
}

// Fields
function ougc_signcontrol_fields()
{
	return array(
		'sc_sightml'		=> "int(1) NOT NULL default '0'",
		'sc_sigmycode'		=> "int(1) NOT NULL default '1'",
		'sc_sigsmilies'		=> "int(1) NOT NULL default '1'",
		'sc_sigimgcode'		=> "int(1) NOT NULL default '1'",
		'sc_sigcountmycode'	=> "int(1) NOT NULL default '1'",
		'sc_siglength'		=> "int(3) NOT NULL default '200'",
		'sc_maxsigimages'	=> "int(2) NOT NULL default '2'",
		'sc_hidetoguest'	=> "int(1) NOT NULL default '1'",
		'sc_maxsiglines'	=> "int(2) NOT NULL default '2'",
		'sc_maximgsize'		=> "int(3) NOT NULL DEFAULT '100'",
		'sc_maximgdims'		=> "varchar(7) NOT NULL DEFAULT '250x100'",
	);
}

//Insert the require code in the group edit page.
function ougc_signcontrol_permission()
{
	global $run_module, $form_container, $lang;

	if($run_module == 'user' && $form_container->_title == $lang->users_permissions)
	{
		global $form, $mybb;
		isset($lang->setting_group_ougc_signcontrol) or $lang->load('ougc_signcontrol');

		$sc_options = array(
			$form->generate_check_box('sc_sightml', 1, $lang->ougc_signcontrol_sightml, array('checked' => $mybb->get_input('sc_sightml', 1))),
			$form->generate_check_box('sc_sigmycode', 1, $lang->ougc_signcontrol_sigmycode, array('checked' => $mybb->get_input('sc_sigmycode', 1))),
			$form->generate_check_box('sc_sigsmilies', 1, $lang->ougc_signcontrol_sigsmilies, array('checked' => $mybb->get_input('sc_sigsmilies', 1))),
			$form->generate_check_box('sc_sigimgcode', 1, $lang->ougc_signcontrol_sigimgcode, array('checked' => $mybb->get_input('sc_sigimgcode', 1))),
			$form->generate_check_box('sc_sigcountmycode', 1, $lang->ougc_signcontrol_sigcountmycode, array('checked' => $mybb->get_input('sc_sigcountmycode', 1))),
			$form->generate_check_box('sc_hidetoguest', 1, $lang->ougc_signcontrol_hidetoguest, array('checked' => $mybb->get_input('sc_hidetoguest', 1))),
			"<br />{$lang->ougc_signcontrol_maxsiglines}<br /><small>{$lang->ougc_signcontrol_maxsiglines_desc}</small><br />{$form->generate_text_box('sc_maxsiglines', $mybb->get_input('sc_maxsiglines', 1), array('id' => 'sc_maxsiglines', 'class' => 'field50'))}",
			"<br />{$lang->ougc_signcontrol_sc_maximgsize}<br /><small>{$lang->ougc_signcontrol_sc_maximgsize_desc}</small><br />{$form->generate_text_box('sc_maximgsize', $mybb->get_input('sc_maximgsize', 1), array('id' => 'sc_maximgsize', 'class' => 'field50'))}",
			"<br />{$lang->ougc_signcontrol_sc_maximgdims}<br /><small>{$lang->ougc_signcontrol_sc_maximgdims_desc}</small><br />{$form->generate_text_box('sc_maximgdims', $mybb->get_input('sc_maximgdims'), array('id' => 'sc_maximgdims', 'class' => 'field50'))}",
			"<br />{$lang->ougc_signcontrol_siglength}<br /><small>{$lang->ougc_signcontrol_siglength_desc}</small><br />{$form->generate_text_box('sc_siglength', $mybb->get_input('sc_siglength', 1), array('id' => 'sc_siglength', 'class' => 'field50'))}",
			"<br />{$lang->ougc_signcontrol_maxsigimages}<br /><small>{$lang->ougc_signcontrol_maxsigimages_desc}</small><br />{$form->generate_text_box('sc_maxsigimages', $mybb->get_input('sc_maxsigimages', 1), array('id' => 'sc_maxsigimages', 'class' => 'field50'))}"
		);
		$form_container->output_row($lang->setting_group_ougc_signcontrol, '', '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $sc_options).'</div>');
	}
}

//Save the data.
function ougc_signcontrol_permission_commit()
{
	global $updated_group, $mybb, $db;

	$sc_maximgdims = implode('x', array_map('intval', explode('x', my_strtolower($mybb->get_input('sc_maximgdims')))));

	$array_data = array(
		'sc_sightml'		=>	$mybb->get_input('sc_sightml', 1),
		'sc_sigmycode'		=>	$mybb->get_input('sc_sigmycode', 1),
		'sc_sigsmilies'		=>	$mybb->get_input('sc_sigsmilies', 1),
		'sc_sigimgcode'		=>	$mybb->get_input('sc_sigimgcode', 1),
		'sc_sigcountmycode'	=>	$mybb->get_input('sc_sigcountmycode', 1),
		'sc_hidetoguest'	=>	$mybb->get_input('sc_hidetoguest', 1),
		'sc_siglength'		=>	$mybb->get_input('sc_siglength', 1),
		'sc_maxsigimages'	=>	$mybb->get_input('sc_maxsigimages', 1),
		'sc_maxsiglines'	=>	$mybb->get_input('sc_maxsiglines', 1),
		'sc_maximgsize'		=>	$mybb->get_input('sc_maximgsize', 1),
		'sc_maximgdims'		=>	$db->escape_string($sc_maximgdims)
	);

	$updated_group = array_merge($updated_group, $array_data);
}

//Modify the settings for users editing their signatures.
function ougc_signcontrol_usercp()
{
	global $mybb;

	$mybb->settings['sightml'] = (int)$mybb->usergroup['sc_sightml'];
	$mybb->settings['sigmycode'] = (int)$mybb->usergroup['sc_sigmycode'];
	$mybb->settings['sigsmilies'] = (int)$mybb->usergroup['sc_sigsmilies'];
	$mybb->settings['sigimgcode'] = (int)$mybb->usergroup['sc_sigimgcode'];
	$mybb->settings['sigcountmycode'] = (int)$mybb->usergroup['sc_sigcountmycode'];
	$mybb->settings['siglength'] = (int)$mybb->usergroup['sc_siglength'];
	$mybb->settings['maxsigimages'] = (int)$mybb->usergroup['sc_maxsigimages'];

	if(!($mybb->get_input('action') == 'do_editsig' && $mybb->request_method == 'post' && $mybb->usergroup['sc_maxsiglines']))
	{
		return;
	}

	global $lang, $error;
	isset($lang->setting_group_ougc_signcontrol) or $lang->load('ougc_signcontrol');

	$signature = $mybb->get_input('signature');

	if(count(explode("\n", $signature)) > $mybb->usergroup['sc_maxsiglines'])
	{
		$error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsigimages, my_number_format($mybb->usergroup['sc_maxsiglines'])));
		return;
	}

	if(!function_exists('getimagesize') || !($mybb->usergroup['sc_maximgsize'] && $mybb->usergroup['sc_maximgdims']))
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

	preg_match_all('#<img(.+?)src=\"(.+?)\"(.+?)/>#i', (string)$parser->parse_message($signature, $parser_options), $matches);

	$matches = array_unique($matches[2]);

	$invalid_found = $maxsize_found = $maxdims_found = false;
	list($maxwidth, $maxheight) = explode('x', my_strtolower($mybb->usergroup['sc_maximgdims']));

	if(is_array($matches))
	{
		foreach($matches as $match)
		{
			$filesize = false;
			if($mybb->usergroup['sc_maximgsize'])
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
						$filesize = filesize($tmp_name);
						$imginfo = @getimagesize($tmp_name);
						@unlink($tmp_name);
					}
				}
			}

			if($filesize && $filesize > $mybb->usergroup['sc_maximgsize'])
			{
				_dump($filesize, $mybb->usergroup['sc_maximgsize']);
				$maxsize_found = true;
				break;
			}

			$imginfo or $imginfo = @getimagesize($match);

			if(!$imginfo)
			{
				continue;
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
				$maxdims_found = true;
			}
		}
	}

	// Invalid image >_>?
	!$invalid_found or $error = inline_error($lang->ougc_signcontrol_sc_invalidimage);

	// A image was found, error for this user!
	!$maxsize_found or $error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxsize, get_friendly_size($mybb->usergroup['sc_maximgsize']*1024)));

	// A image was found, error for this user!
	!$maxdims_found or $error = inline_error($lang->sprintf($lang->ougc_signcontrol_sc_maxdims, $mybb->usergroup['sc_maximgdims']));
}

// Extend the DB class
function ougc_signcontrol_profile_control()
{
	global $mybb;

	if($mybb->user['uid'] && $mybb->user['uid'] == $mybb->get_input('uid', MyBB::INPUT_INT))
	{
		return;
	}

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

	$parser_options = array(
		'allow_html' 		=> (int)$memperms['sc_sightml'],
		'allow_mycode' 		=> (int)$memperms['sc_sigmycode'],
		'allow_smilies'		=> (int)$memperms['sc_sigsmilies'],
		'allow_imgcode'		=> (int)$memperms['sc_sigimgcode'],
		'me_username' 		=> $memprofile['username'],
		'filter_badwords' 	=> 1
	);

	$memprofile['signature'] = $parser->parse_message($memprofile['signature_control'], $parser_options);
	eval('$signature = "'.$templates->get('member_profile_signature').'";');
}

// Modify the settings for post.
function ougc_signcontrol_postbit(&$post)
{
	static $cached_signs = array();

	if($cached_signs[$post['uid']])
	{
		$post['signature'] = $cached_signs[$post['uid']];
	}

	$cached_signs[$post['uid']] = '';

	if(!$post['signature'])
	{
		return;
	}

	global $mybb;

	static $memperms = array();

	if(!$memperms[$post['uid']])
	{
		$memperms[$post['uid']] = usergroup_permissions($post['usergroup'].(!$post['additionalgroups'] ? '' : ','.$post['additionalgroups']));
	}

	$usergroup = $memperms[$post['uid']];

	if(!$mybb->user['uid'] && $usergroup['sc_hidetoguest'])
	{
		$post['signature'] = '';
		return;
	}

	global $parser;

	$parser_options = array(
		'allow_html' 		=> (int)$usergroup['sc_sightml'],
		'allow_mycode' 		=> (int)$usergroup['sc_sigmycode'],
		'allow_smilies'		=> (int)$usergroup['sc_sigsmilies'],
		'allow_imgcode'		=> (int)$usergroup['sc_sigimgcode'],
		'me_username' 		=> $post['username'],
		'filter_badwords' 	=> 1
	);

	static $var = '';

	if(!$var)
	{
		global $plugins;

		$var = ($plugins->current_hook == 'postbit_pm' ? 'pm' : ($plugins->current_hook == 'postbit_announcement' ? 'announcementarray' : 'post'));
	}

	global ${$var}, $templates;

	$post['signature'] = $parser->parse_message(${$var}['signature_control'], $parser_options);
	eval('$post[\'signature\'] = "'.$templates->get('postbit_signature').'";');

	$cached_signs[$post['uid']] = $post['signature'];

	return $post;
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

// Run the hooks.
if(!defined('IN_ADMINCP'))
{
	if(defined('THIS_SCRIPT') && THIS_SCRIPT == 'member.php')
	{
		control_object($GLOBALS['db'], '
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
	switch(THIS_SCRIPT)
	{
		case 'showthread.php':
			control_object($GLOBALS['db'], '
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
			control_object($GLOBALS['db'], '
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