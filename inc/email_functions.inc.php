<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Email Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseEMail')) {

    class HelpbaseEmail {

        private $parent = null;

        public function __construct($parent) {
            global $hesk_settings;
            
            $this->parent = $parent;

            /* Get includes for SMTP */
            if ($hesk_settings['smtp']) {
                require($parent->includes . 'mail/smtp.php');
                if (strlen($hesk_settings['smtp_user']) || strlen($hesk_settings['smtp_password'])) {
                    require_once($parent->includes . 'mail/sasl/sasl.php');
                }
            }
        }

        public function notifyCustomer($email_template = 'new_ticket') {
            global $hesk_settings, $ticket;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return true;
            }

            // Format email subject and message
            $subject = $this->getEmailSubject($email_template, $ticket);
            $message = $this->getEmailMessage($email_template, $ticket);

            // Send e-mail
            $this->mail($ticket['email'], $subject, $message);

            return true;
        }

        public function notifyAssignedStaff($autoassign_owner, $email_template, $type = 'notify_assigned') {
            global $hesk_settings, $ticket;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return true;
            }

            $ticket['owner'] = intval($ticket['owner']);

            /* Need to lookup owner info from the database? */
            if ($autoassign_owner === false) {
                $res = $this->parent->database->query("SELECT `name`, `email`,`language`,`notify_assigned`,`notify_reply_my` FROM `" . $this->parent->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . $ticket['owner'] . "' LIMIT 1");

                $autoassign_owner = $this->parent->database->fetchAssoc($res);
                $hesk_settings['user_data'][$ticket['owner']] = $autoassign_owner;

                /* If owner selected not to be notified or invalid stop here */
                if (empty($autoassign_owner[$type])) {
                    return false;
                }
            }

            /* Set new language if required */
            $this->parent->common->setLanguage($autoassign_owner['language']);

            /* Format email subject and message for staff */
            $subject = $this->getEmailSubject($email_template, $ticket);
            $message = $this->getEmailMessage($email_template, $ticket, 1);

            /* Send email to staff */
            $this->mail($autoassign_owner['email'], $subject, $message);

            /* Reset language to original one */
            $this->parent->common->resetLanguage();

            return true;
        }

        public function notifyStaff($email_template, $sql_where, $is_ticket = 1) {
            global $hesk_settings, $ticket;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return true;
            }

            $admins = array();

            $res = $this->parent->database->query("SELECT `email`,`language`,`isadmin`,`categories` FROM `" . $this->parent->database->escape($hesk_settings['db_pfix']) . "users` WHERE $sql_where ORDER BY `language`");
            while ($myuser = $this->parent->database->fetchAssoc($res)) {
                /* Is this an administrator? */
                if ($myuser['isadmin']) {
                    $admins[] = array('email' => $myuser['email'], 'language' => $myuser['language']);
                    continue;
                }

                /* Not admin, is he/she allowed this category? */
                $myuser['categories'] = explode(',', $myuser['categories']);
                if (in_array($ticket['category'], $myuser['categories'])) {
                    $admins[] = array('email' => $myuser['email'], 'language' => $myuser['language']);
                    continue;
                }
            }

            if (count($admins) > 0) {
                /* Make sure each user gets email in his/her preferred language */
                $current_language = 'NONE';
                $recipients = array();

                /* Loop through staff */
                foreach ($admins as $admin) {
                    /* If admin language is NULL force default HESK language */
                    if (!$admin['language'] || !isset($hesk_settings['languages'][$admin['language']])) {
                        $admin['language'] = $this->parent->common->language;
                    }

                    /* Generate message or add email to the list of recepients */
                    if ($admin['language'] == $current_language) {
                        /* We already have the message, just add email to the recipients list */
                        $recipients[] = $admin['email'];
                    } else {
                        /* Send email messages in previous languages (if required) */
                        if ($current_language != 'NONE') {
                            /* Send e-mail to staff */
                            $this->mail(implode(',', $recipients), $subject, $message);

                            /* Reset list of email addresses */
                            $recipients = array();
                        }

                        /* Set new language */
                        $this->parent->common->setLanguage($admin['language']);

                        /* Format staff email subject and message for this language */
                        $subject = $this->getEmailSubject($email_template, $ticket);
                        $message = $this->getEmailMessage($email_template, $ticket, $is_ticket);

                        /* Add email to the recipients list */
                        $recipients[] = $admin['email'];

                        /* Remember the last processed language */
                        $current_language = $admin['language'];
                    }
                }

                /* Send email messages to the remaining staff */
                $this->mail(implode(',', $recipients), $subject, $message);

                /* Reset language to original one */
                $this->parent->common->resetLanguage();
            }

            return true;
        }

        public function validEmails() {
            return array(
                /*                 * * Emails sent to CLIENT ** */

                // --> Send reminder about existing tickets
                'forgot_ticket_id' => _('List of your support tickets'),
                // --> Staff replied to a ticket
                'new_reply_by_staff' => _('[#%%TRACK_ID%%] New reply to: %%SUBJECT%%'),
                // --> New ticket submitted
                'new_ticket' => _('[#%%TRACK_ID%%] Ticket received: %%SUBJECT%%'),
                /*                 * * Emails sent to STAFF ** */

                // --> Ticket moved to a new category
                'category_moved' => _('[#%%TRACK_ID%%] Ticket moved: %%SUBJECT%%'),
                // --> Client replied to a ticket
                'new_reply_by_customer' => _('[#%%TRACK_ID%%] New reply to: %%SUBJECT%%'),
                // --> New ticket submitted
                'new_ticket_staff' => _('[#%%TRACK_ID%%] New ticket: %%SUBJECT%%'),
                // --> New ticket assigned to staff
                'ticket_assigned_to_you' => _('[#%%TRACK_ID%%] Ticket assigned: %%SUBJECT%%'),
                // --> New private message
                'new_pm' => _('New private message: %%SUBJECT%%'),
                // --> New note by someone to a ticket assigned to you
                'new_note' => _('[#%%TRACK_ID%%] Note added to: %%SUBJECT%%'),
            );
        }

        public function mail($to, $subject, $message) {
            global $hesk_settings;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return true;
            }

            // We need to use RFC2822 date format for emails
            $save_format = $hesk_settings['timeformat'];
            $hesk_settings['timeformat'] = DATE_RFC2822;

            // Encode subject to UTF-8
            $subject = "=?UTF-8?B?" . base64_encode($this->parent->common->html_entity_decode($subject)) . "?=";

            // Setup "name <email>" for headers
            if ($hesk_settings['noreply_name']) {
                $hesk_settings['from_header'] = "=?UTF-8?B?" . base64_encode($this->parent->common->html_entity_decode($hesk_settings['noreply_name'])) . "?= <" . $hesk_settings['noreply_mail'] . ">";
            } else {
                $hesk_settings['from_header'] = $hesk_settings['noreply_mail'];
            }

            // Uncomment for debugging
            # echo "<p>TO: $to<br >SUBJECT: $subject<br >MSG: $message</p><p>DATE: ".date($hesk_settings['timeformat'])."</p><p>HESK DATE: ".$this->parent->common->_date()."</p><p>&nbsp;</p>";
            # return true;
            // Use PHP's mail function
            if (!$hesk_settings['smtp']) {
                // Set additional headers
                $headers = "From: $hesk_settings[from_header]\n";
                $headers.= "Reply-To: $hesk_settings[from_header]\n";
                $headers.= "Return-Path: $hesk_settings[webmaster_mail]\n";
                $headers.= "Date: " . $this->parent->common->_date() . "\n";
                $headers.= "Content-Type: text/plain; charset=" . $this->parent->encoding;

                // Send using PHP mail() function
                ob_start();
                mail($to, $subject, $message, $headers);
                $tmp = trim(ob_get_contents());
                ob_end_clean();

                return (strlen($tmp)) ? $tmp : true;
            }

            // Use a SMTP server directly instead
            $smtp = new smtp_class;
            $smtp->host_name = $hesk_settings['smtp_host_name'];
            $smtp->host_port = $hesk_settings['smtp_host_port'];
            $smtp->timeout = $hesk_settings['smtp_timeout'];
            $smtp->ssl = $hesk_settings['smtp_ssl'];
            $smtp->start_tls = $hesk_settings['smtp_tls'];
            $smtp->user = $hesk_settings['smtp_user'];
            $smtp->password = $this->parent->common->htmlspecialchars_decode($hesk_settings['smtp_password']);
            $smtp->debug = 1;

            // Start output buffering so that any errors don't break headers
            ob_start();

            // Send the e-mail using SMTP
            $to_arr = explode(',', $to);
            if (!$smtp->SendMessage($hesk_settings['noreply_mail'], $to_arr, array(
                        "From: $hesk_settings[from_header]",
                        "To: $to",
                        "Reply-To: $hesk_settings[from_header]",
                        "Return-Path: $hesk_settings[webmaster_mail]",
                        "Subject: " . $subject,
                        "Date: " . $this->parent->common->_date(),
                        "Content-Type: text/html; charset=" . $this->parent->encoding
                            ), $message)) {
                // Suppress errors unless we are in debug mode
                if ($hesk_settings['debug_mode']) {
                    $error = _('Cound not send the message to:') . ' ' . $to . '<br /><br />' .
                            _('Error') . ': ' . htmlspecialchars($smtp->error) . '<br /><br />' .
                            '<textarea name="smtp_log" rows="10" cols="60">' . ob_get_contents() . '</textarea>';
                    ob_end_clean();
                    $this->parent->common->_error($error);
                } else {
                    $_SESSION['HESK_2ND_NOTICE'] = true;
                    $_SESSION['HESK_2ND_MESSAGE'] = _('Could not send email notifications.') . ' ' . _('Please notify webmaster at') . ' <a href="mailto:' . $hesk_settings['webmaster_mail'] . '">' . $hesk_settings['webmaster_mail'] . '</a>';
                }
            }

            ob_end_clean();

            $hesk_settings['timeformat'] = $save_format;
            return true;
        }

        public function getEmailSubject($eml_file, $ticket = '', $is_ticket = 1, $strip = 0) {
            global $hesk_settings;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return '';
            }

            /* Get list of valid emails */
            $valid_emails = $this->validEmails();

            /* Verify this is a valid email include */
            if (!isset($valid_emails[$eml_file])) {
                $this->parent->common->_error(_('Invalid email file'));
            } else {
                $msg = $valid_emails[$eml_file];
            }

            /* If not a ticket-related email return subject as is */
            if (!$ticket) {
                return $msg;
            }

            /* Strip slashes from the subject only if it's a new ticket */
            if ($strip) {
                $ticket['subject'] = stripslashes($ticket['subject']);
            }

            /* Not a ticket, but has some info in the $ticket array */
            if (!$is_ticket) {
                return str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
            }

            /* Set category title */
            $ticket['category'] = $this->parent->common->msgToPlain($this->parent->common->getCategoryName($ticket['category']), 1);

            /* Get priority */
            switch ($ticket['priority']) {
                case 0:
                    $ticket['priority'] = _(' * Critical * ');
                    break;
                case 1:
                    $ticket['priority'] = _('High');
                    break;
                case 2:
                    $ticket['priority'] = _('Medium');
                    break;
                default:
                    $ticket['priority'] = _('Low');
            }

            /* Set status */
            switch ($ticket['status']) {
                case 1:
                    $ticket['status'] = _('Awaiting reply');
                    break;
                case 2:
                    $ticket['status'] = _('Replied');
                    break;
                case 3:
                    $ticket['status'] = _('Closed');
                    break;
                case 4:
                    $ticket['status'] = _('On the bench');
                    break;
                case 5:
                    $ticket['status'] = _('On hold');
                    break;
                case 6:
                    $ticket['status'] = _('Waiting for payment');
                    break;
                case 7:
                    $ticket['status'] = _('Waiting for bench');
                    break;
                case 8:
                    $ticket['status'] = _('Service call');
                    break;
                case 9:
                    $ticket['status'] = _('Remote support');
                    break;
                case 10:
                    $ticket['status'] = _('Ready for pickup');
                    break;
                default:
                    $ticket['status'] = _('New');
            }

            /* Replace all special tags */
            $msg = str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
            $msg = str_replace('%%TRACK_ID%%', $ticket['trackid'], $msg);
            $msg = str_replace('%%CATEGORY%%', $ticket['category'], $msg);
            $msg = str_replace('%%PRIORITY%%', $ticket['priority'], $msg);
            $msg = str_replace('%%STATUS%%', $ticket['status'], $msg);

            return $msg;
        }

        function getEmailMessage($eml_file, $ticket, $is_admin = 0, $is_ticket = 1, $just_message = 0) {
            global $hesk_settings;

            // Demo mode
            if (true == $this->parent->demo_mode) {
                return '';
            }

            /* Get list of valid emails */
            $valid_emails = $this->validEmails();

            /* Verify this is a valid email include */
            if (!isset($valid_emails[$eml_file])) {
                $this->parent->common->_error(_('Invalid email file'));
            }

            /* Get email template */
            $eml_file = 'locale/' . $hesk_settings['lang_folder'] . '/emails/' . $eml_file . '.txt';

            if (file_exists($this->parent->dir . $eml_file)) {
                $msg = file_get_contents($this->parent->dir . $eml_file);
            } else {
                $this->parent->common->_error(_('Missing email file') . ': ' . $eml_file);
            }

            /* Return just the message without any processing? */
            if ($just_message) {
                return $msg;
            }

            // Convert any entities in site title to plain text
            $hesk_settings['site_title'] = $this->parent->common->msgToPlain($hesk_settings['site_title'], 1);

            /* If it's not a ticket-related mail (like "a new PM") just process quickly */
            if (!$is_ticket) {
                $trackingURL = $hesk_settings['hesk_url'] . '/' . $hesk_settings['admin_dir'] . '/mail.php?a=read&id=' . intval($ticket['id']);

                $msg = str_replace('%%NAME%%', $ticket['name'], $msg);
                $msg = str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
                $msg = str_replace('%%TRACK_URL%%', $trackingURL, $msg);
                $msg = str_replace('%%SITE_TITLE%%', $hesk_settings['site_title'], $msg);
                $msg = str_replace('%%SITE_URL%%', $hesk_settings['site_url'], $msg);

                if (isset($ticket['message'])) {
                    return str_replace('%%MESSAGE%%', $ticket['message'], $msg);
                } else {
                    return $msg;
                }
            }

            // Is email required to view ticket (for customers only)?
            $hesk_settings['e_param'] = $hesk_settings['email_view_ticket'] ? '&e=' . rawurlencode($ticket['email']) : '';

            /* Generate the ticket URLs */
            $trackingURL = $hesk_settings['hesk_url'];
            $trackingURL.= $is_admin ? '/' . $hesk_settings['admin_dir'] . '/admin_ticket.php' : '/ticket.php';
            $trackingURL.= '?track=' . $ticket['trackid'] . ($is_admin ? '' : $hesk_settings['e_param']) . '&refresh=' . rand(10000, 99999);

            /* Set category title */
            $ticket['category'] = $this->parent->common->msgToPlain($this->parent->common->getCategoryName($ticket['category']), 1);

            /* Set priority title */
            switch ($ticket['priority']) {
                case 0:
                    $ticket['priority'] = _(' * Critical * ');
                    break;
                case 1:
                    $ticket['priority'] = _('High');
                    break;
                case 2:
                    $ticket['priority'] = _('Medium');
                    break;
                default:
                    $ticket['priority'] = _('Low');
            }

            /* Get owner name */
            $ticket['owner'] = $this->parent->common->msgToPlain($this->parent->common->getOwnerName($ticket['owner']), 1);

            /* Set status */
            switch ($ticket['status']) {
                case 1:
                    $ticket['status'] = _('Awaiting reply');
                    break;
                case 2:
                    $ticket['status'] = _('Replied');
                    break;
                case 3:
                    $ticket['status'] = _('Closed');
                    break;
                case 4:
                    $ticket['status'] = _('On the bench');
                    break;
                case 5:
                    $ticket['status'] = _('On hold');
                    break;
                case 6:
                    $ticket['status'] = _('Waiting for payment');
                    break;
                case 7:
                    $ticket['status'] = _('Waiting for bench');
                    break;
                case 8:
                    $ticket['status'] = _('Service call');
                    break;
                case 9:
                    $ticket['status'] = _('Remote support');
                    break;
                case 10:
                    $ticket['status'] = _('Ready for pickup');
                    break;
                default:
                    $ticket['status'] = _('New');
            }

            /* Replace all special tags */
            $msg = str_replace('%%NAME%%', $ticket['name'], $msg);
            $msg = str_replace('%%SUBJECT%%', $ticket['subject'], $msg);
            $msg = str_replace('%%TRACK_ID%%', $ticket['trackid'], $msg);
            $msg = str_replace('%%TRACK_URL%%', $trackingURL, $msg);
            $msg = str_replace('%%SITE_TITLE%%', $hesk_settings['site_title'], $msg);
            $msg = str_replace('%%SITE_URL%%', $hesk_settings['site_url'], $msg);
            $msg = str_replace('%%CATEGORY%%', $ticket['category'], $msg);
            $msg = str_replace('%%PRIORITY%%', $ticket['priority'], $msg);
            $msg = str_replace('%%OWNER%%', $ticket['owner'], $msg);
            $msg = str_replace('%%STATUS%%', $ticket['status'], $msg);
            $msg = str_replace('%%EMAIL%%', $ticket['email'], $msg);
            $msg = str_replace('%%CREATED%%', $ticket['dt'], $msg);
            $msg = str_replace('%%UPDATED%%', $ticket['lastchange'], $msg);

            /* All custom fields */
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use']) {
                    if ($v['type'] == 'checkbox') {
                        $ticket[$k] = str_replace("<br />", "\n", $ticket[$k]);
                    }

                    $msg = str_replace('%%' . strtoupper($k) . '%%', stripslashes($ticket[$k]), $msg);
                } else {
                    $msg = str_replace('%%' . strtoupper($k) . '%%', '', $msg);
                }
            }

            // Is message tag in email template?
            if (strpos($msg, '%%MESSAGE%%') !== false) {
                // Replace message
                $msg = str_replace('%%MESSAGE%%', $ticket['message'], $msg);

                // Add direct links to any attachments at the bottom of the email message
                if ($hesk_settings['attachments']['use'] && isset($ticket['attachments']) && strlen($ticket['attachments'])) {
                    $msg .= "\n\n\n" . _('Files attached to this message:');

                    $att = explode(',', substr($ticket['attachments'], 0, -1));
                    foreach ($att as $myatt) {
                        list($att_id, $att_name) = explode('#', $myatt);
                        $msg .= "\n\n" . $att_name . "\n" . $hesk_settings['hesk_url'] . '/download_attachment.php?att_id=' . $att_id . '&track=' . $ticket['trackid'] . $hesk_settings['e_param'];
                    }
                }

                // For customer notifications: if we allow email piping/pop 3 fetching and
                // stripping quoted replies add an "reply above this line" tag
                if (!$is_admin && ($hesk_settings['email_piping'] || $hesk_settings['pop3']) && $hesk_settings['strip_quoted']) {
                    $msg = _('------ Reply above this line ------') . "\n\n" . $msg;
                }
            }

            return $msg;
        }

    }

}

