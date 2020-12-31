<?php

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './../../');
require FORUM_ROOT.'include/common.php';

$userlist = array();

if (!$forum_user['is_guest'] && !empty($_GET['fid'])) {
	$clean_search = $forum_db->escape($_GET['search']);
	$query = array(
		'SELECT'	=> 'u.username',
		'FROM'		=> 'users u',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=\''.(int)$_GET['fid'].'\' AND fp.group_id=u.group_id)'
			),
			array(
				'LEFT JOIN'		=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'u.id > 1 AND u.group_id != '.FORUM_UNVERIFIED.' AND u.username LIKE \'%'.$clean_search.'%\' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND g.g_read_board=1',
		'ORDER BY'	=> 'u.username LIKE \''.$clean_search.'%\' DESC, u.username',
		'LIMIT'		=> '5'
	);
	
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($user = $forum_db->fetch_assoc($result)) {
		$userlist[] = $user['username'];
	}
}

if (count($userlist) > 0) {
	echo 'bbnotify_usernames=new Array(\''.implode('\',\'',$userlist).'\');';
}
else {
	echo 'bbnotify_usernames=new Array();';
}
