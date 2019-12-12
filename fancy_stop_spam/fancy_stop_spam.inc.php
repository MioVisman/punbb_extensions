<?php

if (!defined('FORUM'))
    exit;

class Fancy_stop_spam
{
    // CONFIGS
    const NUMBER_POSTS_FOR_SIGNATURE            = 5;

    // CONFIGS IDENTICAL
    const IDENTICAL_POST_LIFETIME               = 10800;
    const IDENTICAL_POST_MIN_LENGTH             = 24;
    const IDENTICAL_USER_MAX_POSTS_FOR_CHECK    = 5;

    //
    const LIFETIME_SFS_EMAIL_IP_CACHED          = 3600;
    const LIFETIME_SFS_IP_CACHED                = 259200;
    const LIFETIME_SFS_EMAIL_CACHED             = 259200;
    const LIFETIME_SFS_IP_ACTIVITY              = 15552000;     // 180 days
    const LIFETIME_SFS_IP_1_FREQ_ACTIVITY       = 432000;       // 5 days

    //
    const TIMEOUT_REGISTER_HONEYPOT_LOG_CHECK   = 3600;

    //
    const FORM_FILL_MIN_TIME                    = 3;
    const SUBMIT_MARK                           = ' ';

    const NUMBER_LOGS_FOR_SAVE                  = 5000;
    const TIMEOUT_FOR_CLEARING_LOGS             = 86400;

    const LOG_SYSTEM_EVENT                  = 0;

    // LOGS EVENTS REGISTER
    const LOG_REGISTER_SUBMIT               = 1;
    const LOG_REGISTER_TIMEOUT              = 2;
    const LOG_REGISTER_TIMEZONE             = 3;
    const LOG_REGISTER_HONEYPOT             = 4;
    const LOG_REGISTER_HONEYPOT_EMPTY       = 5;
    const LOG_REGISTER_HONEYPOT_REPEATED    = 11;
    const LOG_REGISTER_EMAIL_SFS            = 6;
    const LOG_REGISTER_EMAIL_SFS_CACHED     = 7;
    const LOG_REGISTER_EMAIL_SFS_IP_CACHED  = 8;
    const LOG_REGISTER_IP_SFS               = 9;
    const LOG_REGISTER_IP_SFS_CACHED        = 10;
    const LOG_REGISTER_INTEGRITY            = 19;

    // LOGS EVENTS POST
    const LOG_POST_SUBMIT                   = 20;
    const LOG_POST_TIMEOUT                  = 21;
    const LOG_POST_HONEYPOT                 = 22;
    const LOG_POST_HONEYPOT_EMPTY           = 23;

    // LOGS EVENTS LOGIN
    const LOG_LOGIN_HONEYPOT                = 40;
    const LOG_LOGIN_HONEYPOT_EMPTY          = 41;

    // LOGS EVENTS SIGNATURE
    const LOG_SIGNATURE_HIDDEN              = 60;

    // LOGS IDENTICAL POSTS
    const LOG_IDENTICAL_POST                = 30;

    // LOGS EVENTS ACTIVATE
    const LOG_ACTIVATE_SUBMIT               = 70;
    const LOG_ACTIVATE_HONEYPOT             = 71;
    const LOG_ACTIVATE_HONEYPOT_EMPTY       = 72;

    // SPAM STATUS
    const STATUS_NOT_SPAM                   = 'not_spam';
    const STATUS_SPAM                       = 'spam';
    const STATUS_MAYBE_SPAM                 = 'maybe_spam';
    const STATUS_SFS_NO_CONNECT             = 'unknown';


    private static $instance;
    private $lang;


    // No contruct - only singleton
    private function __construct()
    {
        global $lang_fancy_stop_spam;

        $this->lang = $lang_fancy_stop_spam;
    }


    // Access point
    public static function singleton()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }

        return self::$instance;
    }


    // No clone
    public function __clone()
    {
        trigger_error('Clone forbiden.', E_USER_ERROR);
    }


    // Log spam event to database
    public function log($activity_type, $user_id, $user_ip, $comment = '', $user_agent = null)
    {
        global $forum_db, $forum_config;

        // LOGS enabled?
        if ($forum_config['o_fancy_stop_spam_use_logs'] == '0') {
            return true;
        }

        $comment = utf8_substr($comment, 0, 200);
        $user_agent = utf8_substr(
            is_string($user_agent) ? $user_agent : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
        , 0, 255);

        // CLEAR OLD ENTRIES
        $this->clear_old_logs();

        $query = array(
            'INSERT'    => 'user_id, ip, activity_type, activity_time, comment, user_agent',
            'INTO'      => 'fancy_stop_spam_logs',
            'VALUES'    => intval($user_id).', \''.$forum_db->escape($this->ip2hex($user_ip)).'\', '.intval($activity_type).', '.time().', \''.$forum_db->escape($comment).'\', \''.$forum_db->escape($user_agent).'\'',
        );
        $forum_db->query_build($query) or error(__FILE__, __LINE__);
    }


    //
    public function identical_message_check($poster_id, $message_hash)
    {
        global $forum_db;

        // REMOVE EXPIRED
        $this->identical_message_prune_expired();

        $query = array(
            'SELECT'    => 'COUNT(f.id)',
            'FROM'      => 'fancy_stop_spam_identical_posts AS f',
            'WHERE'     => 'f.poster_id='.intval($poster_id).' AND post_hash=\''.$forum_db->escape($message_hash).'\''
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
        $num_identical_messages = $forum_db->result($result);

        // Check repeated
        //$this->check_identical_posts_repeated(intval($poster_id));

        return (bool)/**/($num_identical_messages > 0);
    }


    //
    public function identical_message_add($poster_id, $post_id, $message_hash, $posted)
    {
        global $forum_db;

        // REMOVE EXPIRED
        $this->identical_message_prune_expired();

        // Add the post hash
        $query = array(
            'INSERT'    => 'poster_id, post_id, post_hash, posted',
            'INTO'      => 'fancy_stop_spam_identical_posts',
            'VALUES'    => intval($poster_id).', '.intval($post_id).', \''.$forum_db->escape($message_hash).'\', '.intval($posted),
        );
        $forum_db->query_build($query) or error(__FILE__, __LINE__);
    }


    //
    public function mark_user_as_spammer($user_id)
    {
        global $forum_db;

        $user_id = intval($user_id);

        // Update the user table
        if ($user_id > 1) {
            $query = array(
                'UPDATE'    => 'users',
                'SET'       => 'fancy_stop_spam_bot = fancy_stop_spam_bot + 1',
                'WHERE'     => 'id = '.$user_id,
            );
            $forum_db->query_build($query) or error(__FILE__, __LINE__);
        }
    }


    //
    public function check_register_honeypot_repeated($ip)
    {
        global $forum_db;

        if (empty($ip)) {
            return false;
        }

        $query = array(
            'SELECT'    => 'COUNT(ip)',
            'FROM'      => 'fancy_stop_spam_logs',
            'WHERE'     => 'ip=\''.$forum_db->escape($this->ip2hex($ip)).'\' AND
                            activity_type IN ('.self::LOG_REGISTER_HONEYPOT.','.self::LOG_REGISTER_HONEYPOT_EMPTY.') AND
                            activity_time > '.(time() - self::TIMEOUT_REGISTER_HONEYPOT_LOG_CHECK),
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
        return (bool)/**/($forum_db->result($result) > 4);
    }


    //
    public function check_identical_posts_repeated($user_id)
    {
        global $forum_db;

        $user_id = intval($user_id);

        if ($user_id > 2) {
            $query = array(
                'SELECT'    => 'COUNT(ip)',
                'FROM'      => 'fancy_stop_spam_logs',
                'WHERE'     => 'user_id='.$user_id.' AND
                                activity_type='.self::LOG_IDENTICAL_POST.' AND
                                activity_time > '.(time() - self::TIMEOUT_REGISTER_HONEYPOT_LOG_CHECK),
            );
            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
            if ($forum_db->result($result) > 2) {
                $this->mark_user_as_spammer($user_id);
                $this->log(self::LOG_SYSTEM_EVENT, $user_id, get_remote_address(), $this->lang['Identical check repeated event']);
            }
        }
    }


    //
    public function check_by_sfs(&$errors, $data = array())
    {
        global $forum_db, $forum_user, $forum_config;

        $need_check_email = ($forum_config['o_fancy_stop_spam_register_form_sfs_email'] == '1' && !empty($data['email']));
        $need_check_ip = ($forum_config['o_fancy_stop_spam_register_form_sfs_ip'] == '1' && !empty($data['ip']));
        $spam_data = null;

        // IP CHECKS
        if ($need_check_ip) {
             // Clear ip cache
            $query = array(
                'DELETE'    => 'fancy_stop_spam_sfs_ip_cache',
                'WHERE'     => 'added < '.(time() - self::LIFETIME_SFS_IP_CACHED),
            );
            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

            $query = array(
                'SELECT'    => 'COUNT(ip)',
                'FROM'      => 'fancy_stop_spam_sfs_ip_cache',
                'WHERE'     => 'ip=\''.$forum_db->escape($this->ip2hex($data['ip'])).'\'',
            );
            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
            if ($forum_db->result($result) > 0) {
                $this->log(self::LOG_REGISTER_IP_SFS_CACHED, $forum_user['id'], $data['ip']);
                message($this->lang['Register bot sfs email message']);
            }

            if (is_null($spam_data)) {
                $spam_data = $this->make_request_to_sfs($data);
            }

            $is_spam_ip = false;
            if ($spam_data !== false && isset($spam_data['ip']) && is_array($spam_data['ip'])) {
                if (!empty($spam_data['ip']['appears']) && !empty($spam_data['ip']['frequency'])) {
                    // Check spam IP with frequency 1
                    if (intval($spam_data['ip']['frequency']) === 1) {
                        if (!empty($spam_data['ip']['lastseen']) && $spam_data['ip']['lastseen'] > (time() - self::LIFETIME_SFS_IP_1_FREQ_ACTIVITY)) {
                            $is_spam_ip = true;
                        }
                    }

                    // Check spam IP with frequency > 1
                    if (intval($spam_data['ip']['frequency']) > 1) {
                        if (!empty($spam_data['ip']['lastseen']) && $spam_data['ip']['lastseen'] > (time() - self::LIFETIME_SFS_IP_ACTIVITY)) {
                            $is_spam_ip = true;
                        }
                    }
                }

                if (true === $is_spam_ip) {
                    $query = array(
                        'INSERT'    => 'ip, added',
                        'INTO'      => 'fancy_stop_spam_sfs_ip_cache',
                        'VALUES'    => '\''.$forum_db->escape($this->ip2hex($data['ip'])).'\', '.time(),
                    );
                    $forum_db->query_build($query) or error(__FILE__, __LINE__);
                    $this->log(self::LOG_REGISTER_IP_SFS, $forum_user['id'], $data['ip']);
                    message($this->lang['Register bot sfs ip message']);
                }
            }
        }

        // EMAIL CHECKS
        if ($need_check_email) {
            // Clear email cache
            $query = array(
                'DELETE'    => 'fancy_stop_spam_sfs_email_cache',
                'WHERE'     => 'added < '.(time() - self::LIFETIME_SFS_EMAIL_CACHED),
            );
            $forum_db->query_build($query) or error(__FILE__, __LINE__);

            // Check email in email cache
            $query = array(
                'SELECT'    => 'COUNT(email)',
                'FROM'      => 'fancy_stop_spam_sfs_email_cache',
                'WHERE'     => 'email=\''.$forum_db->escape($data['email']).'\'',
            );
            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
            if ($forum_db->result($result) > 0) {
                $this->log(self::LOG_REGISTER_EMAIL_SFS_CACHED, $forum_user['id'], get_remote_address(), $data['email']);
                message($this->lang['Register bot sfs email message']);
            }

            // Check ip in email cache
            if (!empty($data['ip'])) {
                $query = array(
                    'SELECT'    => 'COUNT(ip)',
                    'FROM'      => 'fancy_stop_spam_sfs_email_cache',
                    'WHERE'     => 'ip=\''.$forum_db->escape($this->ip2hex($data['ip'])).'\' AND added > '.(time() - self::LIFETIME_SFS_EMAIL_IP_CACHED),
                );
                $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
                if ($forum_db->result($result) > 0) {
                    $this->log(self::LOG_REGISTER_EMAIL_SFS_IP_CACHED, $forum_user['id'], get_remote_address());
                    $errors[] = $this->lang['Register bot sfs email ip message'];
                    return;
                }
            }

            // Check email in SFS
            if (is_null($spam_data)) {
                $spam_data = $this->make_request_to_sfs($data);
            }
            if ($spam_data !== false && isset($spam_data['email']) && is_array($spam_data['email'])) {
                if (!empty($spam_data['email']['appears'])) {
                    $this->log(self::LOG_REGISTER_EMAIL_SFS, $forum_user['id'], get_remote_address(), $data['email']);

                    // Add to cache
                    $query = array(
                        'INSERT'    => 'email, added, ip',
                        'INTO'      => 'fancy_stop_spam_sfs_email_cache',
                        'VALUES'    => '\''.$forum_db->escape($data['email']).'\', '.time().', \''.$forum_db->escape($this->ip2hex(get_remote_address())).'\'',
                    );
                    $forum_db->query_build($query) or error(__FILE__, __LINE__);
                    message($this->lang['Register bot sfs email message']);
                }
            }
        }
    }


    // Check message by count number links
    public function max_links_check($post_message)
    {
        global $forum_user, $forum_config;

        $max_links = intval($forum_user['is_guest'] ? $forum_config['o_fancy_stop_spam_max_guest_links'] : $forum_config['o_fancy_stop_spam_max_links']);

        if ($max_links < 0
            || $forum_user['is_admmod']
            || $forum_user['num_posts'] > self::IDENTICAL_USER_MAX_POSTS_FOR_CHECK
            || $this->get_number_links_in_message($post_message) <= $max_links
        ) {
            return true;
        } else {
            return sprintf($this->lang['Error many links'], $max_links);
        }
    }


    // Print logs table
    public function print_logs($user_id = null, $ip = null)
    {
        global $forum_db, $forum_config, $forum_page, $forum_url;

        $out = '';

        $query = array(
            'SELECT'    => 'fl.activity_type, fl.ip, fl.user_agent, fl.activity_time, fl.user_id, fl.comment, u.username',
            'FROM'      => 'fancy_stop_spam_logs AS fl',
            'JOINS'     => array(
                array(
                    'LEFT JOIN'     => 'users AS u',
                    'ON'            => 'u.id=fl.user_id', // AND fl.activity_time>u.registered // Need a performance test
                ),                                        // SQLite The new user receives data from the remote user if he was the last in the table
            ),
            'ORDER BY'  => 'fl.id DESC',
            'LIMIT'     => '100',
        );

        if (!is_null($user_id) && $user_id > 1) {
            $query['WHERE'] = 'fl.user_id = '.intval($user_id);
        }

        if (!is_null($ip)) {
            $query['WHERE'] = 'fl.ip = \''.$forum_db->escape($this->ip2hex($ip)).'\'';
            $query['LIMIT'] = '20';
        }

        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

        $acts = array();
        while ($cur_act = $forum_db->fetch_assoc($result)) {
            $cur_act['ip'] = $this->hex2ip($cur_act['ip']);
            $acts[] = $cur_act;
        }

        if (empty($acts)) {
            $out = '<div class="ct-box info-box"><p>'.$this->lang['No activity'].'</p></div>'."\n";
        } else {
            $logs_data = '';
            foreach ($acts as $act) {
                $username_row = '-';
                if (intval($act['user_id']) > 0 && !empty($act['username'])) {
                    if ($act['user_id'] == '1') {
                        $username_row = forum_htmlencode($act['username']);
                    } else {
                        $username_row = '<a href="'.forum_link($forum_url['user'], forum_htmlencode(intval($act['user_id']))).'">'.
                            forum_htmlencode($act['username']).
                            '</a>';
                    }
                }
                $ip_row = '<a href="'.forum_link($forum_url['get_host'], forum_htmlencode($act['ip'])).'"'.
                    (isset($act['user_agent'][0]) ? ' title="'.forum_htmlencode($act['user_agent']).'"' : '').
                    '>'.forum_htmlencode($act['ip']).'</a>';

                $logs_data .= '
                    <tr>
                        <td>'.forum_htmlencode($this->get_log_event_name($act['activity_type'])).'</td>
                        <td>'.$ip_row.'</td>
                        <td>'.$username_row.'</td>
                        <td>'.format_time($act['activity_time']).'</td>
                        <td>'.forum_htmlencode($act['comment']).'</td>
                    </tr>';
            }

            $out = '<div class="ct-group">
                <table cellpadding="0" summary="" style="table-layout: auto;">
                <thead>
                <tr>
                    <th class="tc0" scope="col">'.$this->lang['Type'].'</th>
                    <th class="tc1" scope="col">'.$this->lang['IP'].'</th>
                    <th class="tc1" scope="col">'.$this->lang['User'].'</th>
                    <th class="tc3" scope="col">'.$this->lang['Time'].'</th>
                    <th class="tc2" scope="col">'.$this->lang['Comment'].'</th>
                </tr>
                </thead>
                <tbody>'.$logs_data.'</tbody>
                </table>
                </div>'."\n";
        }

        return $out;
    }


    // Print admin new users page
    public function print_new_users()
    {
        global $forum_db, $forum_config, $forum_page, $forum_url;

        $out = '';

        // Fetch user count
        $query = array(
            'SELECT'    => 'COUNT(u.id)',
            'FROM'      => 'users AS u',
            'WHERE'     => 'u.id > 1 AND u.group_id != '.FORUM_UNVERIFIED,
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
        $forum_page['num_users'] = $forum_db->result($result);
        $forum_page['start_from'] = 0;

        // Grab the users
        $query = array(
            'SELECT'    => 'u.id, u.username, u.email, u.title, u.num_posts, u.registered, u.registration_ip, g.g_id, g.g_user_title, COUNT(fssl.user_id) AS num_logs',
            'FROM'      => 'users AS u',
            'JOINS'     => array(
                array(
                    'LEFT JOIN'     => 'groups AS g',
                    'ON'            => 'g.g_id=u.group_id',
                ),
                array(
                    'LEFT JOIN'     => 'fancy_stop_spam_logs AS fssl',
                    'ON'            => 'fssl.user_id=u.id',
                )
            ),
            'WHERE'     => 'u.id > 1 AND u.group_id != '.FORUM_UNVERIFIED,
            'GROUP BY'  => 'u.id',
            'ORDER BY'  => 'u.id DESC',
            'LIMIT'     => $forum_page['start_from'].', 15',
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

        $founded_user_datas = array();
        while ($user_data = $forum_db->fetch_assoc($result)) {
            $founded_user_datas[] = $user_data;
        }

        $forum_page['item_count'] = 0;

        if (!empty($founded_user_datas)) {
            // Make request to StopForumSpam
            $sfs_request_email_data = array("email" => array());
            $sfs_request_ip_data = array("ip" => array());
            foreach ($founded_user_datas as $founded_user) {
                $sfs_request_email_data["email"][] = $founded_user["email"];
                $sfs_request_ip_data["ip"][] = $founded_user["registration_ip"];
            }

            $fancy_stop_spam_email_data = $this->make_request_to_sfs($sfs_request_email_data);
            $fancy_stop_spam_ip_data = $this->make_request_to_sfs($sfs_request_ip_data);

            $users_data = '';
            foreach ($founded_user_datas as $founded_user) {
                $username_row = '-';
                if (intval($founded_user['id']) > 0 && !empty($founded_user['username'])) {
                    if ($founded_user['id'] == '1') {
                        $username_row = forum_htmlencode($founded_user['username']);
                    } else {
                        $username_row = '<a href="'.forum_link($forum_url['user'], intval($founded_user['id'])).'">'.
                            forum_htmlencode($founded_user['username']).
                            '</a>';
                    }
                }

                if ($fancy_stop_spam_email_data === false) {
                    $email_status = 'no connection to SFS';
                    $spam_status_email = self::STATUS_SFS_NO_CONNECT;
                } else {
                    $email_status = $this->get_sfs_status_for_email($founded_user["email"], $fancy_stop_spam_email_data, $spam_status_email);
                }
                if ($fancy_stop_spam_ip_data === false) {
                    $ip_status = 'no connection to SFS';
                    $spam_status_ip = self::STATUS_SFS_NO_CONNECT;
                } else {
                    $ip_status = $this->get_sfs_status_for_ip($founded_user["registration_ip"], $fancy_stop_spam_ip_data, $spam_status_ip);
                }

                // NUMBER of POSTS
                $num_posts_row = '0';
                if (intval($founded_user['num_posts']) > 0) {
                    $num_posts_row = '<a href="'.forum_link($forum_url['search_user_posts'], $founded_user['id']).'">'.$founded_user['num_posts'].'</a>';
                }

                // NUMBER of LOGS
                $num_logs_row = '0';
                if (intval($founded_user['num_logs']) > 0) {
                    $num_logs_row = '<a href="'.forum_link($forum_url['fancy_stop_spam_profile_section'], $founded_user['id']).'">'.$founded_user['num_logs'].'</a>';
                }

                $users_data .= '
                    <tr class="fancy_spam_status_email_'.forum_htmlencode($spam_status_email).' fancy_spam_status_ip_'.forum_htmlencode($spam_status_ip).'">
                        <td>'.$username_row.'</td>
                        <td class="number_posts">'.$num_posts_row.'</td>
                        <td class="number_logs">'.$num_logs_row.'</td>
                        <td>'.$email_status.'</td>
                        <td>'.$ip_status.'</td>
                    </tr>';
            }

            $table = '<div class="ct-group">
                <table cellpadding="0" class="fancy_stop_spam_table">
                <thead>
                <tr>
                    <th class="tc0" scope="col">'.$this->lang['User'].'</th>
                    <th class="number_posts" scope="col">'.$this->lang['Number posts'].'</th>
                    <th class="number_logs" scope="col">'.$this->lang['Admin submenu logs'].'</th>
                    <th class="tc1" scope="col">'.$this->lang['Email check'].'</th>
                    <th class="tc2" scope="col">'.$this->lang['IP check'].'</th>
                </tr>
                </thead>
                <tbody>'.$users_data.'</tbody>
                </table>
                </div>';

            $out = $table;
        }

        echo $out;
    }


    // Print admin suspicious users page
    public function print_suspicious_users()
    {
        global $forum_db, $forum_config, $forum_page, $forum_url;

        $query = array(
            'SELECT'    => 'u.id, u.username, u.registered, u.num_posts, u.fancy_stop_spam_bot',
            'FROM'      => 'users AS u',
            'WHERE'     => 'u.fancy_stop_spam_bot > 0',
            'ORDER BY'  => 'u.id DESC',
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

        $suspicious_users = array();
        while ($current_user = $forum_db->fetch_assoc($result)) {
            $suspicious_users[] = $current_user;
        }

        if (empty($suspicious_users)) {
            $out = '<div class="ct-box info-box"><p>'.$this->lang['No suspicious_users'].'</p></div>'."\n";
        } else {
            $users_data = '';
            foreach ($suspicious_users as $user) {
                $users_data .= '
                    <tr>
                        <td>
                            <a href="'.forum_link($forum_url['user'], intval($user['id'])).'">'.
                                forum_htmlencode($user['username']).'
                            </a>
                        </td>
                        <td>'.forum_htmlencode($user['fancy_stop_spam_bot']).'</td>
                        <td></td>
                    </tr>';
            }

            $out = '<div class="ct-group">
                <table cellpadding="0" summary="" style="table-layout: auto;">
                <thead>
                <tr>
                    <th class="tc1" scope="col">'.$this->lang['User'].'</th>
                    <th class="tc2" scope="col">'.$this->lang['Comment'].'</th>
                    <th class="tc3" scope="col">'.$this->lang['Time'].'</th>
                </tr>
                </thead>
                <tbody>'.$users_data.'</tbody>
                </table>
                </div>'."\n";
        }


        echo $out;
    }


    // Print user info from SFS
    public function print_user_status($user)
    {
        global $lang_profile, $forum_url, $forum_page;

        $fancy_stop_spam_data = $this->make_request_to_sfs(array(
            'email' => $user['email'],
            'ip'    => $user['registration_ip'],
        ));

        if ($fancy_stop_spam_data === false) {
            echo '<div class="ct-box info-box"><p>Can not get info from Stop Forum Spam server. Try again later.</p></div>'."\n";
            return;
        }

        // Email block
        $fancy_stop_spam_email_info = array();
        $fancy_stop_spam_email_info[] = '<li><a href="mailto:'.forum_htmlencode($user['email']).'">'.forum_htmlencode($user['email']).'</a></li>';
        if (isset($fancy_stop_spam_data['email']) && is_array($fancy_stop_spam_data['email'])) {
            if (!empty($fancy_stop_spam_data['email']['appears'])) {
                $fancy_stop_spam_email_info[] = '<li>'.$this->lang['Status'].': '.$this->lang['Status found'].'</li>';
                if (!empty($fancy_stop_spam_data['email']['lastseen'])) {
                    $fancy_stop_spam_email_info[] = '<li>'.$this->lang['Last seen'].': '.forum_htmlencode(format_time($fancy_stop_spam_data['email']['lastseen'])).'</li>';
                }
                if (!empty($fancy_stop_spam_data['email']['frequency'])) {
                    $fancy_stop_spam_email_info[] = '<li>'.$this->lang['Frequency'].': '.intval($fancy_stop_spam_data['email']['frequency']).'</li>';
                }
            } else {
                $fancy_stop_spam_email_info[] = '<li>'.$this->lang['Status'].': '.$this->lang['Status not found'].'</li>';
            }
        } else {
            $fancy_stop_spam_email_info[] = '<li>'.$this->lang['Status error'].'</li>';
        }


        // IP block
        $fancy_stop_spam_ip_info = array();
        $fancy_stop_spam_ip_info[] = '<li><a href="'.forum_link($forum_url['get_host'], forum_htmlencode($user['registration_ip'])).'">'.forum_htmlencode($user['registration_ip']).'</a><li>';
        if (isset($fancy_stop_spam_data['ip']) && is_array($fancy_stop_spam_data['ip'])) {
            if (!empty($fancy_stop_spam_data['ip']['appears'])) {
                $fancy_stop_spam_ip_info[] = '<li>'.$this->lang['Status'].': '.$this->lang['Status found'].'</li>';
                if (!empty($fancy_stop_spam_data['ip']['lastseen'])) {
                    $fancy_stop_spam_ip_info[] = '<li>'.$this->lang['Last seen'].': '.forum_htmlencode(format_time($fancy_stop_spam_data['ip']['lastseen'])).'</li>';
                }
                if (!empty($fancy_stop_spam_data['ip']['frequency'])) {
                    $fancy_stop_spam_ip_info[] = '<li>'.$this->lang['Frequency'].': '.intval($fancy_stop_spam_data['ip']['frequency']).'</li>';
                }
            } else {
                $fancy_stop_spam_ip_info[] = '<li>'.$this->lang['Status'].': '.$this->lang['Status not found'].'</li>';
            }
        } else {
            $fancy_stop_spam_ip_info[] = '<li>'.$this->lang['Status error'].'</li>';
        }

?>
            <div class="ct-set data-set set<?php echo ++$forum_page['item_count'] ?>">
                <div class="ct-box data-box">
                    <h4 class="ct-legend hn"><span><?php echo $lang_profile['E-mail'] ?></span></h4>
                    <ul class="data-box"><?php echo implode('', $fancy_stop_spam_email_info); ?></ul>
                </div>
            </div>
            <div class="ct-set data-set">
                <div class="ct-box data-box set<?php echo ++$forum_page['item_count'] ?>">
                    <h4 class="ct-legend hn"><span><?php echo $lang_profile['IP'] ?></span></h4>
                    <ul class="data-box"><?php echo implode('', $fancy_stop_spam_ip_info); ?></ul>
                </div>
            </div>
<?php

    }


    //
    private function get_log_event_name($event)
    {
        $event = intval($event);
        if (!empty($this->lang['log event name ' . $event])) {
            return $this->lang['log event name ' . $event];
        }

        return $this->lang['log event name unknown'];
    }


    // Send spam report to StoForumSpam
    public function send_spam_data_to_sfs($username, $email, $ip)
    {
        global $forum_config, $lang_common;

        if (empty($forum_config['o_fancy_stop_spam_sfs_api_key'])) {
            return false;
        }

        // Construct report data
        $data = array(
            'username'  => $username,
            'ip_addr'   => $ip,
            'api_key'   => $forum_config['o_fancy_stop_spam_sfs_api_key'],
        );

        // Report only verified emails
        if ($forum_config['o_regs_verify'] == '1') {
            $data['email'] = $email;
        }

        $report_url = 'http://www.stopforumspam.com/add.php?'.http_build_query($data);
        get_remote_file($report_url, 15, false, 2);
    }


    // Send spam check request to StoForumSpam
    private function make_request_to_sfs($data = array())
    {
        $result = false;

        if (!empty($data)) {
            $check_url = 'http://api.stopforumspam.org/api?'.http_build_query($data).'&unix';
            $check_url .= function_exists('json_decode') ? '&json' : '&serial';

            $check_result = get_remote_file($check_url, 15, false, 2);

            if (isset($check_result['content']) !== false && !empty($check_result['content'])) {
                if (function_exists('json_decode')) {
                    $result_data = json_decode($check_result['content'], true);
                } else {
                    $result_data = unserialize($check_result['content']);
                }
                if (!empty($result_data)
                    && is_array($result_data)
                    && isset($result_data['success'])
                    && intval($result_data['success']) === 1
                ) {
                    $result = $result_data;
                }
            }
        }

        return $result;
    }


    // Clear old logs
    private function clear_old_logs()
    {
        global $forum_db;

        if (true !== $this->need_clear_logs()) {
            return;
        }

        if ($this->get_num_logs() > (self::NUMBER_LOGS_FOR_SAVE + 100)) {
            $max_old_id = $this->get_last_old_id_logs();

            // DEL OLDEST
            if ($max_old_id > 0) {
                $query = array(
                    'DELETE'    => 'fancy_stop_spam_logs',
                    'WHERE'     => 'id < '.$max_old_id,
                );
                $forum_db->query_build($query) or error(__FILE__, __LINE__);
            }
        }
    }


    // Return number entries in logs table
    private function get_num_logs()
    {
        global $forum_db;

        $query = array(
            'SELECT'    => 'COUNT(id) AS num',
            'FROM'      => 'fancy_stop_spam_logs',
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

        return intval($forum_db->result($result));
    }


    // Return oldest log entries id
    private function get_last_old_id_logs()
    {
        global $forum_db;

        $query = array(
            'SELECT'    => 'id',
            'FROM'      => 'fancy_stop_spam_logs',
            'ORDER BY'  => 'id DESC',
            'LIMIT'     => self::NUMBER_LOGS_FOR_SAVE.', 1',
        );
        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

        return intval($forum_db->result($result));
    }


    //
    private function debug_log($x, $m = null)
    {
        if (!defined('FANCY_STOP_SPAM_DEBUG_LOG')) {
            return;
        }

        if (is_writable(FANCY_STOP_SPAM_DEBUG_LOG)) {
            if (is_array($x)) {
                ob_start();
                print_r($x);
                $x = $m.($m != null ? "\n" : '').ob_get_clean();
            } else {
                $x .= "\n";
            }

            error_log(strftime('%c').' '.$x . "\n", 3, FANCY_STOP_SPAM_DEBUG_LOG);
        }
    }


    //
    private function identical_message_prune_expired()
    {
        global $forum_db;

        // REMOVE EXPIRED
        $query = array(
            'DELETE'    => 'fancy_stop_spam_identical_posts',
            'WHERE'     => 'posted < '.(time() - self::IDENTICAL_POST_LIFETIME),
        );
        $forum_db->query_build($query) or error(__FILE__, __LINE__);
    }


    // return number links in post
    private function get_number_links_in_message($post_message)
    {
        global $base_url;

        $host = parse_url($base_url, PHP_URL_HOST);
        if (0 === strpos($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Clearing post from internal links
        if (false !== strpos($post_message, $host)) {
            $post_message = preg_replace('%(?:\[url[=\]])?(?:https?://)?(?:www\.)?'. preg_quote($host, '%') . '(?![.])\S+%i', '', $post_message);
        }

        $patterns = [
            '%\[url[=\]].*?\[/url\]%i',
            '%https?://\S+%i',
            '%www\.[\p{L}\p{N}]\S+%iu',
        ];
        if (null !== preg_replace($patterns, '', $post_message, -1, $count)) {
            return $count;
        } else {
            return max(substr_count($post_message, 'http'), substr_count($post_message, 'www'));
        }
    }


    //
    private function get_sfs_status_for_email($email, $sfs_result, &$spam_status)
    {
        $status = '';
        $spam_status = self::STATUS_NOT_SPAM;

        $sfs_emails_data = $sfs_result['email'];
        foreach ($sfs_emails_data as $sfs_email_data) {
            if ($email === $sfs_email_data['value']) {
                if (!empty($sfs_email_data['appears'])) {
                    $spam_status = self::STATUS_SPAM;
                    if (!empty($sfs_email_data['lastseen'])) {
                        $status = $this->lang['Last seen'].': '.forum_htmlencode(format_time($sfs_email_data['lastseen']));

                        if (!empty($sfs_email_data['frequency'])) {
                            $status .= '<span title="'.$this->lang['Frequency'].'">&nbsp;('.intval($sfs_email_data['frequency']).')</span>';
                        }
                    }
                }
                break;
            }
        }

        return $status;
    }


    //
    private function get_sfs_status_for_ip($ip, $sfs_result, &$spam_status)
    {
        $status = '';
        $spam_status = self::STATUS_NOT_SPAM;

        $sfs_ips_data = $sfs_result['ip'];
        foreach ($sfs_ips_data as $sfs_data) {
            if ($ip === $sfs_data['value']) {
                if (!empty($sfs_data['appears'])) {
                    $spam_status = self::STATUS_SPAM;
                    if (!empty($sfs_data['lastseen'])) {
                        $status = $this->lang['Last seen'].': '.forum_htmlencode(format_time($sfs_data['lastseen']));

                        if (!empty($sfs_data['frequency'])) {
                            $status .= '<span title="'.$this->lang['Frequency'].'">&nbsp;('.intval($sfs_data['frequency']).')</span>';
                        }
                    }
                }
                break;
            }
        }

        return $status;
    }


    // Convert IP-address to hex string
    private function ip2hex($ip)
    {
        $bin = inet_pton($ip);
        if (false === $bin) {
            return '-00000000';
        }
        // The hyphen is needed for the joint sorting ipv4 and ipv6
        return (isset($bin[4]) ? '' : '-') . bin2hex($bin);
    }


    // Convert hex string to IP-address
    private function hex2ip($hex)
    {
        return inet_ntop(hex2bin(ltrim($hex, '-')));
    }


    function need_clear_logs()
    {
        $cache_name = FORUM_CACHE_DIR.'cache_fss_logs_time.php';

        if (file_exists($cache_name)) {
            $time = include $cache_name;
            if ($time > 0 && time() - intval($time) < self::TIMEOUT_FOR_CLEARING_LOGS) {
                return false;
            }
        }

        if (!defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
            require FORUM_ROOT.'include/cache.php';
        }

        if (!write_cache_file($cache_name, '<?php'."\n\n".'return '.time().';'."\n")) {
            error('Unable to write fss_logs_time file to cache directory.<br />Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
        }

        return true;
    }
}
