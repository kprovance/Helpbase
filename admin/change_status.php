<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Change Ticket Status
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseChangeStatus')){
    class HelpbaseChangeStatus {
        private $helpbase = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');
            $helpbase->admin->checkPermission('can_reply_tickets');

            /* A security check */
            $helpbase->common->token_check();  
            
            $this->change_status();
            
            unset($helpbase);
        }
        
        private function change_status() {
            global $hesk_settings;
            
            /* Ticket ID */
            $trackingID = $this->helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

            /* Valid statuses */
            $status_options = array(
                0 => _('New'),
                1 => _('Awaiting reply'),
                2 => _('Replied'),
                3 => _('Closed'),
                4 => _('On the bench'),
                5 => _('On hold'),
                6 => _('Waiting for payment'),
                7 => _('Waiting for bench'),
                8 => _('Service call'),
                9 => _('Remote support'),
                10 => _('Ready for pickup'),
            );

            /* New status */
            $status = intval($this->helpbase->common->_request('s'));
            if (!isset($status_options[$status])) {
                $this->helpbase->common->process_messages(_('Select the new Status'), 'admin_ticket.php?track=' . $trackingID . '&refresh=' . mt_rand(10000, 99999), 'NOTICE');
            }

            $locked = false;

            switch($status) {
                case 3:
                    $action = _('This ticket has been closed');
                    $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

                    if ($hesk_settings['custopen'] != 1) {
                        $locked = true;
                    }                    
                break;
                case 0:
                    $action = _('This ticket has been opened');
                    $revision = sprintf(_('<li class="smaller">%s | opened by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                break;
                case 1:
                case 2:
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:
                case 10:
                    $action = sprintf(_('Ticket status has been set to %s'), $status_options[$status]);
                    $revision = sprintf(_('<li class="smaller">%s | status changed to %s by %s</li>'), $this->helpbase->common->_date(), $status_options[$status], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
            }
            
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `status`='{$status}', `locked`='{$locked}', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");

            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Internal script error') . ': ' . _('Tracking ID not found'));
            }

            $this->helpbase->common->process_messages($action, 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999), 'SUCCESS');

        }
    }

    new HelpbaseChangeStatus;
}

?>
