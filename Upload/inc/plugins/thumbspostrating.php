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
$plugins->add_hook('postbit','tpr_box');
$plugins->add_hook('xmlhttp','tpr_action');

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
        'compatibility' => '16*'
   );
}

// Install function
function thumbspostrating_install()
{
    global $db;

    $db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts ADD `thumbsup` INT NOT NULL DEFAULT 0, `thumbsdown` INT NOT NULL DEFAULT 0', true);
	$db->write_query('CREATE TABLE IF NOT EXISTS '.TABLE_PREFIX.'thumbspostrating (
        uid INT NOT NULL ,
        pid INT NOT NULL ,
        rating SMALLINT NOT NULL ,
		PRIMARY KEY ( uid, pid )
		) ENGINE = MYISAM ;'
	);
}

// Activate function
function thumbspostrating_activate()
{
    global $db, $lang;
    $lang->load('thumbspostrating');

	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
    find_replace_templatesets('postbit','#'.preg_quote('<div class="post_body" id="pid_{$post[\'pid\']}">').'#','<div class="float_right">{$post[\'tprdsp\']}</div><div class="post_body" id="pid_{$post[\'pid\']}">');
    find_replace_templatesets('postbit_classic','#'.preg_quote('{$post[\'message\']}').'#','<div class="float_right">{$post[\'tprdsp\']}</div>{$post[\'message\']}');

    $tpr_setting_group_1 = array(
        'name' => 'tpr_group',
        'title' => 'Thumbs Post Rating',
        'description' => $db->escape_string($lang->tpr_settings),
        'disporder' => '38',
        'isdefault' => 'no'
    );

    $db->insert_query('settinggroups',$tpr_setting_group_1);
    $gid = $db->insert_id();

    $tpr_setting_item_1 = array(
        'name' => 'tpr_usergroups',
        'title' => $db->escape_string($lang->tpr_usergroup),
        'description' => $db->escape_string($lang->tpr_usergroup_description),
        'optionscode' => 'text',
        'value' => '2,3,4,6',
        'disporder' => 1,
        'gid' => $gid
    );

    $tpr_setting_item_2 = array(
        'name' => 'tpr_forums',
        'title' => $db->escape_string($lang->tpr_forums),
        'description' => $db->escape_string($lang->tpr_forums_description),
        'optionscode' => 'text',
        'value' => '0',
        'disporder' => 2,
        'gid' => $gid
    );

    $tpr_setting_item_3 = array(
        'name' => 'tpr_selfrate',
        'title' => $db->escape_string($lang->tpr_selfrate),
        'description' => $db->escape_string($lang->tpr_selfrate_description),
        'optionscode' => 'yesno',
        'value' => '1',
        'disporder' => 3,
        'gid' => $gid
    );

    $db->insert_query('settings',$tpr_setting_item_1);
    $db->insert_query('settings',$tpr_setting_item_2);
    $db->insert_query('settings',$tpr_setting_item_3);
    rebuild_settings();
}

// Deactivate function
function thumbspostrating_deactivate()
{
    global $db;

    require MYBB_ROOT.'/inc/adminfunctions_templates.php';
    find_replace_templatesets('postbit','#'.preg_quote('<div class="float_right">{$post[\'tprdsp\']}</div>').'#','');
    find_replace_templatesets('postbit_classic','#'.preg_quote('<div class="float_right">{$post[\'tprdsp\']}</div>').'#','');
 
	$db->delete_query('settings','name IN("tpr_usergroups","tpr_forums","tpr_selfrate")');
	$db->delete_query('settinggroups','name="tpr_group"');
	rebuild_settings();
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

    $db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts DROP thumbsup, DROP thumbsdown', true);
	$db->write_query('DROP TABLE IF EXISTS '.TABLE_PREFIX.'thumbspostrating');
}

// Display the RATEBOX
function tpr_box($post)
{
    global $db, $mybb, $templates, $lang, $current_page, $tprdsp;
    $pid = (int) $post['pid'];
	if(!$pid || $current_page != 'showthread.php') return; // paranoia
    
    static $done_init = false;
	static $user_rates = null;
	if(!$done_init)
	{
		$done_init = true;
		
		// Check whether the posts in the forum can be rated
		if( $mybb->settings['tpr_forums'] != 0 )
		{
			$fcr = ;
			foreach(array_map('trim', explode(',',$mybb->settings['tpr_forums'])) as $fid)
			{
				if( ($fid == $post['fid']) )
				{
					global $plugins;
					$plugins->remove_hook('postbit', 'tpr_box');
					return;
				}
			}
		}
		
		// build user rating cache
		$user_rates = array();
		if($mybb->user['uid'])
		{
			if($mybb->input['mode'] == 'threaded')
				$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND pid='.(int)$mybb->input['pid']);
			else
				$query = $db->simple_select('thumbspostrating', 'rating,pid', 'uid='.$mybb->user['uid'].' AND '.$GLOBALS['pids']);
			
			while($ttrate = $db->fetch_array($query))
			{
				$user_rates[$ttrate['pid']] = $ttrate['rating'];
			}
			$db->free_result($query);
		}
		
		$lang->load('thumbspostrating');
		// stick in additional header stuff
		$GLOBALS['headerinclude'] .= '<script type="text/javascript" src="'.$mybb->settings['bburl'].'/jscripts/thumbspostrating.js?ver=1600"></script><link type="text/css" rel="stylesheet" href="'.$mybb->settings['bburl'].'/css/thumbspostrating.css" />';
	}

    $uid = $mybb->user['uid'];
    $fid = $post['fid'];

	// Check whether the user can rate
	$gcr = explode(',',$mybb->settings['tpr_usergroups']);

	for( $num=0; $num < count($gcr); $num++ )
	{
		if( (trim($gcr[$num]) == $mybb->usergroup['gid']) )
		{
			$pem = true;
		}
	}

	if ( $mybb->settings['tpr_selfrate'] == 1 )
	{
		if( $uid == $post['uid'] )
		{
			$pem = false;
		}
	}

	$rated_result = $user_rates[$pid];

	// Make the thumb
	// for user who cannot rate
	if( $pem !=true || $rated_result )
	{
		$tu_img = '<div class="tpr_thumb tu_rd"></div>';
		$td_img = '<div class="tpr_thumb td_ru"></div>';
	}
	// for user already rated thumb up
	elseif( $rated_result == 1 )
	{
		$tu_img = '<div class="tpr_thumb tu_ru"></div>';
		$td_img = '<div class="tpr_thumb td_ru"></div>';
	}
	// for user already rated thumb down
	elseif( $rated_result == -1 )
	{
		$tu_img = '<div class="tpr_thumb tu_rd"></div>';
		$td_img = '<div class="tpr_thumb td_rd"></div>';
	}
	// for user who can rate
	else
	{
		$tu_img = '<a href="javascript:void(0);" class="tpr_thumb tu_nr" title="'.$lang->tpr_rate_up.'" onclick="thumbRate(1,0,'.$pid.')" ></a>';
		$td_img = '<a href="javascript:void(0);" class="tpr_thumb td_nr" title="'.$lang->tpr_rate_down.'" onclick="thumbRate(0,1,'.$pid.')" ></a>';
	};

	// Display the rating box
	$tu_no = $post['thumbsup'];
	$td_no = $post['thumbsdown'];

	$box = <<<BOX
<table class="tpr_box" id="tpr_stat_$pid">
<tr>
	<td class="tu_stat" id="tu_stat_$pid">$tu_no</td>
	<td>$tu_img</td>
	<td>$td_img</td>
	<td class="td_stat" id="td_stat_$pid">$td_no</td>
</tr>
</table>
BOX;

	$post['tprdsp'] = $box;
}

function tpr_action()
{
    global $mybb, $db, $post, $tid;

    $uid = $mybb->user['uid'];
    $tu = $_GET['tu'];
    $td = $_GET['td'];
    $pid = $_GET['pid'];

    //User has rated, first check whether the rating is valid
    if( $mybb->input['action'] == 'tpr' )
    {
        // Check whether the user can rate
        $gcr = explode(',',$mybb->settings['tpr_usergroups']);

        for( $num=0; $num < count($gcr); $num++ )
        {
            if( (trim($gcr[$num]) == $mybb->usergroup['gid']) )
            {
                $can_rate = true;
            }
        }

        // Check whether the user has rated
        $rated = $db->simple_select('thumbspostrating','*','uid='.$uid.' && pid='.$pid);
        $count = $db->num_rows($rated);

        if( $count == 1 )
        {
            $can_rate = false;
        }
    }

    // What to do if user rated thumbs up
    if( ($mybb->input['action'] == 'tpr') && ($tu == 1) && ($can_rate == true) )
    {
        $insert_thumbs = array(
            'rating' => 1,
            'uid' => $uid,
            'pid' => $pid
        );

        $result_initial = $post['thumbsup'];

        $update_post = array(
            'thumbsup' => ++$result_initial,
        );

        // Insert the data into database
        $db->insert_query('thumbspostrating',$insert_thumbs);
        $db->update_query('posts',$update_post,'pid='.$pid);
    }
    // What to do if user rated thumbs down
    elseif( ($mybb->input['action'] == 'tpr') && ($td == 1) && ($can_rate == true) )
    {
        $insert_thumbs = array(
            'rating' => -1,
            'uid' => $uid,
            'pid' => $pid
        );

        $result_initial = $post['thumbsdown'];

        $update_post = array(
            'thumbsdown' => ++$result_initial,
        );

        // Insert the datas into database
        $db->insert_query('thumbspostrating',$insert_thumbs);
        $db->update_query('posts',$update_post,'pid='.$pid);
    }
    else
    {
        return;
    }
}
?>