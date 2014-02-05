<?php

/*******************************************************************************
*  Title:   HelpBase
*  Version: 1.2.1 from January 17, 2014
*  Author:  C. Kevin Provance
*  Website: http://www.tpasoft.com
********************************************************************************
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2013-2014 C. Kevin Provance. All Rights Reserved.

*  The HelpBase software may be used and modified free of charge by anyone
*  AS LONG AS COPYRIGHT NOTICES AND ALL THE COMMENTS REMAIN INTACT.
*  By using this code you agree to indemnify C. Kevin Provance from any
*  liability that might arise from it's use.

*  Selling the code for this program, in part or full, without prior
*  written consent is expressly forbidden.

*  Using this code, in part or full, to create derivate work,
*  new scripts or products is expressly forbidden. Obtain permission
*  before redistributing this software over the Internet or in
*  any other medium. In all cases copyright and header must remain intact.
*  This Copyright is in full effect in any country that has International
*  Trade Agreements with the United States of America or
*  with the European Union.

*  Removing any of the copyright notices without purchasing a license
*  is expressly forbidden. To remove the HelpBase copyright notice you must
*  purchase a license for this script. For more information on how to obtain
*  a license please visit the page below:
*  https://www.tpasoft.com/helpbase/buy.php
*******************************************************************************/

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

if (!class_exists('HelpbaseCore')){

    class HelpbaseCore {
        private $is_admin           = '';

        public $version             = '1.2.1';
        public $dir                 = '';
        public $includes            = '';
        public $url                 = '';
        public $img                 = '';
        public $encoding            = '';
        public $collate             = '';
        public $database            = null;
        public $common              = null;
        public $email               = null;
        public $kb                  = null;
        public $users_online        = null;
        public $posting             = null;
        public $ssl                 = false;
        public $no_robots           = false;
        public $load_calander       = false;
        public $main_page           = false;
        public $load_tabs           = false;
        public $demo_mode           = false;
        public $timer               = false;
        public $user                = '';
        public $wysiwyg             = false;
        public $autofocus           = false;
        public $show_online         = false;
        public $article_attach      = false;

        public function __construct($admin = false) {
            global $hesk_settings;

            $this->ssl      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
            $this->dir      = str_replace( '\\', '/', dirname( __FILE__ ) ) . '/';
            $this->includes = $this->dir . 'inc/';
            $this->img      = $this->dir . 'img/';
            $this->lang     = $this->dir . 'locale/';
            $this->is_admin = $admin;
            $this->encoding = 'UTF-8';
            $this->collate  = 'utf8_unicode_ci';

            // Load database settings from config
            include_once ($this->dir . 'helpbase_config.php');
            
            $hesk_settings = array();
            
            $hesk_settings['db_host'] = $database['db_host'];
            $hesk_settings['db_name'] = $database['db_name'];
            $hesk_settings['db_user'] = $database['db_user'];
            $hesk_settings['db_pass'] = $database['db_pass'];
            $hesk_settings['db_pfix'] = $database['db_pre'];
            $hesk_settings['db_vrsn'] = $database['db_ver'];
            
            $hesk_settings['language']  = $locale['language'];
            $hesk_settings['languages'] = $locale['languages'];
            //print_r($hesk_settings['languages']);
            //$a = $hesk_settings['languages'];
            //echo count($a);
            //foreach ($hesk_settings['languages'] as $key => $value) {
            //    echo '\'' . $key . '\' => \'' . $value . '\',';
            //}

            $this->init();
        }

        public function __destruct() {
            $this->common->database->close();

            unset ($this->common);
            unset ($this->database);
            unset ($this->admin);
            unset ($this->admin_nav);
            unset ($this->footer);
            unset ($this->header);
            unset ($this->users_online);
            unset ($this->kb);
        }

        public function init(){
            global $hesk_settings;
            // This load order is important, so don't mess with it...or else!

            // Load database functions
            $this->load_db_functions();

            // Load settings
            //include_once($this->dir . 'hesk_settings.inc.php');
            $this->load_settings();

            // Set site URL
            $this->url = $hesk_settings['hesk_url'] . '/';

            // Load admin functions, if required
            if ( true == $this->is_admin ) {
                include_once($this->includes . 'admin_functions.inc.php');
                $this->admin = new HelpBaseAdmin($this);

                include_once($this->includes . 'show_admin_nav.inc.php');
                $this->admin_nav = new HelpbaseAdminNav($this);
            }

            // Load header function
            include_once($this->includes . 'header.inc.php');
            $this->header = new HelpbaseHeader($this);

            // Load footer function
            include_once($this->includes . 'footer.inc.php');
            $this->footer = new HelpbaseFooter($this);

            // Load common functions
            include_once($this->includes . 'common.inc.php');
            $this->common = new HelpbaseCommon($this);

            // Begin session
            $this->common->session_start();
        }

        public function load_email_functions() {
            include_once($this->includes . 'email_functions.inc.php');
            $this->email = new HelpbaseEmail($this);
        }

        public function load_kb_functions() {
            include_once($this->includes . 'knowledgebase_functions.inc.php');
            $this->kb = new HelpbaseKbInc($this);
        }

        public function load_posting_functions(){
            include_once($this->includes . 'posting_functions.inc.php');
            $this->posting = new HelpbasePosting($this);
        }

        public function load_db_functions() {
            if (function_exists('mysqli_connect')) {
                // Preferrably use the MySQLi functions
                require_once($this->includes . 'database_mysqli.inc.php');
            } else {
                // Default to MySQL
                require_once($this->includes . 'database.inc.php');
            }

            $this->database = new HelpBaseDB($this);
            $this->database->connect();
        }

        private function load_settings() {
            global $hesk_settings;
            
            $row = array();

            $res = $this->database->query('SELECT * FROM `' . $this->database->escape($hesk_settings['db_pfix']) . "options` WHERE `key`='hb_data'");
            $row = $this->database->fetchAssoc($res);
            $row = unserialize($row['settings']);

            //if ($row['db_pfix'] !== $hesk_settings['db_pfix']) {
            //    $hesk_settings['db_pfix'] = $row['db_pfix'];
            //}
            
            $hesk_settings = array_merge((array)$hesk_settings, (array)$row);

            if ($hesk_settings['debug_mode']) {
                error_reporting(E_ALL);
            } else {
                error_reporting(0);
            }
        }

        public function save_settings($data) {
            global $hesk_settings;

            // Serialize settings
            $data = serialize($data);

            // Check for active connection
            if (!$this->database->is_connected) {
                $this->database->connect();
            }

            // Save options to database
            $res = $this->database->query('UPDATE `' . $this->database->escape($hesk_settings['db_pfix']) . "options` SET `settings`='" . $this->database->escape($data) . "' WHERE `key`='hb_data' LIMIT 1");
            
            // Save new settings to helpbase.config.php
            $output = '
<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Database config 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */                    

//' . time() . '

if (!defined(\'EXECUTING\')) {
    exit();
}

$database[\'db_host\']    = \'' . $hesk_settings['db_host'] . '\';
$database[\'db_name\']    = \'' . $hesk_settings['db_name'] . '\';
$database[\'db_user\']    = \'' . $hesk_settings['db_user'] . '\';
$database[\'db_pass\']    = \'' . $hesk_settings['db_pass'] . '\';
$database[\'db_pre\']     = \'' . $hesk_settings['db_pfix'] . '\';
$database[\'db_ver\']     = ' . $hesk_settings['db_vrsn'] . ';

$locale[\'language\']     = \'' . $hesk_settings['language'] . '\';
$locale[\'languages\']    = array(';
       
            foreach ($hesk_settings['languages'] as $key => $value) {
                $output .= '
    \'' . $key . '\' => \'' . $value . '\',';
            }
            
            $output .= '
);

?>';
            
            if ( ! file_put_contents($this->dir . 'helpbase_config.php', $output) ){
                $this->common->_error(_('Can\'t open file <b>ehlpbase_config.php</b> for writing. Please CHMOD this file to 666 (rw-rw-rw-)'));
            }            
        }
    }
}
