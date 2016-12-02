<?php if (!defined('FORUM')) die();

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Load LANG
if (file_exists($ext_info['path'].'/lang/'.$forum_user['language'].'/sva_parserus.php')) {
    include $ext_info['path'].'/lang/'.$forum_user['language'].'/sva_parserus.php';
} else {
    include $ext_info['path'].'/lang/English/sva_parserus.php';
}

$smFolder = $base_url . '/img/smilies/';
$smTpl = '<img src="{url}" alt="{alt}">';
$whiteListForSign = ['b', 'i', 'u', 'color', 'colour', 'email', 'img', 'url'];

($hook = get_hook('parserus_start_vars')) ? eval($hook) : null;

$sm = [];
foreach ($smilies as $smiley_text => $smiley_img) {
    $sm[$smiley_text] = $smFolder . $smiley_img;
}

$parser = ParserusEx::singleton();
$parser->setAttr('lang_common', $lang_common)
    ->setAttr('lang_parserus', $lang_parserus)
    ->setAttr('lang_parserus_errors', $lang_parserus_errors)
#    ->setAttr('forum_user', $forum_user)
#    ->setAttr('showImg', $forum_user['show_img'] != '0')
#    ->setAttr('showImgSign', $forum_user['show_img_sig'] != '0')
    ->setAttr('whiteListForSign', $whiteListForSign)
    ->setSmilies($sm)
    ->setSmTpl($smTpl);

unset($sm, $smFolder, $smTpl, $whiteListForSign);

($hook = get_hook('parserus_start_set')) ? eval($hook) : null;
