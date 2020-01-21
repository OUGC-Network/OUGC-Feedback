<?php

/***************************************************************************
 *
 *	OUGC Feedback plugin (/inc/plugins/ougc_feedback.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012-2019 Omar Gonzalez
 *
 *	Website: https://omarg.me
 *
 *	Adds a powerful feedback system to your forum.
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

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Tell MyBB when to run the hook
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_config_settings_start', array('OUGC_Feedback', 'load_language'));
	$plugins->add_hook('admin_style_templates_set', array('OUGC_Feedback', 'load_language'));
	$plugins->add_hook('admin_config_settings_change', array('OUGC_Feedback', 'hook_admin_config_settings_change'));
	$plugins->add_hook('admin_formcontainer_end', array('OUGC_Feedback', 'hook_admin_formcontainer_end'));
	$plugins->add_hook('admin_user_groups_edit_commit', array('OUGC_Feedback', 'hook_admin_user_groups_edit_commit'));
	$plugins->add_hook('admin_forum_management_edit_commit', array('OUGC_Feedback', 'hook_admin_forum_management_edit_commit'));
	$plugins->add_hook('admin_forum_management_add_commit', array('OUGC_Feedback', 'hook_admin_forum_management_edit_commit'));
	$plugins->add_hook('report_content_types', array('OUGC_Feedback', 'hook_report_content_types'));
}
else
{
	global $templatelist, $settings;

	$plugins->add_hook('global_intermediate', array('OUGC_Feedback', 'hook_global_intermediate'));
	$plugins->add_hook('member_profile_end', array('OUGC_Feedback', 'hook_member_profile_end'));
	$plugins->add_hook('postbit', array('OUGC_Feedback', 'hook_postbit'));
	$plugins->add_hook('postbit_prev', array('OUGC_Feedback', 'hook_postbit'));
	$plugins->add_hook('postbit_pm', array('OUGC_Feedback', 'hook_postbit'));
	$plugins->add_hook('postbit_announcement', array('OUGC_Feedback', 'hook_postbit'));
	//$plugins->add_hook('memberlist_end', array('OUGC_Feedback', 'hook_memberlist_end'));
	//$plugins->add_hook('memberlist_intermediate', array('OUGC_Feedback', 'hook_memberlist_intermediate'));
	//$plugins->add_hook('memberlist_user', array('OUGC_Feedback', 'hook_memberlist_user'));
	$plugins->add_hook('report_start', array('OUGC_Feedback', 'hook_report_start'));
	$plugins->add_hook('report_type', array('OUGC_Feedback', 'hook_report_type'));
	$plugins->add_hook('modcp_reports_report', array('OUGC_Feedback', 'hook_modcp_reports_report'));

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	else
	{
		$templatelist = '';
	}

	$templatelist .= 'ougcfeedback_js';

	switch(THIS_SCRIPT)
	{
		case 'member.php':
			$templatelist .= ',ougcfeedback_profile,ougcfeedback_profile_add,ougcfeedback_add,ougcfeedback_add_comment';
			break;
		case 'showthread.php':
			$templatelist .= ',ougcfeedback_postbit';
			break;
	}
}

// Plugin class
class OUGC_Feedback
{
	static public $plugin_info;
	static public $error;
	static public $go_back_button;
	static public $data = array();
	static public $fid;

	function __construct()
	{
		self::set_go_back_button();
	}

	// Plugin API:_info() routine
	function _info()
	{
		global $lang;

		self::load_language();

		return array(
			'name'					=> 'OUGC Feedback',
			'description'			=> $lang->ougc_feedback_desc,
			'website'				=> 'https://ougc.network',
			'author'				=> 'Omar G.',
			'authorsite'			=> 'https://ougc.network',
			'version'				=> '1.8.22',
			'versioncode'			=> 1822,
			'compatibility'			=> '18*',
			'codename'				=> 'ougc_feedback',
			'pl'			=> array(
				'version'	=> 13,
				'url'		=> 'https://community.mybb.com/mods.php?action=view&pid=573'
			)
		);
	}

	// Plugin API:_activate() routine
	function _activate()
	{
		global $PL, $lang, $cache, $db;
		self::load_pluginlibrary();

		$PL->settings('ougc_feedback', $lang->setting_group_ougc_feedback, $lang->setting_group_ougc_feedback_desc, array(
			'allow_profile'				=> array(
			   'title'			=> $lang->setting_ougc_feedback_allow_profile,
			   'description'	=> $lang->setting_ougc_feedback_allow_profile_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),
			'allow_profile_multiple'	=> array(
			   'title'			=> $lang->setting_ougc_feedback_allow_profile_multiple,
			   'description'	=> $lang->setting_ougc_feedback_allow_profile_multiple_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),
			'showin_profile'			=> array(
			   'title'			=> $lang->setting_ougc_feedback_showin_profile,
			   'description'	=> $lang->setting_ougc_feedback_showin_profile_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),
			'showin_postbit'			=> array(
			   'title'			=> $lang->setting_ougc_feedback_showin_postbit,
			   'description'	=> $lang->setting_ougc_feedback_showin_postbit_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),
			'showin_forums'			=> array(
			   'title'			=> $lang->setting_ougc_feedback_showin_forums,
			   'description'	=> $lang->setting_ougc_feedback_showin_forums_desc,
			   'optionscode'	=> 'forumselect',
			   'value'			=> -1
			),
			'postbit_hide_button'					=> array(
			   'title'			=> $lang->setting_ougc_feedback_postbit_hide_button,
			   'description'	=> $lang->setting_ougc_feedback_postbit_hide_button_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 0
			),
			'profile_hide_add'					=> array(
			   'title'			=> $lang->setting_ougc_feedback_profile_hide_add,
			   'description'	=> $lang->setting_ougc_feedback_profile_hide_add_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 0
			),
			'comments_minlength'		=> array(
			   'title'			=> $lang->setting_ougc_feedback_comments_minlength,
			   'description'	=> $lang->setting_ougc_feedback_comments_minlength_desc,
			   'optionscode'	=> 'numeric',
			   'value'			=> 15
			),
			'comments_maxlength'		=> array(
			   'title'			=> $lang->setting_ougc_feedback_comments_maxlength,
			   'description'	=> $lang->setting_ougc_feedback_comments_maxlength_desc,
			   'optionscode'	=> 'numeric',
			   'value'			=> 100
			),
			/*'showin_memberlist'			=> array(
			   'title'			=> $lang->setting_ougc_feedback_showin_memberlist,
			   'description'	=> $lang->setting_ougc_feedback_showin_memberlist_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),*/
			'allow_pm_notifications'	=> array(
			   'title'			=> $lang->setting_ougc_feedback_allow_pm_notifications,
			   'description'	=> $lang->setting_ougc_feedback_allow_pm_notifications_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),
			'allow_email_notifications'	=> array(
			   'title'			=> $lang->setting_ougc_feedback_allow_email_notifications,
			   'description'	=> $lang->setting_ougc_feedback_allow_email_notifications_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 0
			),
			'perpage'					=> array(
			   'title'			=> $lang->setting_ougc_feedback_perpage,
			   'description'	=> $lang->setting_ougc_feedback_perpage_desc,
			   'optionscode'	=> 'numeric',
			   'value'			=> 20
			),
			/*'allow_alert_notifications'	=> array(
			   'title'			=> $lang->setting_ougc_feedback_allow_alert_notifications,
			   'description'	=> $lang->setting_ougc_feedback_allow_alert_notifications_desc,
			   'optionscode'	=> 'yesno',
			   'value'			=> 1
			),*/
		));

		$PL->templates('ougcfeedback', 'OUGC Feedback', array(
			'js'	=> '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/ougc_feedback.js?ver=1819"></script>',
			'form'	=> '<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;" class="modal_{$feedback[\'uid\']}_{$feedback[\'pid\']}">
	<form method="post" action="{$mybb->settings[\'bburl\']}/feedback.php" id="ougcfeedback_form" class="feedback_{$feedback[\'uid\']}_{$feedback[\'pid\']}" onsubmit="javascript: return OUGC_Plugins.{$method};">
		<input name="action" type="hidden" value="{$mybb->input[\'action\']}" />
		<input name="uid" type="hidden" value="{$feedback[\'uid\']}" />
		<input name="pid" type="hidden" value="{$feedback[\'pid\']}" />
		<input name="fid" type="hidden" value="{$feedback[\'fid\']}" />
		<input name="my_post_key" type="hidden" value="{$mybb->post_code}" />
		<input name="reload" type="hidden" value="{$mybb->input[\'reload\']}" />
		<table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" border="0" class="tborder">
			<tr>
				<td class="thead" colspan="2"><strong>{$lang->ougc_feedback_profile_add}</strong></td>
			</tr>
			<tr>
				<td class="trow1" width="40%"><strong>{$lang->ougc_feedback_modal_type}</strong></td>
				<td class="trow1"><select name="type">
	<option value="1"{$type_slected[\'buyer\']}>{$lang->ougc_feedback_type_buyer}</option>
	<option value="2"{$type_slected[\'seller\']}>{$lang->ougc_feedback_type_seller}</option>
	<option value="3"{$type_slected[\'trader\']}>{$lang->ougc_feedback_type_trader}</option>
</select></td>
			</tr>
			<tr>
				<td class="trow2" width="40%"><strong>{$lang->ougc_feedback_modal_feedback}</strong></td>
				<td class="trow2"><select name="feedback">
	<option value="1"{$feedback_slected[\'positibve\']}>{$lang->ougc_feedback_profile_positive}</option>
	<option value="0"{$feedback_slected[\'neutral\']}>{$lang->ougc_feedback_profile_neutral}</option>
	<option value="-1"{$feedback_slected[\'negative\']}>{$lang->ougc_feedback_profile_negative}</option>
</select></td>
			</tr>
			{$comment_row}
			<tr>
				<td class="tfoot" colspan="2" align="center">
					<input name="submit" type="submit" class="button" value="{$lang->ougc_feedback_profile_add}" />
				</td>
			</tr>
		</table>
	</form>
	<script>
		OUGC_Plugins.Feedback_unBind();
	</script>
  </div>
</div>',
			'form_comment'	=> '<tr>
	<td class="trow1" width="40%"><strong>{$lang->ougc_feedback_modal_comment}</strong></td>
	<td class="trow1"><input name="comment" type="text" value="{$mybb->input[\'comment\']}" class="textbox" /></td>
</tr>',
			'modal'	=> '<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">
		<table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" border="0" class="tborder">
			<tr>
				<td class="thead"><strong>{$title}</strong></td>
			</tr>
			<tr>
				<td class="trow1">{$message}</td>
			</tr>
			{$tfoot}
		</table>
  </div>
</div>',
			'modal_tfoot'	=> '<tr>
		<td class="tfoot" align="center"><input name="submit" type="button" class="button" value="{$lang->ougc_feedback_go_back}" onclick="return OUGC_Plugins.Feedback_Add(\'{$uid}\', \'{$pid}\', \'{$mybb->input[\'type\']}\', \'{$mybb->input[\'feedback\']}\', \'{$mybb->input[\'reload\']}\', \'{$mybb->input[\'comment\']}\', \'1\'); return false;" /></td>
</tr>',
			'modal_error'	=> '<blockquote>{$message}</blockquote>',
			'postbit'	=> '<span class="ougcfeedback_info_{$post[\'uid\']}" title="{$lang->ougc_feedback_profile_positive} {$lang->ougc_feedback_profile_title}: {$stats[\'positive\']} ({$stats[\'positive_percent\']}% - {$stats[\'positive_users\']} {$lang->ougc_feedback_profile_users})
{$lang->ougc_feedback_profile_neutral} {$lang->ougc_feedback_profile_title}: {$stats[\'neutral\']} ({$stats[\'neutral_percent\']}% - {$stats[\'neutral_users\']} {$lang->ougc_feedback_profile_users})
{$lang->ougc_feedback_profile_negative} {$lang->ougc_feedback_profile_title}: {$stats[\'negative\']} ({$stats[\'negative_percent\']}% - {$stats[\'negative_users\']} {$lang->ougc_feedback_profile_users})">
	<br />{$lang->ougc_feedback_profile_total} {$lang->ougc_feedback_profile_title}: <a href="{$mybb->settings[\'bburl\']}/feedback.php?uid={$post[\'uid\']}"><strong class="{$class}">{$average}</strong></a></span>
</span>',
			'postbit_button'	=> '<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Add(\'{$post[\'uid\']}\', \'{$post[\'pid\']}\', \'1\', \'1\', \'0\', \'\'); return false;" title="{$lang->ougc_feedback_profile_add}" class="postbit_reputation_add ougcfeedback_add_{$post[\'uid\']}"><span>{$lang->ougc_feedback_profile_add}</span></a>',
			'profile'	=> '<div class="ougcfeedback_info_{$memprofile[\'uid\']}">
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td colspan="2" class="thead"><strong>{$lang->ougc_feedback_profile_title}</strong>{$view_all}</td>
		</tr>
		<tr>
			<td class="trow1" style="width: 30%;"><strong>{$lang->ougc_feedback_profile_total}:</strong></td>
			<td class="trow1">{$stats[\'total\']}</td>
		</tr>
		<tr style="color: green;">
			<td class="trow2" style="width: 30%;"><strong>{$lang->ougc_feedback_profile_positive}:</strong></td>
			<td class="trow2">{$stats[\'positive\']} ({$stats[\'positive_percent\']}% - {$stats[\'positive_users\']} {$lang->ougc_feedback_profile_users})</td>
		</tr>
		<tr style="color: gray;">
			<td class="trow1" style="width: 30%;"><strong>{$lang->ougc_feedback_profile_neutral}:</strong></td>
			<td class="trow1">{$stats[\'neutral\']} ({$stats[\'neutral_percent\']}% - {$stats[\'neutral_users\']} {$lang->ougc_feedback_profile_users})</td>
		</tr>
		<tr style="color: red;">
			<td class="trow2" style="width: 30%;"><strong>{$lang->ougc_feedback_profile_negative}:</strong></td>
			<td class="trow2">{$stats[\'negative\']} ({$stats[\'negative_percent\']}% - {$stats[\'negative_users\']} {$lang->ougc_feedback_profile_users})</td>
		</tr>
		{$add_row}
	</table><br />
</div>',
			'profile_view_all'	=> '<span class="smalltext float_right">(<a href="{$mybb->settings[\'bburl\']}/feedback.php?uid={$memprofile[\'uid\']}">{$lang->ougc_feedback_profile_view}</a>)',
			'profile_add'	=> '<tr class="ougcfeedback_add_{$memprofile[\'uid\']}">
	<td class="trow1" colspan="2" align="right"><a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Add(\'{$memprofile[\'uid\']}\', \'0\', \'1\', \'1\', \'\', \'\'); return false;" title="{$lang->ougc_feedback_profile_add}" class="button small_button">{$lang->ougc_feedback_profile_add}</a></td>
</tr>',
			'page'	=> '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->ougc_feedback_page_title}</title>
{$headerinclude}
<script type="text/javascript">
<!--
	var delete_feedback_confirm = "{$lang->ougc_feedback_confirm_delete}";
	var restore_feedback_confirm = "{$lang->ougc_feedback_confirm_restore}";
// -->
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/report.js?ver=1804"></script>
</head>
<body>
{$header}
{$add_feedback}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder clear">
<tr>
	<td class="thead"><strong>{$lang->ougc_feedback_page_report_for}</strong></td>
</tr>
<tr>
	<td class="tcat"><strong>{$lang->ougc_feedback_page_summary}</strong></td>
</tr>
<tr>
	<td class="trow1">
	<table width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td>
				<span class="largetext"><strong>{$username}</strong></span><br />
				<span class="smalltext">
					({$usertitle})<br />
					<br />
					<strong>{$lang->ougc_feedback_page_stats_total}:</strong> <span class="ougc_feedback_repbox {$total_class}">{$user[\'ougc_feedback\']}</span><br /><br />
					<strong>{$lang->ougc_feedback_page_stats_members}: {$stats[\'members\']}</strong><br />
					<strong>{$lang->ougc_feedback_page_stats_posts}: {$stats[\'posts\']}</strong>
				</span>
			</td>
			<td align="right" style="width: 300px;">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder trow2">
						<tr>
							<td>&nbsp;</td>
							<td><span class="smalltext reputation_positive">{$lang->positive_count}</span></td>
							<td><span class="smalltext reputation_neutral">{$lang->neutral_count}</span></td>
							<td><span class="smalltext reputation_negative">{$lang->negative_count}</span></td>
						</tr>
						<tr>
							<td style="text-align: right;"><span class="smalltext">{$lang->last_week}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$positive_week}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$neutral_week}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$negative_week}</span></td>
						</tr>
						<tr>
							<td style="text-align: right;"><span class="smalltext">{$lang->last_month}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$positive_month}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$neutral_month}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$negative_month}</span></td>
						</tr>
						<tr>
							<td style="text-align: right;"><span class="smalltext">{$lang->last_6months}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$positive_6months}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$neutral_6months}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$negative_6months}</span></td>
						</tr>
						<tr>
							<td style="text-align: right;"><span class="smalltext">{$lang->all_time}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$positive_count}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$neutral_count}</span></td>
							<td style="text-align: center;"><span class="smalltext">{$negative_count}</span></td>
						</tr>
					</table>
			</td>
		</tr>
	</table>
	</td>
</tr>
<tr>
	<td class="tcat"><strong>{$lang->comments}</strong></td>
</tr>
{$feedback_list}
<tr>
	<td class="tfoot" align="right">
	<form action="{$mybb->settings[\'bburl\']}/feedback.php" method="get">
		<input type="hidden" name="uid" value="{$user[\'uid\']}" />
		<select name="show">
			<option value="all"{$show_selected[\'all\']}>{$lang->show_all}</option>
			<option value="positive"{$show_selected[\'positive\']}>{$lang->show_positive}</option>
			<option value="neutral"{$show_selected[\'neutral\']}>{$lang->show_neutral}</option>
			<option value="negative"{$show_selected[\'negative\']}>{$lang->show_negative}</option>
			<option value="gived"{$show_selected[\'gived\']}>{$lang->show_gived}</option>
		</select>
		<select name="sort">
			<option value="dateline"{$sort_selected[\'last_updated\']}>{$lang->sort_updated}</option>
			<option value="username"{$sort_selected[\'username\']}>{$lang->sort_username}</option>
		</select>
		{$gobutton}
	</form>
	</td>
</tr>
</table>
{$multipage}
{$footer}
</body>
</html>',
			'page_addlink'	=> '<div class="float_right" style="padding-bottom: 4px;"><a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Add(\'{$user[\'uid\']}\', \'0\', \'1\', \'1\', \'1\', \'\'); return false;" class="button rate_user_button"><span>{$lang->ougc_feedback_profile_title}</span></a></div>',
			'page_empty'	=> '<tr>
	<td class="trow1" style="text-align: center;">{$lang->ougc_feedback_page_empty}</td>
</tr>',
			'page_item'	=> '<tr>
	<td class="trow1 {$class[\'status\']}" id="fid{$feedback[\'fid\']}">
		{$report_link}{$edit_link}{$delete_hard_link}{$delete_link}
		{$feedback[\'user_username\']} <span class="smalltext">{$last_updated}<br />{$postfeed_given}</span>
		<br />
		<strong class="{$class[\'type\']}">{$vote_type} ({$feedback[\'feedback\']}):</strong> {$feedback[\'comment\']}
	</td>
</tr>',
			'page_item_edit'	=> '<div class="float_right postbit_buttons">
	<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Edit(\'{$feedback[\'fid\']}\'); return false;" class="postbit_edit"><span>{$lang->ougc_feedback_page_edit}</span></a>
</div>',
			'page_item_delete'	=> '<div class="float_right postbit_buttons">
	<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Delete(\'{$feedback[\'fid\']}\', \'{$mybb->post_code}\'); return false;" class="postbit_qdelete"><span>{$lang->ougc_feedback_page_delete}</span></a>
</div>',
			'page_item_delete_hard'	=> '<div class="float_right postbit_buttons">
	<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Delete(\'{$feedback[\'fid\']}\', \'{$mybb->post_code}\', \'1\'); return false;" class="postbit_qdelete"><span>{$lang->ougc_feedback_page_delete_hard}</span></a>
</div>',
			'page_item_report'	=> '<div class="float_right postbit_buttons">
	<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Report(\'{$feedback[\'fid\']}\'); return false;" class="postbit_report"><span>{$lang->ougc_feedback_page_report}</span></a>
</div>',
			'page_item_restore'	=> '<div class="float_right postbit_buttons">
	<a href="javascript: void(0);" onclick="return OUGC_Plugins.Feedback_Restore(\'{$feedback[\'fid\']}\', \'{$mybb->post_code}\'); return false;" class="postbit_qrestore"><span>{$lang->ougc_feedback_page_restore}</span></a>
</div>',
			/*'memberlist_header'	=> '<td class="tcat" width="10%" align="center"><span class="smalltext"><a href="{$sorturl}&amp;sort=feedbacks&amp;order=descending"><strong>{$lang->ougc_feedback_profile_title}</strong></a> {$orderarrow[\'feedback\']}</span></td>',
			'memberlist_sort'	=> '<option value="positive_feedback"{$sort_selected[\'positive_feedback\']}>{$lang->ougc_feedback_memberlist_sort_positive}</option>
<option value="neutral_feedback"{$sort_selected[\'neutral_feedback\']}>{$lang->ougc_feedback_memberlist_sort_neutral}</option>
<option value="negative_feedback"{$sort_selected[\'negative_feedback\']}>{$lang->ougc_feedback_memberlist_sort_negative}</option>',
			'memberlist_user'	=> '<td class="{$alt_bg}" align="center">{$user[\'uid\']}{$user[\'feedback\']}</td>',*/
		));

		$PL->stylesheet('ougc_feedback', '/***************************************************************************
 *
 *	OUGC Feedback plugin (~/ougc_feedback.css)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012-2019 Omar Gonzalez
 *
 *	Website: https://omarg.me
 *
 *	Adds a powerful feedback system to your forum.
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

.ougc_feedback_repbox {
	font-size:16px;
	font-weight: bold;
	padding:5px 7px 5px 7px;
}

._negative {
	background-color: #FDD2D1;
	color: #CB0200;
	border:1px solid #980201;
}

._neutral {
	background-color:#FAFAFA;
	color: #999999;
	border:1px solid #CCCCCC;
}

._positive {
	background-color:#E8FCDC;
	color: #008800;
	border:1px solid #008800;
}', array('feedback.php' => '', 'member.php' => 'profile'));

		self::_deactivate();

		require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
		find_replace_templatesets('member_profile', '#'.preg_quote('{$profilefields}').'#i', '{$profilefields}{$ougc_feedback}');
		find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'button_rep\']}').'#i', '{$post[\'button_rep\']}{$post[\'ougc_feedback_button\']}');
		find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'button_rep\']}').'#i', '{$post[\'button_rep\']}{$post[\'ougc_feedback_button\']}');
		find_replace_templatesets('postbit_author_user', '#'.preg_quote('{$post[\'warninglevel\']}').'#i', '{$post[\'warninglevel\']}<!--OUGC_FEEDBACK-->');
		//find_replace_templatesets('memberlist_user', '#'.preg_quote('{$referral_bit}').'#i', '{$referral_bit}{$ougc_feedback_bit}');
		//find_replace_templatesets('memberlist', '#'.preg_quote('{$referral_header}').'#i', '{$referral_header}{$ougc_feedback_header}');
		//find_replace_templatesets('memberlist', '#'.preg_quote('{$lang->sort_by_referrals}</option>').'#i', '{$lang->sort_by_referrals}</option>{$ougc_feedback_sort}');
		find_replace_templatesets('headerinclude', '#'.preg_quote('{$stylesheets}').'#i', '{$stylesheets}{$ougc_feedback_js}');

		// Insert/update version into cache
		$plugins = $cache->read('ougc_plugins');
		if(!$plugins)
		{
			$plugins = array();
		}

		self::load_plugin_info();

		if(!isset($plugins['feedback']))
		{
			$plugins['feedback'] = self::$plugin_info['versioncode'];
		}

		// TODO:: ip should be stored

		// Add DB fields
		foreach(self::get_db_fields() as $table => $fields)
		{
			foreach($fields as $name => $definition)
			{
				if(!$db->field_exists($name, $table))
				{
					$db->add_column($table, $name, $definition);

					// Set default group permissions
					if($table == 'usergroups')
					{
						if(in_array($name, array('ougc_feedback_mod_candelete')))
						{
							$db->update_query('usergroups', array($name => 1), "gid='4'"); // Administrators
						}

						if(in_array($name, array('ougc_feedback_ismod', 'ougc_feedback_mod_canedit', 'ougc_feedback_mod_canremove')))
						{
							$db->update_query('usergroups', array($name => 1), "gid='4'"); // Administrators
							$db->update_query('usergroups', array($name => 1), "gid='3'"); // Super moderators
						}
					}
				}
				else
				{
					$db->modify_column($table, $name, $definition);
				}
			}
		}

		/*~*~* RUN UPDATES START *~*~*/

		/*~*~* RUN UPDATES END *~*~*/

		$cache->update_usergroups();

		$cache->update_forums();

		$plugins['feedback'] = self::$plugin_info['versioncode'];
		$cache->update('ougc_plugins', $plugins);
	}

	// Plugin API:_deactivate() routine
	function _deactivate()
	{
		require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
		find_replace_templatesets('member_profile', '#'.preg_quote('{$ougc_feedback}').'#i', '', 0);
		find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'ougc_feedback_button\']}').'#i', '', 0);
		find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'ougc_feedback_button\']}').'#i', '', 0);
		find_replace_templatesets('postbit_author_user', '#'.preg_quote('<!--OUGC_FEEDBACK-->').'#i', '', 0);
		//find_replace_templatesets('memberlist_user', '#'.preg_quote('{$ougc_feedback_bit}').'#i', '', 0);
		//find_replace_templatesets('memberlist', '#'.preg_quote('{$ougc_feedback_header}').'#i', '', 0);
		//find_replace_templatesets('memberlist', '#'.preg_quote('{$ougc_feedback_sort}').'#i', '', 0);
		find_replace_templatesets('headerinclude', '#'.preg_quote('{$ougc_feedback_js}').'#i', '', 0);
		//find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'ougc_feedback\']}').'#i', '', 0);
		//find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'ougc_feedback\']}').'#i', '', 0);
	}

	// Plugin API:_install() routine
	function _install()
	{
		global $db, $cache;

		// Create DB table
		switch($db->type)
		{
			case 'pgsql':
				$db->write_query("CREATE TABLE `".TABLE_PREFIX."ougc_feedback` (
						`fid` serial,
						`uid` int NOT NULL DEFAULT '0',
						`fuid` int NOT NULL DEFAULT '0',
						`pid` int NOT NULL DEFAULT '0',
						`type` int NOT NULL DEFAULT '0',
						`feedback` smallint NOT NULL DEFAULT '0',
						`comment` text NOT NULL DEFAULT '',
						`status` tinyint(1) NOT NULL DEFAULT '1',
						`dateline` int NOT NULL DEFAULT '0',,
						PRIMARY KEY(fid)
					);"
				);
				break;
			case 'sqlite':
				$db->write_query("CREATE TABLE `".TABLE_PREFIX."ougc_feedback` (
						`fid` INTEGER PRIMARY KEY,
						`uid` int NOT NULL DEFAULT '0',
						`fuid` int NOT NULL DEFAULT '0',
						`pid` int NOT NULL DEFAULT '0',
						`type` int NOT NULL DEFAULT '0',
						`feedback` smallint NOT NULL DEFAULT '0',
						`comment` text NOT NULL,
						`status` tinyint(1) NOT NULL DEFAULT '1',
						`dateline` int UNSIGNED NOT NULL DEFAULT '0'
					);"
				);
				break;
			default:
				$collation = $db->build_create_table_collation();
				$db->write_query("CREATE TABLE `".TABLE_PREFIX."ougc_feedback` (
						`fid` int UNSIGNED NOT NULL AUTO_INCREMENT,
						`uid` int UNSIGNED NOT NULL DEFAULT '0',
						`fuid` int UNSIGNED NOT NULL DEFAULT '0',
						`pid` int UNSIGNED NOT NULL DEFAULT '0',
						`type` int UNSIGNED NOT NULL DEFAULT '0',
						`feedback` smallint NOT NULL default '0',
						`comment` text NOT NULL,
						`status` tinyint(1) NOT NULL DEFAULT '1',
						`dateline` int UNSIGNED NOT NULL DEFAULT '0',
						KEY uid (uid),
						PRIMARY KEY (`fid`)
					) ENGINE=MyISAM{$collation};"
				);
				break;
		}

		$cache->update_forums();
		$cache->update_usergroups();
	}

	// Plugin API:_is_installed() routine
	function _is_installed()
	{
		global $db;

		return $db->table_exists('ougc_feedback');
	}

	// Plugin API:_uninstall() routine
	function _uninstall()
	{
		global $db, $PL, $cache;
		self::load_pluginlibrary();

		// Drop table
		$db->drop_table('ougc_feedback');

		// Remove DB fields
		foreach(self::get_db_fields() as $table => $fields)
		{
			foreach($fields as $name => $definition)
			{
				if($db->field_exists($name, $table))
				{
					$db->drop_column($table, $name);
				}
			}
		}

		// Delete settings
		$PL->settings_delete('ougc_feedback');

		// Delete templates
		$PL->templates_delete('ougcfeedback');

		// Delete version from cache
		$plugins = (array)$cache->read('ougc_plugins');

		if(isset($plugins['feedback']))
		{
			unset($plugins['feedback']);
		}

		if(!empty($plugins))
		{
			$cache->update('ougc_plugins', $plugins);
		}
		else
		{
			$PL->cache_delete('ougc_plugins');
		}

		$cache->update_forums();
		$cache->update_usergroups();
	}

	// Load language file
	function load_language($force=false)
	{
		global $lang;

		(isset($lang->ougc_feedback) && !$force) or $lang->load('ougc_feedback');
	}

	// Build plugin info
	function load_plugin_info()
	{
		self::$plugin_info = self::_info();
	}

	// PluginLibrary requirement check
	function load_pluginlibrary()
	{
		global $lang;
		self::load_plugin_info();
		self::load_language();

		if(!file_exists(PLUGINLIBRARY))
		{
			flash_message($lang->sprintf($lang->ougc_feedback_pluginlibrary_required, self::$plugin_info['pl']['ulr'], self::$plugin_info['pl']['version']), 'error');
			admin_redirect('index.php?module=config-plugins');
		}

		global $PL;
		$PL or require_once PLUGINLIBRARY;

		if($PL->version < self::$plugin_info['pl']['version'])
		{
			global $lang;

			flash_message($lang->sprintf($lang->ougc_feedback_pluginlibrary_old, $PL->version, self::$plugin_info['pl']['version'], self::$plugin_info['pl']['ulr']), 'error');
			admin_redirect('index.php?module=config-plugins');
		}
	}

	// DB Fields
	function get_db_fields()
	{
		global $db;

		// Create DB table
		switch($db->type)
		{
			case 'pgsql':
				$fields = array(
					'usergroups'	=> array(
						'ougc_feedback_canview'			=> "smallint NOT NULL DEFAULT '1'",
						'ougc_feedback_cangive'			=> "smallint NOT NULL DEFAULT '1'",
						'ougc_feedback_canreceive'		=> "smallint NOT NULL DEFAULT '1'",
						'ougc_feedback_canedit'			=> "smallint NOT NULL DEFAULT '1'",
						'ougc_feedback_canremove'		=> "smallint NOT NULL DEFAULT '1'",
						//'ougc_feedback_value'			=> "int NOT NULL DEFAULT '1'",
						'ougc_feedback_maxperday'		=> "int NOT NULL DEFAULT '5'",
						'ougc_feedback_ismod'			=> "smallint NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_canedit'		=> "smallint NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_canremove'	=> "smallint NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_candelete'	=> "smallint NOT NULL DEFAULT '0'",
					),
					'forums'	=> array(
						'ougc_feedback_allow_threads'	=> "smallint NOT NULL DEFAULT '1'",
						'ougc_feedback_allow_posts'		=> "smallint NOT NULL DEFAULT '1'",
					),
					'users'			=> array(
						'ougc_feedback_notification'	=> "varchar(5) NOT NULL DEFAULT ''",
						'ougc_feedback'	=> "int NOT NULL DEFAULT '0'",
					)
				);
				break;
			default:
				$fields = array(
					'usergroups'	=> array(
						'ougc_feedback_canview'			=> "tinyint(1) NOT NULL DEFAULT '1'",
						'ougc_feedback_cangive'			=> "tinyint(1) NOT NULL DEFAULT '1'",
						'ougc_feedback_canreceive'		=> "tinyint(1) NOT NULL DEFAULT '1'",
						'ougc_feedback_canedit'			=> "tinyint(1) NOT NULL DEFAULT '1'",
						'ougc_feedback_canremove'		=> "tinyint(1) NOT NULL DEFAULT '1'",
						//'ougc_feedback_value'			=> "int UNSIGNED NOT NULL DEFAULT '1'",
						'ougc_feedback_maxperday'		=> "int UNSIGNED NOT NULL DEFAULT '5'",
						'ougc_feedback_ismod'			=> "tinyint(1) NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_canedit'		=> "tinyint(1) NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_canremove'	=> "tinyint(1) NOT NULL DEFAULT '0'",
						'ougc_feedback_mod_candelete'	=> "tinyint(1) NOT NULL DEFAULT '0'",
					),
					'forums'	=> array(
						'ougc_feedback_allow_threads'	=> "tinyint(1) NOT NULL DEFAULT '1'",
						'ougc_feedback_allow_posts'		=> "tinyint(1) NOT NULL DEFAULT '1'",
					),
					'users'			=> array(
						'ougc_feedback_notification'	=> "varchar(5) NOT NULL DEFAULT ''",
						'ougc_feedback'	=> "int NOT NULL DEFAULT '0'",
					)
				);
				break;
		}

		return $fields;
	}

	// Default status
	function default_status()
	{
		return 1;
	}

	// Send an error to the browser
	function error($message, $title='', $success=false, $replacement='', $hide_add=1)
	{
		global $templates, $lang, $theme, $mybb;
		self::load_language();

		$title = $title ? $title : $lang->error;
		$message = $message ? $message : $lang->message;

		if($success)
		{
			header('Content-type: application/json; charset='.$lang->settings['charset']);

			$data = array('replacement' => $replacement, 'hide_add' => $hide_add, 'reload' => $mybb->get_input('reload', 1));

			eval('$data[\'modal\'] = "'.$templates->get('ougcfeedback_modal', 1, 0).'";');

			echo json_encode($data);
		}
		else
		{
			self::set_error($message);

			$message = self::get_error();

			eval('$message = "'.$templates->get('ougcfeedback_modal_error').'";');

			$tfoot = self::get_go_back_button();

			eval('echo "'.$templates->get('ougcfeedback_modal', 1, 0).'";');
		}

		exit;
	}

	// Send an error to the browser
	function success($message, $title='', $replacement='', $hide_add=1)
	{
		//self::set_go_back_button(false);
		self::error($message,  $title, true, $replacement, $hide_add);
	}

	// Set error
	function set_error($message)
	{
		self::$error = $message;
	}

	// Get error
	function get_error()
	{
		return self::$error;
	}

	// Set go back button status
	function set_go_back_button($_=true)
	{
		self::$go_back_button = $_;
	}

	// Get go back button
	function get_go_back_button()
	{
		if(self::$go_back_button)
		{
			global $mybb, $templates, $lang;

			self::load_language();

			$mybb->input['type'] = $mybb->get_input('type', MyBB::INPUT_INT);
			$mybb->input['feedback'] = $mybb->get_input('feedback', MyBB::INPUT_INT);
			$mybb->input['reload'] = $mybb->get_input('reload', MyBB::INPUT_INT);
			$mybb->input['comment'] = $mybb->get_input('comment', MyBB::INPUT_STRING);

			$uid = self::$data['uid'];
			$pid = self::$data['pid'];

			return eval('return "'.$templates->get('ougcfeedback_modal_tfoot').'";');
		}

		return '';
	}

	// Feedback: 
	function set_data($feedback)
	{
		global $db;

		!isset($feedback['fid']) or self::$data['fid'] = (int)$feedback['fid'];
		!isset($feedback['fid']) or self::$data['fid'] = (int)$feedback['fid'];
		!isset($feedback['uid']) or self::$data['uid'] = (int)$feedback['uid'];
		!isset($feedback['fuid']) or self::$data['fuid'] = (int)$feedback['fuid'];
		!isset($feedback['pid']) or self::$data['pid'] = (int)$feedback['pid'];
		!isset($feedback['type']) or self::$data['type'] = (int)$feedback['type'];
		!isset($feedback['feedback']) or self::$data['feedback'] = (int)$feedback['feedback'];
		!isset($feedback['comment']) or self::$data['comment'] = (string)$feedback['comment'];
		!isset($feedback['status']) or self::$data['status'] = (int)$feedback['status'];
		!isset($feedback['dateline']) or self::$data['dateline'] = TIME_NOW;
	}

	// Feedback: get data
	function get_data()
	{
		return self::$data;
	}

	// Feedback: Insert
	function validate_feedback()
	{
		if(self::$error)
		{
			return false;
		}

		return true;
	}

	// Feedback: Fetch
	function fetch_feedback( int $fid)
	{
		global $db;

		$query = $db->simple_select('ougc_feedback', '*', "fid='{$fid}'");
		$feedback = $db->fetch_array($query);

		return $feedback;
	}

	// Feedback: Insert
	function insert_feedback($update=false)
	{
		global $db;

		$feedback = &self::$data;

		$insert_data = array();

		//!isset($feedback['fid']) or $insert_data['fid'] = (int)$feedback['fid'];
		!isset($feedback['uid']) or $insert_data['uid'] = (int)$feedback['uid'];
		!isset($feedback['fuid']) or $insert_data['fuid'] = (int)$feedback['fuid'];
		!isset($feedback['pid']) or $insert_data['pid'] = (int)$feedback['pid'];
		!isset($feedback['type']) or $insert_data['type'] = (int)$feedback['type'];
		!isset($feedback['feedback']) or $insert_data['feedback'] = (int)$feedback['feedback'];
		!isset($feedback['comment']) or $insert_data['comment'] = $db->escape_string($feedback['comment']);
		!isset($feedback['status']) or $insert_data['status'] = (int)$feedback['status'];

		if(!$update)
		{
			!isset($feedback['dateline']) or $insert_data['dateline'] = (int)$feedback['dateline'];
		}

		if($update)
		{
			self::$fid = $feedback['fid'];

			$db->update_query('ougc_feedback', $insert_data, "fid='{$feedback['fid']}'");
		}
		else
		{
			$insert_data['dateline'] = TIME_NOW;

			self::$fid = $db->insert_query('ougc_feedback', $insert_data);
		}

		//self::sync_user($insert_data['uid']);

		return $insert_data;
	}

	// Feedback: Update
	function update_feedback()
	{
		self::insert_feedback(true);
	}

	// Feedback: Update
	function delete_feedback( int $fid)
	{
		global $db;

		$db->delete_query('ougc_feedback', "fid='{$fid}'");
	}

	// Send a Private Message to an user (Copied from MyBB 1.7)
	function send_pm($pm, $fromid=0, $admin_override=false)
	{
		global $mybb;

		if(!$mybb->settings['ougc_feedback_allow_pm_notifications'] || !$mybb->settings['enablepms'] || !is_array($pm))
		{
			return false;
		}

		if (!$pm['subject'] ||!$pm['message'] || !$pm['touid'] || (!$pm['receivepms'] && !$admin_override))
		{
			return false;
		}

		global $lang, $session;
		$lang->load('messages');

		require_once MYBB_ROOT."inc/datahandlers/pm.php";

		$pmhandler = new PMDataHandler();

		$user = get_user($pm['touid']);

		// Build our final PM array
		$pm = array(
			'subject'		=> $pm['subject'],
			'message'		=> $lang->sprintf($pm['message'], $user['username'], $mybb->settings['bbname']),
			'icon'			=> -1,
			'fromid'		=> ($fromid == 0 ? (int)$mybb->user['uid'] : ($fromid < 0 ? 0 : $fromid)),
			'toid'			=> array($pm['touid']),
			'bccid'			=> array(),
			'do'			=> '',
			'pmid'			=> '',
			'saveasdraft'	=> 0,
			'options'	=> array(
				'signature'			=> 0,
				'disablesmilies'	=> 0,
				'savecopy'			=> 0,
				'readreceipt'		=> 0
			)
		);

		if(isset($mybb->session))
		{
			$pm['ipaddress'] = $mybb->session->packedip;
		}

		// Admin override
		$pmhandler->admin_override = (int)$admin_override;

		$pmhandler->set_data($pm);

		if($pmhandler->validate_pm())
		{
			$pmhandler->insert_pm();
			return true;
		}

		return false;
	}

	// Send a e-mail to an user
	function send_email($email)
	{
		global $mybb, $db, $lang;

		if(!$mybb->settings['ougc_feedback_allow_email_notifications'])
		{
			return false;
		}

		// Load language
		if($email['language'] != $mybb->user['language'] && $lang->language_exists($email['language']))
		{
			$reset_lang = true;
			$lang->set_language($email['language']);
			self::load_language(true);
		}

		foreach(array('subject', 'message') as $key)
		{
			$lang_string = $email[$key];
			if(is_array($email[$key]))
			{
				$num_args = count($email[$key]);

				for($i = 1; $i < $num_args; $i++)
				{
					$lang->{$email[$key][0]} = str_replace('{'.$i.'}', $email[$key][$i], $lang->{$email[$key][0]});
				}

				$lang_string = $email[$key][0];
			}

			$email[$key] = $lang->{$lang_string};
		}

		if(!$email['subject'] || !$email['message'] || !$email['to'])
		{
			return false;
		}

		my_mail($email['to'], $email['subject'], $email['message'], $email['from'], '', '', false, 'text', '', '');

		// Log the message
		if($mybb->settings['mail_logging'])
		{
			$entry = array(
				'subject'	=> $db->escape_string($email['subject']),
				'message'	=> $db->escape_string($email['message']),
				'dateline'	=> TIME_NOW,
				'fromuid'	=> 0,
				'fromemail'	=> $db->escape_string($email['from']),
				'touid'		=> $email['touid'],
				'toemail'	=> $db->escape_string($email['to']),
				'tid'		=> 0,
				'ipaddress'	=> $db->escape_binary($mybb->session->packedip),
				'type'		=> 1
			);

			$db->insert_query('maillogs', $entry);
		}

		// Reset language
		if(isset($reset_lang))
		{
			$lang->set_language($mybb->user['language']);
			self::load_language(true);
		}
	}

	// Sync user feedback
	function sync_user( int $uid)
	{
		global $db;

		$query = $db->simple_select('ougc_feedback', 'SUM(feedback) AS feedback', "uid='{$uid}' AND status='1'");
		$feedback = $db->fetch_field($query, 'feedback');

		$db->update_query('users', array('ougc_feedback' => $feedback), "uid='{$uid}'");
	}

	// Hook: admin_config_settings_change
	function hook_admin_config_settings_change()
	{
		global $db, $mybb;

		$query = $db->simple_select('settinggroups', 'name', "gid='{$mybb->get_input('gid', 1)}'");

		!($db->fetch_field($query, 'name') == 'ougc_feedback') or self::load_language();
	}

	// Hook: admin_formcontainer_end
	function hook_admin_formcontainer_end()
	{
		global $run_module, $form_container, $lang;

		if($run_module == 'user' && isset($form_container->_title) && $form_container->_title == $lang->users_permissions)
		{
			global $form, $mybb;
			self::load_language();

			$perms = array();

			$db_fields = self::get_db_fields();
			foreach($db_fields['usergroups'] as $name => $definition)
			{
				if($name == 'ougc_feedback_maxperday')
				{
					$perms[] = "<br />{$lang->ougc_feedback_permission_maxperday}<br /><small>{$lang->ougc_feedback_permission_maxperday_desc}</small><br />{$form->generate_text_box($name, $mybb->get_input($name, 1), array('id' => $name, 'class' => 'field50'))}";
				}
				else
				{
					$lang_var = 'ougc_feedback_permission_'.str_replace('ougc_feedback_', '', $name);
					$perms[] = $form->generate_check_box($name, 1, $lang->{$lang_var}, array('checked' => $mybb->get_input($name, 1)));
				}
			}

			$form_container->output_row($lang->setting_group_ougc_feedback, '', '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $perms).'</div>');
		}

		if($run_module == 'forum' && isset($form_container->_title) && ($form_container->_title == $lang->additional_forum_options || $form_container->_title == "<div class=\"float_right\" style=\"font-weight: normal;\"><a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">{$lang->hide_additional_options}</a></div>".$lang->additional_forum_options))
		{
			global $form, $mybb, $forum_data;
			self::load_language();

			$perms = array();

			$db_fields = self::get_db_fields();
			foreach($db_fields['forums'] as $name => $definition)
			{
				$lang_var = 'ougc_feedback_permission_'.str_replace('ougc_feedback_', '', $name);
				$perms[] = $form->generate_check_box($name, 1, $lang->{$lang_var}, array('checked' => isset($forum_data[$name]) ? (int)$forum_data[$name] : 1));
			}

			$form_container->output_row($lang->setting_group_ougc_feedback, '', '<div class="forum_settings_bit">'.implode('</div><div class="forum_settings_bit">', $perms).'</div>');
		}
	}

	// Hook: admin_user_groups_edit_commit
	function hook_admin_user_groups_edit_commit()
	{
		global $updated_group, $mybb;

		$array_data = array();
		$db_fields = self::get_db_fields();
		foreach($db_fields['usergroups'] as $name => $definition)
		{
			$array_data[$name] = $mybb->get_input($name, 1);
		}

		$updated_group = array_merge($updated_group, $array_data);
	}

	// Hook: admin_forum_management_edit_commit
	function hook_admin_forum_management_edit_commit()
	{
		global $db, $mybb, $fid, $plugins;

		$array_data = array();
		$db_fields = self::get_db_fields();
		foreach($db_fields['forums'] as $name => $definition)
		{
			$array_data[$name] = $mybb->get_input($name, 1);
		}

		$db->update_query('forums', $array_data, "fid='{$fid}'");

		$mybb->cache->update_forums();
	}

	// Hook: global_intermediate
	function hook_global_intermediate()
	{
		global $templates, $ougc_feedback_js, $mybb;

        eval('$ougc_feedback_js = "'.$templates->get('ougcfeedback_js').'";');
	}

	// Hook: member_profile_end
	function hook_member_profile_end()
	{
		global $db, $memprofile, $templates, $ougc_feedback, $theme, $lang, $mybb;

		self::load_language();

		$ougc_feedback = '';
		if(!$mybb->settings['ougc_feedback_showin_profile'])
		{
			return;
		}

		$where = array("uid='{$memprofile['uid']}'", /*"fuid!='0'", */"status='1'");
		/*if(!$mybb->usergroup['ougc_feedback_ismod'])
		{
			$where[] = "status='1'";
		}*/

		$stats = array('total' => 0, 'positive' => 0, 'neutral' => 0, 'negative' => 0, 'positive_percent' => 0, 'neutral_percent' => 0, 'negative_percent' => 0, 'positive_users' => array(), 'neutral_users' => array(), 'negative_users' => array());

		$query = $db->simple_select('ougc_feedback', '*', implode(' AND ', $where));
		while($feedback = $db->fetch_array($query))
		{
			++$stats['total'];

			$feedback['feedback'] = (int)$feedback['feedback'];
			switch($feedback['feedback'])
			{
				case 1:
					++$stats['positive'];
					$stats['positive_users'][$feedback['fuid']] = 1;
					break;
				case 0:
					++$stats['neutral'];
					$stats['neutral_users'][$feedback['fuid']] = 1;
					break;
				case -1:
					++$stats['negative'];
					$stats['negative_users'][$feedback['fuid']] = 1;
					break;
			}
		}

		if($stats['total'])
		{
			$stats['positive_percent'] = floor(100*($stats['positive']/$stats['total']));
			$stats['neutral_percent'] = floor(100*($stats['neutral']/$stats['total']));
			$stats['negative_percent'] = floor(100*($stats['negative']/$stats['total']));
		}

		$stats['positive_users'] = count($stats['positive_users']);
		$stats['neutral_users'] = count($stats['neutral_users']);
		$stats['negative_users'] = count($stats['negative_users']);

		$stats = array_map('my_number_format', $stats);

		$add_row = '';
		$trow = 'trow1';

		$memprofile_perms = usergroup_permissions($memprofile['usergroup'].','.$memprofile['additionalgroups']);

		if($mybb->settings['ougc_feedback_allow_profile'] && $mybb->usergroup['ougc_feedback_cangive'] && $memprofile_perms['ougc_feedback_canreceive'] && $mybb->user['uid'] != $memprofile['uid'])
		{
			$show = true;
			if(!$mybb->settings['ougc_feedback_allow_profile_multiple'] && $mybb->settings['ougc_feedback_profile_hide_add'])
			{
				$where = array("uid='{$memprofile['uid']}'", /*"fuid!='0'", */"fuid='{$mybb->user['uid']}'");

				if(!$mybb->usergroup['ougc_feedback_ismod'])
				{
					$where[] = "status='1'";
				}

				$query = $db->simple_select('ougc_feedback', 'fid', implode(' AND ', $where));

				if($db->fetch_field($query, 'fid'))
				{
					$show = false;
				}
			}

			if($show)
			{
				$trow = 'trow2';
				$pid = '';

				$uid = $memprofile['uid'];

				$mybb->input['type'] = isset($mybb->input['type']) ? $mybb->get_input('type', 1) : 1 ;
				$mybb->input['feedback'] = isset($mybb->input['feedback']) ? $mybb->get_input('feedback', 1) : 1 ;

				eval('$add_row = "'.$templates->get('ougcfeedback_profile_add').'";');
			}
		}

		$view_all = '';
		if($mybb->usergroup['ougc_feedback_canview'])
		{
			eval('$view_all = "'.$templates->get('ougcfeedback_profile_view_all').'";');
		}

		eval('$ougc_feedback = "'.$templates->get('ougcfeedback_profile').'";');
	}

	// Hook: postbit
	function hook_postbit(&$post)
	{
		global $db, $templates, $theme, $lang, $mybb, $pids;

		self::load_language();

		$post['ougc_feedback'] = $post['ougc_feedback_button'] = '';

		$show = true;
		if(!empty($post['fid']) && (!$mybb->settings['ougc_feedback_showin_forums'] || ($mybb->settings['ougc_feedback_showin_forums'] != -1 && !in_array($post['fid'], array_map('intval', explode(',', $mybb->settings['ougc_feedback_showin_forums']))))))
		{
			$show = false;
		}

		if($show && $mybb->settings['ougc_feedback_showin_postbit'])
		{
			static $query_cache;

			if(!isset($query_cache))
			{
				global $plugins;

				$where = array(/*"fuid!='0'", */"status='1'");

				/*if(!$mybb->usergroup['ougc_feedback_ismod'])
				{
					$where[] = "status='1'";
				}*/

				if($plugins->current_hook == 'postbit' && $mybb->get_input('mode') != 'threaded' && !empty($pids) && THIS_SCRIPT != 'newreply.php')
				{
					$uids = array();

					$query = $db->simple_select('users u LEFT JOIN '.TABLE_PREFIX.'posts p ON (p.uid=u.uid)', 'u.uid', "p.{$pids}");
					while($uid = $db->fetch_field($query, 'uid'))
					{
						$uids[$uid] = (int)$uid;
					}
					$where[] = "uid IN ('".implode("','", $uids)."')";
				}
				else
				{
					$where[] = "uid='{$post['uid']}'";
				}

				$query = $db->simple_select('ougc_feedback', 'feedback,uid,fuid', implode(' AND ', $where));
				while($feedback = $db->fetch_array($query))
				{
					$uid = (int)$feedback['uid'];
					unset($feedback['uid']);
					$query_cache[$uid][] = $feedback;
				}
			}

			$stats = array(
				'total' => 0,
				'positive' => 0,
				'neutral' => 0,
				'negative' => 0,
				'positive_percent' => 0,
				'neutral_percent' => 0,
				'negative_percent' => 0,
				'positive_users' => array(),
				'neutral_users' => array(),
				'negative_users' => array()
			);

			if(!empty($query_cache[$post['uid']]))
			{
				foreach($query_cache[$post['uid']] as $feedback)
				{
					++$stats['total'];

					$feedback['feedback'] = (int)$feedback['feedback'];
					switch((int)$feedback['feedback'])
					{
						case 1:
							++$stats['positive'];
							$stats['positive_users'][$feedback['fuid']] = 1;
							break;
						case 0:
							++$stats['neutral'];
							$stats['neutral_users'][$feedback['fuid']] = 1;
							break;
						case -1:
							++$stats['negative'];
							$stats['negative_users'][$feedback['fuid']] = 1;
							break;
					}
				}
			}

			if($stats['total'])
			{
				$stats['positive_percent'] = floor(100*($stats['positive']/$stats['total']));
				$stats['neutral_percent'] = floor(100*($stats['neutral']/$stats['total']));
				$stats['negative_percent'] = floor(100*($stats['negative']/$stats['total']));
			}

			$stats['positive_users'] = count($stats['positive_users']);
			$stats['neutral_users'] = count($stats['neutral_users']);
			$stats['negative_users'] = count($stats['negative_users']);

			$stats = array_map('my_number_format', $stats);

			/*$view_all = '';
			if($mybb->usergroup['ougc_feedback_canview'])
			{
				eval('$view_all = "'.$templates->get('ougcfeedback_postbit_view_all').'";');
			}*/

			$average = $stats['positive'] - $stats['negative'];

			$class = 'reputation_neutral';
			if($average > 0)
			{
				$class = 'reputation_positive';
			}
			elseif($average < 0)
			{
				$class = 'reputation_negative';
			}

			$average = my_number_format($average);

			eval('$post[\'ougc_feedback\'] = "'.$templates->get('ougcfeedback_postbit').'";');
			$post['user_details'] = str_replace('<!--OUGC_FEEDBACK-->', $post['ougc_feedback'], $post['user_details']);
		}

		global $plugins, $thread, $forum;

		if(!$forum['ougc_feedback_allow_threads'] && !$forum['ougc_feedback_allow_posts'])
		{
			return;
		}

		if($forum['ougc_feedback_allow_threads'] && !$forum['ougc_feedback_allow_posts'] && $thread['firstpost'] != $post['pid'])
		{
			return;
		}

		if(!$forum['ougc_feedback_allow_threads'] && $forum['ougc_feedback_allow_posts'] && $thread['firstpost'] == $post['pid'])
		{
			return;
		}

		$post_perms = usergroup_permissions($post['usergroup'].','.$post['additionalgroups']);

		if($mybb->usergroup['ougc_feedback_cangive'] && $post_perms['ougc_feedback_canreceive'] && $mybb->user['uid'] != $post['uid'])
		{
			static $button_query_cache;

			if(!isset($button_query_cache) && $mybb->settings['ougc_feedback_postbit_hide_button'])
			{
				global $plugins;

				$where = array("f.fuid='{$mybb->user['uid']}'");

				if(!$mybb->usergroup['ougc_feedback_ismod'])
				{
					$where[] = "f.status='1'";
				}

				if($plugins->current_hook == 'postbit' && $mybb->get_input('mode') != 'threaded' && !empty($pids) && THIS_SCRIPT != 'newreply.php')
				{
					$where[] = "p.{$pids}";
					$join = ' LEFT JOIN '.TABLE_PREFIX.'posts p ON (p.pid=f.pid)';
				}
				else
				{
					$where[] = "f.pid='{$post['pid']}'";
					$join = '';
				}

				$query = $db->simple_select('ougc_feedback f'.$join, 'f.pid', implode(' AND ', $where));
				while($pid = $db->fetch_field($query, 'pid'))
				{
					$query_cache[$pid][] = $pid;
				}
			}

			if(!isset($button_query_cache[$post['pid']]))
			{
				eval('$post[\'ougc_feedback_button\'] = "'.$templates->get('ougcfeedback_postbit_button').'";');
			}
		}

		#$plugins->remove_hook('postbit', array('OUGC_Feedback', 'hook_postbit'));
	}

	// Hook: report_content_types
	function hook_report_content_types(&$args)
	{
		self::load_language();

		$args[] = 'feedback';
	}

	// Hook: report_start
	function hook_report_start()
	{
		global $mybb;

		if($mybb->get_input('type') == 'feedback')
		{
			self::load_language();
		}
	}

	// Hook: report_type
	function hook_report_type()
	{
		global $report_type;

		if($report_type != 'feedback')
		{
			return;
		}

		global $db, $mybb, $error, $verified, $id, $id2, $id3, $report_type_db, $lang;

		$fid = $mybb->get_input('pid', MyBB::INPUT_INT);

		// Any member can report a reputation comment but let's make sure it exists first
		$query = $db->simple_select('ougc_feedback', '*', "fid='{$fid}'");
		$feedback = $db->fetch_array($query);

		if(empty($feedback))
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			$verified = true;

			$id = $feedback['fid']; // id is the feedback id
			$id2 = $feedback['fuid']; // id2 is the user who gave the feedback
			$id3 = $feedback['uid']; // id3 is the user who received the feedback

			$report_type_db = "type='feedback'";
		}
	}

	// Hook: modcp_reports_report
	function hook_modcp_reports_report()
	{
		global $report;

		if($report['type'] != 'feedback')
		{
			return;
		}

		global $reputation_link, $bad_user, $lang, $good_user, $usercache, $report_data;
		self::load_language();

		$user = get_user($report['id3']);

		$reputation_link = "feedback.php?uid={$user['uid']}&amp;fid={$report['id']}";
		$bad_user = build_profile_link($usercache[$report['id2']]['username'], $usercache[$report['id2']]['uid']);
		$report_data['content'] = $lang->sprintf($lang->ougc_feedback_report_info, $reputation_link, $bad_user);

		$good_user = build_profile_link($user['username'], $user['uid']);
		$report_data['content'] .= $lang->sprintf($lang->ougc_feedback_report_info_profile, $good_user);
	}

	/*
	// Hook: memberlist_end
	function hook_memberlist_end()
	{
		global $mybb;

		if(!$mybb->settings['ougc_feedback_showin_memberlist'])
		{
			return;
		}

		global $templates, $ougc_feedback_header, $ougc_feedback_sort, $sorturl, $lang, $colspan, $sort_selected;
		self::load_language();

		++$colspan;

		eval('$ougc_feedback_header = "'.$templates->get('ougcfeedback_memberlist_header').'";');

		eval('$ougc_feedback_sort = "'.$templates->get('ougcfeedback_memberlist_sort').'";');
	}

	// Hook: memberlist_user
	function hook_memberlist_user(&$user)
	{
		global $mybb;

		if(!$mybb->settings['ougc_feedback_showin_memberlist'])
		{
			return;
		}

		global $templates, $ougc_feedback_bit, $alt_bg;
		self::load_language();

		static $done = false;

		if(!$done)
		{
			global $alttrow;

			$done = true;

			if($alttrow == "trow1")
			{
				$alt_bg = "trow2";
			}
			else
			{
				$alt_bg = "trow1";
			}
		}

		eval('$ougc_feedback_bit = "'.$templates->get('ougcfeedback_memberlist_user').'";');
	}
	*/
}

// Plugin API
function ougc_feedback_info()
{
	return OUGC_Feedback::_info();
}

// _activate() routine
function ougc_feedback_activate()
{
	return OUGC_Feedback::_activate();
}

// _deactivate() routine
function ougc_feedback_deactivate()
{
	return OUGC_Feedback::_deactivate();
}

// _install() routine
function ougc_feedback_install()
{
	return OUGC_Feedback::_install();
}

// _is_installed() routine
function ougc_feedback_is_installed()
{
	return OUGC_Feedback::_is_installed();
}

// _uninstall() routine
function ougc_feedback_uninstall()
{
	return OUGC_Feedback::_uninstall();
}