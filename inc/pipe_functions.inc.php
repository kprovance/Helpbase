<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Pipe Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbasePipe')) {
    class HelpbasePipe {
        private $helpbase = null;
        
        public function __construct($core){
            $this->helpbase = $core;
            
            $this->helpbase->load_email_functions();
            $this->helpbase->load_posting_functions();
            
            require($this->helpbase->includes . 'mail/rfc822_addresses.php');
            require($this->helpbase->includes . 'mail/mime_parser.php');
            require($this->helpbase->includes . 'mail/email_parser.php');
        }
        
        public function email2ticket($results, $pop3 = 0) {
            global $hesk_settings, $hesk_db_link, $ticket;

            // Process "From:" email
            $tmpvar['email'] = $this->helpbase->common->validateEmail($results['from'][0]['address'], 'ERR', 0);

            // "From:" email missing or invalid?
            if (!$tmpvar['email']) {
                return $this->cleanExit();
            }

            // Process "From:" name, convert to UTF-8, set to "[Customer]" if not set
            $tmpvar['name'] = isset($results['from'][0]['name']) ? $results['from'][0]['name'] : _('[Customer]');
            if (!empty($results['from'][0]['encoding'])) {
                $tmpvar['name'] = $this->encodeUTF8($tmpvar['name'], $results['from'][0]['encoding']);
            }
            $tmpvar['name'] = $this->helpbase->common->_input($tmpvar['name'], '', '', 1, 50) or $tmpvar['name'] = _('[Customer]');

            // Process "To:" email (not yet implemented, for future use)
            // $tmpvar['to_email']	= $this->helpbase->common->validateEmail($results['to'][0]['address'],'ERR',0);
            // Process email subject, convert to UTF-8, set to "[Piped email]" if none set
            $tmpvar['subject'] = isset($results['subject']) ? $results['subject'] : _('[Piped email]');
            if (!empty($results['subject_encoding'])) {
                $tmpvar['subject'] = $this->encodeUTF8($tmpvar['subject'], $results['subject_encoding']);
            }
            $tmpvar['subject'] = $this->helpbase->common->_input($tmpvar['subject'], '', '', 1, 70) or $tmpvar['subject'] = _('[Piped email]');

            // Process email message, convert to UTF-8
            $tmpvar['message'] = isset($results['message']) ? $results['message'] : '';
            if (!empty($results['encoding'])) {
                $tmpvar['message'] = $this->encodeUTF8($tmpvar['message'], $results['encoding']);
            }
            $tmpvar['message'] = $this->helpbase->common->_input($tmpvar['message'], '', '', 1);

            // Message missing? We require it!
            if (!$tmpvar['message']) {
                return $this->cleanExit();
            }

            // Strip quoted reply from email
            $tmpvar['message'] = $this->stripQuotedText($tmpvar['message']);

            // Convert URLs to links, change newlines to <br />
            $tmpvar['message'] = $this->helpbase->common->makeURL($tmpvar['message']);
            $tmpvar['message'] = nl2br($tmpvar['message']);

            # For debugging purposes
            # die( bin2hex($tmpvar['message']) );
            # die($tmpvar['message']);
            // Try to detect "delivery failed" and "noreply" emails - ignore if detected
            if ($this->isReturnedEmail($tmpvar)) {
                return $this->cleanExit();
            }

            // Check for email loops
            if ($this->isEmailLoop($tmpvar['email'], md5($tmpvar['message']))) {
                return $this->cleanExit();
            }

            // Set up database strings
            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $trackID = $this->helpbase->database->escape($tmpvar['trackid']);
            
            // OK, everything seems OK. Now determine if this is a reply to a ticket or a new ticket
            if (preg_match('/\[#([A-Z0-9]{3}\-[A-Z0-9]{3}\-[A-Z0-9]{4})\]/', $tmpvar['subject'], $matches)) {
                // We found a possible tracking ID
                $tmpvar['trackid'] = $matches[1];

                // Does it match one in the database?
                $res = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "tickets` WHERE `trackid`='" . $trackID . "' LIMIT 1");
                if ($this->helpbase->database->numRows($res)) {
                    $ticket = $this->helpbase->database->fetchAssoc($res);

                    // Do email addresses match?
                    if (strpos(strtolower($ticket['email']), strtolower($tmpvar['email'])) === false) {
                        $tmpvar['trackid'] = '';
                    }

                    // Is this ticket locked? Force create a new one if it is
                    if ($ticket['locked']) {
                        $tmpvar['trackid'] = '';
                    }
                } else {
                    $tmpvar['trackid'] = '';
                }
            }

            // If tracking ID is empty, generate a new one
            if (empty($tmpvar['trackid'])) {
                $tmpvar['trackid'] = $this->helpbase->common->createID();
                $is_reply = 0;
            } else {
                $is_reply = 1;
            }

            // Process attachments
            $tmpvar['attachmment_notices'] = '';
            $tmpvar['attachments'] = '';
            $num = 0;
            if ($hesk_settings['attachments']['use'] && isset($results['attachments'][0])) {
                #print_r($results['attachments']);

                foreach ($results['attachments'] as $k => $v) {

                    // Clean attachment names
                    $myatt['real_name'] = $this->helpbase->posting->cleanFileName($v['orig_name']);

                    // Check number of attachments, delete any over max number
                    if ($num >= $hesk_settings['attachments']['max_number']) {
                        $tmpvar['attachmment_notices'] .= sprintf(_('Max number reached: %s'), $myatt['real_name']) . "\n";
                        continue;
                    }

                    // Check file extension
                    $ext = strtolower(strrchr($myatt['real_name'], "."));
                    if (!in_array($ext, $hesk_settings['attachments']['allowed_types'])) {
                        $tmpvar['attachmment_notices'] .= sprintf(_('Type not allowed: %s'), $myatt['real_name']) . "\n";
                        continue;
                    }

                    // Check file size
                    $myatt['size'] = $v['size'];
                    if ($myatt['size'] > ($hesk_settings['attachments']['max_size'])) {
                        $tmpvar['attachmment_notices'] .= sprintf(_('File too large: %s'), $myatt['real_name']) . "\n";
                        continue;
                    }

                    // Generate a random file name
                    $useChars = 'AEUYBDGHJLMNPQRSTVWXZ123456789';
                    $tmp = $useChars{mt_rand(0, 29)};
                    for ($j = 1; $j < 10; $j++) {
                        $tmp .= $useChars{mt_rand(0, 29)};
                    }
                    $myatt['saved_name'] = substr($tmpvar['trackid'] . '_' . md5($tmp . $myatt['real_name']), 0, 200) . $ext;

                    // Rename the temporary file
                    rename($v['stored_name'], $this->helpbase->dir . $hesk_settings['attach_dir'] . '/' . $myatt['saved_name']);

                    // Insert into database
                    $this->helpbase->database->query("INSERT INTO `" . $prefix . "attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('" . $trackID . "','" . $this->helpbase->database->escape($myatt['saved_name']) . "','" . $this->helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
                    $tmpvar['attachments'] .= $this->helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';

                    $num++;
                }

                if (strlen($tmpvar['attachmment_notices'])) {
                    $tmpvar['message'] .= "<br /><br />" . $this->helpbase->common->_input(_('* Some attached files have been removed *'), '', '', 1) . "<br />" . nl2br($this->helpbase->common->_input($tmpvar['attachmment_notices'], '', '', 1));
                }
            }

            // Delete the temporary files
            deleteAll($results['tempdir']);

            // If this is a reply add a new reply
            if ($is_reply) {
                // Set last replier name to customer name
                $ticket['lastreplier'] = ($tmpvar['name'] == _('[Customer]')) ? $tmpvar['email'] : $tmpvar['name'];
                ;

                // If staff hasn't replied yet, keep ticket status "New", otherwise set it to "Waiting reply from staff"
                $ticket['status'] = $ticket['status'] ? 1 : 0;

                // Update ticket as necessary
                $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `lastchange`=NOW(),`status`='{$ticket['status']}',`lastreplier`='0' WHERE `id`='" . intval($ticket['id']) . "' LIMIT 1");

                // Insert reply into database
                $this->helpbase->database->query("INSERT INTO `" . $prefix . "replies` (`replyto`,`name`,`message`,`dt`,`attachments`) VALUES ('" . intval($ticket['id']) . "','" . $this->helpbase->database->escape($ticket['lastreplier']) . "','" . $this->helpbase->database->escape($tmpvar['message']) . "',NOW(),'" . $this->helpbase->database->escape($tmpvar['attachments']) . "')");

                // --> Prepare reply message
                // 1. Generate the array with ticket info that can be used in emails
                $info = array(
                    'email'         => $ticket['email'],
                    'homephone'     => $ticket['homephone'],
                    'mobilephone'   => $ticket['mobilephone'],
                    'workphone'     => $ticket['workphone'],
                    'category'      => $ticket['category'],
                    'priority'      => $ticket['priority'],
                    'owner'         => $ticket['owner'],
                    'trackid'       => $ticket['trackid'],
                    'status'        => $ticket['status'],
                    'devicetype'    => $ticket['devicetype'],
                    'devicebrand'   => $ticket['devicebrand'],
                    'deviceid'      => $ticket['deviceid'],
                    'name'          => $ticket['name'],
                    'company'       => $ticket['company'],
                    'lastreplier'   => $ticket['lastreplier'],
                    'subject'       => $ticket['subject'],
                    'message'       => stripslashes($tmpvar['message']),
                    'attachments'   => $tmpvar['attachments'],
                    'dt'            => $this->helpbase->common->_date($ticket['dt']),
                    'lastchange'    => $this->helpbase->common->_date($ticket['lastchange']),
                );

                // 2. Add custom fields to the array
                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    $info[$k] = $v['use'] ? $ticket[$k] : '';
                }

                // 3. Make sure all values are properly formatted for email
                $ticket = $this->helpbase->common->ticketToPlain($info, 1, 0);

                // --> Process custom fields before sending
                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    $ticket[$k] = $v['use'] ? $this->helpbase->common->msgToPlain($ticket[$k], 1) : '';
                }

                // --> If ticket is assigned just notify the owner
                if ($ticket['owner']) {
                    $this->helpbase->email->notifyAssignedStaff(false, 'new_reply_by_customer', 'notify_reply_my');
                }
                // --> No owner assigned, find and notify appropriate staff
                else {
                    $this->helpbase->email->notifyStaff('new_reply_by_customer', "`notify_reply_unassigned`='1'");
                }

                return $ticket['trackid'];
            } // END REPLY
            // Not a reply, but a new ticket. Add it to the database
            $tmpvar['category'] = 1;
            $tmpvar['priority'] = 3;
            $_SERVER['REMOTE_ADDR'] = _('Unknown');

            // Auto assign tickets if aplicable
            $tmpvar['owner'] = 0;
            $tmpvar['history'] = $pop3 ? sprintf(_('<li class="smaller">%s | submitted by POP3 fetching</li>'), $this->helpbase->common->_date()) : sprintf(_('<li class="smaller">%s | submitted by email piping</li>'), $this->helpbase->common->_date());

            $autoassign_owner = $this->helpbase->common->autoAssignTicket($tmpvar['category']);

            #print_r($autoassign_owner);

            if ($autoassign_owner) {
                $tmpvar['owner'] = $autoassign_owner['id'];
                $tmpvar['history'] .= sprintf(_('<li class="smaller">%s | automatically assigned to %s</li>'), $this->helpbase->common->_date(), $autoassign_owner['name'] . ' (' . $autoassign_owner['user'] . ')');
            }

            // Custom fields will be empty as there is no reliable way of detecting them
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                $tmpvar[$k] = '';
            }

            // Insert ticket to database
            $ticket = $this->helpbase->posting->newTicket($tmpvar);

            // Notify the customer
            $this->helpbase->email->notifyCustomer();

            // Need to notify staff?
            // --> From autoassign?
            if ($tmpvar['owner'] && $autoassign_owner['notify_assigned']) {
                $this->helpbase->email->notifyAssignedStaff($autoassign_owner, 'ticket_assigned_to_you');
            }
            // --> No autoassign, find and notify appropriate staff
            elseif (!$tmpvar['owner']) {
                $this->helpbase->email->notifyStaff('new_ticket_staff', " `notify_new_unassigned` = '1' ");
            }

            return $ticket['trackid'];
        }

        private function encodeUTF8($in, $encoding) {
            $encoding = strtoupper($encoding);

            switch ($encoding) {
                case 'UTF-8':
                    return $in;
                    break;
                case 'ISO-8859-1':
                    return utf8_encode($in);
                    break;
                default:
                    return iconv($encoding, 'UTF-8', $in);
                    break;
            }
        }

        private function stripQuotedText($message) {
            global $hesk_settings;

            // Stripping quoted text disabled?
            if (!$hesk_settings['strip_quoted']) {
                return $message;
            }

            // Loop through available languages and ty to find the tag
            foreach ($hesk_settings['languages'] as $language => $settings) {
                if (($found = strpos($message, $settings['hr']) ) !== false) {
                    // "Reply above this line" tag found, strip quoted reply
                    $message = substr($message, 0, $found);
                    $message .= "\n" . _('(quoted reply removed)');

                    // Set language to the detected language
                    $this->helpbase->common->setLanguage($language);
                    break;
                }
            }

            return $message;
        }

        private function isReturnedEmail($tmpvar) {
            // Check noreply email addresses
            if (preg_match('/not?[\-_]reply@/i', $tmpvar['email'])) {
                return true;
            }

            // Check mailer daemon email addresses
            if (preg_match('/mail(er)?[\-_]daemon@/i', $tmpvar['email'])) {
                return true;
            }

            // Check autoreply subjects
            if (preg_match('/^[\[\(]?Auto(mat(ic|ed))?[ \-]?reply/i', $tmpvar['subject'])) {
                return true;
            }

            // Check out of office subjects
            if (preg_match('/^Out of Office/i', $tmpvar['subject'])) {
                return true;
            }

            // Check delivery failed email subjects
            if (
                    preg_match('/DELIVERY FAILURE/i', $tmpvar['subject']) ||
                    preg_match('/Undelivered Mail Returned to Sender/i', $tmpvar['subject']) ||
                    preg_match('/Delivery Status Notification \(Failure\)/i', $tmpvar['subject']) ||
                    preg_match('/Returned mail\: see transcript for details/i', $tmpvar['subject'])
            ) {
                return true;
            }

            // Check Mail Delivery sender name
            if (preg_match('/Mail[ \-_]?Delivery/i', $tmpvar['name'])) {
                return true;
            }

            // Check Delivery failed message
            if (preg_match('/postmaster@/i', $tmpvar['email']) && preg_match('/Delivery has failed to these recipients/i', $tmpvar['message'])) {
                return true;
            }

            // No pattern detected, seems like this is not a returned email
            return false;
        }

        private function isEmailLoop($email, $message_hash) {
            global $hesk_settings, $hesk_db_link;

            // If $hesk_settings['loop_hits'] is set to 0 this function is disabled
            if (!$hesk_settings['loop_hits']) {
                return false;
            }

            // Escape wildcards in email
            $like = $this->helpbase->database->like($email);
            $email_like = $this->helpbase->database->escape($like);

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            // Delete expired DB entries
            $this->helpbase->database->query("DELETE FROM `" . $prefix . "pipe_loops` WHERE `dt` < (NOW() - INTERVAL " . intval($hesk_settings['loop_time']) . " SECOND) ");

            // Check current entry
            $res = $this->helpbase->database->query("SELECT `hits`, `message_hash` FROM `" . $prefix . "pipe_loops` WHERE `email` LIKE '{$email_like}' LIMIT 1");

            // Any active entry*
            if ($this->helpbase->database->numRows($res)) {
                list($num, $md5) = $this->helpbase->database->fetchRow($res);

                $num++;

                // Number of emails in a time period reached?
                if ($num >= $hesk_settings['loop_hits']) {
                    return true;
                }

                // Message exactly the same as in previous email?
                if ($message_hash == $md5) {
                    return true;
                }

                // Update DB entry
                $this->helpbase->database->query("UPDATE `" . $prefix . "pipe_loops` SET `hits` = `hits` + 1, `message_hash` = '" . $this->helpbase->database->escape($message_hash) . "' WHERE `email` LIKE '{$email_like}' LIMIT 1");
            } else {
                // First instance, insert a new database row
                $this->helpbase->database->query("INSERT INTO `" . $prefix . "pipe_loops` (`email`, `message_hash`) VALUES ('" . $this->helpbase->database->escape($email) . "', '" . $this->helpbase->database->escape($message_hash) . "')");
            }

            // No loop rule trigered
            return false;
        }

        private function cleanExit() {
            global $results;

            // Delete the temporary files
            deleteAll($results['tempdir']);

            // Return NULL
            return NULL;
        }
    }
}