<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Test Connection
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
define('EXECUTING', true);

if (!class_exists('HelpbaseTextConnection')) {

    class HelpbaseTextConnection {
        private $helpbase   = null;
        private $setup      = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(false);
            $this->helpbase = $helpbase;
            
            require($helpbase->includes . 'setup_functions.inc.php');
            $this->setup = new HelpbaseSetup($helpbase);

            // Print header
            header('Content-Type: text/html; charset=' . $helpbase->encoding);

            // Demo mode?
            if (true == $helpbase->demo_mode) {
                $helpbase->common->show_notice(_('Sorry, this function has been disabled in DEMO mode!'));
                $this->closeShop();
            }
            $this->runTests();
        }

        private function runTests(){

            // Test type?
            $test_type = $this->helpbase->common->_post('test');

            // Test MySQL connection
            if ($test_type == 'mysql') {
                if ($this->setup->testMySQL()) {
                    $this->helpbase->common->show_success(_('Connection successful!'));
                } elseif (!empty($mysql_log)) {
                    $this->helpbase->common->show_error($mysql_error . '<br /><br /><b>' . _('MySQL said') . ':</b> ' . $mysql_log);
                } else {
                    $this->helpbase->common->show_error($mysql_error);
                }
            } elseif ($test_type == 'pop3') {
                // Test POP3 connection
                if ($this->setup->testPOP3()) {
                    $this->helpbase->common->show_success(_('Connection successful!'));
                } else {
                    $this->helpbase->common->show_error($pop3_error . '<br /><br /><textarea name="pop3_log" rows="10" cols="60">' . $pop3_log . '</textarea>');
                }
            } elseif ($test_type == 'smtp') {
                // Test SMTP connection
                if ($this->setup->testSMTP()) {
                    // If no username/password add a notice
                    if ($set['smtp_user'] == '' && $set['smtp_user'] == '') {
                        $errMsg = _('Connection successful!') . '<br /><br />' . _('However, if your server requires a username and password, the email will not be sent!');
                    }

                    $this->helpbase->common->show_success($errMsg);
                } else {
                    $this->helpbase->common->show_error($smtp_error . '<br /><br /><textarea name="smtp_log" rows="10" cols="60">' . $smtp_log . '</textarea>');
                }
            } else {
                // Not a valid test...
                $this->closeShop();
            }
            $this->closeShop();
        }
        
        private function closeShop(){
            unset($this->setup);
            unset($this->helpbase);

            exit();                                    
        } 
    }
    new HelpbaseTextConnection;
}

?>
