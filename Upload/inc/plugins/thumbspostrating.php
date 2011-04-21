<?php
/**
 *  Plugin  : Thumbs Post Rating
 *  Author  : TY Yew
 *  Version : 1.2
 *  Website : http://tyyew.com/mybb
 *  Contact : mybb@tyyew.com
 *
 *  Copyright 2010 TY Yew
 *  mybb@tyyew.com
 *
 *  This file is part of Thumbs Post Rating plugin for MyBB.
 *
 *  Thumbs Post Rating plugin for MyBB is free software; you can
 *  redistribute it and/or modify it under the terms of the GNU General
 *  Public License as published by the Free Software Foundation; either
 *  version 3 of the License, or (at your option) any later version.
 *
 *  Thumbs Post Rating plugin for MyBB is distributed in the hope that it
 *  will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See
 *  the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http:www.gnu.org/licenses/>.
 */

// No direct initiation
if( !defined('IN_MYBB') )
{
	die('Direct initialization of this file is not allowed.');
}

// Add hooks
$plugins->add_hook('xmlhttp','tpr_action');
$plugins->add_hook('global_start','tpr_global');
$plugins->add_hook('postbit','tpr_box');
$plugins->add_hook('class_moderation_delete_post','tpr_deletepost');
$plugins->add_hook('class_moderation_delete_thread_start','tpr_deletethread');
$plugins->add_hook('class_moderation_delete_thread','tpr_deletethread2');

// Plugin information
function thumbspostrating_info()
{
	global $lang;
	$lang->load('thumbspostrating');
	
	return array(
		'name' => $lang->tpr_info_name,
		'description' => $lang->tpr_info_description,
		'website' => 'http://community.mybb.com/thread-84250.html',
		'author' => 'TY Yew',
		'authorsite' => 'http://www.tyyew.com/mybb',
		'version' => '1.2',
		'guid' => '21de27b859c0095ec17f86f561fa3737',
		'compatibility' => '14*,15*,16*'
	);
}

// Install function
function thumbspostrating_install()
{
	global $db, $lang;
	
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts ADD `thumbsup` INT UNSIGNED NOT NULL DEFAULT 0, ADD `thumbsdown` INT UNSIGNED NOT NULL DEFAULT 0', true);
	$db->write_query('CREATE TABLE IF NOT EXISTS '.TABLE_PREFIX.'thumbspostrating (
		uid INT UNSIGNED NOT NULL ,
		pid INT UNSIGNED NOT NULL ,
		rating SMALLINT NOT NULL ,
		PRIMARY KEY ( uid, pid ),
		KEY ( pid )
		) ENGINE = MYISAM ;'
	);
	
	$lang->load('thumbspostrating');
	$tpr_setting_group_1 = array(
		'name' => 'tpr_group',
		'title' => $db->escape_string($lang->setting_group_tpr_group),
		'description' => $db->escape_string($lang->setting_group_tpr_group_desc),
		'disporder' => '38',
		'isdefault' => 'no'
	);
	$db->insert_query('settinggroups',$tpr_setting_group_1);
	$gid = $db->insert_id();
	
	$disporder = 0;
	foreach(array(
		'usergroups' => array('text', '2,3,4,6'),
		'forums'     => array('text', '0'),
		'selfrate'   => array('yesno', 1),
		'updaterate' => array('yesno', 0),
	) as $name => $opts) {
		$lang_title = 'setting_tpr_'.$name;
		$lang_desc = 'setting_tpr_'.$name.'_desc';
		$db->insert_query('settings', array(
			'name'        => 'tpr_'.$name,
			'title'       => $db->escape_string($lang->$lang_title),
			'description' => $db->escape_string($lang->$lang_desc),
			'optionscode' => $opts[0],
			'value'       => $db->escape_string($opts[1]),
			'disporder'   => ++$disporder,
			'gid'         => $gid,
		));
	}
	rebuild_settings();
	
	$db->insert_query('templates', array(
		'title' => 'postbit_tpr',
		'template' => $db->escape_string('<div class="float_right"><table class="tpr_box" id="tpr_stat_{$post[\'pid\']}">
<tr>
	<td class="tu_stat" id="tu_stat_{$post[\'pid\']}">{$post[\'thumbsup\']}</td>
	<td>{$tu_img}</td>
	<td>{$td_img}</td>
	<td class="td_stat" id="td_stat_{$post[\'pid\']}">{$post[\'thumbsdown\']}</td>
</tr>
</table></div>'),
		'sid' => -1,
		'version' => 1600
	));
}

// Activate function
function thumbspostrating_activate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit','#'.preg_quote('<div class="post_body" id="pid_{$post[\'pid\']}">').'#','{$post[\'tprdsp\']}<div class="post_body" id="pid_{$post[\'pid\']}">');
	find_replace_templatesets('postbit_classic','#'.preg_quote('{$post[\'message\']}').'#','{$post[\'tprdsp\']}{$post[\'message\']}');
}

// Deactivate function
function thumbspostrating_deactivate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit','#'.preg_quote('{$post[\'tprdsp\']}').'#','');
	find_replace_templatesets('postbit_classic','#'.preg_quote('{$post[\'tprdsp\']}').'#','');
}

// Is Installed function
function thumbspostrating_is_installed()
{
	global $db;
	return (bool) $db->table_exists('thumbspostrating');
}

// Uninstall function
function thumbspostrating_uninstall()
{
	global $db;
	$gid = $db->fetch_field($db->simple_select('settinggroups','gid','name="tpr_group"'), 'gid');
	if($gid)
	{
		$db->delete_query('settings', 'gid='.$gid);
		$db->delete_query('settinggroups', 'gid='.$gid);
	}
	rebuild_settings();
	
	$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts DROP thumbsup, DROP thumbsdown', true);
	$db->write_query('DROP TABLE IF EXISTS '.TABLE_PREFIX.'thumbspostrating');
	
	$db->delete_query('templates', 'title="postbit_tpr" AND sid=-1');
}

function tpr_global()
{
	if($GLOBALS['current_page'] != 'showthread.php') return;
	$GLOBALS['templatelist'] .= ',postbit_tpr';
}

// returns true if ratings are enabled for this forum
function tpr_enabled_forum($fidcheck)
{
	$forums =& $GLOBALS['mybb']->settings['tpr_forums'];
	if( $forums != 0 )
	{
		foreach(array_map('trim', explode(',',$forums)) as $fid)
		{
			if( $fid == $fidcheck )
			{
				return false;
			}
		}
	}
	return true;
}

// returns true if the current user ($mybb->user) has permissions to rate
// the post (if $postuid is supplied), based on usergroup permissions
function tpr_user_can_rate($postuid=0)
{
	global $mybb;
	$user =& $mybb->user;
	// guests can never rate
	if(!$user['uid']) return false;
	
	// cache the group checking result
	static $can_rate = null;
	
	if(!isset($can_rate))
	{
		$can_rate = false;
		// first, gather all the groups the user is in
		$usergroups = array();
		if($user['additionalgroups'])
			$usergroups = array_flip(explode(',', $user['additionalgroups']));
		$usergroups[$user['usergroup']] = 1;
		// next, check that the groups are allowed
		foreach(array_map('intval', array_map('trim', explode(',',$mybb->settings['tpr_usergroups']))) as $grp)
		{
			if(isset($usergroups[$grp]))
			{
				$can_rate = true;
				break;
			}
		}
	}
	
	if($can_rate)
	{
		// check self rating perm
		return ($postuid != $user['uid'] || $mybb->settings['tpr_selfrate'] != 1);
	}
	return false;
}

// Display the RATEBOX
function tpr_box(&$post)
{
	global $db, $mybb, $templates, $lang, $current_page;
	$pid = (int) $post['pid'];
	if(!$pid) return; // paranoia
	
	static $done_init = false;
	static $user_rates = null;
	static $thread_closed = false; // closed thread and not moderator
	if(!$done_init)
	{
		$done_init = true;
		
		// Check whether the posts in the forum can be rated
		if(!tpr_enabled_forum($post['fid']))
		{
			global $plugins;
			$plugins->remove_hook('postbit', 'tpr_box');
			return;
		}
		
		// build user rating cache
		$user_rates = array();
		if($current_page == 'showthread.php')
		{
			// tricky little optimisation :P
			// - on AJAX new reply, it's impossible for this post to have been rated, therefore, we don't need to build a cache at all
			if($mybb->user['uid'])
			{
				if($mybb->input['mode'] == 'threaded')
					$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND pid='.(int)$mybb->input['pid']);
				else
					$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND '.$GLOBALS['pids']);
				
				while($ttrate = $db->fetch_array($query))
					$user_rates[$ttrate['pid']] = $ttrate['rating'];
				$db->free_result($query);
			}
			
			// stick in additional header stuff
			$GLOBALS['headerinclude'] .= '<script type="text/javascript" src="'.$mybb->settings['bburl'].'/jscripts/thumbspostrating.js?ver=1600"></script><link type="text/css" rel="stylesheet" href="'.$mybb->settings['bburl'].'/css/thumbspostrating.css" />';
			
			// new replying implies thread isn't closed or user is moderator
			$thread_closed = ($GLOBALS['ismod'] || $GLOBALS['thread']['closed']);
		}
		
		$lang->load('thumbspostrating');
	}
	
	$cantrate = ($thread_closed || !tpr_user_can_rate($post['uid']));
	$url = $mybb->settings['bburl'].'/xmlhttp.php?action=tpr&amp;pid='.$pid.'&amp;my_post_key='.$mybb->post_code.'&amp;rating=';
	// Make the thumb
	// for user already rated thumb
	if( $user_rates[$pid] )
	{
		$ud = ($user_rates[$pid] == 1 ? 'u' : 'd');
		$tu_img = '<div class="tpr_thumb tu_r'.$ud.'"></div>';
		$td_img = '<div class="tpr_thumb td_r'.$ud.'"></div>';
		if($mybb->settings['tpr_updaterate'] == 1 && !$cantrate)
		{
			// allow rating the opposite one
			if($user_rates[$pid] == 1)
				$td_img = '<a href="'.$url.'-1" class="tpr_thumb td_nr" title="'.$lang->tpr_rate_down.'" onclick="return thumbRate(-1,'.$pid.');"></a>';
			else
				$tu_img = '<a href="'.$url.'1" class="tpr_thumb tu_nr" title="'.$lang->tpr_rate_up.'" onclick="return thumbRate(1,'.$pid.');"></a>';
		}
	}
	// for user who cannot rate
	elseif( $cantrate )
	{
		$tu_img = '<div class="tpr_thumb tu_rd"></div>';
		$td_img = '<div class="tpr_thumb td_ru"></div>';
	}
	// for user who can rate
	else
	{
		$tu_img = '<a href="'.$url.'1" class="tpr_thumb tu_nr" title="'.$lang->tpr_rate_up.'" onclick="return thumbRate(1,'.$pid.');"></a>';
		$td_img = '<a href="'.$url.'-1" class="tpr_thumb td_nr" title="'.$lang->tpr_rate_down.'" onclick="return thumbRate(-1,'.$pid.');"></a>';
	}
	// respect MyBB's wish to disable xmlhttp (eh, like who turns it off?)
	if(!$cantrate && $mybb->settings['use_xmlhttprequest'] == 0)
	{
		$tu_img = str_replace('onclick="return thumbRate', 'rel="', $tu_img);
		$td_img = str_replace('onclick="return thumbRate', 'rel="', $td_img);
	}
	
	$post['thumbsup'] = (int)$post['thumbsup'];
	$post['thumbsdown'] = (int)$post['thumbsdown'];
	
	// Display the rating box
	eval('$post[\'tprdsp\'] = "'.$templates->get('postbit_tpr').'";');
}

function tpr_action()
{
	global $mybb, $db, $lang;
	if($mybb->input['action'] != 'tpr') return;
	if(!verify_post_check($mybb->input['my_post_key'], true))
		xmlhttp_error($lang->invalid_post_code);
	
	$uid = $mybb->user['uid'];
	$rating = (int)$mybb->input['rating'];
	$pid = (int)$mybb->input['pid'];
	$lang->load('thumbspostrating');
	
	// check for invalid rating
	if($rating != 1 && $rating != -1) xmlhttp_error($lang->tpr_error_invalid_rating);
	
	//User has rated, first check whether the rating is valid
	// Check whether the user can rate
	if(!tpr_user_can_rate($pid)) xmlhttp_error($lang->tpr_error_cannot_rate);
	
	$post = get_post($pid);
	if(!$post['pid']) xmlhttp_error($lang->post_doesnt_exist);
	if(!tpr_enabled_forum($post['fid'])) xmlhttp_error($lang->tpr_error_cannot_rate);
	
	// permissions checking
	$forumpermissions = forum_permissions($post['fid']);
	if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1) xmlhttp_error($lang->post_doesnt_exist);
	$thread = get_thread($post['tid']);
	if($forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid']) xmlhttp_error($lang->post_doesnt_exist);
	$ismod = is_moderator($post['fid']);
	if(($thread['visible'] != 1 || $post['visible'] != 1) && !$ismod) xmlhttp_error($lang->post_doesnt_exist);
	// ... we'll assume the thread belongs to a valid forum
	if($thread['closed'] && !$ismod) xmlhttp_error($lang->tpr_error_cannot_rate);
	
	// Check whether the user has rated
	$rated = $db->fetch_field($db->simple_select('thumbspostrating','rating','uid='.$uid.' and pid='.$pid), 'rating');
	if(($mybb->settings['tpr_updaterate'] == 1 && $rated == $rating)
	|| ($mybb->settings['tpr_updaterate'] != 1 && $rated))
		xmlhttp_error($lang->tpr_error_already_rated);
	
	$db->replace_query('thumbspostrating', array(
		'rating' => $rating,
		'uid' => $uid,
		'pid' => $pid
	));
	$field = ($rating == 1 ? 'thumbsup' : 'thumbsdown');
	++$post[$field];
	$qryappend = '';
	if($rated)
	{
		$oldfield = ($rating =! 1 ? 'thumbsup' : 'thumbsdown');
		--$post[$oldfield];
		$qryappend = ', '.$oldfield.'=IF('.$oldfield.'>0,'.$oldfield.'-1,0)';
	}
	$db->write_query('UPDATE '.TABLE_PREFIX.'posts SET '.$field.'='.$field.'+1'.$qryappend.' WHERE pid='.$pid);
	
	if(!$mybb->input['ajax'])
	{
		header('Location: '.htmlspecialchars_decode(get_post_link($pid, $post['tid'])).'#pid'.$pid);
	}
	else
	{
		// push new values to client
		echo 'success||', $post['pid'], '||', (int)$post['thumbsup'], '||', (int)$post['thumbsdown'];
		if($mybb->settings['tpr_updaterate'] == 1) {
			$url = $mybb->settings['bburl'].'/xmlhttp.php?action=tpr&amp;pid='.$post['pid'].'&amp;my_post_key='.$mybb->post_code.'&amp;rating=';
			// allow rating the opposite one
			if($rating == 1)
				echo '||x[2].innerHTML = \'<a href="'.$url.'-1" class="tpr_thumb td_nr" title="'.$lang->tpr_rate_down.'" onclick="return thumbRate(-1,'.$post['pid'].');"></a>\';';
			else
				echo '||x[1].innerHTML = \'<a href="'.$url.'1" class="tpr_thumb tu_nr" title="'.$lang->tpr_rate_up.'" onclick="return thumbRate(1,'.$post['pid'].');"></a>\';';
		}
	}
	// TODO: for non-AJAX, it makes more sense to go through global.php
}

function tpr_deletepost($pid)
{
	global $db;
	$db->delete_query('thumbspostrating', 'pid='.$pid);
}
function tpr_deletethread($tid)
{
	global $db, $tpr_pids;
	// grab pids, but only delete later
	$tpr_pids = '';
	$query = $db->simple_select('posts', 'pid', 'tid='.$tid);
	while($pid = $db->fetch_field($query, 'pid'))
		$tpr_pids .= ($tpr_pids ? ',':'') . $pid;
	$db->free_result($query);
}
function tpr_deletethread2($tid)
{
	global $tpr_pids, $db;
	if(!$tpr_pids) return;
	
	$db->delete_query('thumbspostrating', 'pid IN ('.$tpr_pids.')');
	$tpr_pids = ''; // stop us trying to redelete if this hook happens to be called again
}
// we won't bother with delete forum handling; MyBB doesn't even bother to clear out attachments, reported posts etc (plus it sucks to have to find all posts in it anyway)
// also won't worry about deleting on post pruning - MyBB thinks it's great to duplicate code and offer no plugin hooks there; practically every plugin which tries to cleanup on delete is gonna get f*ck'd over by that anyway

// TODO: perhaps include a rebuild thumb ratings section in ACP
