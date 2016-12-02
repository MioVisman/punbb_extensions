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

$parser = ParserusEx::singleton();

$whiteList = $forum_config['p_sig_bbcode'] == '1' ? $parser->attr('whiteListForSign') : [];
$blackList = $forum_config['p_sig_img_tag'] == '1' ? [] : ['img'];

$parser->setAttr('isSign', true)
    ->setWhiteList($whiteList)
    ->setBlackList($blackList)
    ->parse($text);

if ($forum_config['o_smilies_sig'] == '1'
    && $forum_user['show_smilies'] == '1'
) {
    $parser->detectSmilies();
}

return $parser->getHtml();
