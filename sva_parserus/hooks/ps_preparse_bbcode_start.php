<?php if (!defined('FORUM')) die();

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

$parser = ParserusEx::singleton();

if ($is_signature) {

    $whiteList = $forum_config['p_sig_bbcode'] == '1' ? $parser->attr('whiteListForSign') : [];
    $blackList = $forum_config['p_sig_img_tag'] == '1' ? [] : ['img'];

} else {

    $whiteList = $forum_config['p_message_bbcode'] == '1' ? null : [];
    $blackList = $forum_config['p_message_img_tag'] == '1' ? [] : ['img'];

}

$parser->setWhiteList($whiteList)
    ->setBlackList($blackList)
    ->parse($text, ['strict' => true])
    ->stripEmptyTags(" \n\t\r\v", true);

if ($forum_config['o_make_links'] == '1') {
    $parser->detectUrls();
}

$errors = $parser->getErrors($parser->attr('lang_parserus_errors'), $errors);

return forum_trim($parser->getCode());
