<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Reply Ticket 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

$helpbase->load_email_functions();
require($helpbase->includes . 'posting_functions.inc.php');

// We only allow POST requests to this file
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    unset ($helpbase);
    exit();
}

// Check for POST requests larger than what the server can handle
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $helpbase->common->_error(_('You probably tried to submit more data than this server accepts.<br /><br />Please try submitting the form again with smaller or no attachments.'));
}

$helpbase->common->session_start();

/* A security check */
# $helpbase->common->token_check('POST');

$hesk_error_buffer = array();

// Tracking ID
$trackingID = $helpbase->common->cleanID('orig_track') or die(_('Internal script error') . ': No orig_track');

// Email required to view ticket?
$my_email = $helpbase->common->getCustomerEmail();

// Get message
$message = $helpbase->common->_input($helpbase->common->_post('message'));

// If the message was entered, further parse it
if (strlen($message)) {
    // Make links clickable
    $message = $helpbase->common->makeURL($message);

    // Turn newlines into <br />
    $message = nl2br($message);
} else {
    $hesk_error_buffer[] = _('Please enter your message');
}

/* Attachments */
if ($hesk_settings['attachments']['use']) {
    require($helpbase->includes . 'attachments.inc.php');
    $attachments = array();
    for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
        $att = hesk_uploadFile($i);
        if ($att !== false && !empty($att)) {
            $attachments[$i] = $att;
        }
    }
}
$myattachments = '';

/* Any errors? */
if (count($hesk_error_buffer) != 0) {
    $_SESSION['ticket_message'] = $helpbase->common->_post('message');

    // If this was a reply after re-opening a ticket, force the form at the top
    if ($helpbase->common->_post('reopen') == 1) {
        $_SESSION['force_form_top'] = true;
    }

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
    $helpbase->common->process_messages($hesk_error_buffer, 'ticket.php?track=' . $trackingID . $hesk_settings['e_param'] . '&refresh=' . rand(10000, 99999));
}

/* Get details about the original ticket */
$res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid`='{$trackingID}' LIMIT 1");
if ($helpbase->database->numRows($res) != 1) {
    $helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
}
$ticket = $helpbase->database->fetchAssoc($res);

/* If we require e-mail to view tickets check if it matches the one in database */
$helpbase->common->verifyEmailMatch($trackingID, $my_email, $ticket['email']);

/* Ticket locked? */
if ($ticket['locked']) {
    $helpbase->common->process_messages(_('This ticket has been locked, you cannot post a reply.'), 'ticket.php?track=' . $trackingID . $hesk_settings['e_param'] . '&refresh=' . rand(10000, 99999));
    exit();
}

/* Insert attachments */
if ($hesk_settings['attachments']['use'] && !empty($attachments)) {
    foreach ($attachments as $myatt) {
        $helpbase->database->query("INSERT INTO `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('{$trackingID}','" . $helpbase->database->escape($myatt['saved_name']) . "','" . $helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
        $myattachments .= $helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
    }
}

// If staff hasn't replied yet, keep ticket status "New", otherwise set it to "Waiting reply from staff"
$ticket['status'] = $ticket['status'] ? 1 : 0;

/* Update ticket as necessary */
$res = $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `lastchange`=NOW(), `status`='{$ticket['status']}',`lastreplier`='0' WHERE `id`='{$ticket['id']}' LIMIT 1");

// Insert reply into database
$helpbase->database->query("INSERT INTO `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` (`replyto`,`name`,`message`,`dt`,`attachments`) VALUES ({$ticket['id']},'" . $helpbase->database->escape($ticket['name']) . "','" . $helpbase->database->escape($message) . "',NOW(),'" . $helpbase->database->escape($myattachments) . "')");


/* * * Need to notify any staff? ** */

// --> Prepare reply message
// 1. Generate the array with ticket info that can be used in emails

$email      = isset($ticket['email']) ? $ticket['email'] : '';
$homephone  = isset($ticket['homephone']) ? $ticket['homephone'] : '';
$mobile     = isset($ticket['mobilephone']) ? $ticket['mobilephone'] : '';
$work       = isset($ticket['workphone']) ? $ticket['workphone'] : '';
$category   = isset($ticket['category']) ? $ticket['category'] : '';
$priority   = isset($ticket['priority']) ? $ticket['priority'] : '';
$owner      = isset($ticket['owner']) ? $ticket['owner'] : '';
$trackID    = isset($ticket['trackid']) ? $ticket['trackid'] : '';
$name       = isset($ticket['name']) ? $ticket['name'] : '';
$company    = isset($ticket['company']) ? $ticket['company'] : '';
$dev_type   = isset($ticket['devicetype']) ? $ticket['devicetype'] : '';
$dev_brand  = isset($ticket['devicebrand']) ? $ticket['devicebrand'] : '';
$dev_id     = isset($ticket['deviceid']) ? $ticket['deviceid'] : '';
$name       = isset($ticket['name']) ? $ticket['name'] : '';
$subject    = isset($ticket['subject']) ? $ticket['subject'] : '';

$info = array(
    'email'         => $email ,
    'homephone'     => $homephone,
    'mobilephone'   => $mobile,
    'workphone'     => $work,
    'category'      => $category,
    'priority'      => $priority,
    'owner'         => $owner,
    'trackid'       => $trackID,
    'status'        => $ticket['status'],
    'name'          => $name,
    'company'       => $company,
    'devicetype'    => $dev_type,
    'devicebrand'   => $dev_brand,
    'deviceid'      => $dev_id,
    'lastreplier'   => $name,
    'subject'       => $subject,
    'message'       => stripslashes($message),
    'attachments'   => $myattachments,
    'dt'            => $helpbase->common->_date($ticket['dt']),
    'lastchange'    => $helpbase->common->_date($ticket['lastchange']),
);

// 2. Add custom fields to the array
foreach ($hesk_settings['custom_fields'] as $k => $v) {
    $info[$k] = $v['use'] ? $ticket[$k] : '';
}

// 3. Make sure all values are properly formatted for email
$ticket = $helpbase->common->ticketToPlain($info, 1, 0);

// --> If ticket is assigned just notify the owner
if ($ticket['owner']) {
    $helpbase->email->notifyAssignedStaff(false, 'new_reply_by_customer', 'notify_reply_my');
}
// --> No owner assigned, find and notify appropriate staff
else {
    $helpbase->email->notifyStaff('new_reply_by_customer', "`notify_reply_unassigned`='1'");
}

/* Clear unneeded session variables */
$helpbase->common->cleanSessionVars('ticket_message');

/* Show the ticket and the success message */
$helpbase->common->process_messages(_('Your reply to this ticket has been successfully submitted'), 'ticket.php?track=' . $trackingID . $hesk_settings['e_param'] . '&refresh=' . rand(10000, 99999), 'SUCCESS');

unset($helpbase);

exit();
?>
