<?php

/***************************************************************************
 *
 *	OUGC Signature Control plugin (/inc/languages/english/admin/ougc_signcontrol.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012-2014 Omar Gonzalez
 *
 *	Website: http://omarg.me
 *
 *	Manage signatures in group basis, to extend groups functionality.
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

// Plugin API
$l['ougc_signcontrol_plugin'] = 'OUGC Signature Control';
$l['ougc_signcontrol_plugin_desc'] = 'Manage signatures in group basis, to extend groups functionality.';

// Usergroup Settings
$l['ougc_signcontrol_sightml'] = 'Allow HTML in signatures?';
$l['ougc_signcontrol_sigmycode'] = 'Allow MyCode in signatures?';
$l['ougc_signcontrol_sigsmilies'] = 'Allow smilies in signatures?';
$l['ougc_signcontrol_sigimgcode'] = 'Allow [img] MyCode in signatures?';
$l['ougc_signcontrol_sigcountmycode'] = 'MyCode affects signature length?';
$l['ougc_signcontrol_hidetoguest'] = 'Hide signature to guests?';
$l['ougc_signcontrol_maxsiglines'] = 'Lines limit in signatures';
$l['ougc_signcontrol_maxsiglines_desc'] = 'Maximum number of lines allowed in signatures. 0 = no limit.';
$l['ougc_signcontrol_siglength'] = 'Length limit in signatures';
$l['ougc_signcontrol_siglength_desc'] = 'The maximum number of characters an user can use in a signature.';
$l['ougc_signcontrol_maxsigimages'] = 'Images limit in signatures?';
$l['ougc_signcontrol_maxsigimages_desc'] = 'The maximum number of images an user can use in a signature.';
$l['ougc_signcontrol_sc_maximgsize'] = 'Maximum Image Dimensions';
$l['ougc_signcontrol_sc_maximgsize_desc'] = 'Maximum dimensions for images in signatures allowed for this group. empty = no limit.';
$l['ougc_signcontrol_sc_maximgsize_unavailable'] = 'This feature is unavailable in your server (getimagesize() is required).';