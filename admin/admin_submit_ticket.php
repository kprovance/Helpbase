<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Submit Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 **/

define('EXECUTING', true);

if (!class_exists('HelpbaseSubmitTicket')) {
    class HelpbaseSubmitTicket {
        private $helpbase = null;

        public function __construct(){
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            $helpbase->load_email_functions();
            $helpbase->load_posting_functions();

            $helpbase->admin->isLoggedIn();
            $this->render();
        }

        private function render() {
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

            $hesk_error_buffer = array();

            $tmpvar['name']         = $this->helpbase->common->_input($this->helpbase->common->_post('name')) or $hesk_error_buffer['name'] = _('Please enter your name');
            $tmpvar['company']      = $this->helpbase->common->_input($this->helpbase->common->_post('company'));
            $tmpvar['homephone']    = $this->helpbase->common->_input($this->helpbase->common->_post('homephone'));
            $tmpvar['mobilephone']  = $this->helpbase->common->_input($this->helpbase->common->_post('mobilephone'));
            $tmpvar['workphone']    = $this->helpbase->common->_input($this->helpbase->common->_post('workphone'));
            $tmpvar['devicetype']   = $this->helpbase->common->_input($this->helpbase->common->_post('devicetype'));
            $tmpvar['devicebrand']  = $this->helpbase->common->_input($this->helpbase->common->_post('devicebrand'));
            $tmpvar['deviceid']     = $this->helpbase->common->_input($this->helpbase->common->_post('deviceid'));
            $tmpvar['email']        = $this->helpbase->common->validateEmail($this->helpbase->common->_post('email'), 'ERR', 0) or $hesk_error_buffer['email'] = _('Please enter a valid email address');
            $tmpvar['category']     = intval($this->helpbase->common->_post('category')) or $hesk_error_buffer['category'] = _('Please select the appropriate category');
            $tmpvar['priority']     = intval($this->helpbase->common->_post('priority'));

            if ($tmpvar['priority'] < 0 || $tmpvar['priority'] > 3) {
                $hesk_error_buffer['priority'] = _('Please select the appropriate priority');
            }

            $tmpvar['subject'] = $this->helpbase->common->_input($this->helpbase->common->_post('subject')) or $hesk_error_buffer['subject'] = _('Please enter your ticket subject');
            $tmpvar['message'] = $this->helpbase->common->_input($this->helpbase->common->_post('message')) or $hesk_error_buffer['message'] = _('Please enter your message');

            // Is category a valid choice?
            if ($tmpvar['category']) {
                $this->helpbase->posting->verifyCategory(1);

                // Is auto-assign of tickets disabled in this category?
                if (empty($hesk_settings['category_data'][$tmpvar['category']]['autoassign'])) {
                    $hesk_settings['autoassign'] = false;
                }
            }

            // Custom fields
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && isset($_POST[$k])) {
                    if (is_array($_POST[$k])) {
                        $tmpvar[$k] = '';
                        foreach ($_POST[$k] as $myCB) {
                            $tmpvar[$k] .= ( is_array($myCB) ? '' : $this->helpbase->common->_input($myCB) ) . '<br />';
                        }
                        $tmpvar[$k] = substr($tmpvar[$k], 0, -6);
                    } else {
                        $tmpvar[$k] = $this->helpbase->common->makeURL(nl2br($this->helpbase->common->_input($_POST[$k])));
                    }
                } else {
                    $tmpvar[$k] = '';
                }
            }

            // Generate tracking ID
            $tmpvar['trackid'] = $this->helpbase->common->createID();

            // Log who submitted ticket
            $tmpvar['history'] = sprintf(_('<li class="smaller">%s | ticket created by %s</li>'), $this->helpbase->common->_date(), $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

            // Owner
            $tmpvar['owner'] = 0;
            if ($this->helpbase->admin->checkPermission('can_assign_others', 0)) {
                $tmpvar['owner'] = intval($this->helpbase->common->_post('owner'));

                // If ID is -1 the ticket will be unassigned
                if ($tmpvar['owner'] == -1) {
                    $tmpvar['owner'] = 0;
                }
                // Automatically assign owner?
                elseif ($tmpvar['owner'] == -2 && $hesk_settings['autoassign'] == 1) {
                    $autoassign_owner = $this->helpbase->common->autoAssignTicket($tmpvar['category']);
                    if ($autoassign_owner) {
                        $tmpvar['owner'] = intval($autoassign_owner['id']);
                        $tmpvar['history'] .= sprintf(_('<li class="smaller">%s | automatically assigned to %s</li>'), $this->helpbase->common->_date(), $autoassign_owner['name'] . ' (' . $autoassign_owner['user'] . ')');
                    } else {
                        $tmpvar['owner'] = 0;
                    }
                }
                // Check for invalid owner values
                elseif ($tmpvar['owner'] < 1) {
                    $tmpvar['owner'] = 0;
                } else {
                    // Has the new owner access to the selected category?
                    $res = $this->helpbase->database->query("SELECT `name`,`isadmin`,`categories` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='{$tmpvar['owner']}' LIMIT 1");
                    if ($this->helpbase->database->numRows($res) == 1) {
                        $row = $this->helpbase->database->fetchAssoc($res);
                        if (!$row['isadmin']) {
                            $row['categories'] = explode(',', $row['categories']);
                            if (!in_array($tmpvar['category'], $row['categories'])) {
                                $_SESSION['isnotice'][] = 'category';
                                $hesk_error_buffer['owner'] = _('This owner doesn\'t have access to the selected category.');
                            }
                        }
                    } else {
                        $_SESSION['isnotice'][] = 'category';
                        $hesk_error_buffer['owner'] = _('This owner doesn\'t have access to the selected category.');
                    }
                }
            } elseif ($this->helpbase->admin->checkPermission('can_assign_self', 0) && $this->helpbase->admin->okCategory($tmpvar['category'], 0) && !empty($_POST['assing_to_self'])) {
                $tmpvar['owner'] = intval($_SESSION['id']);
            }

            // Notify customer of the ticket?
            $notify = !empty($_POST['notify']) ? 1 : 0;

            // Show ticket after submission?
            $show = !empty($_POST['show']) ? 1 : 0;

            // Attachments
            if ($hesk_settings['attachments']['use']) {
                require_once($this->helpbase->includes . 'attachments.inc.php');

                $attachments = array();
                $trackingID = $tmpvar['trackid'];

                for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                    $att = hesk_uploadFile($i);
                    if ($att !== false && !empty($att)) {
                        $attachments[$i] = $att;
                    }
                }
            }
            $tmpvar['attachments'] = '';

            // If we have any errors lets store info in session to avoid re-typing everything
            if (count($hesk_error_buffer) != 0) {
                $_SESSION['iserror'] = array_keys($hesk_error_buffer);

                $_SESSION['as_name']        = $this->helpbase->common->_post('name');
                $_SESSION['as_company']     = $this->helpbase->common->_post('company');
                $_SESSION['as_homephone']   = $this->helpbase->common->_post('homephone');
                $_SESSION['as_mobilephone'] = $this->helpbase->common->_post('mobilephone');
                $_SESSION['as_workphone']   = $this->helpbase->common->_post('workphone');
                $_SESSION['as_devicetype']  = $this->helpbase->common->_post('devicetype');
                $_SESSION['as_devicebrand'] = $this->helpbase->common->_post('devicebrand');
                $_SESSION['as_deviceid']    = $this->helpbase->common->_post('deviceid');
                $_SESSION['as_email']       = $this->helpbase->common->_post('email');
                $_SESSION['as_category']    = $this->helpbase->common->_post('category');
                $_SESSION['as_priority']    = $this->helpbase->common->_post('priority');
                $_SESSION['as_subject']     = $this->helpbase->common->_post('subject');
                $_SESSION['as_message']     = $this->helpbase->common->_post('message');
                $_SESSION['as_owner']       = $tmpvar['owner'];
                $_SESSION['as_notify']      = $notify;
                $_SESSION['as_show']        = $show;

                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    if ($v['use']) {
                        $_SESSION["as_$k"] = $this->helpbase->common->_post($k);
                    }
                }

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                if ($hesk_settings['attachments']['use']) {
                    hesk_removeAttachments($attachments);
                }

                $hesk_error_buffer = _('Please correct the following errors:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'new_ticket.php');
            }

            if ($hesk_settings['attachments']['use'] && !empty($attachments)) {
                foreach ($attachments as $myatt) {
                    $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('" . $this->helpbase->database->escape($tmpvar['trackid']) . "','" . $this->helpbase->database->escape($myatt['saved_name']) . "','" . $this->helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
                    $tmpvar['attachments'] .= $this->helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
                }
            }

            $tmpvar['message'] = $this->helpbase->common->makeURL($tmpvar['message']);
            $tmpvar['message'] = nl2br($tmpvar['message']);

            // Insert ticket to database
            $ticket = $this->helpbase->posting->newTicket($tmpvar);

            // Notify the customer about the ticket?
            if ($notify) {
                $this->helpbase->email->notifyCustomer();
            }

            // If ticket is assigned to someone notify them?
            if ($ticket['owner'] && $ticket['owner'] != intval($_SESSION['id'])) {
                // If we don't have info from auto-assign get it from database
                if (!isset($autoassign_owner['email'])) {
                    $this->helpbase->email->notifyAssignedStaff(false, 'ticket_assigned_to_you');
                } else {
                    $this->helpbase->email->notifyAssignedStaff($autoassign_owner, 'ticket_assigned_to_you');
                }
            } elseif (!$ticket['owner']) {
                // Ticket unassigned, notify everyone that selected to be notified about unassigned tickets
                $this->helpbase->email->notifyStaff('new_ticket_staff', " `id` != " . intval($_SESSION['id']) . " AND `notify_new_unassigned` = '1' ");
            }

            // Unset temporary variables
            unset($tmpvar);
            $this->helpbase->common->cleanSessionVars('tmpvar');
            $this->helpbase->common->cleanSessionVars('as_name');
            $this->helpbase->common->cleanSessionVars('as_company');
            $this->helpbase->common->cleanSessionVars('as_email');
            $this->helpbase->common->cleanSessionVars('as_devicetype');
            $this->helpbase->common->cleanSessionVars('as_devicebrand');
            $this->helpbase->common->cleanSessionVars('as_deviceid');
            $this->helpbase->common->cleanSessionVars('as_homephone');
            $this->helpbase->common->cleanSessionVars('as_mobilephone');
            $this->helpbase->common->cleanSessionVars('as_workphone');
            $this->helpbase->common->cleanSessionVars('as_category');
            $this->helpbase->common->cleanSessionVars('as_priority');
            $this->helpbase->common->cleanSessionVars('as_subject');
            $this->helpbase->common->cleanSessionVars('as_message');
            $this->helpbase->common->cleanSessionVars('as_owner');
            $this->helpbase->common->cleanSessionVars('as_notify');
            $this->helpbase->common->cleanSessionVars('as_show');

            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use']) {
                    $this->helpbase->common->cleanSessionVars("as_$k");
                }
            }

            // If ticket has been assigned to the person submitting it lets show a message saying so
            if ($ticket['owner'] && $ticket['owner'] == intval($_SESSION['id'])) {
                $msg = _('New support ticket submitted') . '<br />&nbsp;<br /><img src="' . $this->helpbase->url . 'img/notice.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> <b>' . (isset($autoassign_owner) ? _('This ticket has been auto-assigned to you') : _('This ticket has been assigned to you')) . '</b>';
            }

            // Show the ticket or just the success message
            if ($show) {
                $this->helpbase->common->process_messages($msg, 'admin_ticket.php?track=' . $ticket['trackid'] . '&refresh=' . mt_rand(10000, 99999), 'SUCCESS');
            } else {
                $this->helpbase->common->process_messages($msg . '. <a href="admin_ticket.php?track=' . $ticket['trackid'] . '&refresh=' . mt_rand(10000, 99999) . '">' . _('View ticket') . '</a>', 'new_ticket.php', 'SUCCESS');
            }

            unset($this->helpbase);
        }
    }

    new HelpbaseSubmitTicket;
}

?>