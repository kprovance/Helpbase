<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Admin Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpBaseAdmin')) {

    class HelpBaseAdmin {
        private $helpbase = null;
        
        public function __construct($parent){
            $this->helpbase = $parent;
        }
        
        public function getHHMMSS($in) {
            $in = $this->getTime($in);
            return explode(':', $in);
        }

        public function getTime($in) {
            $in = trim($in);

            /* If everything is OK this simple check should return true */
            if (preg_match('/^([0-9]{2,3}):([0-5][0-9]):([0-5][0-9])$/', $in)) {
                return $in;
            }

            /* No joy, let's try to figure out the correct values to use... */
            $h = 0;
            $m = 0;
            $s = 0;

            /* How many parts do we have? */
            $parts = substr_count($in, ':');

            switch ($parts) {
                /* Only two parts, let's assume minutes and seconds */
                case 1:
                    list($m, $s) = explode(':', $in);
                    break;

                /* Three parts, so explode to hours, minutes and seconds */
                case 2:
                    list($h, $m, $s) = explode(':', $in);
                    break;

                /* Something other was entered, let's assume just minutes */
                default:
                    $m = $in;
            }

            /* Make sure all inputs are integers */
            $h = intval($h);
            $m = intval($m);
            $s = intval($s);

            /* Convert seconds to minutes if 60 or more seconds */
            if ($s > 59) {
                $m = floor($s / 60) + $m;
                $s = intval($s % 60);
            }

            /* Convert minutes to hours if 60 or more minutes */
            if ($m > 59) {
                $h = floor($m / 60) + $h;
                $m = intval($m % 60);
            }

            /* MySQL accepts max time value of 838:59:59 */
            if ($h > 838) {
                return '838:59:59';
            }

            /* That's it, let's send out formatted time string */
            return str_pad($h, 2, "0", STR_PAD_LEFT) . ':' . str_pad($m, 2, "0", STR_PAD_LEFT) . ':' . str_pad($s, 2, "0", STR_PAD_LEFT);
        }

        public function mergeTickets($merge_these, $merge_into) {
            global $hesk_settings, $hesk_db_link;

            /* Target ticket must not be in the "merge these" list */
            if (in_array($merge_into, $merge_these)) {
                $merge_these = array_diff($merge_these, array($merge_into));
            }

            /* At least 1 ticket needs to be merged with target ticket */
            if (count($merge_these) < 1) {
                $_SESSION['error'] = _('select at least two tickets.');
                return false;
            }

            /* Make sure target ticket exists */
            $res = $this->helpbase->database->query("SELECT `id`,`trackid`,`category` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `id`='" . intval($merge_into) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($res) != 1) {
                $_SESSION['error'] = _('target ticket not found.');
                return false;
            }
            $ticket = $this->helpbase->database->fetchAssoc($res);

            /* Make sure user has access to ticket category */
            if (!$this->okCategory($ticket['category'], 0)) {
                $_SESSION['error'] = _('ticket in a category you don\'t have access to.');
                return false;
            }

            /* Set some variables for later */
            $merge['attachments'] = '';
            $merge['replies'] = array();
            $merge['notes'] = array();
            $sec_worked = 0;
            $history = '';
            $merged = '';

            /* Get messages, replies, notes and attachments of tickets that will be merged */
            foreach ($merge_these as $this_id) {
                /* Validate ID */
                if (is_array($this_id)) {
                    continue;
                }
                $this_id = intval($this_id) or $this->helpbase->common->_error(_('This is not a valid ID'));

                /* Get required ticket information */
                $res = $this->helpbase->database->query("SELECT `id`,`trackid`,`category`,`name`,`message`,`dt`,`time_worked`,`attachments` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                if ($this->helpbase->database->numRows($res) != 1) {
                    continue;
                }
                $row = $this->helpbase->database->fetchAssoc($res);

                /* Has this user access to the ticket category? */
                if (!$this->okCategory($row['category'], 0)) {
                    continue;
                }

                /* Insert ticket message as a new reply to target ticket */
                $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "replies` (`replyto`,`name`,`message`,`dt`,`attachments`) VALUES ('" . intval($ticket['id']) . "','" . $this->helpbase->database->escape($row['name']) . "','" . $this->helpbase->database->escape($row['message']) . "','" . $this->helpbase->database->escape($row['dt']) . "','" . $this->helpbase->database->escape($row['attachments']) . "')");

                /* Update attachments  */
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "attachments` SET `ticket_id`='" . $this->helpbase->database->escape($ticket['trackid']) . "' WHERE `ticket_id`='" . $this->helpbase->database->escape($row['trackid']) . "'");

                /* Get old ticket replies and insert them as new replies */
                $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `replyto`='" . intval($row['id']) . "'");
                while ($reply = $this->helpbase->database->fetchAssoc($res)) {
                    $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "replies` (`replyto`,`name`,`message`,`dt`,`attachments`,`staffid`,`rating`,`read`) VALUES ('" . intval($ticket['id']) . "','" . $this->helpbase->database->escape($reply['name']) . "','" . $this->helpbase->database->escape($reply['message']) . "','" . $this->helpbase->database->escape($reply['dt']) . "','" . $this->helpbase->database->escape($reply['attachments']) . "','" . intval($reply['staffid']) . "','" . intval($reply['rating']) . "','" . intval($reply['read']) . "')");
                }

                /* Delete replies to the old ticket */
                $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `replyto`='" . intval($row['id']) . "'");

                /* Get old ticket notes and insert them as new notes */
                $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "notes` WHERE `ticket`='" . intval($row['id']) . "'");
                while ($note = $this->helpbase->database->fetchAssoc($res)) {
                    $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "notes` (`ticket`,`who`,`dt`,`message`) VALUES ('" . intval($ticket['id']) . "','" . intval($note['who']) . "','" . $this->helpbase->database->escape($note['dt']) . "','" . $this->helpbase->database->escape($note['message']) . "')");
                }

                /* Delete replies to the old ticket */
                $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "notes` WHERE `ticket`='" . intval($row['id']) . "'");

                /* Delete old ticket */
                $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `id`='" . intval($row['id']) . "'");

                /* Log that ticket has been merged */
                $history .= sprintf(_('<li class="smaller">%s | merged with ticket %s by %s</li>'), $this->helpbase->common->_date(), $row['trackid'], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

                /* Add old ticket ID to target ticket "merged" field */
                $merged .= '#' . $row['trackid'];

                /* Convert old ticket "time worked" to seconds and add to $sec_worked variable */
                list ($hr, $min, $sec) = explode(':', $row['time_worked']);
                $sec_worked += (((int) $hr) * 3600) + (((int) $min) * 60) + ((int) $sec);
            }

            /* Convert seconds to HHH:MM:SS */
            $sec_worked = $this->getTime('0:' . $sec_worked);

            /* Update history (log) and merged IDs of target ticket */
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `time_worked`=ADDTIME(`time_worked`, '" . $this->helpbase->database->escape($sec_worked) . "'), `merged`=CONCAT(`merged`,'" . $this->helpbase->database->escape($merged . '#') . "'), `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($history) . "') WHERE `id`='" . intval($merge_into) . "' LIMIT 1");

            return true;
        }

        public function updateStaffDefaults() {
            global $hesk_settings;

            // Demo mode
            if (true == $this->helpbase->demo_mode) {
                return true;
            }
            // Remove the part that forces saving as default - we don't need it every time
            $default_list = str_replace('&def=1', '', $_SERVER['QUERY_STRING']);

            // Update database
            $res = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET `default_list`='" . $this->helpbase->database->escape($default_list) . "' WHERE `id`='" . intval($_SESSION['id']) . "'");

            // Update session values so the changes take effect immediately
            $_SESSION['default_list'] = $default_list;

            return true;
        }

        public function makeJsString($in) {
            return addslashes(preg_replace("/\s+/", ' ', $in));
        }

        public function checkNewMail() {
            global $hesk_settings;

            $res = $this->helpbase->database->query("SELECT COUNT(*) FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` WHERE `to`='" . intval($_SESSION['id']) . "' AND `read`='0' AND `deletedby`!='" . intval($_SESSION['id']) . "' ");
            $num = $this->helpbase->database->result($res, 0, 0);

            return $num;
        }

        public function dateToString($dt, $returnName = 1, $returnTime = 0, $returnMonth = 0) {
            list($y, $m, $n, $d, $G, $i, $s) = explode('-', date('Y-n-j-w-G-i-s', strtotime($dt)));

            $m = $this->helpbase->common->getMonth($m);
            $d = $this->helpbase->common->getWeekday($d);

            if ($returnName) {
                return "$d, $m $n, $y";
            }

            if ($returnTime) {
                return "$d, $m $n, $y $G:$i:$s";
            }

            if ($returnMonth) {
                return "$m $y";
            }

            return "$m $n, $y";
        }

        public function getCategoriesArray($kb = 0) {
            global $hesk_settings, $hesk_db_link;

            $categories = array();
            if ($kb) {
                $result = $this->helpbase->database->query('SELECT `id`, `name` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `cat_order` ASC');
            } else {
                $result = $this->helpbase->database->query('SELECT `id`, `name` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'categories` ORDER BY `cat_order` ASC');
            }

            while ($row = $this->helpbase->database->fetchAssoc($result)) {
                $categories[$row['id']] = $row['name'];
            }

            return $categories;
        }

        public function getHTML($in) {
            global $hesk_settings;

            $replace_from = array("\t", "<?", "?>", "$", "<%", "%>");
            $replace_to = array("", "&lt;?", "?&gt;", "\$", "&lt;%", "%&gt;");

            $in = trim($in);
            $in = str_replace($replace_from, $replace_to, $in);
            $in = preg_replace('/\<script(.*)\>(.*)\<\/script\>/Uis', "<script$1></script>", $in);
            $in = preg_replace('/\<\!\-\-(.*)\-\-\>/Uis', "<!-- comments have been removed -->", $in);

            $in = addslashes($in);

            $in = str_replace('\"', '"', $in);

            return $in;
        }

        public function autoLogin($noredirect = 0) {
            global $hesk_settings, $hesk_db_link;

            if (!$hesk_settings['autologin']) {
                return false;
            }

            $user = $this->helpbase->common->htmlspecialchars($this->helpbase->common->get_cookie('hesk_username'));
            $hash = $this->helpbase->common->htmlspecialchars($this->helpbase->common->get_cookie('hesk_p'));

            $this->helpbase->user = $user;

            if (empty($user) || empty($hash)) {
                return false;
            }

            /* Login cookies exist, now lets limit brute force attempts */
            $this->helpbase->common->limitBfAttempts();

            /* Check username */
            $result = $this->helpbase->database->query('SELECT * FROM `' . $hesk_settings['db_pfix'] . "users` WHERE `user` = '" . $this->helpbase->database->escape($user) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($result) != 1) {
                setcookie('hesk_username', '');
                setcookie('hesk_p', '');
                header('Location: index.php?a=login&notice=1');
                exit();
            }

            $res = $this->helpbase->database->fetchAssoc($result);
            foreach ($res as $k => $v) {
                $_SESSION[$k] = $v;
            }

            /* Check password */
            if ($hash != $this->pass2Hash($_SESSION['pass'] . strtolower($user) . $_SESSION['pass'])) {
                setcookie('hesk_username', '');
                setcookie('hesk_p', '');
                header('Location: index.php?a=login&notice=1');
                exit();
            }

            /* Check if default password */
            if ($_SESSION['pass'] == '499d74967b28a841c98bb4baaabaad699ff3c079') {
                $this->helpbase->common->process_messages(_('Please change the default password on your <a href="profile.php">Profile</a> page!'), 'NOREDIRECT', 'NOTICE');
            }

            unset($_SESSION['pass']);

            /* Login successful, clean brute force attempts */
            $this->helpbase->common->cleanBfAttempts();

            /* Regenerate session ID (security) */
            $this->helpbase->common->session_regenerate_id();

            /* Get allowed categories */
            if (empty($_SESSION['isadmin'])) {
                $_SESSION['categories'] = explode(',', $_SESSION['categories']);
            }

            /* Renew cookies */
            setcookie('hesk_username', "$user", strtotime('+1 year'));
            setcookie('hesk_p', "$hash", strtotime('+1 year'));

            /* Close any old tickets here so Cron jobs aren't necessary */
            if ($hesk_settings['autoclose']) {
                $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $this->helpbase->common->_date(), _('(automatically)'));
                $dt = date('Y-m-d H:i:s', time() - $hesk_settings['autoclose'] * 86400);
                $this->helpbase->database->query("UPDATE `" . $hesk_settings['db_pfix'] . "tickets` SET `status`='3', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `status` = '2' AND `lastchange` <= '" . $this->helpbase->database->escape($dt) . "' ");
            }

            /* If session expired while a HESK page is open just continue using it, don't redirect */
            if ($noredirect) {
                return true;
            }

            /* Redirect to the destination page */
            if ($this->helpbase->common->isREQUEST('goto') && $url = $this->helpbase->common->_request('goto')) {
                $url = str_replace('&amp;', '&', $url);
                header('Location: ' . $url);
            } else {
                header('Location: admin_main.php');
            }
            exit();
        }

        public function isLoggedIn() {
            global $hesk_settings;

            $referer = $this->helpbase->common->_input($_SERVER['REQUEST_URI']);
            $referer = str_replace('&amp;', '&', $referer);

            if (empty($_SESSION['id'])) {
                if ($hesk_settings['autologin'] && $this->autoLogin(1)) {
                    // Users online
                    if ($hesk_settings['online']) {
                        require($this->helpbase->includes . 'users_online.inc.php');
                        $usersOnline = new HelpbaseUsersOnline($this->helpbase);
                        $usersOnline->initOnline($_SESSION['id']);
                    }

                    return true;
                }

                // Some pages cannot be redirected to
                $modify_redirect = array(
                    'admin_reply_ticket.php'    => 'admin_main.php',
                    'admin_settings_save.php'   => 'admin_settings.php',
                    'delete_tickets.php'        => 'admin_main.php',
                    'move_category.php'         => 'admin_main.php',
                    'priority.php'              => 'admin_main.php',
                );

                foreach ($modify_redirect as $from => $to) {
                    if (strpos($referer, $from) !== false) {
                        $referer = $to;
                    }
                }

                $url = 'index.php?a=login&notice=1&goto=' . urlencode($referer);
                header('Location: ' . $url);
                exit();
            } else {
                $this->helpbase->common->session_regenerate_id();

                // Need to update permissions?
                if (empty($_SESSION['isadmin'])) {
                    $res = $this->helpbase->database->query("SELECT `isadmin`, `categories`, `heskprivileges` FROM `" . $hesk_settings['db_pfix'] . "users` WHERE `id` = '" . intval($_SESSION['id']) . "' LIMIT 1");
                    if ($this->helpbase->database->numRows($res) == 1) {
                        $me = $this->helpbase->database->fetchAssoc($res);
                        foreach ($me as $k => $v) {
                            $_SESSION[$k] = $v;
                        }

                        // Get allowed categories
                        if (empty($_SESSION['isadmin'])) {
                            $_SESSION['categories'] = explode(',', $_SESSION['categories']);
                        }
                    } else {
                        $this->helpbase->common->session_stop();
                        $url = 'index.php?a=login&notice=1&goto=' . urlencode($referer);
                        header('Location: ' . $url);
                        exit();
                    }
                }

                // Users online
                if ($hesk_settings['online']) {
                    require_once($this->helpbase->includes . 'users_online.inc.php');
                    $usersOnline = new HelpbaseUsersOnline($this->helpbase);
                    $usersOnline->initOnline($_SESSION['id']);
                }

                return true;
            }
        }

        public function pass2Hash($plaintext) {
            $majorsalt = '';
            $len = strlen($plaintext);
            for ($i = 0; $i < $len; $i++) {
                $majorsalt .= sha1(substr($plaintext, $i, 1));
            }
            $corehash = sha1($majorsalt);
            return $corehash;
        }

        public function formatDate($dt) {
            $dt = $this->helpbase->common->_date($dt);
            $dt = str_replace(' ', '<br />', $dt);
            return $dt;
        }

        public function jsString($str) {
            $str = str_replace(array('\'', '<br />'), array('\\\'', ''), $str);
            $from = array("/\r\n|\n|\r/", '/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
            $to = array("\\r\\n' + \r\n'", "$1", "$1");
            return preg_replace($from, $to, $str);
        }

        public function myCategories($what = 'category') {
            if (!empty($_SESSION['isadmin'])) {
                return '1';
            } else {
                return " `" . $this->helpbase->database->escape($what) . "` IN ('" . implode("','", array_map('intval', $_SESSION['categories'])) . "')";
            }
        }

        public function okCategory($cat, $error = 1, $user_isadmin = false, $user_cat = false) {

            /* Checking for current user or someone else? */
            if ($user_isadmin === false) {
                $user_isadmin = $_SESSION['isadmin'];
            }

            if ($user_cat === false) {
                $user_cat = $_SESSION['categories'];
            }

            /* Is admin? */
            if ($user_isadmin) {
                return true;
            }
            /* Staff with access? */ elseif (in_array($cat, $user_cat)) {
                return true;
            }
            /* No access */ else {
                if ($error) {
                    $this->helpbase->common->_error(_('You are not authorized to view tickets inside this category!'));
                } else {
                    return false;
                }
            }
        }

        public function checkPermission($feature, $showerror = 1) {

            /* Admins have full access to all features */
            if ($_SESSION['isadmin']) {
                return true;
            }

            /* Check other staff for permissions */
            if (strpos($_SESSION['heskprivileges'], $feature) === false) {
                if ($showerror) {
                    $this->helpbase->common->_error(_('You don\'t have permission to perform this task, please login with an account that has.') . '<p>&nbsp;</p><p align="center"><a href="index.php">' . _('Click here to login') . '</a>');
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }
    }
}