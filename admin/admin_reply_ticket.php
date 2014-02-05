<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Reply Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseAdminReplyTicket')) {
    class HelpbaseAdminReplyTicket {
        private $helpbase = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->load_email_functions();
            $helpbase->load_posting_functions();
            
            $this->render();
        }
        
        private function render(){
            global $hesk_settings;
            
            // We only allow POST requests from the HESK form to this file
            if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                header('Location: admin_main.php');
                exit();
            }

            // Check for POST requests larger than what the server can handle
            if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
                $this->helpbase->common->_error(_('You probably tried to submit more data than this server accepts.<br /><br />Please try submitting the form again with smaller or no attachments.'));
            }

            $this->helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $this->helpbase->admin->checkPermission('can_reply_tickets');

            /* A security check */
            # $this->helpbase->common->token_check('POST');

            /* Original ticket ID */
            $replyto = intval($this->helpbase->common->_post('orig_id', 0)) or die(_('Internal script error'));

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
                    
            /* Get details about the original ticket */
            $result = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "tickets` WHERE `id`='{$replyto}' LIMIT 1");
            if ($this->helpbase->database->numRows($result) != 1) {
                $this->helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
            }
            $ticket = $this->helpbase->database->fetchAssoc($result);
            $trackingID = $ticket['trackid'];

            $hesk_error_buffer = array();

            // Get the message
            $message = $this->helpbase->common->_input($this->helpbase->common->_post('message'));

            if (strlen($message)) {
                // Attach signature to the message?
                if (!empty($_POST['signature'])) {
                    $message .= "\n\n" . addslashes($_SESSION['signature']) . "\n";
                }

                // Make links clickable
                $message = $this->helpbase->common->makeURL($message);

                // Turn newlines into <br /> tags
                $message = nl2br($message);
            } else {
                $hesk_error_buffer[] = _('Please enter your message');
            }

            /* Attachments */
            if ($hesk_settings['attachments']['use']) {
                require($this->helpbase->includes . 'attachments.inc.php');
                $attachments = array();
                for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                    $att = hesk_uploadFile($i);
                    if ($att !== false && !empty($att)) {
                        $attachments[$i] = $att;
                    }
                }
            }
            $myattachments = '';

            /* Time spent working on ticket */
            $time_worked = $this->helpbase->admin->getTime($this->helpbase->common->_post('time_worked'));

            /* Any errors? */
            if (count($hesk_error_buffer) != 0) {
                $_SESSION['ticket_message'] = $this->helpbase->common->_post('message');
                $_SESSION['time_worked'] = $time_worked;

                // Remove any successfully uploaded attachments
                if ($hesk_settings['attachments']['use']) {
                    hesk_removeAttachments($attachments);
                }

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                $hesk_error_buffer = _('Please correct the following errors:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'admin_ticket.php?track=' . $ticket['trackid'] . '&refresh=' . rand(10000, 99999));
            }

            if ($hesk_settings['attachments']['use'] && !empty($attachments)) {
                foreach ($attachments as $myatt) {
                    $this->helpbase->database->query("INSERT INTO `" . $prefix . "attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('" . $this->helpbase->database->escape($trackingID) . "','" . $this->helpbase->database->escape($myatt['saved_name']) . "','" . $this->helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
                    $myattachments .= $this->helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
                }
            }

            /* Add reply */
            $result = $this->helpbase->database->query("INSERT INTO `" . $prefix . "replies` (`replyto`,`name`,`message`,`dt`,`attachments`,`staffid`) VALUES ('" . intval($replyto) . "','" . $this->helpbase->database->escape(addslashes($_SESSION['name'])) . "','" . $this->helpbase->database->escape($message) . "',NOW(),'" . $this->helpbase->database->escape($myattachments) . "','" . intval($_SESSION['id']) . "')");

            /* Track ticket status changes for history */
            $revision = '';

            /* Change the status of priority? */
            if (!empty($_POST['set_priority'])) {
                $priority = intval($this->helpbase->common->_post('priority'));
                if ($priority < 0 || $priority > 3) {
                    $this->helpbase->common->_error(_('Please select priority'));
                }

                $options = array(
                    0 => '<font class="critical">' . _(' * Critical * ') . '</font>',
                    1 => '<font class="important">' . _('High') . '</font>',
                    2 => '<font class="medium">' . _('Medium') . '</font>',
                    3 => _('Low')
                );

                $revision = sprintf(_('<li class="smaller">%s | priority changed to %s by %s</li>'), $this->helpbase->common->_date(), $options[$priority], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

                $priority_sql = ",`priority`='$priority', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') ";
            } else {
                $priority_sql = "";
            }

            /* Update the original ticket */
            $new_status = empty($_POST['close']) ? 2 : 3;

            /* --> If a ticket is locked keep it closed */
            if ($ticket['locked']) {
                $new_status = 3;
            }

            $sql = "UPDATE `" . $prefix . "tickets` SET `status`='{$new_status}', `lastreplier`='1', `replierid`='" . intval($_SESSION['id']) . "' ";

            /* Update time_worked or force update lastchange */
            if ($time_worked == '00:00:00') {
                $sql .= ", `lastchange` = NOW() ";
            } else {
                $sql .= ",`time_worked` = ADDTIME(`time_worked`,'" . $this->helpbase->database->escape($time_worked) . "') ";
            }

            if (!empty($_POST['assign_self']) && $this->helpbase->admin->checkPermission('can_assign_self', 0)) {
                $revision = sprintf(_('<li class="smaller">%s | assigned to %s by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')', $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                $sql .= " , `owner`=" . intval($_SESSION['id']) . ", `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') ";
            }

            $sql .= " $priority_sql ";

            if ($new_status == 3) {
                $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                $sql .= " , `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') ";

                if ($hesk_settings['custopen'] != 1) {
                    $sql .= " , `locked`='1' ";
                }
            }
            $sql .= " WHERE `id`='{$replyto}' LIMIT 1";
            $this->helpbase->database->query($sql);
            unset($sql);

            /* Update number of replies in the users table */
            $this->helpbase->database->query("UPDATE `" . $prefix . "users` SET `replies`=`replies`+1 WHERE `id`='" . intval($_SESSION['id']) . "' LIMIT 1");

            // --> Prepare reply message
            // 1. Generate the array with ticket info that can be used in emails
            $info = array(
                'email'         => $ticket['email'],
                'category'      => $ticket['category'],
                'priority'      => $ticket['priority'],
                'owner'         => $ticket['owner'],
                'trackid'       => $ticket['trackid'],
                'status'        => $new_status,
                'name'          => $ticket['name'],
                'company'       => $ticket['company'],
                'homephone'     => $ticket['homephone'],
                'mobilephone'   => $ticket['mobilephone'],
                'devicetype'    => $ticket['devicetype'],
                'devicebrand'   => $ticket['devicebrand'],
                'deviceid'      => $ticket['deviceid'],
                'workphone'     => $ticket['workphone'],
                'lastreplier'   => $_SESSION['name'],
                'subject'       => $ticket['subject'],
                'message'       => stripslashes($message),
                'attachments'   => $myattachments,
                'dt'            => $this->helpbase->common->_date($ticket['dt']),
                'lastchange'    => $this->helpbase->common->_date($ticket['lastchange']),
            );

            // 2. Add custom fields to the array
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                $info[$k] = $v['use'] ? $ticket[$k] : '';
            }

            // 3. Make sure all values are properly formatted for email
            $ticket = $this->helpbase->common->ticketToPlain($info, 1, 0);

            // Notify the customer
            if (!isset($_POST['no_notify']) || intval($this->helpbase->common->_post('no_notify')) != 1) {
                $this->helpbase->email->notifyCustomer('new_reply_by_staff');
            }

            /* Set reply submitted message */
            $_SESSION['HESK_SUCCESS'] = TRUE;
            $_SESSION['HESK_MESSAGE'] = _('Reply submitted');
            if (!empty($_POST['close'])) {
                $_SESSION['HESK_MESSAGE'] .= '<br /><br />' . _('This ticket has been marked') . ' <span class="resolved">' . _('Closed') . '</span>';
            }

            /* What to do after reply? */
            if ($_SESSION['afterreply'] == 1) {
                header('Location: admin_main.php');
            } elseif ($_SESSION['afterreply'] == 2) {
                /* Get the next open ticket that needs a reply */
                $res = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "tickets` WHERE `owner` IN ('0','" . intval($_SESSION['id']) . "') AND " . $this->helpbase->admin->myCategories() . " AND `status` IN ('0','1') ORDER BY `owner` DESC, `priority` ASC LIMIT 1");

                if ($this->helpbase->database->numRows($res) == 1) {
                    $row = $this->helpbase->database->fetchAssoc($res);
                    $_SESSION['HESK_MESSAGE'] .= '<br /><br />' . _('Showing next ticket that needs your attention');
                    header('Location: admin_ticket.php?track=' . $row['trackid'] . '&refresh=' . rand(10000, 99999));
                } else {
                    header('Location: admin_main.php');
                }
            } else {
                header('Location: admin_ticket.php?track=' . $ticket['trackid'] . '&refresh=' . rand(10000, 99999));
            }

            unset ($this->helpbase);
            exit();            
        }
    }
    
    new HelpbaseAdminReplyTicket;
}

?>
