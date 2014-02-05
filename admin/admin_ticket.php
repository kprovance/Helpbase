<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseAdminTicket')) {
    class HelpbaseAdminTicket {
        private $helpbase           = null;
        private $ticket             = '';
        private $reply              = array();
        private $trackingID         = '';
        private $can_edit           = false;
        private $can_delete         = false;
        private $can_archive        = false;
        private $can_reply          = false;
        private $can_options        = '';
        private $can_assign_self    = false;
        private $can_change_cat     = false;
        private $can_del_notes      = false;
        private $admins             = array();
        private $options            = array();
        private $prefix             = '';
        private $id                 = 0;
        private $category           = array();
        private $ticketID           = '';
        
        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $this->helpbase->admin->checkPermission('can_view_tickets');
            
            $this->can_del_notes    = $this->helpbase->admin->checkPermission('can_del_notes', 0);
            $this->can_reply        = $this->helpbase->admin->checkPermission('can_reply_tickets', 0);
            $this->can_delete       = $this->helpbase->admin->checkPermission('can_del_tickets', 0);
            $this->can_edit         = $this->helpbase->admin->checkPermission('can_edit_tickets', 0);
            $this->can_archive      = $this->helpbase->admin->checkPermission('can_add_archive', 0);
            $this->can_assign_self  = $this->helpbase->admin->checkPermission('can_assign_self', 0);
            $can_view_unassigned    = $this->helpbase->admin->checkPermission('can_view_unassigned', 0);
            $this->can_change_cat   = $this->helpbase->admin->checkPermission('can_change_cat', 0);

            // Get ticket ID
            $this->trackingID = $this->helpbase->common->cleanID() or $this->print_form();

            $_SERVER['PHP_SELF'] = 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999);

            /* We will need timer function */
            $this->helpbase->timer = true;

            $this->prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $this->id = $this->helpbase->database->escape($this->trackingID);

            /* Get ticket info */
            $res = $this->helpbase->database->query("SELECT `t1`.* , `t2`.name AS `repliername` FROM `" . $this->prefix . "tickets` AS `t1` LEFT JOIN `" . $this->prefix . "users` AS `t2` ON `t1`.`replierid` = `t2`.`id` WHERE `trackid`='" . $this->id . "' LIMIT 1");

            /* Ticket found? */
            if ($this->helpbase->database->numRows($res) != 1) {
                /* Ticket not found, perhaps it was merged with another ticket? */
                $res = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "tickets` WHERE `merged` LIKE '%#" . $this->id . "#%' LIMIT 1");

                if ($this->helpbase->database->numRows($res) == 1) {
                    /* OK, found in a merged ticket. Get info */
                    $this->ticket = $this->helpbase->database->fetchAssoc($res);
                    $this->helpbase->common->process_messages(sprintf(_('Ticket %s has been merged with this ticket (%s).'), $this->trackingID, $this->ticket['trackid']), 'NOREDIRECT', 'NOTICE');
                    $this->trackingID = $this->ticket['trackid'];
                } else {
                    /* Nothing found, error out */
                    $this->helpbase->common->process_messages(_('Ticket not found! Please make sure you have entered the correct tracking ID!'), 'NOREDIRECT');
                    $this->print_form();
                }
            } else {
                /* We have a match, get ticket info */
                $this->ticket = $this->helpbase->database->fetchAssoc($res);
            }

            /* Permission to view this ticket? */
            if ($this->ticket['owner'] && $this->ticket['owner'] != $_SESSION['id'] && !$this->helpbase->admin->checkPermission('can_view_ass_others', 0)) {
                $this->helpbase->common->_error(_('You are not allowed to view tickets assigned to others'));
            }

            if (!$this->ticket['owner'] && !$can_view_unassigned) {
                $this->helpbase->common->_error(_('You can only view tickets assigned to you'));
            }

            /* Set last replier name */
            if ($this->ticket['lastreplier']) {
                if (empty($this->ticket['repliername'])) {
                    $this->ticket['repliername'] = _('Staff');
                }
            } else {
                $this->ticket['repliername'] = $this->ticket['name'];
            }

            /* Get category name and ID */
            $result = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "categories` WHERE `id`='" . intval($this->ticket['category']) . "' LIMIT 1");

            /* If this category has been deleted use the default category with ID 1 */
            if ($this->helpbase->database->numRows($result) != 1) {
                $result = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "categories` WHERE `id`='1' LIMIT 1");
            }

            $this->category = $this->helpbase->database->fetchAssoc($result);

            /* Is this user allowed to view tickets inside this category? */
            $this->helpbase->admin->okCategory($this->category['id']);

            $this->ticketID = intval($this->ticket['id']);            
            
            $this->do_post_action();
            $this->render();
        }
        
        private function do_post_action(){
            /* Delete post action */
            if (isset($_GET['delete_post']) && $this->can_delete && $this->helpbase->common->token_check()) {
                $n = intval($this->helpbase->common->_get('delete_post'));
                if ($n) {
                    /* Get last reply ID, we'll need it later */
                    $res = $this->helpbase->database->query("SELECT `id` FROM `" . $this->prefix . "replies` WHERE `replyto`='" . $this->ticketID . "' ORDER BY `id` DESC LIMIT 1");
                    $last_reply_id = $this->helpbase->database->result($res, 0, 0);

                    /* Does this post have any attachments? */
                    $res = $this->helpbase->database->query("SELECT `attachments` FROM `" . $this->prefix . "replies` WHERE `id`='" . intval($n) . "' AND `replyto`='" . $this->ticketID . "' LIMIT 1");
                    $attachments = $this->helpbase->database->result($res, 0, 0);

                    /* Delete any attachments to this post */
                    if (strlen($attachments)) {
                        $hesk_settings['server_path'] = dirname(dirname(__FILE__));

                        /* List of attachments */
                        $att = explode(',', substr($attachments, 0, -1));
                        foreach ($att as $myatt) {
                            list($att_id, $att_name) = explode('#', $myatt);

                            /* Delete attachment files */
                            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "attachments` WHERE `att_id`='" . intval($att_id) . "' LIMIT 1");
                            if ($this->helpbase->database->numRows($res) && $file = $this->helpbase->database->fetchAssoc($res)) {
                                $this->helpbase->common->unlink($hesk_settings['server_path'] . '/' . $hesk_settings['attach_dir'] . '/' . $file['saved_name']);
                            }

                            /* Delete attachments info from the database */
                            $this->helpbase->database->query("DELETE FROM `" . $this->prefix . "attachments` WHERE `att_id`='" . intval($att_id) . "' LIMIT 1");
                        }
                    }

                    /* Delete this reply */
                    $this->helpbase->database->query("DELETE FROM `" . $this->prefix . "replies` WHERE `id`='" . intval($n) . "' AND `replyto`='" . $this->ticketID . "' LIMIT 1");

                    /* Reply wasn't deleted */
                    if ($this->helpbase->database->affectedRows() != 1) {
                        $this->helpbase->common->process_messages(_('This post doesn\'t exist'), $_SERVER['PHP_SELF']);
                    } else {
                        /* Reply deleted. Need to update status and last replier? */
                        $res = $this->helpbase->database->query("SELECT `staffid` FROM `" . $this->prefix . "replies` WHERE `replyto`='" . $this->ticketID . "' ORDER BY `id` DESC LIMIT 1");
                        if ($this->helpbase->database->numRows($res)) {
                            $replier_id = $this->helpbase->database->result($res, 0, 0);
                            $last_replier = $replier_id ? 1 : 0;

                            /* Change status? */
                            $status_sql = '';
                            if ($last_reply_id == $n) {
                                $status = $this->ticket['locked'] ? 3 : ($last_replier ? 2 : 1);
                                $status_sql = " , `status`='" . intval($status) . "' ";
                            }

                            $this->helpbase->database->query("UPDATE `" . $this->prefix . "tickets` SET `lastchange`=NOW(), `lastreplier`='{$last_replier}', `replierid`='" . intval($replier_id) . "' $status_sql WHERE `id`='" . $this->ticketID . "' LIMIT 1");
                        } else {
                            $status = $this->ticket['locked'] ? 3 : 0;
                            $this->helpbase->database->query("UPDATE `" . $this->prefix . "tickets` SET `lastchange`=NOW(), `lastreplier`='0', `status`='$status' WHERE `id`='" . $this->ticketID . "' LIMIT 1");
                        }

                        $this->helpbase->common->process_messages(_('Selected post has been deleted'), $_SERVER['PHP_SELF'], 'SUCCESS');
                    }
                } else {
                    $this->helpbase->common->process_messages(_('Insufficient permissions to perform this task'), $_SERVER['PHP_SELF']);
                }
            }

            /* Delete notes action */
            if (isset($_GET['delnote']) && $this->helpbase->common->token_check()) {
                $n = intval($this->helpbase->common->_get('delnote'));
                if ($n) {
                    if ($this->can_del_notes) {
                        $this->helpbase->database->query("DELETE FROM `" . $this->prefix . "notes` WHERE `id`='" . intval($n) . "' LIMIT 1");
                    } else {
                        $this->helpbase->database->query("DELETE FROM `" . $this->prefix . "notes` WHERE `id`='" . intval($n) . "' AND `who`='" . intval($_SESSION['id']) . "' LIMIT 1");
                    }
                }
                header('Location: admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999));
                exit();
            }

            /* Add a note action */
            if (isset($_POST['notemsg']) && $this->helpbase->common->token_check('POST')) {
                $msg = $this->helpbase->common->_input($this->helpbase->common->_post('notemsg'));

                if ($msg) {
                    /* Add note to database */
                    $msg = nl2br($this->helpbase->common->makeURL($msg));
                    $this->helpbase->database->query("INSERT INTO `" . $this->prefix . "notes` (`ticket`,`who`,`dt`,`message`) VALUES ('" . $this->ticketID . "','" . intval($_SESSION['id']) . "',NOW(),'" . $this->helpbase->database->escape($msg) . "')");

                    /* Notify assigned staff that a note has been added if needed */
                    if ($this->ticket['owner'] && $this->ticket['owner'] != $_SESSION['id']) {
                        $res = $this->helpbase->database->query("SELECT `email`, `notify_note` FROM `" . $this->prefix . "users` WHERE `id`='" . intval($this->ticket['owner']) . "' LIMIT 1");

                        if ($this->helpbase->database->numRows($res) == 1) {
                            $owner = $this->helpbase->database->fetchAssoc($res);

                            // 1. Generate the array with ticket info that can be used in emails
                            $info = array(
                                'email'         => $this->ticket['email'],
                                'company'       => $this->ticket['company'],
                                'devicetype'    => $this->ticket['devicetype'],
                                'devicebrand'   => $this->ticket['devicebrand'],
                                'deviceid'      => $this->ticket['deviceid'],
                                'homephone'     => $this->ticket['homephone'],
                                'mobilephone'   => $this->ticket['mobilephone'],
                                'workphone'     => $this->ticket['workphone'],
                                'category'      => $this->ticket['category'],
                                'priority'      => $this->ticket['priority'],
                                'owner'         => $this->ticket['owner'],
                                'trackid'       => $this->ticket['trackid'],
                                'status'        => $this->ticket['status'],
                                'name'          => $_SESSION['name'],
                                'lastreplier'   => $this->ticket['lastreplier'],
                                'subject'       => $this->ticket['subject'],
                                'message'       => stripslashes($msg),
                            );

                            // 2. Add custom fields to the array
                            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                                $info[$k] = $v['use'] ? $this->ticket[$k] : '';
                            }

                            // 3. Make sure all values are properly formatted for email
                            $this->ticket = $this->helpbase->common->ticketToPlain($info, 1, 0);

                            /* Get email functions */
                            $this->helpbase->load_email_functions();

                            /* Format email subject and message for staff */
                            $subject = $this->helpbase->email->getEmailSubject('new_note', $this->ticket);
                            $message = $this->helpbase->email->getEmailMessage('new_note', $this->ticket, 1);

                            /* Send email to staff */
                            $this->helpbase->email->mail($owner['email'], $subject, $message);
                        }
                    }
                }
                header('Location: admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999));
                exit();
            }

            /* Update time worked */
            if (($this->can_reply || $this->can_edit) && isset($_POST['h']) && isset($_POST['m']) && isset($_POST['s']) && $this->helpbase->common->token_check('POST')) {
                $h = intval($this->helpbase->common->_post('h'));
                $m = intval($this->helpbase->common->_post('m'));
                $s = intval($this->helpbase->common->_post('s'));

                /* Get time worked in proper format */
                $time_worked = $this->helpbase->admin->getTime($h . ':' . $m . ':' . $s);

                /* Update database */
                $revision = sprintf(_('<li class="smaller">%s | time worked updated to %s by %s</li>'), $this->helpbase->common->_date(), $time_worked, $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                $this->helpbase->database->query("UPDATE `" . $this->prefix . "tickets` SET `time_worked`='" . $this->helpbase->database->escape($time_worked) . "', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `trackid`='" . $this->id . "' LIMIT 1");

                /* Show ticket */
                $this->helpbase->common->process_messages(_('Time worked on ticket has been updated.'), 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999), 'SUCCESS');
            }

            /* Delete attachment action */
            if (isset($_GET['delatt']) && $this->helpbase->common->token_check()) {
                if (!$this->can_delete || !$this->can_edit) {
                    $this->helpbase->common->process_messages(_('You don\'t have permission to perform this task, please login with an account that has.'), 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999));
                }

                $att_id = intval($this->helpbase->common->_get('delatt')) or $this->helpbase->common->_error(_('Invalid attachment ID!'));

                $reply = intval($this->helpbase->common->_get('reply', 0));
                if ($reply < 1) {
                    $reply = 0;
                }

                /* Get attachment info */
                $res = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "attachments` WHERE `att_id`='" . intval($att_id) . "' LIMIT 1");
                if ($this->helpbase->database->numRows($res) != 1) {
                    $this->helpbase->common->process_messages(_('This is not a valid ID') . ' (att_id)', 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999));
                }
                $att = $this->helpbase->database->fetchAssoc($res);

                /* Is ticket ID valid for this attachment? */
                if ($att['ticket_id'] != $this->trackingID) {
                    $this->helpbase->common->process_messages(_('Tracking ID not found'), 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999));
                }

                /* Delete file from server */
                $this->helpbase->common->unlink($this->helpbase->dir . $hesk_settings['attach_dir'] . '/' . $att['saved_name']);

                /* Delete attachment from database */
                $this->helpbase->database->query("DELETE FROM `" . $this->prefix . "attachments` WHERE `att_id`='" . intval($att_id) . "'");

                /* Update ticket or reply in the database */
                $revision = sprintf(_('<li class="smaller">%s | attachment %s deleted by %s</li>'), $this->helpbase->common->_date(), $att['real_name'], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');
                if ($reply) {
                    $this->helpbase->database->query("UPDATE `" . $this->prefix . "replies` SET `attachments`=REPLACE(`attachments`,'" . $this->helpbase->database->escape($att_id . '#' . $att['real_name']) . ",','') WHERE `id`='" . intval($reply) . "' LIMIT 1");
                    $this->helpbase->database->query("UPDATE `" . $this->prefix . "tickets` SET `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `id`='" . $this->ticketID . "' LIMIT 1");
                } else {
                    $this->helpbase->database->query("UPDATE `" . $this->prefix . "tickets` SET `attachments`=REPLACE(`attachments`,'" . $this->helpbase->database->escape($att_id . '#' . $att['real_name']) . ",',''), `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `id`='" . $this->ticketID . "' LIMIT 1");
                }

                $this->helpbase->common->process_messages(_('Selected attachment has been removed'), 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999), 'SUCCESS');
            }            
        }
        
        private function render(){
            global $hesk_settings;

            /* Print header */
            $this->helpbase->header->render();

            /* List of categories */
            $result = $this->helpbase->database->query("SELECT `id`,`name` FROM `" . $this->prefix . "categories` ORDER BY `cat_order` ASC");
            $categories_options = '';
            while ($row = $this->helpbase->database->fetchAssoc($result)) {
                if ($row['id'] == $this->ticket['category']) {
                    continue;
                }
                $categories_options.='<option value="' . $row['id'] . '">' . $row['name'] . '</option>';
            }

            /* List of users */
            $this->admins = array();
            $result = $this->helpbase->database->query("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `" . $this->prefix . "users` ORDER BY `id` ASC");
            while ($row = $this->helpbase->database->fetchAssoc($result)) {
                /* Is this an administrator? */
                if ($row['isadmin']) {
                    $this->admins[$row['id']] = $row['name'];
                    continue;
                }

                /* Not admin, is user allowed to view tickets? */
                if (strpos($row['heskprivileges'], 'can_view_tickets') !== false) {
                    /* Is user allowed to access this category? */
                    $cat = substr($row['categories'], 0);
                    $row['categories'] = explode(',', $cat);
                    if (in_array($this->ticket['category'], $row['categories'])) {
                        $this->admins[$row['id']] = $row['name'];
                        continue;
                    }
                }
            }

            /* Get replies */
            //$this->reply = '';
            $result = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "replies` WHERE `replyto`='" . $this->ticketID . "' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC'));
            $replies = $this->helpbase->database->numRows($result);

            $s_go = ('Go');

            // Demo mode
            if (true == $this->helpbase->demo_mode) {
                $this->ticket['email'] = 'hidden@demo.com';
                $this->ticket['ip'] = '127.0.0.1';
            }

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();

            /* Do we need or have any canned responses? */
            $this->can_options = $this->printCanned();
?>
                        <h3 style="padding-bottom:5px"> &nbsp;
<?php
            if ($this->ticket['archive']) {
                echo '
                            <img src="../img/tag.png" width="16" height="16" alt="' . _('Tagged') . '" title="' . _('Tagged') . '"  border="0" style="vertical-align:text-bottom" /> ';
            }
            if ($this->ticket['locked']) {
                $msg    = _('Customers cannot reply to or re-open locked tickets. When locked ticket is marked as closed.');
                $lock   = _('Locked');
                echo '
                            <img src="../img/lock.png" width="16" height="16" alt="' . $lock . ' - ' . $msg . '" title="' . $lock . ' - ' . $msg . '" border="0" style="vertical-align:text-bottom" /> ';
            }
            echo $this->ticket['subject'];
?>
                        </h3>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    
                                <!-- START TICKET HEAD -->
                                    <table border="0" cellspacing="1" cellpadding="1" width="100%">
<?php
            $tmp = '';
            if ($hesk_settings['sequential']) {
                $tmp = ' (' . _('Ticket number') . ': ' . $this->ticket['id'] . ')';
            }

            echo '
                                        <tr>
                                            <td>' . _('Tracking ID') . ': </td>
                                            <td>' . $this->trackingID . ' ' . $tmp . '</td>
                                            <td style="text-align:right">' . $this->getAdminButtons() . '</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Created on') . ': </td>
                                            <td>' . $this->helpbase->common->_date($this->ticket['dt']) . '</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Ticket status') . ': </td>
                                            <td>';

            $random = rand(10000, 99999);

            $status_options = array(
                0 => '<option value="0">' . _('New') . '</option>',
                1 => '<option value="1">' . _('Awaiting reply') . '</option>',
                2 => '<option value="2">' . _('Replied') . '</option>',
                7 => '<option value="7">' . _('Waiting for bench') . '</option>',
                4 => '<option value="4">' . _('On the bench') . '</option>',
                8 => '<option value="8">' . _('Service call') . '</option>',
                9 => '<option value="9">' . _('Remote support') . '</option>',
                5 => '<option value="5">' . _('On hold') . '</option>',
                6 => '<option value="6">' . _('Waiting for payment') . '</option>',
                10 => '<option value="10">' . _('Ready for pickup') . '</option>',
                3 => '<option value="3">' . _('Closed') . '</option>',
            );

            $msg = _('Mark as Closed');

            switch ($this->ticket['status']) {
                case 0:
                    echo '
                                                <font class="open">' . _('New') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[0]);
                break;
                case 1:
                    echo '
                                                <font class="waitingreply">' . _('Awaiting reply') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[1]);
                break;
                case 2:
                    echo '
                                                <font class="replied">' . _('Replied') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[2]);
                break;
                case 4:
                    echo '
                                                <font class="inprogress">' . _('On the bench') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[4]);
                break;
                case 5:
                    echo '
                                                <font class="onhold">' . _('On hold') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[5]);
                break;
                case 6:
                    echo '
                                                <font class="waitforpayment">' . _('Waiting for payment') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[6]);
                break;
                case 7:
                    echo '
                                                <font class="waitingforbench">' . _('Waiting for bench') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[7]);
                break;
                case 8:
                    echo '
                                                <font class="servicecall">' . _('Service call') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[8]);
                break;
                case 9:
                    echo '
                                                <font class="remotesupport">' . _('Remote support') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[9]);
                break;
                case 10:
                    echo '
                                                <font class="readyforpickup">' . _('Ready for pickup') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=3&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . $msg . '</a>]';
                    unset($status_options[10]);
                break;
                default:
                    echo '
                                                <font class="resolved">' . _('Closed') . '</font> [<a href="change_status.php?track=' . $this->trackingID . '&amp;s=1&amp;refresh=' . $random . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . _('Open ticket') . '</a>]';
                    unset($status_options[3]);
            }

            echo '
                                            </td>
                                            <td style="text-align:right">
                                                <form style="margin-bottom:0;" action="change_status.php" method="post">
                                                    <i>' . _('Change status to') . '</i>
                                                    <span style="white-space:nowrap;">
                                                        <select name="s">
                                                            <option value="-1" selected="selected">' . _(' - - Click to Select - - ') . '</option>
                                                            ' . implode('', $status_options) . '
                                                        </select>
                                                        <input type="submit" value="' . $s_go . '" class="button blue small" /><input type="hidden" name="track" value="' . $this->trackingID . '" />
                                                        <input type="hidden" name="token" value="' . $this->helpbase->common->token_echo(0) . '" />
                                                    </span>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Updated') . ': </td>
                                            <td>' . $this->helpbase->common->_date($this->ticket['lastchange']) . '</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Category') . ': </td>
                                            <td>' . $this->category['name'] . '</td>
                                            <td style="text-align:right">';

            if ($this->can_change_cat) {
                echo '
                                                <form style="margin-bottom:0;" action="move_category.php" method="post">
                                                    <i>' . _('Move ticket to') . '</i>
                                                    <span style="white-space:nowrap;">
                                                        <select name="category">
                                                            <option value="-1" selected="selected">' . _(' - - Click to Select - - ') . '</option>
                                                            ' . $categories_options . '
                                                        </select>
                                                        <input type="submit" value="' . $s_go . '" class="button blue small" /><input type="hidden" name="track" value="' . $this->trackingID . '" />
                                                        <input type="hidden" name="token" value="' . $this->helpbase->common->token_echo(0) . '" />
                                                    </span>
                                                </form>';
            }
            echo '
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Replies') . ': </td>
                                            <td>' . $replies . '</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Priority') . ': </td>
                                            <td>';
            $this->options = array(
                0 => '<option value="0">' . _(' * Critical * ') . '</option>',
                1 => '<option value="1">' . _('High') . '</option>',
                2 => '<option value="2">' . _('Medium') . '</option>',
                3 => '<option value="3">' . _('Low') . '</option>'
            );

            switch ($this->ticket['priority']) {
                case 0:
                    echo '
                                                <font class="critical">' . _(' * Critical * ') . '</font>';
                    unset($this->options[0]);
                break;
                case 1:
                    echo '
                                                <font class="important">' . _('High') . '</font>';
                    unset($this->options[1]);
                break;
                case 2:
                    echo '
                                                <font class="medium">' . _('Medium') . '</font>';
                    unset($this->options[2]);
                break;
                default:
                    echo _('Low');
                    unset($this->options[3]);
            }
            echo '
                                            </td>
                                            <td style="text-align:right">
                                                <form style="margin-bottom:0;" action="priority.php" method="post">
                                                    <i>' . _('Change priority to') . '</i>
                                                    <span style="white-space:nowrap;">
                                                        <select name="priority">
                                                            <option value="-1" selected="selected">' . _(' - - Click to Select - - ') . '</option>';
                                                            echo implode('', $this->options);
            echo '
                                                        </select>
                                                        <input type="submit" value="' . $s_go . '" class="button blue small" /><input type="hidden" name="track" value="' . $this->trackingID . '" />
                                                        <input type="hidden" name="token" value="' . $this->helpbase->common->token_echo(0) . '" />
                                                    </span>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Last replier') . ': </td>
                                            <td>' . $this->ticket['repliername'] . '</td>
                                            <td>&nbsp;</td>
                                        </tr>';
?>
                                        <tr>
                                            <td><?php echo _('Owner'); ?>: </td>
                                            <td>
                                                <?php
                                                echo isset($this->admins[$this->ticket['owner']]) ? '<b>' . $this->admins[$this->ticket['owner']] . '</b>' :
                                                        ($this->can_assign_self ? '<b>' . _('Unassigned') . '</b>' . ' [<a href="assign_owner.php?track=' . $this->trackingID . '&amp;owner=' . $_SESSION['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '">' . _('Assign to self') . '</a>]' : '<b>' . _('Unassigned') . '</b>');
                                                ?>
                                            </td>
                                            <td style="text-align:right">
                                                <form style="margin-bottom:0;" action="assign_owner.php" method="post">
<?php
            if ($this->helpbase->admin->checkPermission('can_assign_others', 0)) {
?>
                                                    <i><?php echo _('Assign to'); ?></i>
                                                    <span style="white-space:nowrap;">
                                                        <select name="owner">
                                                            <option value="" selected="selected"><?php echo _(' - - Click to Select - - '); ?></option>
<?php
                if ($this->ticket['owner']) {
                    echo '
                                                            <option value="-1"> &gt; ' . _('Unassigned') . ' &lt; </option>';
                }

                foreach ($this->admins as $k => $v) {
                    if ($k != $this->ticket['owner']) {
                        echo '
                                                            <option value="' . $k . '">' . $v . '</option>';
                    }
                }
?>
                                                        </select>
                                                        <input type="submit" value="<?php echo $s_go; ?>" class="button blue small" />
                                                        <input type="hidden" name="track" value="<?php echo $this->trackingID; ?>" />
                                                        <input type="hidden" name="token" value="<?php echo $this->helpbase->common->token_echo(0); ?>" />
                                                    </span>
<?php
            }
?>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td valign="top"><?php echo _('Time worked'); ?>:</td>
<?php
            if ($this->can_reply || $this->can_edit) {
?>
                                            <td>
                                                <a href="Javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('modifytime')"><?php echo $this->ticket['time_worked']; ?></a>
<?php 
                $t = $this->helpbase->admin->getHHMMSS($this->ticket['time_worked']); 
?>
                                                <div id="modifytime" style="display:none">
                                                    <br />
                                                    <form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;">
                                                        <table class="white">
                                                            <tr>
                                                                <td class="admin_gray"><?php echo _('Hours'); ?>:</td>
                                                                <td class="admin_gray"><input type="text" name="h" value="<?php echo $t[0]; ?>" size="3" /></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="admin_gray"><?php echo _('Minutes'); ?>:</td>
                                                                <td class="admin_gray"><input type="text" name="m" value="<?php echo $t[1]; ?>" size="3" /></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="admin_gray"><?php echo _('Seconds'); ?>:</td>
                                                                <td class="admin_gray"><input type="text" name="s" value="<?php echo $t[2]; ?>" size="3" /></td>
                                                            </tr>
                                                        </table>
                                                        <br />
                                                        <input type="submit" value="<?php echo _('Save'); ?>" class="button blue small" />
                                                        |
                                                        <a href="Javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('modifytime')"><?php echo _('Cancel'); ?></a>
                                                        <input type="hidden" name="track" value="<?php echo $this->trackingID; ?>" />
                                                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                    </form>
                                                </div>
                                            </td>
<?php
            } else {
                echo '<td>' . $this->ticket['time_worked'] . '</td>';
            }
?>
                                            <td>&nbsp;</td>
                                        </tr>
                                    </table>
                                    <br />
                                    <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                        <tr>
                                            <td>
                                                <b><i><?php echo _('Notes'); ?>:</i></b>
<?php
            if ($this->can_reply) {
?>
                                                &nbsp; 
                                                <a href="Javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('notesform')"><?php echo _('+ Add note'); ?></a>
<?php
            }
?>
                                                <div id="notesform" style="display:none">
                                                    <form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;">
                                                        <textarea name="notemsg" rows="6" cols="60"></textarea><br />
                                                        <input type="submit" value="<?php echo _('Submit'); ?>" class="button blue small" /><input type="hidden" name="track" value="<?php echo $this->trackingID; ?>" />
                                                        <i><?php echo _('Notes are hidden from clients.'); ?></i>
                                                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                    </form>
                                                </div>
                                            </td>
                                            <td>&nbsp;</td>
                                        </tr>
<?php
            $res = $this->helpbase->database->query("SELECT t1.*, t2.`name` FROM `" . $this->prefix . "notes` AS t1 LEFT JOIN `" . $this->prefix . "users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='" . $this->ticketID . "' ORDER BY t1.`id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC'));
            while ($note = $this->helpbase->database->fetchAssoc($res)) {
?>
                                        <tr>
                                            <td>
                                                <table border="0" width="100%" cellspacing="0" cellpadding="3">
                                                    <tr>
                                                        <td class="notes"><i><?php echo _('Note by'); ?> <b><?php echo ($note['name'] ? $note['name'] : _('(User deleted)')); ?></b></i> - <?php echo $this->helpbase->common->_date($note['dt']); ?><br /><img src="../img/blank.gif" border="0" width="5" height="5" alt="" /><br />
                                                            <?php echo $note['message']; ?></td>
                                                    </tr>
                                                </table>
                                            </td>
<?php
                if ($this->can_del_notes || $note['who'] == $_SESSION['id']) {
?>
                                            <td width="1" valign="top">
                                                <a href="admin_ticket.php?track=<?php echo $this->trackingID; ?>&amp;refresh=<?php echo mt_rand(10000, 99999); ?>&amp;delnote=<?php echo $note['id']; ?>&amp;token=<?php $this->helpbase->common->token_echo(); ?>" onclick="return hb_confirmExecute('<?php echo _('Delete note') . '?'; ?>');"><img src="../img/delete.png" alt="<?php echo _('Delete note'); ?>" title="<?php echo _('Delete note'); ?>" width="16" height="16" /></a>
                                            </td>
<?php
                } else {
                    echo '
                                            <td width="1" valign="top">&nbsp;</td>';
                }
?>
                                        </tr>
<?php
            }
?>
                                    </table>
                                    <!-- END TICKET HEAD -->
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <br />
<?php
            /* Reply form on top? */
            if ($this->can_reply && $hesk_settings['reply_top'] == 1) {
                $this->printReplyForm();
                echo '<br />';
            }
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    
                                    <!-- START TICKET REPLIES -->
                                    <table border="0" cellspacing="1" cellpadding="1" width="100%">
<?php
            if ($hesk_settings['new_top']) {
                $i = $this->printTicketReplies($result) ? 0 : 1;
            } else {
                $i = 1;
            }

            /* Make sure original message is in correct color if newest are on top */
            $color = $i ? 'class="ticketalt"' : 'class="ticketrow"';
?>
                                        <tr>
                                            <td <?php echo $color; ?>>
                                                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                    <tr>
                                                        <td valign="top">
                                                            <table border="0" cellspacing="1">
                                                                <tr>
                                                                    <td><?php echo _('Date'); ?>:</td>
                                                                    <td><?php echo $this->helpbase->common->_date($this->ticket['dt']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><?php echo _('Name'); ?>:</td>
                                                                    <td><?php echo $this->ticket['name']; ?></td>
                                                                </tr>
<?php 
            if ($this->ticket['company']) { 
?>
                                                                <tr>
                                                                    <td><?php echo _('Company'); ?>:</td>
                                                                    <td><?php echo $this->ticket['company']; ?></td>
                                                                </tr>                      
<?php 
            }; 
?>
                                                                <tr>
                                                                    <td><?php echo _('Email'); ?>:</td>
                                                                    <td><a href="mailto:<?php echo $this->ticket['email']; ?>"><?php echo $this->ticket['email']; ?></a></td>
                                                                </tr>

<?php 
            if ($this->ticket['homephone']){ 
?>
                                                                <tr>
                                                                    <td><?php echo _('Home phone'); ?>:</td>
                                                                    <td><?php echo $this->ticket['homephone']; ?></td>
                                                                </tr>
<?php 
            } 

            if ($this->ticket['mobilephone']){ 
?>
                                                                <tr>
                                                                    <td><?php echo _('Mobile phone'); ?>:</td>
                                                                    <td><?php echo $this->ticket['mobilephone']; ?></td>
                                                                </tr>
<?php 
            }
            
            if ($this->ticket['workphone']){ 
?>
                                                                <tr>
                                                                    <td><?php echo _('Work phone'); ?>:</td>
                                                                    <td><?php echo $this->ticket['workphone']; ?></td>
                                                                </tr>
<?php 
            } 
?>
                                                                <tr>
                                                                    <td><?php echo _('IP'); ?>:</td>
                                                                    <td><?php echo $this->ticket['ip']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        <br>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td style="text-align:right; vertical-align:top;">
                                                            <?php echo $this->getAdminButtons(0, $i); ?>
                                                        </td>
                                                    </tr>
                                                </table>
<?php
            /* custom fields before message */
            $print_table = 0;
            $myclass = ' class="tickettd"';

            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place'] == 0) {
                    if ($print_table == 0) {
                        echo '
                                                <table border="0" cellspacing="1" cellpadding="2">';
                        $print_table = 1;
                    }

                    if ($this->ticket[$k]){
                        echo '
                                                    <tr>
                                                        <td valign="top" ' . $myclass . '>' . $v['name'] . ':</td>
                                                        <td valign="top" ' . $myclass . '>' . $this->ticket[$k] . '</td>
                                                    </tr>';
                    }
                }
            }
            
            if ($print_table) {
                echo '
                                                    <tr>
                                                        <td>
                                                            <br>
                                                        </td>
                                                    </tr>
                                                </table>';
            }
?>
                                                <p><b><?php echo _('Message'); ?>:</b></p>
                                                <p><?php echo $this->ticket['message']; ?><br />&nbsp;</p>
<?php
            /* custom fields after message */
            $print_table = 0;

            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place']) {
                    if ($print_table == 0) {
                        echo '
                                                <table border="0" cellspacing="1" cellpadding="2">';
                        $print_table = 1;
                    }

                    echo '
                                                    <tr>
                                                        <td valign="top" ' . $myclass . '>' . $v['name'] . ':</td>
                                                        <td valign="top" ' . $myclass . '>' . $this->ticket[$k] . '</td>
                                                    </tr>';
                }
            }
            if ($print_table) {
                echo '
                                                </table>';
            }
            
            // Device ID data 
?>
                                                <table border="0" cellspacing="1" cellpadding="2">
                                                    <tr><td><br></td></tr>
<?php 
            if ($this->ticket['devicetype']) { 
?>
                                                    <tr>
                                                        <td><?php echo _('Device type'); ?>:</td>
                                                        <td><?php echo $this->ticket['devicetype']; ?></td>                                
                                                    </tr>
<?php 
            }

            if ($this->ticket['devicebrand']){ 
?>
                                                    <tr>
                                                        <td><?php echo _('Device brand'); ?>:</td>
                                                        <td><?php echo $this->ticket['devicebrand']; ?></td>                                
                                                    </tr>
<?php 
            }

            if ($this->ticket['deviceid']){
?>
                                                    <tr>
                                                        <td><?php echo _('Device ID'); ?>:</td>
                                                        <td><?php echo $this->ticket['deviceid']; ?></td>                                
                                                    </tr>
<?php 
            } 
?>
                                                </table>
<?php
            /* Print attachments */
            $this->listAttachments($this->ticket['attachments'], 0, $i);
?>
                                            </td>
                                        </tr>
<?php
            if (!$hesk_settings['new_top']) {
                $this->printTicketReplies($result);
            }
?>
                                    </table>
                                    <!-- END TICKET REPLIES -->
                                    
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <br />
<?php
            /* Reply form on bottom? */
            if ($this->can_reply && !$hesk_settings['reply_top']) {
                $this->printReplyForm();
            }

            /* Display ticket history */
            if (strlen($this->ticket['history'])) {
?>
                        <p>&nbsp;</p>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <h3><?php echo _('Ticket history'); ?></h3>
                                    <ul><?php echo $this->ticket['history']; ?></ul>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
<?php
            }

            /* Clear unneeded session variables */
            $this->helpbase->common->cleanSessionVars('ticket_message');
            $this->helpbase->common->cleanSessionVars('time_worked');

            $this->helpbase->footer->render();

            unset($this->helpbase);
        }
        
        private function listAttachments($attachments = '', $reply = 0, $white = 1) {
            global $hesk_settings;

            /* Attachments disabled or not available */
            if (!$hesk_settings['attachments']['use'] || !strlen($attachments)) {
                return false;
            }

            /* Style and mousover/mousout */
            $tmp = $white ? 'White' : 'Blue';
            $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';

            /* List attachments */
            echo '<p><b>' . _('Attachments') . ':</b><br />';
            $att = explode(',', substr($attachments, 0, -1));
            foreach ($att as $myatt) {
                list($att_id, $att_name) = explode('#', $myatt);

                /* Can edit and delete tickets? */
                if ($this->can_edit && $this->can_delete) {
                    echo '<a href="admin_ticket.php?delatt=' . $att_id . '&amp;reply=' . $reply . '&amp;track=' . $this->trackingID . '&amp;' . $tmp . '&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '" onclick="return hb_confirmExecute(\'' . _('Do you want to permanently delete this attachment?') . '\');"><img src="../img/delete.png" width="16" height="16" alt="' . _('Delete this attachment') . '" title="' . _('Delete this attachment') . '" ' . $style . ' /></a> ';
                }

                echo '
                <a href="../download_attachment.php?att_id=' . $att_id . '&amp;track=' . $this->trackingID . '"><img src="../img/clip.png" width="16" height="16" alt="' . _('Download') . ' ' . $att_name . '" title="' . _('Download') . ' ' . $att_name . '" ' . $style . ' /></a>
                <a href="../download_attachment.php?att_id=' . $att_id . '&amp;track=' . $this->trackingID . '">' . $att_name . '</a><br />
        ';
            }
            echo '</p>';

            return true;
        }

        private function getAdminButtons($reply = 0, $white = 1) {
            global $hesk_settings;

            $options = '';

            /* Style and mousover/mousout */
            $tmp = $white ? 'White' : 'Blue';
            $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';

            /* Lock ticket button */
            if (/* ! $reply && */ $this->can_edit) {
                $msg = _('Customers cannot reply to or re-open locked tickets. When locked ticket is marked as closed.');
                if ($this->ticket['locked']) {
                    $des = _('Unlock ticket') . ' - ' . $msg;
                    $options .= '<a href="lock.php?track=' . $this->trackingID . '&amp;locked=0&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/unlock.png" width="16" height="16" alt="' . $des . '" title="' . $des . '" ' . $style . ' /></a> ';
                } else {
                    $des = _('Lock ticket') . ' - ' . $msg;
                    $options .= '<a href="lock.php?track=' . $this->trackingID . '&amp;locked=1&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/lock.png" width="16" height="16" alt="' . $des . '" title="' . $des . '" ' . $style . ' /></a> ';
                }
            }

            /* Tag ticket button */
            if (/* ! $reply && */ $this->can_archive) {
                if ($this->ticket['archive']) {
                    $options .= '<a href="archive.php?track=' . $this->trackingID . '&amp;archived=0&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/tag.png" width="16" height="16" alt="' . _('Untag this ticket') . '" title="' . _('Untag this ticket') . '" ' . $style . ' /></a> ';
                } else {
                    $options .= '<a href="archive.php?track=' . $this->trackingID . '&amp;archived=1&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/tag_off.png" width="16" height="16" alt="' . _('Tag this ticket') . '" title="' . _('Tag this ticket') . '" ' . $style . ' /></a> ';
                }
            }

            /* Import to knowledgebase button */
            if ($hesk_settings['kb_enable'] && $this->helpbase->admin->checkPermission('can_man_kb', 0)) {
                $options .= '<a href="manage_knowledgebase.php?a=import_article&amp;track=' . $this->trackingID . '"><img src="../img/import_kb.png" width="16" height="16" alt="' . _('Import this ticket into a Knowledgebase article') . '" title="' . _('Import this ticket into a Knowledgebase article') . '" ' . $style . ' /></a> ';
            }

            /* Print ticket button */
            $options .= '<a href="../print.php?track=' . $this->trackingID . '"><img src="../img/print.png" width="16" height="16" alt="' . _('Printer friendly version') . '" title="' . _('Printer friendly version') . '" ' . $style . ' /></a> ';

            /* Edit post */
            if ($this->can_edit) {
                $tmp = $reply ? '&amp;reply=' . $this->reply['id'] : '';
                $options .= '<a href="edit_post.php?track=' . $this->trackingID . $tmp . '"><img src="../img/edit.png" width="16" height="16" alt="' . _('Edit post') . '" title="' . _('Edit post') . '" ' . $style . ' /></a> ';
            }


            /* Delete ticket */
            if ($this->can_delete) {
                if ($reply) {
                    $url = 'admin_ticket.php';
                    $tmp = 'delete_post=' . $this->reply['id'];
                    $img = 'delete.png';
                    $txt = _('Delete this post');
                } else {
                    $url = 'delete_tickets.php';
                    $tmp = 'delete_ticket=1';
                    $img = 'delete_ticket.png';
                    $txt = _('Delete this ticket');
                }
                $options .= '<a href="' . $url . '?track=' . $this->trackingID . '&amp;' . $tmp . '&amp;refresh=' . mt_rand(10000, 99999) . '&amp;token=' . $this->helpbase->common->token_echo(0) . '" onclick="return hb_confirmExecute(\'' . $txt . '?\');"><img src="../img/' . $img . '" width="16" height="16" alt="' . $txt . '" title="' . $txt . '" ' . $style . ' /></a> ';
            }

            /* Return generated HTML */
            return $options;
        }

        private function print_form() {
            global $hesk_settings;

            /* Print header */
            $this->helpbase->header->render();

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
            ?>

        </td>
    </tr>
    <tr>
        <td>

            &nbsp;<br />

            <?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
            ?>

            <div align="center">
                <table border="0" cellspacing="0" cellpadding="0" width="50%">
                    <tr>
                        <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornerstop"></td>
                        <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                    <tr>
                        <td class="roundcornersleft">&nbsp;</td>
                        <td>

                            <form action="admin_ticket.php" method="get">

                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="1"><img src="../img/existingticket.png" alt="" width="60" height="60" /></td>
                                        <td>
                                            <p><b><?php echo _('View existing ticket'); ?></a></b></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="1">&nbsp;</td>
                                        <td>&nbsp;</td>
                                    </tr>
                                    <tr>
                                        <td width="1">&nbsp;</td>
                                        <td>
                                            <?php echo _('Ticket tracking ID'); ?>: <br /><input type="text" name="track" maxlength="20" size="35" value="<?php echo $this->trackingID; ?>" /><br />&nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="1">&nbsp;</td>
                                        <td><input type="submit" value="<?php echo _('View ticket'); ?>" class="button blue small" /><input type="hidden" name="Refresh" value="<?php echo rand(10000, 99999); ?>"></td>
                                    </tr>
                                </table>

                            </form>

                        </td>
                        <td class="roundcornersright">&nbsp;</td>
                    </tr>
                    <tr>
                        <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornersbottom"></td>
                        <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                </table>
            </div>

            <p>&nbsp;</p>
            <?php

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function printTicketReplies($result) {
            global $hesk_settings;

            $i = $hesk_settings['new_top'] ? 0 : 1;

            while ($this->reply = $this->helpbase->database->fetchAssoc($result)) {
                if ($i) {
                    $color = 'class="ticketrow"';
                    $i = 0;
                } else {
                    $color = 'class="ticketalt"';
                    $i = 1;
                }

                $this->reply['dt'] = $this->helpbase->common->_date($this->reply['dt']);
                ?>
        <tr>
            <td <?php echo $color; ?>>

                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td valign="top">
                            <table border="0" cellspacing="1">
                                <tr>
                                    <td><?php echo _('Date'); ?>:</td>
                                    <td><?php echo $this->reply['dt']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo _('Name'); ?>:</td>
                                    <td><?php echo $this->reply['name']; ?></td>
                                </tr>
                            </table>
                        </td>
                        <td style="text-align:right; vertical-align:top;">
                            <?php echo $this->getAdminButtons(1, $i); ?>
                        </td>
                    </tr>
                </table>

                <p><b><?php echo _('Message'); ?>:</b></p>
                <p><?php echo $this->reply['message']; ?></p>

                <?php
                /* Attachments */
                $this->listAttachments($this->reply['attachments'], $this->reply['id'], $i);

                /* Staff rating */
                if ($hesk_settings['rating'] && $this->reply['staffid']) {
                    if ($this->reply['rating'] == 1) {
                        echo '<p class="rate">' . _('Rated as <i>not helpful</i>') . '</p>';
                    } elseif ($this->reply['rating'] == 5) {
                        echo '<p class="rate">' . _('Rated as <i>helpful</i>') . '</p>';
                    }
                }

                /* Show "unread reply" message? */
                if ($this->reply['staffid'] && !$this->reply['read']) {
                    echo '<p class="rate">' . _('Customer didn\'t read this reply yet.') . '</p>';
                }
                ?>
            </td>
        </tr>
        <?php
    }

    return $i;
        }

        private function printReplyForm() {
            global $hesk_settings;
            ?>
            <!-- START REPLY FORM -->

            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                    <td class="roundcornerstop"></td>
                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                </tr>
                <tr>
                    <td class="roundcornersleft">&nbsp;</td>
                    <td>

                        <h3 align="center"><?php echo _('Add reply'); ?></h3>



                        <form method="post" action="admin_reply_ticket.php" enctype="multipart/form-data" name="form1" onsubmit="javascript:force_stop();
                                return true;">

                            <br />

                            <?php
                            /* Ticket assigned to someone else? */
                            if ($this->ticket['owner'] && $this->ticket['owner'] != $_SESSION['id'] && isset($this->admins[$this->ticket['owner']])) {
                                $this->helpbase->common->show_notice(_('This ticket is assigned to') . ' ' . $this->admins[$this->ticket['owner']]);
                            }

                            /* Ticket locked? */
                            if ($this->ticket['locked']) {
                                $this->helpbase->common->show_notice(_('This ticket has been locked, the customer will not be able to post a reply.'));
                            }
                            ?>

                            <div align="center">
                                <table class="white" style="min-width:600px;">
                                    <tr>
                                        <td colspan="2">
                                            &raquo; <?php echo _('Time worked'); ?>
                                            <input type="text" name="time_worked" id="time_worked" size="10" value="<?php echo ( isset($_SESSION['time_worked']) ? $this->helpbase->admin->getTime($_SESSION['time_worked']) : '00:00:00'); ?>" />
                                            <input type="button" class="button blue small" onclick="ss()" id="startb" value="<?php echo _('Start / Stop'); ?>" />
                                            <input type="button" class="button blue small" onclick="r()" value="<?php echo _('Reset'); ?>" />
                                            <br />&nbsp;
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <?php
                            /* Do we have any canned responses? */
                            if (strlen($this->can_options)) {
                                ?>
                                <div align="center">
                                    <table class="white" style="min-width:600px;">
                                        <tr>
                                            <td class="admin_gray" colspan="2"><b>&raquo; <?php echo _('Canned responses'); ?></b></td>
                                        </tr>
                                        <tr>
                                            <td class="admin_gray">
                                                <label><input type="radio" name="mode" id="modeadd" value="1" checked="checked" /> <?php echo _('Add to the bottom'); ?></label><br />
                                                <label><input type="radio" name="mode" id="moderep" value="0" /> <?php echo _('Replace message'); ?></label>
                                            </td>
                                            <td class="admin_gray">
                                                <?php echo _('Select a canned response'); ?>:<br />
                                                <select name="saved_replies" onchange="setMessage(this.value)">
                                                    <option value="0"> - <?php echo _('Select / Empty'); ?> - </option>
                                                    <?php echo $this->can_options; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <?php
                            }
                            ?>

                            <p align="center"><?php echo _('Message'); ?>: <font class="important">*</font><br />
                                <span id="HeskMsg"><textarea name="message" id="message" rows="12" cols="72"><?php
                                        if (isset($_SESSION['ticket_message'])) {
                                            echo stripslashes($this->helpbase->common->_input($_SESSION['ticket_message']));
                                        }
                                        ?></textarea></span></p>

                            <?php
                            /* attachments */
                            if ($hesk_settings['attachments']['use']) {
                                ?>
                                <p align="center">
                                    <?php
                                    echo _('Attachments') . ' (<a href="Javascript:void(0)" onclick="Javascript:hb_window(\'../file_limits.php\',250,500);return false;">' . _('File upload limits') . '</a>):<br />';
                                    for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                                        echo '<input type="file" name="attachment[' . $i . ']" size="50" /><br />';
                                    }
                                    ?>
                                </p>
                                <?php
                            }
                            ?>

                            <div align="center">
                                <center>
                                    <table>
                                        <tr>
                                            <td>
                                                <?php
                                                if ($this->ticket['owner'] != $_SESSION['id'] && $this->can_assign_self) {
                                                    if (empty($this->ticket['owner'])) {
                                                        echo '<label><input type="checkbox" name="assign_self" value="1" checked="checked" /> <b>' . _('Assign this ticket to myself') . '</b></label><br />';
                                                    } else {
                                                        echo '<label><input type="checkbox" name="assign_self" value="1" /> ' . _('Assign this ticket to myself') . '</label><br />';
                                                    }
                                                }
                                                if ($this->ticket['status'] != 3) {
                                                    echo '<label><input type="checkbox" name="close" value="1" /> ' . _('Close this ticket') . '</label><br />';
                                                }
                                                ?>
                                                <label><input type="checkbox" name="set_priority" value="1" /> <?php echo _('Change priority to'); ?> </label>
                                                <select name="priority">
            <?php echo implode('', $this->options); ?>
                                                </select><br />
                                                <label><input type="checkbox" name="signature" value="1" checked="checked" /> <?php echo _('Attach signature'); ?></label>
                                                (<a href="profile.php"><?php echo _('Profile settings'); ?></a>)<br />
                                                <label><input type="checkbox" name="no_notify" value="1" /> <?php echo _('Don\'t send email notification of this reply to the customer'); ?></label>
                                            </td>
                                        </tr>
                                    </table>
                                </center>
                            </div>

                            <p align="center">
                                <input type="hidden" name="orig_id" value="<?php echo $this->ticket['id']; ?>" />
                                <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                <input type="submit" value="<?php echo _('Submit reply'); ?>" class="button blue small" /></p>

                        </form>

                    </td>
                    <td class="roundcornersright">&nbsp;</td>
                </tr>
                <tr>
                    <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                    <td class="roundcornersbottom"></td>
                    <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                </tr>
            </table>

            <!-- END REPLY FORM -->
            <?php
        }

        private function printCanned() {
            global $hesk_settings;

            /* Can user reply to tickets? */
            if (!$this->can_reply) {
                return '';
            }

            /* Get canned replies from the database */
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->prefix . "std_replies` ORDER BY `reply_order` ASC");

            /* If no canned replies return empty */
            if (!$this->helpbase->database->numRows($res)) {
                return '';
            }

            /* We do have some replies, print the required Javascript and select field options */
            $this->can_options = '';
            ?>
            <script language="javascript" type="text/javascript"><!--
            // -->
                var myMsgTxt = new Array();
                myMsgTxt[0] = '';

            <?php
            while ($mysaved = $this->helpbase->database->fetchRow($res)) {
                $this->can_options .= '<option value="' . $mysaved[0] . '">' . $mysaved[1] . "</option>\n";
                echo 'myMsgTxt[' . $mysaved[0] . ']=\'' . str_replace("\r\n", "\\r\\n' + \r\n'", addslashes($mysaved[2])) . "';\n";
            }
            ?>

                function setMessage(msgid)
                {
                    var myMsg = myMsgTxt[msgid];

                    if (myMsg == '')
                    {
                        if (document.form1.mode[1].checked)
                        {
                            document.getElementById('message').value = '';
                        }
                        return true;
                    }

                    myMsg = myMsg.replace(/%%HESK_NAME%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['name']); ?>');
                    myMsg = myMsg.replace(/%%HESK_EMAIL%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['email']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom1%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom1']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom2%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom2']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom3%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom3']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom4%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom4']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom5%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom5']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom6%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom6']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom7%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom7']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom8%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom8']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom9%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom9']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom10%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom10']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom11%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom11']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom12%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom12']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom13%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom13']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom14%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom14']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom15%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom15']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom16%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom16']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom17%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom17']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom18%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom18']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom19%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom19']); ?>');
                    myMsg = myMsg.replace(/%%HESK_custom20%%/g, '<?php echo $this->helpbase->admin->jsString($this->ticket['custom20']); ?>');

                    if (document.getElementById)
                    {
                        if (document.getElementById('moderep').checked)
                        {
                            document.getElementById('HeskMsg').innerHTML = '<textarea name="message" id="message" rows="12" cols="72">' + myMsg + '</textarea>';
                        }
                        else
                        {
                            var oldMsg = document.getElementById('message').value;
                            document.getElementById('HeskMsg').innerHTML = '<textarea name="message" id="message" rows="12" cols="72">' + oldMsg + myMsg + '</textarea>';
                        }
                    }
                    else
                    {
                        if (document.form1.mode[0].checked)
                        {
                            document.form1.message.value = myMsg;
                        }
                        else
                        {
                            var oldMsg = document.form1.message.value;
                            document.form1.message.value = oldMsg + myMsg;
                        }
                    }

                }
            //-->
            </script>
            <?php
            /* Return options for select box */
            return $this->can_options;
        }            
        
    }
    
    new HelpbaseAdminTicket;
}

?>
