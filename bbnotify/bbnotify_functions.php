<?php

function bbnotify_enabled() {
	global $forum_user;
	return !$forum_user['is_guest'];
}

function bbnotify_match_users($text, $fid) {
	global $forum_db;

	$bbnotify_userlist = array();

	preg_match_all('#\[notify\]([^\[]*?)\[/notify\]#', $text, $bbnotify_all_matches, PREG_PATTERN_ORDER);
	if (count($bbnotify_all_matches[1]) > 0) {
		$bbnotify_sql = array();

		foreach ($bbnotify_all_matches[1] as $bbnotify_match) {
			$bbnotify_sql[] = "'".$forum_db->escape($bbnotify_match)."'";
		}
		$query = array(
			'SELECT'	=> 'u.username, u.id, u.email, u.language',
			'FROM'		=> 'users u',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=\''.(int)$fid.'\' AND fp.group_id=u.group_id)'
				),
				array(
					'LEFT JOIN'		=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
			),
			'WHERE'		=> 'u.id > 1 AND u.group_id != '.FORUM_UNVERIFIED.' AND u.username in('.implode(',', $bbnotify_sql).') AND (fp.read_forum IS NULL OR fp.read_forum=1) AND g.g_read_board=1'
		);

		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		while ($bbnotify_user = $forum_db->fetch_assoc($result)) {
			$bbnotify_userlist[] = $bbnotify_user;
		}
	}

	return $bbnotify_userlist;
}

function bbnotify_parse_tags($text, $is_signature) {
	global $forum_url;

	// notify not supported in signature
	if ($is_signature) {
		return str_replace(array('[notify]', '[/notify]'), '', $text);
	}

	// forum id -1: no need to check here as we don't notify anybody
	$bbnotify_userlist = bbnotify_match_users($text, -1);

	if (count($bbnotify_userlist) == 0) {
		return $text;
	}

	$bbnotify_search = array();
	$bbnotify_replace = array();

	foreach ($bbnotify_userlist as $bbnotify_user) {
		$bbnotify_search[] = '[notify]'.$bbnotify_user['username'].'[/notify]';
		$bbnotify_replace[] = '<a href="'.forum_link($forum_url['user'], (int) $bbnotify_user['id']).'">@'.forum_htmlencode($bbnotify_user['username']).'</a>';
	}

	return str_replace($bbnotify_search, $bbnotify_replace, $text);
}

function bbnotify_send_notifications($post_info, $new_pid) {
	global $lang_common, $forum_user, $forum_config, $forum_url, $forum_db;

	$bbnotify_userlist = bbnotify_match_users($post_info['message'], $post_info['forum_id']);

	if (count($bbnotify_userlist) == 0) {
		return;
	}

	if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/email.php';

	$notification_emails = array();

	// Loop through subscribed users and send e-mails
	foreach ($bbnotify_userlist as $cur_recipient) {
		// don't notify yourself!
		if ($cur_recipient['id'] == $forum_user['id']) continue;

		if ($forum_config['o_bbnotify_send_email'] == '1') {
			// Is the subscription e-mail for $cur_recipient['language'] cached or not?
			if (!isset($notification_emails[$cur_recipient['language']]) && file_exists(__DIR__.'/lang/'.$cur_recipient['language'].'/mail_templates/new_notify.tpl')) {
				// Load the "new reply" template
				$mail_tpl = forum_trim(file_get_contents(__DIR__.'/lang/'.$cur_recipient['language'].'/mail_templates/new_notify.tpl'));

				// The first row contains the subject (it also starts with "Subject:")
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = forum_trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = forum_trim(substr($mail_tpl, $first_crlf));

				$mail_subject = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_subject);
				$mail_message = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_message);
				$mail_message = str_replace('<replier>', $post_info['poster'], $mail_message);
				$mail_message = str_replace('<post_url>', forum_link($forum_url['post'], $new_pid), $mail_message);
				$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $forum_config['o_board_title']), $mail_message);

				$notification_emails[$cur_recipient['language']][0] = $mail_subject;
				$notification_emails[$cur_recipient['language']][1] = $mail_message;

				$mail_subject = $mail_message = null;
			}

			// We have to double check here because the templates could be missing
			// Make sure the e-mail address format is valid before sending
			if (isset($notification_emails[$cur_recipient['language']]) && is_valid_email($cur_recipient['email'])) {
				forum_mail($cur_recipient['email'], $notification_emails[$cur_recipient['language']][0], $notification_emails[$cur_recipient['language']][1]);
			}
		}

		if ($forum_config['o_bbnotify_show_list'] == '1') {
			// Save to DB
			$query = array(
				'INSERT'		=> 'user_id, post_id',
				'INTO'			=> 'bbnotify',
				'VALUES'		=> $cur_recipient['id'].', '.$new_pid
			);

			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}
}

function bbnotify_print_notifications() {
	global $forum_user, $forum_config, $forum_url, $forum_db, $lang_common, $lang_bbnotify, $tpl_main;

	// Forum language is just loaded on some views, we need it always
	require FORUM_ROOT.'lang/'.$forum_user['language'].'/forum.php';

	if ($forum_user['is_guest']) return '';

	$query = array(
		'SELECT'	=> 'p.id AS notify_post_id, p.poster AS notify_poster, p.posted AS notify_posted, t.id as topic_id, t.poster, t.subject, t.num_replies, t.last_post, t.last_post_id, t.last_poster, t.closed, t.moved_to, t.forum_id',
		'FROM'		=> 'bbnotify n',
		'JOINS'     => array(
				array(
					'LEFT JOIN' 	=> 'users AS u',
					'ON'            => 'u.id=n.user_id'
				),
				array(
					'LEFT JOIN' 	=> 'posts AS p',
					'ON'            => 'p.id=n.post_id'
				),
				array(
					'LEFT JOIN' 	=> 'topics AS t',
					'ON'            => 't.id=p.topic_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id=u.group_id)'
				),
				array(
					'LEFT JOIN'		=> 'groups AS g',
					'ON'			=> 'g.g_id=u.group_id'
				)
		),
		'WHERE'		=> 'n.user_id='.$forum_user['id'].' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND g.g_read_board=1',
		'ORDER BY'	=> 'p.posted DESC',
	);

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$notifications = array();

	while ($cur_post = $forum_db->fetch_assoc($result)) {
		$notifications[] = $cur_post;
	}

	if (empty($notifications)) return '';

	$forum_page = array();
	$forum_page['item_header'] = array();
	$forum_page['item_header']['subject']['title'] = '<strong class="subject-title">'.$lang_bbnotify['Notify title'].'</strong>';
	$forum_page['item_header']['info']['notifypost'] = '<strong class="info-notifypost">'.$lang_bbnotify['Notify post'].'</strong>';
	$forum_page['item_header']['info']['lastpost'] = '<strong class="info-lastpost">'.$lang_forum['last post'].'</strong>';

	$output = '
	<div id="bbnotify-title" class="main-subhead">
		<p class="item-summary forum-views"><span>'.sprintf($lang_forum['Forum subtitle'], implode(' ', $forum_page['item_header']['subject']), implode(', ', $forum_page['item_header']['info'])).'</span></p>
	</div>
	<div id="bbnotify-list" class="main-content main-forum forum-views">
	';

	$forum_page['item_count'] = 0;
	$tracked_topics = get_tracked_topics();

	foreach ($notifications as $cur_topic) {
		++$forum_page['item_count'];

		// Start from scratch
		$forum_page['item_subject'] = $forum_page['item_body'] = $forum_page['item_status'] = $forum_page['item_nav'] = $forum_page['item_title'] = $forum_page['item_title_status'] = array();

		if ($forum_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		$forum_page['item_subject']['starter'] = '<span class="item-starter">'.sprintf($lang_forum['Topic starter'], forum_htmlencode($cur_topic['poster'])).'</span>';

		if ($cur_topic['moved_to'] != null) {
			$forum_page['item_status']['moved'] = 'moved';
			$forum_page['item_title']['link'] = '<span class="item-status"><em class="moved">'.sprintf($lang_forum['Item status'], $lang_forum['Moved']).'</em></span> <a href="'.forum_link($forum_url['topic'], array($cur_topic['moved_to'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';

			// Combine everything to produce the Topic heading
			$forum_page['item_body']['subject']['title'] = '<h3 class="hn"><span class="item-num">'.forum_number_format($forum_page['item_count']).'</span>'.$forum_page['item_title']['link'].'</h3>';

			$forum_page['item_body']['info']['notifypost'] = '<li class="info-notifypost"><span class="label">'.$lang_bbnotify['Notify post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $cur_topic['notify_post_id']).'">'.format_time($cur_topic['notify_posted']).'</a></strong> <cite>'.sprintf($lang_forum['by poster'], forum_htmlencode($cur_topic['notify_poster'])).'</cite></li>';

			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_forum['No lastpost info'].'</span></li>';
		}
		else {
			// Assemble the Topic heading
			if ($cur_topic['closed'] == '1') {
				$forum_page['item_status']['closed'] = 'closed';
				$forum_page['item_title']['status'] = '<span class="item-status">'.sprintf($lang_forum['Item status'], '<em class="closed">'.$lang_forum['Closed'].'</em>').'</span>';
			}
			else {
				$forum_page['item_status']['normal'] = 'normal';
			}

			$forum_page['item_title']['link'] = '<a href="'.forum_link($forum_url['topic'], array($cur_topic['topic_id'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';

			$forum_page['item_body']['subject']['title'] = '<h3 class="hn"><span class="item-num">'.forum_number_format($forum_page['item_count']).'</span> '.implode(' ', $forum_page['item_title']).'</h3>';

			$forum_page['item_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);

			if ($forum_page['item_pages'] > 1)
				$forum_page['item_nav']['pages'] = '<span>'.$lang_forum['Pages'].'&#160;</span>'.paginate($forum_page['item_pages'], -1, $forum_url['topic'], $lang_common['Page separator'], array($cur_topic['topic_id'], sef_friendly($cur_topic['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if ($cur_topic['last_post'] > $forum_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['topic_id']]) || $tracked_topics['topics'][$cur_topic['topic_id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$cur_topic['forum_id']]) || $tracked_topics['forums'][$cur_topic['forum_id']] < $cur_topic['last_post'])) {
				$forum_page['item_nav']['new'] = '<em class="item-newposts"><a href="'.forum_link($forum_url['topic_new_posts'], array($cur_topic['topic_id'], sef_friendly($cur_topic['subject']))).'">'.$lang_forum['New posts'].'</a></em>';
				$forum_page['item_status']['new'] = 'new';
			}

			if (!empty($forum_page['item_nav']))
				$forum_page['item_subject']['nav'] = '<span class="item-nav">'.sprintf($lang_forum['Topic navigation'], implode('&#160;&#160;', $forum_page['item_nav'])).'</span>';

			$forum_page['item_body']['info']['notifypost'] = '<li class="info-notifypost"><span class="label">'.$lang_bbnotify['Notify post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $cur_topic['notify_post_id']).'">'.format_time($cur_topic['notify_posted']).'</a></strong> <cite>'.sprintf($lang_forum['by poster'], forum_htmlencode($cur_topic['notify_poster'])).'</cite></li>';

			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_forum['Last post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $cur_topic['last_post_id']).'">'.format_time($cur_topic['last_post']).'</a></strong> <cite>'.sprintf($lang_forum['by poster'], forum_htmlencode($cur_topic['last_poster'])).'</cite></li>';
		}

		$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';

		$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? ' odd' : ' even').(($forum_page['item_count'] == 1) ? ' main-first-item' : '').((!empty($forum_page['item_status'])) ? ' '.implode(' ', $forum_page['item_status']) : '');

		$output .= '
		<div id="topic'.$cur_topic['topic_id'].'" class="main-item'.$forum_page['item_style'].'">
			<span class="icon '.implode(' ', $forum_page['item_status']).'"><!-- --></span>
			<div class="item-subject">
				'.implode("\n\t\t\t\t", $forum_page['item_body']['subject'])."\n".'
			</div>
			<ul class="item-info">
				'.implode("\n\t\t\t\t", $forum_page['item_body']['info'])."\n".'
			</ul>
		</div>
		';
	}
	$output .= '	</div>';

	return forum_trim($output);
}
