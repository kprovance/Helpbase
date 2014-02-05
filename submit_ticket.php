<?php
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Submit Ticket 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

$helpbase->load_email_functions();
$helpbase->load_posting_functions();

// We only allow POST requests to this file
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php?a=add');
    unset ($helpbase);
    exit();
}

// Check for POST requests larger than what the server can handle
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $helpbase->common->_error(_('You probably tried to submit more data than this server accepts.<br /><br />Please try submitting the form again with smaller or no attachments.'));
}

// Block obvious spammers trying to inject email headers
if (preg_match("/\n|\r|\t|%0A|%0D|%08|%09/", $helpbase->common->_post('name') . $helpbase->common->_post('subject'))) {
    header('HTTP/1.1 403 Forbidden');
    unset ($helpbase);
    exit();
}

// A security check - not needed here, but uncomment if you require it
# $helpbase->common->token_check();
// Prevent submitting multiple tickets by reloading submit_ticket.php page
if (isset($_SESSION['already_submitted'])) {
    hesk_forceStop();
}

$hesk_error_buffer = array();

// Check anti-SPAM question
if ($hesk_settings['question_use']) {
    $question = $helpbase->common->_input($helpbase->common->_post('question'));

    //if (empty($question))
    if (strlen($question) == 0) {
        $hesk_error_buffer['question'] = _('Please answer the anti-SPAM question');
    } elseif (strtolower($question) != strtolower($hesk_settings['question_ans'])) {
        $hesk_error_buffer['question'] = _('Wrong anti-SPAM answer');
    } else {
        $_SESSION['c_question'] = $question;
    }
}

// Check anti-SPAM image
if ($hesk_settings['secimg_use'] && !isset($_SESSION['img_verified'])) {
    // Using ReCaptcha?
    if ($hesk_settings['recaptcha_use']) {
        require($helpbase->includes . 'recaptcha/recaptchalib.php');

        $resp = recaptcha_check_answer($hesk_settings['recaptcha_private_key'], $_SERVER['REMOTE_ADDR'], $helpbase->common->_post('recaptcha_challenge_field', ''), $helpbase->common->_post('recaptcha_response_field', '')
        );
        if ($resp->is_valid) {
            $_SESSION['img_verified'] = true;
        } else {
            $hesk_error_buffer['mysecnum'] = _('Incorrect SPAM Prevention answer, please try again.');
        }
    }
    // Using PHP generated image
    else {
        $mysecnum = intval($helpbase->common->_post('mysecnum', 0));

        if (empty($mysecnum)) {
            $hesk_error_buffer['mysecnum'] = _('Please enter the security number');
        } else {
            require($helpbase->includes . 'secimg.inc.php');
            $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
            if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                $_SESSION['img_verified'] = true;
            } else {
                $hesk_error_buffer['mysecnum'] = _('Wrong security number');
            }
        }
    }
}

$tmpvar['name'] = $helpbase->common->_input($helpbase->common->_post('name')) or $hesk_error_buffer['name'] = _('Please enter your name');
$tmpvar['company'] = $helpbase->common->_input($helpbase->common->_post('company'));
$tmpvar['homephone'] = $helpbase->common->_input($helpbase->common->_post('homephone'));
$tmpvar['mobilephone'] = $helpbase->common->_input($helpbase->common->_post('mobilephone'));
$tmpvar['workphone'] = $helpbase->common->_input($helpbase->common->_post('workphone'));
$tmpvar['devicetype'] = $helpbase->common->_input($helpbase->common->_post('devicetype'));
$tmpvar['devicebrand'] = $helpbase->common->_input($helpbase->common->_post('devicebrand'));
$tmpvar['deviceid'] = $helpbase->common->_input($helpbase->common->_post('deviceid'));
$tmpvar['email'] = $helpbase->common->validateEmail($helpbase->common->_post('email'), 'ERR', 0) or $hesk_error_buffer['email'] = _('Please enter a valid email address');

if ($hesk_settings['confirm_email']) {
    $tmpvar['email2'] = $helpbase->common->_input($helpbase->common->_post('email2')) or $hesk_error_buffer['email2'] = _('Please confirm your Email address');

    if (strlen($tmpvar['email2']) && ( strtolower($tmpvar['email']) != strtolower($tmpvar['email2']) )) {
        $tmpvar['email2'] = '';
        $_POST['email2'] = '';
        $_SESSION['c_email2'] = '';
        $_SESSION['isnotice'][] = 'email';
        $hesk_error_buffer['email2'] = _('The two email addresses are not identical');
    } else {
        $_SESSION['c_email2'] = $_POST['email2'];
    }
}

$tmpvar['category'] = intval($helpbase->common->_post('category')) or $hesk_error_buffer['category'] = _('Please select the appropriate category');
$tmpvar['priority'] = $hesk_settings['cust_urgency'] ? intval($helpbase->common->_post('priority')) : 3;

// Is priority a valid choice?
if ($tmpvar['priority'] < 1 || $tmpvar['priority'] > 3) {
    $hesk_error_buffer['priority'] = _('Please select the appropriate priority');
}

$tmpvar['subject'] = $helpbase->common->_input($helpbase->common->_post('subject')) or $hesk_error_buffer['subject'] = _('Please enter your ticket subject');
$tmpvar['message'] = $helpbase->common->_input($helpbase->common->_post('message')) or $hesk_error_buffer['message'] = _('Please enter your message');

// Is category a valid choice?
if ($tmpvar['category']) {
    $helpbase->posting->verifyCategory();

    // Is auto-assign of tickets disabled in this category?
    if (empty($hesk_settings['category_data'][$tmpvar['category']]['autoassign'])) {
        $hesk_settings['autoassign'] = false;
    }
}

// Custom fields
foreach ($hesk_settings['custom_fields'] as $k => $v) {
    if ($v['use']) {
        if ($v['type'] == 'checkbox') {
            $tmpvar[$k] = '';

            if (isset($_POST[$k])) {
                if (is_array($_POST[$k])) {
                    foreach ($_POST[$k] as $myCB) {
                        $tmpvar[$k] .= ( is_array($myCB) ? '' : $helpbase->common->_input($myCB) ) . '<br />';
                        ;
                    }
                    $tmpvar[$k] = substr($tmpvar[$k], 0, -6);
                }
            } else {
                if ($v['req']) {
                    $hesk_error_buffer[$k] = _('Missing required field') . ': ' . $v['name'];
                }
                $_POST[$k] = '';
            }
        } elseif ($v['req']) {
            $tmpvar[$k] = $helpbase->common->makeURL(nl2br($helpbase->common->_input($helpbase->common->_post($k))));
            if (!strlen($tmpvar[$k])) {
                $hesk_error_buffer[$k] = _('Missing required field') . ': ' . $v['name'];
            }
        } else {
            $tmpvar[$k] = $helpbase->common->makeURL(nl2br($helpbase->common->_input($helpbase->common->_post($k))));
        }
        $_SESSION["c_$k"] = $helpbase->common->_post($k);
    } else {
        $tmpvar[$k] = '';
    }
}

// Check maximum open tickets limit
$below_limit = true;
if ($hesk_settings['max_open'] && !isset($hesk_error_buffer['email'])) {
    $res = $helpbase->database->query("SELECT COUNT(*) FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `status` IN ('0', '1', '2', '4', '5') AND " . $helpbase->database->formatEmail($tmpvar['email']));
    $num = $helpbase->database->result($res);

    if ($num >= $hesk_settings['max_open']) {
        $hesk_error_buffer = array('max_open' => sprintf(_('You have reached maximum open tickets (%d of %d).  Please wait until your existing tickets are resolved before opening new tickets.'), $num, $hesk_settings['max_open']));
        $below_limit = false;
    }
}

// If we reached max tickets let's save some resources
if ($below_limit) {
    // Generate tracking ID
    $tmpvar['trackid'] = $helpbase->common->createID();

    // Attachments
    if ($hesk_settings['attachments']['use']) {
        require_once($helpbase->includes . 'attachments.inc.php');

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
}

// If we have any errors lets store info in session to avoid re-typing everything
if (count($hesk_error_buffer)) {
    $_SESSION['iserror'] = array_keys($hesk_error_buffer);

    $_SESSION['c_name'] = $helpbase->common->_post('name');
    $_SESSION['c_homephone'] = $helpbase->common->_post('homephone');
    $_SESSION['c_mobilephone'] = $helpbase->common->_post('mobilephone');
    $_SESSION['c_workphone'] = $helpbase->common->_post('workphone');
    $_SESSION['c_email'] = $helpbase->common->_post('email');
    $_SESSION['c_company'] = $helpbase->common->_post('company');
    $_SESSION['c_devicetype'] = $helpbase->common->_post('devicetype');
    $_SESSION['c_devicebrand'] = $helpbase->common->_post('devicebrand');
    $_SESSION['c_deviceid'] = $helpbase->common->_post('deviceid');
    $_SESSION['c_category'] = $helpbase->common->_post('category');
    $_SESSION['c_priority'] = $helpbase->common->_post('priority');
    $_SESSION['c_subject'] = $helpbase->common->_post('subject');
    $_SESSION['c_message'] = $helpbase->common->_post('message');

    $tmp = '';
    foreach ($hesk_error_buffer as $error) {
        $tmp .= "<li>$error</li>\n";
    }

    // Remove any successfully uploaded attachments
    if ($below_limit && $hesk_settings['attachments']['use']) {
        hesk_removeAttachments($attachments);
    }

    $hesk_error_buffer = _('Please correct the following errors:') . '<br /><br /><ul>' . $tmp . '</ul>';
    $helpbase->common->process_messages($hesk_error_buffer, 'index.php?a=add');
}

$tmpvar['message'] = $helpbase->common->makeURL($tmpvar['message']);
$tmpvar['message'] = nl2br($tmpvar['message']);

// All good now, continue with ticket creation
$tmpvar['owner'] = 0;
$tmpvar['history'] = sprintf(_('<li class="smaller">%s | submitted by %s</li>'), $helpbase->common->_date(), $tmpvar['name']);

// Auto assign tickets if aplicable
$autoassign_owner = $helpbase->common->autoAssignTicket($tmpvar['category']);
if ($autoassign_owner) {
    $tmpvar['owner'] = $autoassign_owner['id'];
    $tmpvar['history'] .= sprintf(_('<li class="smaller">%s | automatically assigned to %s</li>'), $helpbase->common->_date(), $autoassign_owner['name'] . ' (' . $autoassign_owner['user'] . ')');
}

// Insert attachments
if ($hesk_settings['attachments']['use'] && !empty($attachments)) {
    foreach ($attachments as $myatt) {
        $helpbase->database->query("INSERT INTO `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "attachments` (`ticket_id`,`saved_name`,`real_name`,`size`) VALUES ('" . $helpbase->database->escape($tmpvar['trackid']) . "','" . $helpbase->database->escape($myatt['saved_name']) . "','" . $helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
        $tmpvar['attachments'] .= $helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
    }
}

// Insert ticket to database
$ticket = $helpbase->posting->newTicket($tmpvar);

// Notify the customer
$helpbase->email->notifyCustomer();

// Need to notify staff?
// --> From autoassign?
if ($tmpvar['owner'] && $autoassign_owner['notify_assigned']) {
    $helpbase->email->notifyAssignedStaff($autoassign_owner, 'ticket_assigned_to_you');
}
// --> No autoassign, find and notify appropriate staff
elseif (!$tmpvar['owner']) {
    $helpbase->email->notifyStaff('new_ticket_staff', " `notify_new_unassigned` = '1' ");
}

// Next ticket show suggested articles again
$_SESSION['ARTICLES_SUGGESTED'] = false;
$_SESSION['already_submitted'] = 1;

// Need email to view ticket? If yes, remember it by default
if ($hesk_settings['email_view_ticket']) {
    setcookie('hesk_myemail', $tmpvar['email'], strtotime('+1 year'));
}

// Unset temporary variables
unset($tmpvar);
$helpbase->common->cleanSessionVars('c_company');
$helpbase->common->cleanSessionVars('c_devicetype');
$helpbase->common->cleanSessionVars('c_devicebrand');
$helpbase->common->cleanSessionVars('c_deviceid');
$helpbase->common->cleanSessionVars('c_homephone');
$helpbase->common->cleanSessionVars('c_mobilephone');
$helpbase->common->cleanSessionVars('c_workphone');
$helpbase->common->cleanSessionVars('tmpvar');
$helpbase->common->cleanSessionVars('c_category');
$helpbase->common->cleanSessionVars('c_priority');
$helpbase->common->cleanSessionVars('c_subject');
$helpbase->common->cleanSessionVars('c_message');
$helpbase->common->cleanSessionVars('c_question');
$helpbase->common->cleanSessionVars('img_verified');

// Print header
$helpbase->header->render();
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
        <td class="headersm"><?php $helpbase->common->showTopBar(_('Ticket submitted')); ?></td>
        <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
    </tr>
</table>

<table width="100%" border="0" cellspacing="0" cellpadding="3">
    <tr>
        <td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                &gt; <?php echo _('Ticket submitted'); ?></span></td>
    </tr>
</table>

</td>
</tr>
<tr>
    <td>

        <p>&nbsp;</p>

<?php
// Show success message with link to ticket
$helpbase->common->show_success(
        _('Ticket submitted') . '<br /><br />' .
        _('Your ticket has been successfully submitted! Ticket ID') . ': <b>' . $ticket['trackid'] . '</b><br /><br />
	<a href="' . $hesk_settings['hesk_url'] . '/ticket.php?track=' . $ticket['trackid'] . '">' . _('View your ticket') . '</a>'
);

// Any other messages to display?
$helpbase->common->handle_messages();
?>

        <p>&nbsp;</p>

        <?php
        $helpbase->footer->render();

        unset($helpbase);

        exit();

        function hesk_forceStop() {
            ?>
    <html>
        <head>
            <meta http-equiv="Refresh" content="0; url=index.php?a=add" />
        </head>
        <body>
            <p><a href="index.php?a=add"><?php echo _('Click to continue'); ?></a>.</p>
        </body>
    </html>
    <?php
    exit();
}

// END hesk_forceStop()
?>
