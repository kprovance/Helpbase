<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Manage Ticket Priority
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('../helpbase.class.php');
$helpbase = new HelpbaseCore(true);

$helpbase->admin->isLoggedIn();

/* Check permissions for this feature */
$helpbase->admin->checkPermission('can_view_tickets');
$helpbase->admin->checkPermission('can_reply_tickets');

/* A security check */
$helpbase->common->token_check('POST');

/* Ticket ID */
$trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

$priority   = intval( $helpbase->common->_post('priority') );
if ($priority < 0 || $priority > 3)
{
	$helpbase->common->process_messages(_('Select the new Priority'),'admin_ticket.php?track='.$trackingID.'&refresh='.mt_rand(10000,99999),'NOTICE');
}

$options = array(
	0 => '<font class="critical">' . _(' * Critical * ') . '</font>',
	1 => '<font class="important">' . _('High') . '</font>',
	2 => '<font class="medium">' . _('Medium') . '</font>',
	3 => _('Low')
);

$revision = sprintf(_('<li class="smaller">%s | priority changed to %s by %s</li>'),$helpbase->common->_date(),$options[$priority],$_SESSION['name'].' ('.$_SESSION['user'].')');

$helpbase->database->query("UPDATE `".$helpbase->database->escape($hesk_settings['db_pfix'])."tickets` SET `priority`='{$priority}', `history`=CONCAT(`history`,'".$helpbase->database->escape($revision)."') WHERE `trackid`='".$helpbase->database->escape($trackingID)."' LIMIT 1");
if ($helpbase->database->affectedRows() != 1)
{
	$helpbase->common->process_messages(_('Select the new Priority'),'admin_ticket.php?track='.$trackingID.'&refresh='.mt_rand(10000,99999),'NOTICE');
}

$helpbase->common->process_messages(sprintf(_('Ticket\'s priority has been changed to %s'),$options[$priority]),'admin_ticket.php?track='.$trackingID.'&refresh='.mt_rand(10000,99999),'SUCCESS');

unset($helpbase);

?>
