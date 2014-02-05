<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Delete Tickets
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if(!class_exists('HelpbaseDeleteTickets')) {
    class HelpbaseDeleteTickets {
        private $helpbase   = null;
        private $ticket     = array();
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();
            
            $this->delete_ticket();
            
            unset($helpbase);
        }
        
        private function delete_ticket(){
            global $hesk_settings;
            
            /* Set correct return URL */
            if (isset($_SERVER['HTTP_REFERER'])) {
                $url = $this->helpbase->common->_input($_SERVER['HTTP_REFERER']);
                $url = str_replace('&amp;', '&', $url);
                if ($tmp = strstr($url, 'show_tickets.php')) {
                    $referer = $tmp;
                } elseif ($tmp = strstr($url, 'find_tickets.php')) {
                    $referer = $tmp;
                } elseif ($tmp = strstr($url, 'admin_main.php')) {
                    $referer = $tmp;
                } else {
                    $referer = 'admin_main.php';
                }
            } else {
                $referer = 'admin_main.php';
            }

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            /* Is this a delete ticket request from within a ticket ("delete" icon)? */
            if (isset($_GET['delete_ticket'])) {
                /* Check permissions for this feature */
                $this->helpbase->admin->checkPermission('can_del_tickets');

                /* A security check */
                $this->helpbase->common->token_check();

                // Tracking ID
                $trackingID = $this->helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

                /* Get ticket info */
                $result = $this->helpbase->database->query("SELECT `id`,`trackid`,`category` FROM `" . $prefix . "tickets` WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");
                if ($this->helpbase->database->numRows($result) != 1) {
                    $this->helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
                }
                $this->ticket = $this->helpbase->database->fetchAssoc($result);

                /* Is this user allowed to delete tickets inside this category? */
                $this->helpbase->admin->okCategory($this->ticket['category']);

                $this->fullyDeleteTicket();

                $this->helpbase->common->process_messages(sprintf(_('<b>%d</b> tickets have been deleted'), 1), $referer, 'SUCCESS');
            }


            /* This is a request from ticket list. Must be POST and id must be an array */
            if (!isset($_POST['id']) || !is_array($_POST['id'])) {
                $this->helpbase->common->process_messages(_('No tickets selected, nothing to change'), $referer, 'NOTICE');
            }
            /* If not, then needs an action (a) POST variable set */ elseif (!isset($_POST['a'])) {
                $this->helpbase->common->process_messages(_('Invalid action'), $referer);
            }

            $i = 0;

            /* DELETE */
            if ($_POST['a'] == 'delete') {
                /* Check permissions for this feature */
                $this->helpbase->admin->checkPermission('can_del_tickets');

                /* A security check */
                $this->helpbase->common->token_check('POST');

                foreach ($_POST['id'] as $this_id) {
                    if (is_array($this_id)) {
                        continue;
                    }

                    $this_id = intval($this_id) or $this->helpbase->common->_error(_('This is not a valid ID'));
                    $result = $this->helpbase->database->query("SELECT `id`,`trackid`,`category` FROM `" . $prefix . "tickets` WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                    if ($this->helpbase->database->numRows($result) != 1) {
                        continue;
                    }
                    $this->ticket = $this->helpbase->database->fetchAssoc($result);

                    $this->helpbase->admin->okCategory($this->ticket['category']);

                    $this->fullyDeleteTicket();
                    $i++;
                }

                $this->helpbase->common->process_messages(sprintf(_('<b>%d</b> tickets have been deleted'), $i), $referer, 'SUCCESS');
            } elseif ($_POST['a'] == 'merge') {  /* MERGE TICKETS */ 
                /* Check permissions for this feature */
                $this->helpbase->admin->checkPermission('can_merge_tickets');

                /* A security check */
                $this->helpbase->common->token_check('POST');

                /* Sort IDs, tickets will be merged to the lowest ID */
                sort($_POST['id'], SORT_NUMERIC);

                /* Select lowest ID as the target ticket */
                $merge_into = array_shift($_POST['id']);

                /* Merge tickets or throw an error */
                if ($this->helpbase->admin->mergeTickets($_POST['id'], $merge_into)) {
                    $this->helpbase->common->process_messages(_('Selected tickets have been merged into one.'), $referer, 'SUCCESS');
                } else {
                    $errMsg = _('There was a problem merging tickets:') .= ' ' . $_SESSION['error'];

                    $this->helpbase->common->cleanSessionVars($_SESSION['error']);
                    $this->helpbase->common->process_messages($errMsg, $referer);
                }
            } elseif ($_POST['a'] == 'tag' || $_POST['a'] == 'untag') {  /* TAG/UNTAG TICKETS */
                /* Check permissions for this feature */
                $this->helpbase->admin->checkPermission('can_add_archive');

                /* A security check */
                $this->helpbase->common->token_check('POST');

                if ($_POST['a'] == 'tag') {
                    $archived = 1;
                    $action = _('<b>%d</b> tickets have been tagged');
                } else {
                    $archived = 0;
                    $action = _('<b>%d</b> tickets have been untagged');
                }

                foreach ($_POST['id'] as $this_id) {
                    if (is_array($this_id)) {
                        continue;
                    }

                    $this_id = intval($this_id) or $this->helpbase->common->_error(_('This is not a valid ID'));
                    $result = $this->helpbase->database->query("SELECT `id`,`trackid`,`category` FROM `" . $prefix . "tickets` WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                    if ($this->helpbase->database->numRows($result) != 1) {
                        continue;
                    }
                    $this->ticket = $this->helpbase->database->fetchAssoc($result);

                    $this->helpbase->admin->okCategory($this->ticket['category']);

                    $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `archive`='$archived' WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                    $i++;
                }

                $this->helpbase->common->process_messages(sprintf($action, $i), $referer, 'SUCCESS');
            } else { /* JUST CLOSE */ 
                /* Check permissions for this feature */
                $this->helpbase->admin->checkPermission('can_view_tickets');
                $this->helpbase->admin->checkPermission('can_reply_tickets');

                /* A security check */
                $this->helpbase->common->token_check('POST');

                $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

                foreach ($_POST['id'] as $this_id) {
                    if (is_array($this_id)) {
                        continue;
                    }

                    $this_id = intval($this_id) or $this->helpbase->common->_error(_('This is not a valid ID'));

                    $result = $this->helpbase->database->query("SELECT `category` FROM `" . $prefix . "tickets` WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                    $this->ticket = $this->helpbase->database->fetchAssoc($result);

                    $this->helpbase->admin->okCategory($this->ticket['category']);

                    $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `status`='3', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `id`='" . intval($this_id) . "' LIMIT 1");
                    $i++;
                }

                $this->helpbase->common->process_messages(sprintf(_('<b>%d</b> tickets have been closed'), $i), $referer, 'SUCCESS');

                unset ($this->helpbase);
            }            
        }
        
        function fullyDeleteTicket() {
            global $hesk_settings;

            $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $ticketID   = intval($this->ticket['id']);
            $trackID    = $this->helpbase->database->escape($this->ticket['trackid']);
            
            /* Delete attachment files */
            $res = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "attachments` WHERE `ticket_id`='" . $trackID . "'");
            if ($this->helpbase->database->numRows($res)) {
                $hesk_settings['server_path'] = dirname(dirname(__FILE__));

                while ($file = $this->helpbase->database->fetchAssoc($res)) {
                    $this->helpbase->common->unlink($hesk_settings['server_path'] . '/' . $hesk_settings['attach_dir'] . '/' . $file['saved_name']);
                }
            }

            /* Delete attachments info from the database */
            $this->helpbase->database->query("DELETE FROM `" . $prefix . "attachments` WHERE `ticket_id`='" . $trackID . "'");

            /* Delete the ticket */
            $this->helpbase->database->query("DELETE FROM `" . $prefix . "tickets` WHERE `id`='" . $ticketID . "'");

            /* Delete replies to the ticket */
            $this->helpbase->database->query("DELETE FROM `" . $prefix . "replies` WHERE `replyto`='" . $ticketID . "'");

            /* Delete ticket notes */
            $this->helpbase->database->query("DELETE FROM `" . $prefix . "notes` WHERE `ticket`='" . $ticketID . "'");

            return true;
        }
    }
    
    new HelpbaseDeleteTickets;
}

?>
