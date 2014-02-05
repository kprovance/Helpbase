<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Ratings 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

// Is rating enabled?
if (!$hesk_settings['rating']) {
    die(_('Rating has been disabled'));
}

// Rating value
$rating = intval($helpbase->common->_get('rating', 0));

// Rating can only be 1 or 5
if ($rating != 1 && $rating != 5) {
    die(_('Invalid attempt!'));
}

// Reply ID
$reply_id = intval($helpbase->common->_get('id', 0)) or die(_('Invalid attempt!'));

// Ticket tracking ID
$trackingID = $helpbase->common->cleanID() or die(_('Invalid attempt!'));

// Get reply info to verify tickets match
$result = $helpbase->database->query("SELECT `replyto`,`rating`,`staffid` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `id`='{$reply_id}' LIMIT 1");
$reply = $helpbase->database->fetchAssoc($result);

// Does the ticket ID match the one in the request?
$result = $helpbase->database->query("SELECT `trackid` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `id`='{$reply['replyto']}' LIMIT 1");
// -> Ticket found?
if ($helpbase->database->numRows($result) != 1) {
    die(_('Invalid attempt!'));
}
// -> Does the tracking ID match?
$ticket = $helpbase->database->fetchAssoc($result);
if ($ticket['trackid'] != $trackingID) {
    die(_('Invalid attempt!'));
}

// OK, tracking ID matches. Now check if this reply has already been rated
if (!empty($reply['rating'])) {
    die(_('Already rated'));
}

// Update reply rating
$helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` SET `rating`='{$rating}' WHERE `id`='{$reply_id}' LIMIT 1");

// Also update staff rating
$helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET `rating`=((`rating`*(`ratingpos`+`ratingneg`))+{$rating})/(`ratingpos`+`ratingneg`+1), " .
        ($rating == 5 ? '`ratingpos`=`ratingpos`+1 ' : '`ratingneg`=`ratingneg`+1 ') .
        "WHERE `id`='{$reply['staffid']}'");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header('Content-type: text/plain');
if ($rating == 5) {
    echo _('Rated as <i>helpful</i>');
} else {
    echo _('Rated as <i>not helpful</i>');
}

unset($helpbase);

exit();
?>
