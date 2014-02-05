<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Archive
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
define('EXECUTING', true);

if (!class_exists('HelpbaseAdminArchive')) {

    class HelpbaseAdminArchive {

        private $helpbase = null;

        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');
            $helpbase->admin->checkPermission('can_add_archive');

            /* A security check */
            $helpbase->common->token_check();

            /* Ticket ID */
            $trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

            /* New archived status */
            if (empty($_GET['archived'])) {
                $status = 0;
                $tmp = _('Ticket has been untagged');
            } else {
                $status = 1;
                $tmp = _('Ticket has been tagged');
            }

            /* Update database */
            $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `archive`='$status' WHERE `trackid`='" . $helpbase->database->escape($trackingID) . "' LIMIT 1");

            /* Back to ticket page and show a success message */
            $helpbase->common->process_messages($tmp, 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999), 'SUCCESS');

            unset($helpbase);            
        }

    }

    new HelpbaseAdminArchive;
}

?>
