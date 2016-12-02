<?php if (!defined('FORUM')) die();

/**
 * Parserus for PunBB
 *
 * sva_parserus
 * Copyright (C) 2016 Visman (mio.visman@yandex.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

return [
    [
        'tag' => 'b',
        'parents' => ['inline', 'block', 'url'],
        'handler' => function($body) {
            return '<strong>' . $body . '</strong>';
        },
    ],
    [
        'tag' => 'i',
        'parents' => ['inline', 'block', 'url'],
        'handler' => function($body) {
            return '<em>' . $body . '</em>';
        },
    ],
    [
        'tag' => 'u',
        'parents' => ['inline', 'block', 'url'],
        'handler' => function($body) {
            return '<span class=\"bbu\">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'color',
        'parents' => ['inline', 'block', 'url'],
        'self nesting' => true,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => function($body, $attrs) {
            return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'colour',
        'parents' => ['inline', 'block', 'url'],
        'self nesting' => true,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => function($body, $attrs) {
            return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'h',
        'type' => 'block',
        'handler' => function($body) {
            return '</p><h5>' . $body . '</h5><p>';
        },
    ],
    [
        'tag' => 'quote',
        'type' => 'block',
        'self nesting' => true,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs, $parser) {
            if (isset($attrs['Def'])) {
                $lang = $parser->attr('lang_common');
                $st = '</p><div class="quotebox"><cite>' . $attrs['Def'] .  ' ' . $lang['wrote'] . '</cite><blockquote><p>';
            } else {
                $st = '</p><div class="quotebox"><blockquote><p>';
            }

            return $st . $body . '</p></blockquote></div><p>';
        },
    ],
    [
        'tag' => 'code',
        'type' => 'block',
        'recursive' => true,
        'text only' => true,
        'pre' => true,
        'attrs' => [
#            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body) {
            return '</p><div class="codebox"><pre><code>' . trim($body, "\n") . '</code></pre></div><p>';
        },
    ],
    [
        'tag' => 'email',
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x20]+?@[^\x00-\x20]+$%',
            ],
            'no attr' => [
                'body format' => '%^[^\x00-\x20]+?@[^\x00-\x20]+$%D',
                'text only' => true,
            ],
        ],
        'handler' => function($body, $attrs) {
            if (empty($attrs['Def'])) {
                return '<a href="mailto:' . $body . '">' . $body . '</a>';
            } else {
                return '<a href="mailto:' . $attrs['Def'] . '">' . $body . '</a>';
            }
        },
    ],
    [
        'tag' => '*',
        'type' => 'block',
        'self nesting' => true,
        'parents' => ['list'],
        'auto' => true,
        'handler' => function($body) {
            return '<li><p>' . $body . '</p></li>';
        },
    ],
    [
        'tag' => 'list',
        'type' => 'list',
        'self nesting' => true,
        'tags only' => true,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs) {
             if (! isset($attrs['Def']) || strlen($attrs['Def']) != 1) {
                 $attrs['Def'] = '*';
             }

             switch ($attrs['Def']) {
                 case '*':
                     return '</p><ul>' . $body . '</ul><p>';
                 case 'a':
                     return '</p><ol class="alpha">' . $body . '</ol><p>';
                 default:
                     return '</p><ol class="decimal">' . $body . '</ol><p>';
         }
        },
    ],
    [
        'tag' => 'img',
        'type' => 'img',
        'parents' => ['inline', 'block', 'url'],
        'text only' => true,
        'attrs' => [
            'Def' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
            'no attr' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
        ],
        'handler' => function($url, $attrs, $parser) {
            // установка окружения
#            $forum_user = $parser->attr('forum_user');
#            $lang_common = $parser->attr('lang_common');
            $is_signature = $parser->attr('isSign');
            $alt = isset($attrs['Def']) ? $attrs['Def']
                : (substr($url, 0, 11) === 'data:image/' ? 'base64' : basename($url));


            return handle_img_tag($url, $is_signature, $alt);
/*
            // содержимое function handle_img_tag($url, $is_signature = false, $alt = null)
            $return = ($hook = get_hook('ps_handle_img_tag_start')) ? eval($hook) : null;
            if ($return !== null) {
                return $return;
            }

            if ($alt === null) {
                $alt = $url;
            }

            $img_tag = '<a href="' . $url . '">&lt;' . $lang_common['Image link'] . '&gt;</a>';

            if ($is_signature && $forum_user['show_img_sig'] != '0') {
                $img_tag = '<img class="sigimage" src="' . $url . '" alt="' . $alt . '" />';
            } else if (!$is_signature && $forum_user['show_img'] != '0') {
                $img_tag = '<span class="postimg"><img src="' . $url . '" alt="' . $alt . '" /></span>';
            }

            $return = ($hook = get_hook('ps_handle_img_tag_end')) ? eval($hook) : null;
            if ($return !== null) {
                return $return;
            }

            return $img_tag; */
        },
    ],
    [
        'tag' => 'url',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x1f]+$%',
            ],
            'no attr' => [
                'body format' => '%^[^\x00-\x1f]+$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            // установка окружения
            if (isset($attrs['Def'])) {
                $link = $body;
                $url = $attrs['Def'];
            } else {
                // возможно внутри была картинка, которая отображается как ссылка
                if (preg_match('%<a[^>]+?href="%', $body)) {
                    return $body;
                }

                $link = $body;
                $url = str_replace(['<', '>'], '', $body);

                // возможно внутри картинка
                if (preg_match('%<img[^>]+?src="([^"]+)"%', $body, $match)) {
                    $link = $body;
                    $url = $match[1];
                }
            }

            return handle_url_tag($url, $link, false);
/*
            $bbcode = false;

            // содержимое (приблизительное) function handle_url_tag($url, $link = '', $bbcode = false)
            $return = ($hook = get_hook('ps_handle_url_tag_start')) ? eval($hook) : null;
            if ($return !== null) {
                return $return;
            }

            $full_url = str_replace(array(' ', '\'', '`', '"', '<', '>'), array('%20', '', '', '', '', ''), $url);
            if (strpos($url, 'www.') === 0) {           // If it starts with www, we add http://
                $full_url = 'http://'.$full_url;
            } else if (strpos($url, 'ftp.') === 0) {   // Else if it starts with ftp, we add ftp://
                $full_url = 'ftp://'.$full_url;
            } else if (! preg_match('#^([a-z0-9]{3,6})://#', $url)) {   // Else if it doesn't start with abcdef://, we add http://
                $full_url = 'http://'.$full_url;
            }

            if ($link === '' || $link === $url) {
                $link = htmlspecialchars_decode($url, ENT_QUOTES);
                $link = utf8_strlen($link) > 55 ? utf8_substr($link, 0, 39) . ' … ' . utf8_substr($link, -10) : $link;
                $link = $parser->e($link);
            }

            $return = ($hook = get_hook('ps_handle_url_tag_end')) ? eval($hook) : null;
            if ($return !== null) {
                return $return;
            }

            return '<a href="' . $full_url . '">'. $link . '</a>'; */
        },
    ],
    [
        'tag' => 'spoiler',
        'type' => 'block',
        'self nesting' => true,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs, $parser) {
            if (isset($attrs['Def'])) {
                $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $attrs['Def'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
            } else {
                $lang = $parser->attr('lang_parserus');
                $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $lang['Hidden text'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
            }

            return $st . $body . '</p></div></div><p>';
        },
    ],
/*    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],
    [
        'tag' => '',
        'handler' => function($body, $attrs, $parser) {
        },
    ],    */
];
