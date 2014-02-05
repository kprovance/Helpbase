<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Lock Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseLock')) {
    class HelpbaseLock {
        
        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);

            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');
            $helpbase->admin->checkPermission('can_reply_tickets');
            $helpbase->admin->checkPermission('can_edit_tickets');

            /* A security check */
            $helpbase->common->token_check();

            /* Ticket ID */
            $trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

            /* New archived status */
            if (empty($_GET['locked']))
            {
                    $status = 0;
                    $tmp = _('Ticket has been unlocked');                                                                      
                $revision = sprintf(_('<li class="smaller">%s | unlocked by %s</li>'),$helpbase->common->_date(),$_SESSION['name'].' ('.$_SESSION['user'].')');
            }
            else
            {
                    $status = 1;
                    $tmp = _('Ticket has been locked');
                $revision = sprintf(_('<li class="smaller">%s | locked by %s</li>'),$helpbase->common->_date(),$_SESSION['name'].' ('.$_SESSION['user'].')');
            }

            /* Update database */
            $helpbase->database->query("UPDATE `".$helpbase->database->escape($hesk_settings['db_pfix'])."tickets` SET `status`='3',`locked`='{$status}', `history`=CONCAT(`history`,'".$helpbase->database->escape($revision)."')  WHERE `trackid`='".$helpbase->database->escape($trackingID)."' LIMIT 1");

            /* Back to ticket page and show a success message */
            $helpbase->common->process_messages($tmp,'admin_ticket.php?track='.$trackingID.'&refresh='.rand(10000,99999),'SUCCESS');

            unset($helpbase);
        }
    }
    
    new HelpbaseLock;
}

?>
