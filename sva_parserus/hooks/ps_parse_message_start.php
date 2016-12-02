<?php if (!defined('FORUM')) die();

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if ($forum_config['o_censoring'] == '1') {
    $text = censor_words($text);
}

$whiteList = $forum_config['p_message_bbcode'] == '1' ? null : [];
$blackList = $forum_config['p_message_img_tag'] == '1' ? [] : ['img'];

$parser = ParserusEx::singleton();
$parser->setAttr('isSign', false)
    ->setWhiteList($whiteList)
    ->setBlackList($blackList)
    ->parse($text);

if ($forum_config['o_smilies'] == '1'
    && $forum_user['show_smilies'] == '1'
    && $hide_smilies == '0'
) {
    $parser->detectSmilies();
}

$text = $parser->getHtml();

$text = preg_replace('%<br>\s*?<br>((\s*<br>)*)%i', '</p>$1<p>', $text);
$text = str_replace('<p><br>', '<p>', $text);
$text = str_replace('<p></p>', '', '<p>'.$text.'</p>');

return $text;
