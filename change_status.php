<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Change Ticket Status 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);


// A security check
$helpbase->common->token_check();

// Get the tracking ID
$trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

// Get new status
$status = intval($helpbase->common->_get('s', 0));

$locked = 0;

if ($status == 3) { // Closed
    $action = _('Closed');
    $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $helpbase->common->_date(), _('Customer'));

    if ($hesk_settings['custopen'] != 1) {
        $locked = 1;
    }
} elseif ($status == 2) { // Opened
    // Is customer reopening tickets enabled?
    if (!$hesk_settings['custopen']) {
        $helpbase->common->_error(_('Invalid attempt!'));
    }

    $action = _('opened');
    $revision = sprintf(_('<li class="smaller">%s | opened by %s</li>'), $helpbase->common->_date(), _('Customer'));

    // We will ask the customer why is the ticket being reopened
    $_SESSION['force_form_top'] = true;
} else {
    unset($helpbase);
    die(_('Internal script error') . ': ' . _('Status not valid'));
}

// Verify email address match if needed
$helpbase->common->verifyEmailMatch($trackingID);

// Modify values in the database
$helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `status`='{$status}', `locked`='{$locked}', `history`=CONCAT(`history`,'" . $helpbase->database->escape($revision) . "') WHERE `trackid`='" . $helpbase->database->escape($trackingID) . "' AND `locked` != '1' LIMIT 1");

// Did we modify anything*
if ($helpbase->database->affectedRows() != 1) {
    $helpbase->common->_error(_('This ticket has been locked or deleted.'));
}

// Show success message
if ($status == 2) {
    $helpbase->common->process_messages(_('Please write a reply after re-opening the ticket.'), 'ticket.php?track=' . $trackingID . $hesk_settings['e_param'] . '&refresh=' . rand(10000, 99999), 'NOTICE');
} else {
    $helpbase->common->process_messages(_('Your ticket has been') . ' ' . $action, 'ticket.php?track=' . $trackingID . $hesk_settings['e_param'] . '&refresh=' . rand(10000, 99999), 'SUCCESS');
}

unset($helpbase);