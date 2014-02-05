<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Manage Category
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('../helpbase.class.php');
$helpbase = new HelpbaseCore(true);

$helpbase->load_email_functions();

$helpbase->admin->isLoggedIn();

/* Check permissions for this feature */
$helpbase->admin->checkPermission('can_change_cat');

/* A security check */
$helpbase->common->token_check('POST');

/* Ticket ID */
$trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

/* Category ID */
$category = intval($helpbase->common->_post('category', -1));
if ($category < 1) {
    $helpbase->common->process_messages(_('Select the new Category'), 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999), 'NOTICE');
}

/* Get new category details */
$res = $helpbase->database->query("SELECT `name`,`autoassign` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='{$category}' LIMIT 1");
if ($helpbase->database->numRows($res) != 1) {
    $helpbase->common->_error(_('Internal script error') . ': ' . _('Invalid category'));
}
$row = $helpbase->database->fetchAssoc($res);

/* Should tickets in new category be auto-assigned if necessary? */
if (!$row['autoassign']) {
    $hesk_settings['autoassign'] = false;
}

/* Is user allowed to view tickets in new category? */
$category_ok = $helpbase->admin->okCategory($category, 0);

/* Get details about the original ticket */
$res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid`='" . $helpbase->database->escape($trackingID) . "' LIMIT 1");
if ($helpbase->database->numRows($res) != 1) {
    $helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
}
$ticket = $helpbase->database->fetchAssoc($res);

/* Log that ticket is being moved */
$history = sprintf(_('<li class="smaller">%s | moved to category %s by %s</li>'), $helpbase->common->_date(), $row['name'], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

/* Is the ticket assigned to someone? If yes, check that the user has access to category or change to unassigned */
$need_to_reassign = 0;
if ($ticket['owner']) {
    if ($ticket['owner'] == $_SESSION['id'] && !$category_ok) {
        $need_to_reassign = 1;
    } else {
        $res = $helpbase->database->query("SELECT `isadmin`,`categories` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . intval($ticket['owner']) . "' LIMIT 1");
        if ($helpbase->database->numRows($res) != 1) {
            $need_to_reassign = 1;
        } else {
            $tmp = $helpbase->database->fetchAssoc($res);
            if (!$helpbase->admin->okCategory($category, 0, $tmp['isadmin'], explode(',', $tmp['categories']))) {
                $need_to_reassign = 1;
            }
        }
    }
}

/* Reassign automatically if possible */
if ($need_to_reassign || !$ticket['owner']) {
    $need_to_reassign = 1;
    $autoassign_owner = $helpbase->common->autoAssignTicket($category);
    if ($autoassign_owner) {
        $ticket['owner'] = $autoassign_owner['id'];
        $history .= sprintf(_('<li class="smaller">%s | automatically assigned to %s</li>'), $helpbase->common->_date(), $autoassign_owner['name'] . ' (' . $autoassign_owner['user'] . ')');
    } else {
        $ticket['owner'] = 0;
    }
}

$helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `category`='" . intval($category) . "', `owner`='" . intval($ticket['owner']) . "' , `history`=CONCAT(`history`,'" . $helpbase->database->escape($history) . "') WHERE `trackid`='" . $helpbase->database->escape($trackingID) . "' LIMIT 1");

$ticket['category'] = $category;

/* --> Prepare message */

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
    'name'          => $ticket['name'],
    'devicetype'    => $ticket['devicetype'],
    'devicebrand'   => $ticket['devicebrand'],
    'deviceid'      => $ticket['deviceid'],
    'company'       => $ticket['company'],
    'lastreplier'   => $ticket['lastreplier'],
    'subject'       => $ticket['subject'],
    'message'       => $ticket['message'],
    'attachments'   => $ticket['attachments'],
    'dt'            => $helpbase->common->_date($ticket['dt']),
    'lastchange'    => $helpbase->common->_date($ticket['lastchange']),
);

// 2. Add custom fields to the array
foreach ($hesk_settings['custom_fields'] as $k => $v) {
    $info[$k] = $v['use'] ? $ticket[$k] : '';
}

// 3. Make sure all values are properly formatted for email
$ticket = $helpbase->common->ticketToPlain($info, 1, 0);

/* Need to notify any staff? */
/* --> From autoassign? */
if ($need_to_reassign && !empty($autoassign_owner['email'])) {
    $helpbase->email->notifyAssignedStaff($autoassign_owner, 'ticket_assigned_to_you');
}
/* --> No autoassign, find and notify appropriate staff */ elseif (!$ticket['owner']) {
    $helpbase->email->notifyStaff('category_moved', "`notify_new_unassigned`='1' AND `id`!=" . intval($_SESSION['id']));
}

/* Is the user allowed to view tickets in the new category? */
$move_to = _('This ticket has been moved to the new category');
if ($category_ok) {
    /* Ticket has an owner */
    if ($ticket['owner']) {
        /* Staff is owner or can view tickets assigned to others */
        if ($ticket['owner'] == $_SESSION['id'] || $helpbase->admin->checkPermission('can_view_ass_others', 0)) {
            $helpbase->common->process_messages($move_to, 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999), 'SUCCESS');
        } else {
            $helpbase->common->process_messages($move_to, 'admin_main.php', 'SUCCESS');
        }
    }
    /* Ticket is unassigned, staff can view unassigned tickets */ elseif ($helpbase->admin->checkPermission('can_view_unassigned', 0)) {
        $helpbase->common->process_messages($move_to, 'admin_ticket.php?track=' . $trackingID . '&refresh=' . rand(10000, 99999), 'SUCCESS');
    }
    /* Ticket is unassigned, staff cannot view unassigned tickets */ else {
        $helpbase->common->process_messages($move_to, 'admin_main.php', 'SUCCESS');
    }
} else {
    $helpbase->common->process_messages($move_to, 'admin_main.php', 'SUCCESS');
}

unset ($helpbase);

?>
