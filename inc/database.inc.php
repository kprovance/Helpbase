<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  mySql Database Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpBaseDB')){
    class HelpBaseDB{
        private $helpbase       = null;
        
        public $is_connected    = false;
        
        public function __construct(){
            $this->helpbase = $parent;
        }
        
        private function setNames() {
            global $hesk_settings, $hesk_db_link;

            if ($hesk_settings['db_vrsn']) {
                mysql_set_charset('utf8', $hesk_db_link);
            } else {
                $this->query("SET NAMES 'utf8'");
            }
        }

        public function formatEmail($email, $field = 'email') {
            global $hesk_settings;

            $email = $this->like($email);

            if ($hesk_settings['multi_eml']) {
                return " (`" . $this->escape($field) . "` LIKE '" . $this->escape($email) . "' OR `" . $this->escape($field) . "` LIKE '%," . $this->escape($email) . "' OR `" . $this->escape($field) . "` LIKE '" . $this->escape($email) . ",%' OR `" . $this->escape($field) . "` LIKE '%," . $this->escape($email) . ",%') ";
            } else {
                return " `" . $this->escape($field) . "` LIKE '" . $this->escape($email) . "' ";
            }
        }

        public function _time() {
            $res = $this->query("SELECT NOW()");
            return strtotime($this->result($res, 0, 0));
        }

        function escape($in) {
            global $hesk_db_link;

            $in = mysql_real_escape_string(stripslashes($in), $hesk_db_link);
            $in = str_replace('`', '&#96;', $in);

            return $in;
        }

        public function like($in) {
            return str_replace(array('_', '%'), array('\\\\_', '\\\\%'), $in);
        }

        public function connect() {
            global $hesk_settings, $hesk_db_link;

            // Is mysql supported?
            if (!function_exists('mysql_connect')) {
                die(_('Your PHP does not have MySQL support enabled (mysqli extension required)'));
            }

            // Connect to the database
            $hesk_db_link = @mysql_connect($hesk_settings['db_host'], $hesk_settings['db_user'], $hesk_settings['db_pass']);

            // Errors?
            $debug_mode = isset($hesk_settings['debug_mode']) ? $hesk_settings['debug_mode'] : true;
            
            if (!$hesk_db_link) {
                if (true == $debug_mode) {
                    $this->helpbase->common->_error(_('Cannot connect to database.') . '</p><p>' . _('MySQL said') . ':<br />' . mysql_error() . '</p>');
                } else {
                    $this->helpbase->common->_error(_('Cannot connect to database.') . '</p><p>' . _('Please notify webmaster at') . ' <a href=\"mailto:' . $hesk_settings['webmaster_mail'] . '\">' . $hesk_settings['webmaster_mail'] . '</a></p>');
                }
            }

            if (!@mysql_select_db($hesk_settings['db_name'], $hesk_db_link)) {
                if ($hesk_settings['debug_mode']) {
                    $this->helpbase->common->_error(_('Cannot connect to database.') . '</p><p>' . _('MySQL said') . ':<br />' . mysql_error() . '</p>');
                } else {
                    $this->helpbase->common->_error(_('Cannot connect to database.') . '</p><p>' . _('Please notify webmaster at') . ' <a href=\"mailto:' . $hesk_settings['webmaster_mail'] . '\">' . $hesk_settings['webmaster_mail'] . '</a></p>');
                }
            }

            // Check MySQL/PHP version and set encoding to utf8
            $this->setNames();
            
            $this->is_connected = true;
            
            return $hesk_db_link;
        }

        public function close() {
            global $hesk_db_link;

            $this->is_connected = false;
            
            return @mysql_close($hesk_db_link);
        }

        public function query($query) {
            global $hesk_last_query, $hesk_db_link, $hesk_settings;

            if (!$hesk_db_link && !$this->connect()) {
                return false;
            }

            $hesk_last_query = $query;

            #echo "<p>EXPLAIN $query</p>\n";

            $debug_mode = isset($hesk_settings['debug_mode']) ? $hesk_settings['debug_mode'] : true;
            
            if ($res = @mysql_query($query, $hesk_db_link)) {
                return $res;
            } elseif (true == $debug_mode) {
                $this->helpbase->common->_error(_('Cannot execute SQL') . ': ' . $query . '</p><p>' . _('MySQL said') . ':<br />' . mysql_error() . '</p>');
            } else {
                $this->helpbase->common->_error(_('Cannot execute SQL') . '</p><p>' . _('Please notify webmaster at') . ' <a href=\"mailto:' . $hesk_settings['webmaster_mail'] . '\">' . $hesk_settings['webmaster_mail'] . '</a></p>');
            }
        }

        public function fetchAssoc($res) {

            return @mysql_fetch_assoc($res);
        }

        public function fetchRow($res) {

            return @mysql_fetch_row($res);
        }

        public function result($res, $row = 0, $column = 0) {
            return @mysql_result($res, $row, $column);
        }

        public function insertID() {
            global $hesk_db_link;

            if ($lastid = @mysql_insert_id($hesk_db_link)) {
                return $lastid;
            }
        }

        public function freeResult($res) {
            return mysql_free_result($res);
        }

        public function numRows($res) {
            return @mysql_num_rows($res);
        }

        public function affectedRows() {
            global $hesk_db_link;

            return @mysql_affected_rows($hesk_db_link);
        }
    }
}