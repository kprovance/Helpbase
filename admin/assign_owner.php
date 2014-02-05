<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Assign Ticket Owner
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseAssignOwner')) {
    class HelpbaseAssignOwner {
        private $helpbase   = null;
        private $ticket     = array();
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->load_email_functions();

            $helpbase->admin->isLoggedIn();
            
            $this->assign_owner();
        }
        
        private function assign_owner(){
            global $hesk_settings;
            
            $can_assign_others = $this->helpbase->admin->checkPermission('can_assign_others', 0);
            if ($can_assign_others) {
                $can_assign_self = TRUE;
            } else {
                $can_assign_self = $this->helpbase->admin->checkPermission('can_assign_self', 0);
            }

            /* A security check */
            $this->helpbase->common->token_check();

            /* Ticket ID */
            $trackingID = $this->helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            $res = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "tickets` WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($res) != 1) {
                $this->helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
            }
            $this->ticket = $this->helpbase->database->fetchAssoc($res);

            $_SERVER['PHP_SELF'] = 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999);

            /* New owner ID */
            $owner = intval($this->helpbase->common->_request('owner'));

            /* If ID is -1 the ticket will be unassigned */
            if ($owner == -1) {
                $revision = sprintf(_('<li class="smaller">%s | assigned to %s by %s</li>'), $this->helpbase->common->_date(), '<i>' . _('Unassigned') . '</i>', $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                $res = $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `owner`=0 , `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");

                $this->helpbase->common->process_messages(_('Ticket is without an owner and ready to be assigned again'), $_SERVER['PHP_SELF'], 'SUCCESS');
            } elseif ($owner < 1) {
                $this->helpbase->common->process_messages(_('Select the new Owner'), $_SERVER['PHP_SELF'], 'NOTICE');
            }

            /* Verify the new owner and permissions */
            $res = $this->helpbase->database->query("SELECT `id`,`user`,`name`,`email`,`isadmin`,`categories`,`notify_assigned` FROM `" . $prefix . "users` WHERE `id`='{$owner}' LIMIT 1");
            $row = $this->helpbase->database->fetchAssoc($res);

            /* Has new owner access to the category? */
            if (!$row['isadmin']) {
                $row['categories'] = explode(',', $row['categories']);
                if (!in_array($this->ticket['category'], $row['categories'])) {
                    $this->helpbase->common->_error(_('Selected user doesn\'t have access to this category'));
                }
            }

            /* Assigning to self? */
            if ($can_assign_others || ($owner == $_SESSION['id'] && $can_assign_self)) {
                $revision = sprintf(_('<li class="smaller">%s | assigned to %s by %s</li>'), $this->helpbase->common->_date(), $row['name'] . ' (' . $row['user'] . ')', $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                $res = $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `owner`={$owner} , `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");

                if ($owner != $_SESSION['id'] && !$this->helpbase->admin->checkPermission('can_view_ass_others', 0)) {
                    $_SERVER['PHP_SELF'] = 'admin_main.php';
                }
            } else {
                $this->helpbase->common->_error(_('You don\'t have permission to perform this task, please login with an account that has.'));
            }

            $this->ticket['owner'] = $owner;  
            
            $this->generate_email();
            
            $tmp = ($owner == $_SESSION['id']) ? _('This ticket has been assigned to you') : _('This ticket has been assigned to the selected user');
            $this->helpbase->common->process_messages($tmp, $_SERVER['PHP_SELF'], 'SUCCESS');

            unset($this->helpbase);
            
        }
        
        private function generate_email(){
            global $hesk_settings;
            
            /* --> Prepare message */

            // 1. Generate the array with ticket info that can be used in emails
            $info = array(
                'email'         => $this->ticket['email'],
                'category'      => $this->ticket['category'],
                'priority'      => $this->ticket['priority'],
                'owner'         => $this->ticket['owner'],
                'trackid'       => $this->ticket['trackid'],
                'status'        => $this->ticket['status'],
                'name'          => $this->ticket['name'],
                'company'       => $this->ticket['company'],
                'devicetype'    => $this->ticket['devicetype'],
                'devicebrand'   => $this->ticket['devicebrand'],
                'deviceid'      => $this->ticket['deviceid'],
                'homephone'     => $this->ticket['homephone'],
                'mobilephone'   => $this->ticket['mobilephone'],
                'workphone'     => $this->ticket['workphone'],
                'lastreplier'   => $this->ticket['lastreplier'],
                'subject'       => $this->ticket['subject'],
                'message'       => $this->ticket['message'],
                'attachments'   => $this->ticket['attachments'],
                'dt'            => $this->helpbase->common->_date($this->ticket['dt']),
                'lastchange'    => $this->helpbase->common->_date($this->ticket['lastchange']),
            );

            // 2. Add custom fields to the array
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                $info[$k] = $v['use'] ? $this->ticket[$k] : '';
            }

            // 3. Make sure all values are properly formatted for email
            $this->ticket = $this->helpbase->common->ticketToPlain($info, 1, 0);

            /* Notify the new owner? */
            if ($this->ticket['owner'] != intval($_SESSION['id'])) {
                $this->helpbase->email->notifyAssignedStaff(false, 'ticket_assigned_to_you');
            }            
        }
    }
    
    new HelpbaseAssignOwner;
}

?>
