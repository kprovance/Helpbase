<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Common Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseCommon')) {

    class HelpbaseCommon {
        private $dir                = '';
        private $brute_force_clean  = false;
        private $brute_force_limit  = false;
        private $session_clean      = false;
        private $original_lang      = '';
        private $url_email          = 1;
        private $url_full           = 2;
        private $url_local          = 3;
        private $url_www            = 4;
        
        public $language            = 'English';
        public $database            = null;
        public $helpbase            = null;
        
        public function __construct($parent) {
            global $hesk_settings;
            
            $this->helpbase = $parent;
            $this->database = $this->helpbase->database;
            
            error_reporting(E_ALL);
            $hesk_settings['debug_mode'] = 1;

            // Define some constants for backward-compatibility
            if (!defined('ENT_SUBSTITUTE')) {
                define('ENT_SUBSTITUTE', 0);
            }
            if (!defined('ENT_XHTML')) {
                define('ENT_XHTML', 0);
            }

            // Load language file
            $this->getLanguage();
        }

        public function __destruct(){
            unset ($this->database);
            unset ($this->helpbase);
        }
        
        private function clean_utf8($in) {
            //reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ?
            $in = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
                    '|[\x00-\x7F][\x80-\xBF]+' .
                    '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
                    '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
                    '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', '?', $in);

            //reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
            $in = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
                    '|\xED[\xA0-\xBF][\x80-\xBF]/S', '?', $in);

            return $in;
        }


        public function unlink($file, $older_than = 0) {
            return ( is_file($file) && (!$older_than || (time() - filectime($file)) > $older_than ) && @unlink($file) ) ? true : false;
        }

        public function utf8_urldecode($in) {
            $in = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($in));
            return $this->html_entity_decode($in);
        }

        public function get_cookie($in, $default = '') {
            return isset($_COOKIE[$in]) && !is_array($_COOKIE[$in]) ? $_COOKIE[$in] : $default;
        }

        public function _get($in, $default = '') {
            return isset($_GET[$in]) && !is_array($_GET[$in]) ? $_GET[$in] : $default;
        }

        public function _post($in, $default = '') {
            return isset($_POST[$in]) && !is_array($_POST[$in]) ? $_POST[$in] : $default;
        }

        public function _request($in, $default = false) {
            return isset($_GET[$in]) ? $this->_input($this->_get($in)) : ( isset($_POST[$in]) ? $this->_input($this->_post($in)) : $default );
        }

        public function isREQUEST($in) {
            return isset($_GET[$in]) || isset($_POST[$in]) ? true : false;
        }

        public function htmlspecialchars_decode($in) {
            return str_replace(array('&amp;', '&lt;', '&gt;', '&quot;'), array('&', '<', '>', '"'), $in);
        }

        public function html_entity_decode($in) {
            return html_entity_decode($in, ENT_COMPAT | ENT_XHTML, 'UTF-8');
            #return html_entity_decode($in, ENT_COMPAT | ENT_XHTML, 'ISO-8859-1');
        }

        public function htmlspecialchars($in) {
            return htmlspecialchars($in, ENT_COMPAT | ENT_SUBSTITUTE | ENT_XHTML, 'UTF-8');
            #return htmlspecialchars($in, ENT_COMPAT | ENT_SUBSTITUTE | ENT_XHTML, 'ISO-8859-1');
        }

        public function htmlentities($in) {
            return htmlentities($in, ENT_COMPAT | ENT_SUBSTITUTE | ENT_XHTML, 'UTF-8');
            #return htmlentities($in, ENT_COMPAT | ENT_SUBSTITUTE | ENT_XHTML, 'ISO-8859-1');
        }

        public function slashJS($in) {
            return str_replace('\'', '\\\'', $in);
        }

        public function verifyEmailMatch($trackingID, $my_email = 0, $ticket_email = 0, $error = 1) {
            global $hesk_settings, $hesk_db_link;

            /* Email required to view ticket? */
            if (!$hesk_settings['email_view_ticket']) {
                $hesk_settings['e_param'] = '';
                $hesk_settings['e_query'] = '';
                return true;
            }

            /* Limit brute force attempts */
            $this->limitBfAttempts();

            /* Get email address */
            if ($my_email) {
                $hesk_settings['e_param'] = '&e=' . rawurlencode($my_email);
                $hesk_settings['e_query'] = '&amp;e=' . rawurlencode($my_email);
            } else {
                $my_email = $this->getCustomerEmail();
            }

            /* Get email from ticket */
            if (!$ticket_email) {
                $res = $this->database->query("SELECT `email` FROM `" . $hesk_settings['db_pfix'] . "tickets` WHERE `trackid`='" . $this->database->escape($trackingID) . "' LIMIT 1");
                if ($this->database->numRows($res) == 1) {
                    $ticket_email = $this->database->result($res);
                } else {
                    $this->process_messages(_('Ticket not found! Please make sure you have entered the correct tracking ID!'), 'ticket.php');
                }
            }

            /* Validate email */
            if ($hesk_settings['multi_eml']) {
                $valid_emails = explode(',', strtolower($ticket_email));
                if (in_array(strtolower($my_email), $valid_emails)) {
                    /* Match, clean brute force attempts and return true */
                    $this->cleanBfAttempts();
                    return true;
                }
            } elseif (strtolower($ticket_email) == strtolower($my_email)) {
                /* Match, clean brute force attempts and return true */
                $this->cleanBfAttempts();
                return true;
            }

            /* Email doesn't match, clean cookies and error out */
            if ($error) {
                setcookie('hesk_myemail', '');
                $this->process_messages(_('The email address you entered doesn\'t match the one in the database for this ticket ID.'), 'ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999));
            } else {
                return false;
            }
        }

        public function getCustomerEmail($can_remember = 0) {
            global $hesk_settings;

            /* Email required to view ticket? */
            if (!$hesk_settings['email_view_ticket']) {
                $hesk_settings['e_param'] = '';
                $hesk_settings['e_query'] = '';
                return '';
            }

            /* Is this a form that enables remembering email? */
            if ($can_remember) {
                global $do_remember;
            }

            $my_email = '';

            /* Is email in query string? */
            if (isset($_GET['e']) || isset($_POST['e'])) {
                $my_email = $this->validateEmail($this->_request('e'), 'ERR', 0);
            } elseif (isset($_COOKIE['hesk_myemail'])) {
                /* Is email in cookie? */ 
                $my_email = $this->validateEmail($this->get_cookie('hesk_myemail'), 'ERR', 0);
                if ($can_remember && $my_email) {
                    $do_remember = ' checked="checked" ';
                }
            }

            $hesk_settings['e_param'] = '&e=' . rawurlencode($my_email);
            $hesk_settings['e_query'] = '&amp;e=' . rawurlencode($my_email);

            return $my_email;
        }

        public function formatBytes($size, $translate_unit = 1, $precision = 2) {

            $units = array(
                'GB' => 1073741824,
                'MB' => 1048576,
                'KB' => 1024,
                'B' => 1
            );

            foreach ($units as $suffix => $bytes) {
                if ($bytes > $size) {
                    continue;
                }

                $full = $size / $bytes;
                $round = round($full, $precision);

                if ($full == $round) {
                    if ($translate_unit) {
                        return $round . ' ' . $this->getFilesizeTranslation($suffix);
                    } else {
                        return $round . ' ' . $suffix;
                    }
                }
            }

            return false;
        }

        private function getFilesizeTranslation($suffix){
            switch($suffix){
                case 'GB': _('GB')  ; break;
                case 'MB': _('MB')  ; break;
                case 'KB': _('KB')  ; break;
                case 'B' : _('B')   ; break;
            }
        }
        
        public function autoAssignTicket($ticket_category) {
            global $hesk_settings ;

            /* Auto assign ticket enabled? */
            if (!$hesk_settings['autoassign']) {
                return false;
            }

            $autoassign_owner = array();

            /* Get all possible auto-assign staff, order by number of open tickets */
            $prefix = $this->database->escape($hesk_settings['db_pfix']);
            $res = $this->database->query("SELECT `t1`.`id`,`t1`.`user`,`t1`.`name`, `t1`.`email`, `t1`.`language`, `t1`.`isadmin`, `t1`.`categories`, `t1`.`notify_assigned`, `t1`.`heskprivileges`,
                                                (SELECT COUNT(*) FROM `" . $prefix . "tickets` FORCE KEY (`statuses`) WHERE `owner`=`t1`.`id` AND `status` IN ('0','1','2','4','5') ) as `open_tickets`
                                                FROM `" . $prefix . "users` AS `t1`
                                                WHERE `t1`.`autoassign`='1' ORDER BY `open_tickets` ASC, RAND()");

            /* Loop through the rows and return the first appropriate one */
            while ($myuser = $this->database->fetchAssoc($res)) {
                /* Is this an administrator? */
                if ($myuser['isadmin']) {
                    $autoassign_owner = $myuser;
                    $hesk_settings['user_data'][$myuser['id']] = $myuser;
                    $this->database->freeResult($res);
                    break;
                }

                /* Not and administrator, check two things: */

                /* --> can view and reply to tickets */
                if (strpos($myuser['heskprivileges'], 'can_view_tickets') === false || strpos($myuser['heskprivileges'], 'can_reply_tickets') === false) {
                    continue;
                }

                /* --> has access to ticket category */
                $myuser['categories'] = explode(',', $myuser['categories']);
                if (in_array($ticket_category, $myuser['categories'])) {
                    $autoassign_owner = $myuser;
                    $hesk_settings['user_data'][$myuser['id']] = $myuser;
                    $this->database->freeResult($res);
                    break;
                }
            }

            return $autoassign_owner;
        }

        public function cleanID($field = 'track') {
            if (isset($_GET[$field]) && !is_array($_GET[$field])) {
                return substr(preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET[$field])), 0, 12);
            } elseif (isset($_POST[$field]) && !is_array($_POST[$field])) {
                return substr(preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_POST[$field])), 0, 12);
            } else {
                return false;
            }
        }

        public function createID() {
            global $hesk_settings, $hesk_error_buffer;

            /*             * * Generate tracking ID and make sure it's not a duplicate one ** */

            /* Ticket ID can be of these chars */
            $useChars = 'AEUYBDGHJLMNPQRSTVWXZ123456789';

            /* Set tracking ID to an empty string */
            $trackingID = '';

            /* Let's avoid duplicate ticket ID's, try up to 3 times */
            for ($i = 1; $i <= 3; $i++) {
                /* Generate raw ID */
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];
                $trackingID .= $useChars[mt_rand(0, 29)];

                /* Format the ID to the correct shape and check wording */
                $trackingID = $this->formatID($trackingID);

                /* Check for duplicate IDs */
                $res = $this->database->query("SELECT `id` FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid` = '" . $this->database->escape($trackingID) . "' LIMIT 1");

                if ($this->database->numRows($res) == 0) {
                    /* Everything is OK, no duplicates found */
                    return $trackingID;
                }

                /* A duplicate ID has been found! Let's try again (up to 2 more) */
                $trackingID = '';
            }

            /* No valid tracking ID, try one more time with microtime() */
            $trackingID = $useChars[mt_rand(0, 29)];
            $trackingID .= $useChars[mt_rand(0, 29)];
            $trackingID .= $useChars[mt_rand(0, 29)];
            $trackingID .= $useChars[mt_rand(0, 29)];
            $trackingID .= $useChars[mt_rand(0, 29)];
            $trackingID .= substr(microtime(), -5);

            /* Format the ID to the correct shape and check wording */
            $trackingID = $this->formatID($trackingID);

            $res = $this->database->query("SELECT `id` FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid` = '" . $this->database->escape($trackingID) . "' LIMIT 1");

            /* All failed, must be a server-side problem... */
            if ($this->database->numRows($res) == 0) {
                return $trackingID;
            }

            $hesk_error_buffer['etid'] = _('Error generating a unique ticket ID, please try submitting the form again later.');
            return false;
        }

        private function formatID($id) {
            $useChars = 'AEUYBDGHJLMNPQRSTVWXZ123456789';

            $replace = $useChars[mt_rand(0, 29)];
            $replace .= mt_rand(1, 9);
            $replace .= $useChars[mt_rand(0, 29)];

            /*
              Remove 3 letter bad words from ID
              Possiblitiy: 1:27,000
             */
            $remove = array(
                'ASS',
                'CUM',
                'FAG',
                'FUK',
                'GAY',
                'GOD',
                'GUK',
                'HOE',
                'JAP',
                'JEW',
                'NIG',
                'SEX',
                'TIT',
                'VAG',
                'WOP',
                'XXX',
            );

            $id = str_replace($remove, $replace, $id);

            /*
              Remove 4 letter bad words from ID
              Possiblitiy: 1:810,000
             */
            $remove = array(
                'ANAL',
                'ANUS',
                'BUTT',
                'CAWK',
                'CLIT',
                'COCK',
                'COON',
                'CRAP',
                'CUNT',
                'DAGO',
                'DAMN',
                'DICK',
                'DIKE',
                'DYKE',
                'FART',
                'FUCK',
                'GOOK',
                'HEEB',
                'HELL',
                'HOMO',
                'JAPS',
                'JERK',
                'JIZZ',
                'KIKE',
                'KNOB',
                'KUNT',
                'KYKE',
                'MICK',
                'MOFO',
                'MUFF',
                'NIGG',
                'PAKI',
                'PISS',
                'POON',
                'POOP',
                'PUTO',
                'PUSS',
                'SHIT',
                'SHIZ',
                'SLUT',
                'SMEG',
                'SPIC',
                'SPIK',
                'SUCK',
                'TARD',
                'TITS',
                'TURD',
                'TWAT',
                'WANK',
                // Also, remove words that are known to trigger mod_security
                'WGET',
                'WSET',
            );

            $replace .= mt_rand(1, 9);
            $id = str_replace($remove, $replace, $id);

            /* Format the ID string into XXX-XXX-XXXX format for easier readability */
            $id = $id[0] . $id[1] . $id[2] . '-' . $id[3] . $id[4] . $id[5] . '-' . $id[6] . $id[7] . $id[8] . $id[9];

            return $id;
        }

        public function cleanBfAttempts() {
            global $hesk_settings;

            /* If this feature is disabled, just return */
            if (!$hesk_settings['attempt_limit'] || true == $this->brute_force_clean) {
                return true;
            }

            /* Delete expired logs from the database */
            $res = $this->database->query("DELETE FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "logins` WHERE `ip`='" . $this->database->escape($_SERVER['REMOTE_ADDR']) . "'");

            $this->brute_force_clean = true;

            return true;
        }

        public function limitBfAttempts($showError = 1) {
            global $hesk_settings;

            /* If this feature is disabled or already called, return false */
            if (!$hesk_settings['attempt_limit'] || true == $this->brute_force_limit) {
                return false;
            }

            /* Define this constant to avoid duplicate checks */
            $this->brite_force_limit = true;

            $ip = $_SERVER['REMOTE_ADDR'];

            /* Get number of failed attempts from the database */
            $res = $this->database->query("SELECT `number`, (CASE WHEN `last_attempt` IS NOT NULL AND DATE_ADD( last_attempt, INTERVAL " . $this->database->escape($hesk_settings['attempt_banmin']) . " MINUTE ) > NOW( ) THEN 1 ELSE 0 END) AS `banned` FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "logins` WHERE `ip`='" . $this->database->escape($ip) . "' LIMIT 1");

            /* Not in the database yet? Add first one and return false */
            if ($this->database->numRows($res) != 1) {
                $this->database->query("INSERT INTO `" . $this->database->escape($hesk_settings['db_pfix']) . "logins` (`ip`) VALUES ('" . $this->database->escape($ip) . "')");
                return false;
            }

            /* Get number of failed attempts and increase by 1 */
            $row = $this->database->fetchAssoc($res);
            $row['number'] ++;

            /* If too many failed attempts either return error or reset count if time limit expired */
            if ($row['number'] >= $hesk_settings['attempt_limit']) {
                if ($row['banned']) {
                    $tmp = sprintf(_('You have been locked out the system for %s minutes because of too many login failures.'), $hesk_settings['attempt_banmin']);

                    unset($_SESSION);

                    if ($showError) {
                        $this->_error($tmp, 0);
                    } else {
                        return $tmp;
                    }
                } else {
                    $row['number'] = 1;
                }
            }

            $this->database->query("UPDATE `" . $this->database->escape($hesk_settings['db_pfix']) . "logins` SET `number`=" . intval($row['number']) . " WHERE `ip`='" . $this->database->escape($ip) . "' LIMIT 1");

            return false;
        }

        public function getCategoryName($id) {
            global $hesk_settings;

            if (empty($id)) {
                return _('Unassigned');
            }

            // If we already have the name no need to query DB another time
            if (isset($hesk_settings['category_data'][$id]['name'])) {
                return $hesk_settings['category_data'][$id]['name'];
            }

            $res = $this->database->query("SELECT `name` FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='" . intval($id) . "' LIMIT 1");

            if ($this->database->numRows($res) != 1) {
                return _('(category deleted)');
            }

            $hesk_settings['category_data'][$id]['name'] = $this->database->result($res, 0, 0);

            return $hesk_settings['category_data'][$id]['name'];
        }

        public function getOwnerName($id) {
            global $hesk_settings;

            if (empty($id)) {
                return _('Unassigned');
            }

            // If we already have the name no need to query DB another time
            if (isset($hesk_settings['user_data'][$id]['name'])) {
                return $hesk_settings['user_data'][$id]['name'];
            }

            $res = $this->database->query("SELECT `name` FROM `" . $this->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . intval($id) . "' LIMIT 1");

            if ($this->database->numRows($res) != 1) {
                return _('Unassigned');
            }

            $hesk_settings['user_data'][$id]['name'] = $this->database->result($res, 0, 0);

            return $hesk_settings['user_data'][$id]['name'];
        }

        public function cleanSessionVars($arr) {
            if (is_array($arr)) {
                foreach ($arr as $str) {
                    if (isset($_SESSION[$str])) {
                        unset($_SESSION[$str]);
                    }
                }
            } elseif (isset($_SESSION[$arr])) {
                unset($_SESSION[$arr]);
            }
        }

        public function process_messages($message, $redirect_to, $type = 'ERROR') {
            global $hesk_settings;

            switch ($type) {
                case 'SUCCESS':
                    $_SESSION['HESK_SUCCESS'] = TRUE;
                    break;
                case 'NOTICE':
                    $_SESSION['HESK_NOTICE'] = TRUE;
                    break;
                default:
                    $_SESSION['HESK_ERROR'] = TRUE;
            }

            $_SESSION['HESK_MESSAGE'] = $message;

            /* In some cases we don't want a redirect */
            if ($redirect_to == 'NOREDIRECT') {
                return TRUE;
            }

            header('Location: ' . $redirect_to);
            exit();
        }

        public function handle_messages() {
            global $hesk_settings;

            $return_value = true;

            // Primary message - only one can be displayed and HESK_MESSAGE is required
            if (isset($_SESSION['HESK_MESSAGE'])) {
                if (isset($_SESSION['HESK_SUCCESS'])) {
                    $this->show_success($_SESSION['HESK_MESSAGE']);
                } elseif (isset($_SESSION['HESK_ERROR'])) {
                    $this->show_error($_SESSION['HESK_MESSAGE']);
                    $return_value = false;
                } elseif (isset($_SESSION['HESK_NOTICE'])) {
                    $this->show_notice($_SESSION['HESK_MESSAGE']);
                }

                $this->cleanSessionVars('HESK_MESSAGE');
            }

            // Cleanup any primary message types set
            $this->cleanSessionVars('HESK_ERROR');
            $this->cleanSessionVars('HESK_SUCCESS');
            $this->cleanSessionVars('HESK_NOTICE');

            // Secondary message
            if (isset($_SESSION['HESK_2ND_NOTICE']) && isset($_SESSION['HESK_2ND_MESSAGE'])) {
                $this->show_notice($_SESSION['HESK_2ND_MESSAGE']);
                $this->cleanSessionVars('HESK_2ND_NOTICE');
                $this->cleanSessionVars('HESK_2ND_MESSAGE');
            }

            return $return_value;
        }

        public function show_error($message, $title = '') {
            global $hesk_settings;
            
            $title = $title ? $title : _('Error');
            ?>
            <div class="error">
                <img src="<?php echo $this->helpbase->url; ?>img/error.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
                <b><?php echo $title; ?>:</b> <?php echo $message; ?>
            </div>
            <br />
            <?php
        }

        public function show_success($message, $title = '') {
            global $hesk_settings;
            
            $title = $title ? $title : _('Success');
            ?>
            <div class="success">
                <img src="<?php echo $this->helpbase->url; ?>img/success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
                <b><?php echo $title; ?>:</b> <?php echo $message; ?>
            </div>
            <br />
            <?php
        }

        public function show_notice($message, $title = '') {
            global $hesk_settings;
            
            $title = $title ? $title : _('Note');
            ?>
            <div class="notice">
                <img src="<?php echo $this->helpbase->url; ?>img/notice.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
                <b><?php echo $title; ?>:</b> <?php echo $message; ?>
            </div>
            <br />
            <?php
        }

        public function token_echo($do_echo = 1) {
            if ( false == $this->session_clean) {
                $_SESSION['token'] = $this->htmlspecialchars(strip_tags($_SESSION['token']));
                $this->session_clean = true;
            }

            if ($do_echo) {
                echo $_SESSION['token'];
            } else {
                return $_SESSION['token'];
            }
        }

        public function token_check($method = 'GET', $show_error = 1) {
            // Get the token
            $my_token = $this->_request('token');
            //echo 'token:' . $my_token;
            // Verify it or throw an error
            if (!$this->token_compare($my_token)) {
                if ($show_error) {
                    global $hesk_settings;
                    $this->_error(_('Invalid request'));
                } else {
                    return false;
                }
            }
            return true;
        }

        private function token_compare($my_token) {
            //echo 'session:' . $_SESSION['token'];
            if (isset($_SESSION['token']) && $my_token == $_SESSION['token']) {
                return true;
            } else {
                return false;
            }
        }

        private function token_hash() {
            return sha1(time() . microtime() . uniqid(rand(), true));
        }

        public function & ref_new(&$new_statement) {
            return $new_statement;
        }

        public function ticketToPlain($ticket, $specialchars = 0, $strip = 1) {
            if (is_array($ticket)) {
                foreach ($ticket as $key => $value) {
                    $ticket[$key] = is_array($ticket[$key]) ? $this->ticketToPlain($value, $specialchars, $strip) : $this->msgToPlain($value, $specialchars, $strip);
                }

                return $ticket;
            } else {
                return $this->msgToPlain($ticket, $specialchars, $strip);
            }
        }

        public function msgToPlain($msg, $specialchars = 0, $strip = 1) {
            $msg = preg_replace('/\<a href="(mailto:)?([^"]*)"[^\<]*\<\/a\>/i', "$2", $msg);
            $msg = preg_replace('/<br \/>\s*/', "\n", $msg);
            $msg = trim($msg);

            if ($strip) {
                $msg = stripslashes($msg);
            }

            if ($specialchars) {
                $msg = $this->html_entity_decode($msg);
            }

            return $msg;
        }

        public function showTopBar($page_title) {
            global $hesk_settings;

            if ($hesk_settings['can_sel_lang']) {

                $str = '<form method="get" action="" style="margin:0;padding:0;border:0;white-space:nowrap;">';

                if (!isset($_GET)) {
                    $_GET = array();
                }

                foreach ($_GET as $k => $v) {
                    if ($k == 'language') {
                        continue;
                    }
                    $str .= '<input type="hidden" name="' . $this->htmlentities($k) . '" value="' . $this->htmlentities($v) . '" />';
                }

                $str .= '<select name="language" onchange="this.form.submit()">';
                $str .= $this->listLanguages(0);
                $str .= '</select>';
                ?>
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="headersm" style="padding-left: 0px;"><?php echo $page_title; ?></td>
                        <td class="headersm" style="padding-left: 0px;text-align: right">
                            <script language="javascript" type="text/javascript">
                                document.write('<?php echo str_replace(array('"', '<', '=', '>'), array('\42', '\74', '\75', '\76'), $str . '</form>'); ?>');
                            </script>
                            <noscript>
                            <?php
                            echo $str . '<input type="submit" value="' . _('Go') . '" /></form>';
                            ?>
                            </noscript>
                        </td>
                    </tr>
                </table>
                <?php
            } else {
                echo $page_title;
            }
        }

        public function listLanguages($doecho = 1) {
            global $hesk_settings;

            $tmp = '';

            foreach ($hesk_settings['languages'] as $lang => $info) {
                if ($lang == $hesk_settings['language']) {
                    $tmp .= '<option value="' . $lang . '" selected="selected">' . $lang . '</option>';
                } else {
                    $tmp .= '<option value="' . $lang . '">' . $lang . '</option>';
                }
            }

            if ($doecho) {
                echo $tmp;
            } else {
                return $tmp;
            }
        }

        public function resetLanguage() {
            global $hesk_settings;

            /* If this is not a valid request no need to change aynthing */
            if (!$hesk_settings['can_sel_lang'] || '' == $this->original_lang) {
                return false;
            }

            /* If we already have original language, just return true */
            if ($hesk_settings['language'] == $this->original_lang) {
                return true;
            }

            /* Get the original language file */
            $hesk_settings['language'] = $this->original_lang;
            return $this->returnLanguage($this->original_lang);
        }

        public function setLanguage($language) {
            global $hesk_settings;

            /* If no language is set, use default */
            if (!$language) {
                $language = $this->language;
            }

            /* If this is not a valid request no need to change aynthing */
            if (!$hesk_settings['can_sel_lang'] || $language == $hesk_settings['language'] || !isset($hesk_settings['languages'][$language])) {
                return false;
            }

            /* Remember current language for future reset - if reset is not set already! */
            if ('' == $this->original_lang) {
                $this->original_lang = $hesk_settings['language'];
            }

            /* Get the new language file */
            $hesk_settings['language'] = $language;

            return $this->returnLanguage($language);
        }

        public function getLanguage() {
            global $hesk_settings, $_SESSION;
            
            $arr = $this->getLanguagesArray();

            $language = $hesk_settings['language'];

            /* Remember what the default language is for some special uses like mass emails */
            $this->language = $language; //$hesk_settings['language'];

            /* Can users select language? */
            if (empty($hesk_settings['can_sel_lang'])) {
                return $this->returnLanguage($language);
            }

            /* Is a non-default language selected? If not use default one */
            if (isset($_GET['language'])) {
                $language = $this->_input($this->_get('language')) or $language = $hesk_settings['language'];
            } elseif (isset($_COOKIE['hesk_language'])) {
                $language = $this->_input($this->get_cookie('hesk_language')) or $language = $hesk_settings['language'];
            } else {
                return $this->returnLanguage($language);
            }

            /* non-default language selected. Check if it's a valid one, if not use default one */
            if ($language != $hesk_settings['language']) { //&& isset($hesk_settings['languages'][$language])) {
                $hesk_settings['language'] = $language;
            }

            /* Remember and set the selected language */
            setcookie('hesk_language', $hesk_settings['language'], time() + 31536000, '/');
            return $this->returnLanguage($language);
        }

        private function returnLanguage($lang) {
            global $hesk_settings;

            $langDir = $hesk_settings['languages'][$lang];
            $hesk_settings['lang_folder'] = $langDir;
            
            putenv('LC_ALL=' . $langDir);

            bindtextdomain("text", $this->helpbase->dir . "locale");
            bind_textdomain_codeset('text', 'UTF-8');
            textdomain("text"); 
            
            // test
             //echo _('(HIDDEN IN DEMO)');

            return true;
        }

        public function getLanguagesArray($returnArray = 0) {
            global $hesk_settings;

            /* Get a list of valid emails */
            $hesk_settings['smtp'] = 0;
            $this->helpbase->load_email_functions();
            $valid_emails = array_keys($this->helpbase->email->validEmails());

            $dir = $this->helpbase->dir . 'locale/';
            $path = opendir($dir);
            $code = '';
            $langArray = array();

            /* Test all folders inside the language folder */
            while (false !== ($subdir = readdir($path))) {
                if ($subdir == "." || $subdir == "..") {
                    continue;
                }

                if (filetype($dir . $subdir) == 'dir') {
                    $add        = 1;
                    $langName   = $dir . $subdir . '/lang.name';
                    $email      = $dir . $subdir . '/emails';
                    $texts      = $dir . $subdir . '/LC_MESSAGES';

                    // Check lang.name
                    if (file_exists($langName)) {
                        $tmp = file_get_contents($langName);
                        
                        // Some servers add slashes to file_get_contents output
                        if (strpos($tmp, '[\\\'LANGUAGE\\\']') !== false) {
                            $tmp = stripslashes($tmp);
                        }  
                        
                        if (!preg_match('/\[\'LANGUAGE\'\]\=\'(.*)\'/', $tmp, $l)) {
                            $add = 0;
                            $l = $subdir;
                        }
                    } else {
                        $add = 0;
                    }
                    
                    // Check for LC_MESSAGES folder
                    if (file_exists($texts) && filetype($texts) == 'dir') {
                        
                        /* Check the text.po */
                        $langu = $texts . '/text.po';
                        if (file_exists($langu)) {
                            $tmp = file_get_contents($langu);

                            if (strpos($tmp, 'msgid "Language"') === false) {
                                $add = 0;
                            }
                        } else {
                            $add = 0;
                        }
                    } else {
                        $add = 0;
                    }

                    /* Check emails folder */
                    if (file_exists($email) && filetype($email) == 'dir') {
                        foreach ($valid_emails as $eml) {
                            if (!file_exists($email . '/' . $eml . '.txt')) {
                                $add = 0;
                            }
                        }
                    } else {
                        $add = 0;
                    }

                    /* Add an option for the <select> if needed */
                    if ($add) {
                        //$code .= "'" . addslashes($l[1]) . "' => '" . $subdir . "',\n";
                        $kpArray[$l[1]] = $subdir; 
                        $langArray[]    = $l[1];
                    }
                }
            }

            closedir($path);

            if ($returnArray) {
                return $langArray;
            } else {
                return $kpArray;
            }
        }
        
        public function _date($dt = '') {
            global $hesk_settings;

            if (!$dt) {
                $dt = time();
            } else {
                $dt = strtotime($dt);
            }

            $dt += 3600 * $hesk_settings['diff_hours'] + 60 * $hesk_settings['diff_minutes'];

            if ($hesk_settings['daylight'] && date('I', $dt)) {
                $dt += 3600;
            }

            return date($hesk_settings['timeformat'], $dt);
        }

        /* public function array_fill_keys($keys, $value) {
          if (version_compare(PHP_VERSION, '5.2.0', '>=')) {
          return array_fill_keys($keys, $value);
          } else {
          return array_combine($keys, array_fill(0, count($keys), $value));
          }
          } */

        /**
         * makeURL function
         *
         * Replace magic urls of form http://xxx.xxx., www.xxx. and xxx@xxx.xxx.
         * Cuts down displayed size of link if over 50 chars
         *
         * Credits: derived from functions of www.phpbb.com
         */
        public function makeURL($text, $class = '') {
            global $hesk_settings;

            /* if (!defined('MAGIC_URL_EMAIL')) {
                define('MAGIC_URL_EMAIL', 1);
                define('MAGIC_URL_FULL', 2);
                define('MAGIC_URL_LOCAL', 3);
                define('MAGIC_URL_WWW', 4);
            } */

            $class = ($class) ? ' class="' . $class . '"' : '';

            // matches a xxxx://aaaaa.bbb.cccc. ...
            $text = preg_replace_callback(
                    '#(^|[\n\t (>.])([a-z][a-z\d+]*:/{2}(?:(?:[a-z0-9\-._~!$&\'(*+,;=:@|]+|%[\dA-F]{2})+|[0-9.]+|\[[a-z0-9.]+:[a-z0-9.]+:[a-z0-9.:]+\])(?::\d*)?(?:/(?:[a-z0-9\-._~!$&\'(*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&\'(*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&\'(*+,;=:@/?|]+|%[\dA-F]{2})*)?)#i', create_function(
                            "\$matches", "return  \$this->make_clickable_callback(\$this->url_full, \$matches[1], \$matches[2], '', '$class');"
                    ), $text
            );

            // matches a "www.xxxx.yyyy[/zzzz]" kinda lazy URL thing
            $text = preg_replace_callback(
                    '#(^|[\n\t (>.])(www\.(?:[a-z0-9\-._~!$&\'(*+,;=:@|]+|%[\dA-F]{2})+(?::\d*)?(?:/(?:[a-z0-9\-._~!$&\'(*+,;=:@|]+|%[\dA-F]{2})*)*(?:\?(?:[a-z0-9\-._~!$&\'(*+,;=:@/?|]+|%[\dA-F]{2})*)?(?:\#(?:[a-z0-9\-._~!$&\'(*+,;=:@/?|]+|%[\dA-F]{2})*)?)#i', create_function(
                            "\$matches", "return  \$this->make_clickable_callback(\$this->url_www, \$matches[1], \$matches[2], '', '$class');"
                    ), $text
            );

            // matches an email address
            $text = preg_replace_callback(
                    '#(^|[\n\t (>.])(([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*(?:[\w\!\#$\%\'\*\+\-\/\=\?\^\`{\|\}\~]|&amp;)+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,63})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?))#i', create_function(
                            "\$matches", "return  \$this->make_clickable_callback(\$this->url_email, \$matches[1], \$matches[2], '', '$class');"
                    ), $text
            );

            return $text;
        }

        private function make_clickable_callback($type, $whitespace, $url, $relative_url, $class) {
            global $hesk_settings;

            $orig_url = $url;
            $orig_relative = $relative_url;
            $append = '';
            $url = htmlspecialchars_decode($url);
            $relative_url = htmlspecialchars_decode($relative_url);

            // make sure no HTML entities were matched
            $chars = array('<', '>', '"');
            $split = false;

            foreach ($chars as $char) {
                $next_split = strpos($url, $char);
                if ($next_split !== false) {
                    $split = ($split !== false) ? min($split, $next_split) : $next_split;
                }
            }

            if ($split !== false) {
                // an HTML entity was found, so the URL has to end before it
                $append = substr($url, $split) . $relative_url;
                $url = substr($url, 0, $split);
                $relative_url = '';
            } else if ($relative_url) {
                // same for $relative_url
                $split = false;
                foreach ($chars as $char) {
                    $next_split = strpos($relative_url, $char);
                    if ($next_split !== false) {
                        $split = ($split !== false) ? min($split, $next_split) : $next_split;
                    }
                }

                if ($split !== false) {
                    $append = substr($relative_url, $split);
                    $relative_url = substr($relative_url, 0, $split);
                }
            }

            // if the last character of the url is a punctuation mark, exclude it from the url
            $last_char = ($relative_url) ? $relative_url[strlen($relative_url) - 1] : $url[strlen($url) - 1];

            switch ($last_char) {
                case '.':
                case '?':
                case '!':
                case ':':
                case ',':
                    $append = $last_char;
                    if ($relative_url) {
                        $relative_url = substr($relative_url, 0, -1);
                    } else {
                        $url = substr($url, 0, -1);
                    }
                    break;

                // set last_char to empty here, so the variable can be used later to
                // check whether a character was removed
                default:
                    $last_char = '';
                    break;
            }

            $short_url = ($hesk_settings['short_link'] && strlen($url) > 70) ? substr($url, 0, 54) . ' ... ' . substr($url, -10) : $url;

            switch ($type) {
                case $this->url_local:
                    $tag = 'l';
                    $relative_url = preg_replace('/[&?]sid=[0-9a-f]{32}$/', '', preg_replace('/([&?])sid=[0-9a-f]{32}&/', '$1', $relative_url));
                    $url = $url . '/' . $relative_url;
                    $text = $relative_url;

                    // this url goes to http://domain.tld/path/to/board/ which
                    // would result in an empty link if treated as local so
                    // don't touch it and let MAGIC_URL_FULL take care of it.
                    if (!$relative_url) {
                        return $whitespace . $orig_url . '/' . $orig_relative; // slash is taken away by relative url pattern
                    }
                    break;

                case $this->url_full:
                    $tag = 'm';
                    $text = $short_url;
                    break;

                case $this->url_www:
                    $tag = 'w';
                    $url = 'http://' . $url;
                    $text = $short_url;
                    break;

                case $this->url_email:
                    $tag = 'e';
                    $text = $short_url;
                    $url = 'mailto:' . $url;
                    break;
            }

            $url = htmlspecialchars($url);
            $text = htmlspecialchars($text);
            $append = htmlspecialchars($append);

            $html = "$whitespace<a href=\"$url\"$class>$text</a>$append";

            return $html;
        }

        public function unshortenUrl($in) {
            global $hesk_settings;
            return $hesk_settings['short_link'] ? preg_replace('/\<a href="(mailto:)?([^"]*)"[^\<]*\<\/a\>/i', "<a href=\"$1$2\">$2</a>", $in) : $in;
        }

        public function isNumber($in, $error = 0) {
            $in = trim($in);

            if (preg_match("/\D/", $in) || $in == "") {
                if ($error) {
                    $this->_error($error);
                } else {
                    return 0;
                }
            }

            return $in;
        }

        public function validateURL($url, $error) {
            $url = trim($url);

            if (strpos($url, "'") !== false || strpos($url, "\"") !== false) {
                die(_('Invalid attempt!'));
            }

            if (preg_match('/^https?:\/\/+(localhost|[\w\-]+\.[\w\-]+)/i', $url)) {
                return $this_input($url);
            }

            $this->_error($error);
        }

        public function _input($in, $error = 0, $redirect_to = '', $force_slashes = 0, $max_length = 0) {
            // Strip whitespace
            $in = trim($in);

            // Is value length 0 chars?
            if (strlen($in) == 0) {
                // Do we need to throw an error?
                if ($error) {
                    if ($redirect_to == 'NOREDIRECT') {
                        $this->process_messages($error, 'NOREDIRECT');
                    } elseif ($redirect_to) {
                        $this->process_messages($error, $redirect_to);
                    } else {
                        $this->_error($error);
                    }
                }
                // Just ignore and return the empty value
                else {
                    return $in;
                }
            }

            // Sanitize input
            $in = $this->clean_utf8($in);
            $in = $this->htmlspecialchars($in);
            $in = preg_replace('/&amp;(\#[0-9]+;)/', '&$1', $in);

            // Add slashes
            if ($force_slashes) {
                $in = addslashes($in);
            }

            // Check length
            if ($max_length) {
                $in = substr($in, 0, $max_length);
            }

            // Return processed value
            return $in;
        }

        public function validateEmail($address, $error, $required = 1) {
            global $hesk_settings;

            /* Allow multiple emails to be used? */
            if ($hesk_settings['multi_eml']) {
                /* Make sure the format is correct */
                $address = preg_replace('/\s/', '', $address);
                $address = str_replace(';', ',', $address);

                /* Check if addresses are valid */
                $all = explode(',', $address);
                foreach ($all as $k => $v) {
                    if (!$this->isValidEmail($v)) {
                        unset($all[$k]);
                    }
                }

                /* If at least one is found return the value */
                if (count($all)) {
                    return $this->_input(implode(',', $all));
                }
            } else {
                /* Make sure people don't try to enter multiple addresses */
                $address = str_replace(strstr($address, ','), '', $address);
                $address = str_replace(strstr($address, ';'), '', $address);
                $address = trim($address);

                /* Valid address? */
                if ($this->isValidEmail($address)) {
                    return $this->_input($address);
                }
            }


            if ($required) {
                $this->_error($error);
            } else {
                return '';
            }
        }

        private function isValidEmail($email) {
            /* Check for header injection attempts */
            if (preg_match("/\r|\n|%0a|%0d/i", $email)) {
                return false;
            }

            /* Does it contain an @? */
            $atIndex = strrpos($email, "@");
            if ($atIndex === false) {
                return false;
            }

            /* Get local and domain parts */
            $domain = substr($email, $atIndex + 1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);

            /* Check local part length */
            if ($localLen < 1 || $localLen > 64) {
                return false;
            }

            /* Check domain part length */
            if ($domainLen < 1 || $domainLen > 254) {
                return false;
            }

            /* Local part mustn't start or end with a dot */
            if ($local[0] == '.' || $local[$localLen - 1] == '.') {
                return false;
            }

            /* Local part mustn't have two consecutive dots */
            if (strpos($local, '..') !== false) {
                return false;
            }

            /* Check domain part characters */
            if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                return false;
            }

            /* Domain part mustn't have two consecutive dots */
            if (strpos($domain, '..') !== false) {
                return false;
            }

            /* Character not valid in local part unless local part is quoted */
            if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) /* " */ {
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) /* " */ {
                    return false;
                }
            }

            /* All tests passed, email seems to be OK */
            return true;
        }

        public function session_regenerate_id() {
            @session_regenerate_id();
            return true;
        }

        public function session_start() {
            global $hesk_settings;
            
            session_name('HelpBase' . sha1(dirname(__FILE__) . '$r^k*Zkq|w1(G@!-D?3%'));
            session_cache_limiter('nocache');
            if (@session_start()) {
                if (!isset($_SESSION['token'])) {
                    $_SESSION['token'] = $this->token_hash();
                }
                header('P3P: CP="CAO DSP COR CURa ADMa DEVa OUR IND PHY ONL UNI COM NAV INT DEM PRE"');
                return true;
            } else {
                $this->_error(_('Can\'t start a new session!') . _('Please notify webmaster at ') . $hesk_settings['webmaster_mail']);
            }
        }

        public function session_stop() {
            @session_unset();
            @session_destroy();
            return true;
        }

        public function stripArray($a) {
            foreach ($a as $k => $v) {
                if (is_array($v)) {
                    $a[$k] = $this->stripArray($v);
                } else {
                    $a[$k] = stripslashes($v);
                }
            }

            reset($a);
            return ($a);
        }

        public function slashArray($a) {
            foreach ($a as $k => $v) {
                if (is_array($v)) {
                    $a[$k] = $this->slashArray($v);
                } else {
                    $a[$k] = addslashes($v);
                }
            }

            reset($a);
            return ($a);
        }

        public function _error($error, $showback = 1) {
            global $hesk_settings;

            $this->helpbase->header->render();
            ?>
            
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="3"><img src="<?php echo $this->helpbase->url; ?>img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                    <td class="headersm"><?php echo $hesk_settings['hesk_title']; ?></td>
                    <td width="3"><img src="<?php echo $this->helpbase->url; ?>img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                </tr>
            </table>

            <table width="100%" border="0" cellspacing="0" cellpadding="3">
                <tr>
                    <td>
                        <span class="smaller">
                            <a href="<?php echo $hesk_settings['site_url']; ?>"
                               class="smaller"><?php echo $hesk_settings['site_title']; ?>
                            </a> &gt; 
                            <a href="<?php
                                if (empty($_SESSION['id'])) {
                                    echo $hesk_settings['hesk_url'];
                                } else {
                                    echo $this->helpbase->url . $hesk_settings['admin_dir'] . '/admin_main.php';
                                }
                                ?>" 
                                class="smaller"><?php echo $hesk_settings['hesk_title']; ?>
                            </a>
                            &gt; <?php echo _('Error'); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <p>&nbsp;</p>
            <div class="error">
                <img src="<?php echo $this->helpbase->url; ?>img/error.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
                <b><?php echo _('Error'); ?>:</b>
                <br /><br />
                <?php
                echo $error;

                if ($hesk_settings['debug_mode']) {
                    echo '
                          <p>&nbsp;</p>
                          <p><span style="color:red;font-weight:bold">' . _('WARNING') . '</span><br />' . _('Debug mode is enabled. Make sure you disable debug mode in settings once HESK is installed and working properly.') . '</p>
                         ';
                }
                ?>
            </div>
            <br />
            <p>&nbsp;</p>
            <?php if ($showback) { ?>
                <p style="text-align:center">
                    <a href="javascript:history.go(-1)"><?php echo _('Go back'); ?></a>
                </p> 
            <?php } ?>
            <p>&nbsp;</p>
            <p>&nbsp;</p>

            <?php
            
            $this->helpbase->footer->render();
            
            exit();
        }

        public function round_to_half($num) {
            if ($num >= ($half = ($ceil = ceil($num)) - 0.5) + 0.25) {
                return $ceil;
            } elseif ($num < $half - 0.25) {
                return floor($num);
            } else {
                return $half;
            }
        }
        
        public function getMonth($month){
            switch($month){
                case 1:  return 'January';      break;
                case 2:  return 'February';     break;
                case 3:  return 'March';        break;
                case 4:  return 'April';        break;
                case 5:  return 'May';          break;
                case 6:  return 'June';         break;
                case 7:  return 'July';         break;
                case 8:  return 'August';       break;
                case 9:  return 'September';    break;
                case 10: return 'October';      break;
                case 11: return 'November';     break;
                case 12: return 'December';     break;
            }
        }

        public function getWeekday($date){
            switch ($date) {
                case 0: return 'Sunday';    break;
                case 1: return 'Monday';    break;
                case 2: return 'Tuesday';   break;
                case 3: return 'Wednesday'; break;
                case 4: return 'Thursday';  break;
                case 5: return 'Friday';    break;
                case 6: return 'Saturday';  break;    
            }
        }        
    }
}