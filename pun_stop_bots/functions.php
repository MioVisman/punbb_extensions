<?php

/**
 * pun_stop_bots functions file
 *
 * @copyright (C) 2008-2011 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package pun_stop_bots
 */

if (!defined('FORUM')) die();

function pun_stop_bots_generate_cache()
{
	global $forum_db;

	// Get the forum config from the DB
	$query = array(
		'SELECT'	=> 'id, question, answers',
		'FROM'		=> 'pun_stop_bots_questions'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($row = $forum_db->fetch_assoc($result))
	{
		$output['questions'][$row['id']] = array('question' => $row['question'], 'answers' => $row['answers']);
	}

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	if (!write_cache_file(FORUM_CACHE_DIR.'cache_pun_stop_bots.php', '<?php'."\n\n".'define(\'PUN_STOP_BOTS_CACHE_LOADED\', 1);'."\n\n".'$pun_stop_bots_questions = '.var_export($output, true).';'."\n\n".'?>'))
	{
		error('Unable to write cache_pun_stop_bots cache file to cache directory.<br/>Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
	}
}


function pun_stop_bots_add_question($question, $answers)
{
	global $forum_db, $pun_stop_bots_questions, $lang_pun_stop_bots;

	if (!empty($pun_stop_bots_questions['questions']) && array_search(utf8_strtolower($question), array_map(function ($data) {return utf8_strtolower($data['question']);}, $pun_stop_bots_questions['questions'])) !== false)
		return $lang_pun_stop_bots['Management err dupe question'];

	$query = array(
		'INSERT'	=>	'question, answers',
		'INTO'		=>	'pun_stop_bots_questions',
		'VALUES'	=>	'\''.$forum_db->escape($question).'\', \''.$forum_db->escape(utf8_strtolower($answers)).'\''
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	pun_stop_bots_generate_cache();

	return true;
}


function pun_stop_bots_update_question($question_id, $question, $answers)
{
	global $forum_db, $pun_stop_bots_questions, $lang_pun_stop_bots;

	$query = array(
		'SELECT'	=>	'question, answers',
		'FROM'		=>	'pun_stop_bots_questions',
		'WHERE'		=>	'id = '.$question_id
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_assoc($result);

	if (empty($row))
		return $lang_pun_stop_bots['Management err no question'];
	else
	{
		$old_question = $row['question'];
		$old_answers  = $row['answers'];
		$answers = utf8_strtolower($answers);
	}

	$update_fields = array();
	if ($old_question != $question)
		$update_fields[] = 'question = \''.$forum_db->escape($question).'\'';
	if ($old_answers != $answers)
		$update_fields[] = 'answers = \''.$forum_db->escape($answers).'\'';

	if (!empty($update_fields))
	{
		$query = array(
			'UPDATE'	=>	'pun_stop_bots_questions',
			'SET'		=>	implode(',', $update_fields),
			'WHERE'		=>	'id = '.$question_id
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		pun_stop_bots_generate_cache();
	}

	return true;
}


function pun_stop_bots_delete_question($question_id)
{
	global $forum_db, $pun_stop_bots_questions, $lang_pun_stop_bots;

	if (!empty($pun_stop_bots_questions['questions']) && !isset($pun_stop_bots_questions['questions'][$question_id]))
		return $lang_pun_stop_bots['Management err no question'];

	$query = array(
		'DELETE'	=>	'pun_stop_bots_questions',
		'WHERE'		=>	'id = '.$question_id
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	pun_stop_bots_generate_cache();

	return true;
}


function pun_stop_bots_compare_answers($answer, $question_id)
{
	global $forum_db, $forum_user, $pun_stop_bots_questions, $lang_pun_stop_bots;

	if (empty($pun_stop_bots_questions['questions'][$question_id]['answers']))
		return false;

	return in_array(utf8_strtolower($answer), explode(',', $pun_stop_bots_questions['questions'][$question_id]['answers']));
}


function pun_stop_bots_generate_question_id($setOk = false)
{
	global $forum_db, $forum_user, $pun_stop_bots_questions;

	if ($setOk)
	{
		$new_question_id = $forum_user['is_guest'] ? 'NULL' : 2147483647;
	}
	else
	{
		$question_ids = array_keys($pun_stop_bots_questions['questions']);
		$new_question_id = $question_ids[array_rand($question_ids)];
	}

	if ($forum_user['is_guest'])
	{
		$pun_stop_bots_query = array(
			'UPDATE'	=>	'online',
			'SET'		=>	'pun_stop_bots_question_id = '.$new_question_id,
			'WHERE'		=>	'ident = \''.$forum_db->escape($forum_user['ident']).'\''
		);
	}
	else
	{
		$pun_stop_bots_query = array(
			'UPDATE'	=>	'users',
			'SET'		=>	'pun_stop_bots_question_id = '.$new_question_id,
			'WHERE'		=>	'id = '.$forum_user['id']
		);
	}
	$forum_db->query_build($pun_stop_bots_query) or error(__FILE__, __LINE__);

	return $new_question_id;
}


function pun_stop_bots_prepare_answers($answers)
{
	return forum_trim(preg_replace('%\s*,\s*%u', ',', $answers), ", \t\n\r\0\x0B\xC2\xA0");
}
