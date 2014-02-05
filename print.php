<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Print 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */


global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

/* Get the tracking ID */
$trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

/* Verify email address match if needed */
if (empty($_SESSION['id'])) {
    $helpbase->common->verifyEmailMatch($trackingID);
}

/* Get ticket info */
$res = $helpbase->database->query("SELECT `t1`.* , `t2`.name AS `repliername`
					FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` AS `t1` LEFT JOIN `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` AS `t2` ON `t1`.`replierid` = `t2`.`id`
					WHERE `trackid`='" . $helpbase->database->escape($trackingID) . "' LIMIT 1");

if ($helpbase->database->numRows($res) != 1) {
    $helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
}
$ticket = $helpbase->database->fetchAssoc($res);

// Demo mode
if (true == $helpbase->demo_mode) {
    $ticket['email'] = 'hidden@demo.com';
    $ticket['ip'] = '127.0.0.1';
}

/* Get category name and ID */
$res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='{$ticket['category']}' LIMIT 1");

/* If this category has been deleted use the default category with ID 1 */
if ($helpbase->database->numRows($res) != 1) {
    $res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='1' LIMIT 1");
}
$category = $helpbase->database->fetchAssoc($res);

/* Get replies */
$res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `replyto`='{$ticket['id']}' ORDER BY `id` ASC");
$replies = $helpbase->database->numRows($res);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
    <head>
        <title><?php echo $hesk_settings['hesk_title']; ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $helpbase->encoding; ?>">
        <style type="text/css">
            body, table, td, p
            {
                color : black;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-size : <?php echo $hesk_settings['print_font_size']; ?>px;
            }
            table
            {
                border-collapse:collapse;
            }
            hr
            {
                border: 0;
                color: #9e9e9e;
                background-color: #9e9e9e;
                height: 1px;
                width: 100%;
                text-align: left;
            }
        </style>
    </head>
    <body onload="window.print()">

<?php
/* Ticket status */
switch ($ticket['status']) {
    case 0:
        $ticket['status'] = _('New');
        break;
    case 1:
        $ticket['status'] = _('Awaiting reply from staff');
        break;
    case 2:
        $ticket['status'] = _('Awaiting reply from customer');
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
        $ticket['status'] = _('Closed');
}

/* Ticket priority */
switch ($ticket['priority']) {
    case 0:
        $ticket['priority'] = '<b>' . _(' * Critical * ') . '</b>';
        break;
    case 1:
        $ticket['priority'] = '<b>' . _('High') . '</b>';
        break;
    case 2:
        $ticket['priority'] = _('Medium');
        break;
    default:
        $ticket['priority'] = _('Low');
}

/* Set last replier name */
if ($ticket['lastreplier']) {
    if (empty($ticket['repliername'])) {
        $ticket['repliername'] = _('Staff');
    }
} else {
    $ticket['repliername'] = $ticket['name'];
}

/* Other variables that need processing */
$ticket['dt'] = $helpbase->common->_date($ticket['dt']);
$ticket['lastchange'] = $helpbase->common->_date($ticket['lastchange']);
$random = mt_rand(10000, 99999);

// Print ticket head
echo '
<table border="0">
<tr>
	<td>' . _('Subject') . ':</td>
	<td><b>' . $ticket['subject'] . '</b></td>
</tr>
<tr>
	<td>' . _('Tracking ID') . ':</td>
	<td>' . $trackingID . '</td>
</tr>
<tr>
	<td>' . _('Ticket status') . ':</td>
	<td>' . $ticket['status'] . '</td>
</tr>
<tr>
	<td>' . _('Created on') . ':</td>
	<td>' . $ticket['dt'] . '</td>
</tr>
<tr>
	<td>' . _('Updated') . ':</td>
	<td>' . $ticket['lastchange'] . '</td>
</tr>
';

// Assigned to?
if ($ticket['owner'] && !empty($_SESSION['id'])) {
    $ticket['owner'] = $helpbase->common->getOwnerName($ticket['owner']);
    echo'
	<tr>
		<td>' . _('Assigned to:') . '</td>
		<td>' . $ticket['owner'] . '</td>
	</tr>
	';
}

// Continue with ticket head
echo '
<tr>
	<td>' . _('Last replier') . ':</td>
	<td>' . $ticket['repliername'] . '</td>
</tr>
<tr>
	<td>' . _('Category') . ':</td>
	<td>' . $category['name'] . '</td>
</tr>
';

// Show IP and time worked to staff
if (!empty($_SESSION['id'])) {
    echo '
	<tr>
		<td>' . _('Time worked') . ':</td>
		<td>' . $ticket['time_worked'] . '</td>
	</tr>
	<tr>
		<td>' . _('IP') . ':</td>
		<td>' . $ticket['ip'] . '</td>
	</tr>
	<tr>
		<td>' . _('Email') . ':</td>
		<td>' . $ticket['email'] . '</td>
	</tr>
	';
}

echo '
	<tr>
		<td>' . _('Name') . ':</td>
		<td>' . $ticket['name'] . '</td>
	</tr>
	<tr>
		<td>' . _('Company') . ':</td>
		<td>' . $ticket['company'] . '</td>
	</tr>
	<tr>
		<td>' . _('Home phone') . ':</td>
		<td>' . $ticket['homephone'] . '</td>
	</tr>
	<tr>
		<td>' . _('Mobile phone') . ':</td>
		<td>' . $ticket['mobilephone'] . '</td>
	</tr>
	<tr>
		<td>' . _('Work phone') . ':</td>
		<td>' . $ticket['workphone'] . '</td>
	</tr>
    ';

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k => $v) {
    if ($v['use']) {
        ?>
            <tr>
                <td><?php echo $v['name']; ?>:</td>
                <td><?php echo $helpbase->common->unshortenUrl($ticket[$k]); ?></td>
            </tr>
                <?php
            }
        }

        echo '	
        <tr>
		<td>' . _('Device type') . ':</td>
		<td>' . $ticket['devicetype'] . '</td>
	</tr>
        <tr>
		<td>' . _('Device brand') . ':</td>
		<td>' . $ticket['devicebrand'] . '</td>
	</tr>
        <tr>
		<td>' . _('Device ID') . ':</td>
		<td>' . $ticket['deviceid'] . '</td>
	</tr>
     ';


// Close ticket head table
        echo '</table>';

// Print initial ticket message
        echo '<p>' . $helpbase->common->unshortenUrl($ticket['message']) . '</p>';

// Print replies
        while ($reply = $helpbase->database->fetchAssoc($res)) {
            $reply['dt'] = $helpbase->common->_date($reply['dt']);

            echo '
    <hr />

	<table border="0">
	<tr>
		<td>' . _('Date') . ':</td>
		<td>' . $reply['dt'] . '</td>
	</tr>
	<tr>
		<td>' . _('Name') . ':</td>
		<td>' . $reply['name'] . '</td>
	</tr>
	</table>

    <p>' . $helpbase->common->unshortenUrl($reply['message']) . '</p>
    ';
        }

// Print "end of ticket" message
        echo _('--- End of ticket ---');
        ?>

</body>
</html>

<?php

unset($helpbase);

?>