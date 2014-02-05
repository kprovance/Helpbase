<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Download Attachment 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

// Knowledgebase attachments
if (isset($_GET['kb_att'])) {
    // Attachment ID
    $att_id = intval($helpbase->common->_get('kb_att')) or $helpbase->common->_error(_('This is not a valid ID'));

    // Get attachment info
    $res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` WHERE `att_id`='{$att_id}' LIMIT 1");
    if ($helpbase->database->numRows($res) != 1) {
        $helpbase->common->_error(_('This is not a valid ID') . ' (att_id)');
    }
    $file = $helpbase->database->fetchAssoc($res);

    // Is this person allowed access to this attachment?
    $res = $helpbase->database->query("SELECT `t1`.`type` as `cat_type`, `t2`.`type` as `art_type`
						FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` AS `t2`
                        JOIN `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` AS `t1`
                        ON `t2`.`catid` = `t1`.`id`
                        WHERE (`t2`.`attachments` LIKE '{$att_id}#%' OR `t2`.`attachments` LIKE '%,{$att_id}#%' )
                        LIMIT 1");

    // If no attachment found, throw an error
    if ($helpbase->database->numRows($res) != 1) {
        $helpbase->common->_error(_('This is not a valid ID') . ' ' . _('No article.'));
    }
    $row = $helpbase->database->fetchAssoc($res);

    // Private or draft article or category?
    if ($row['cat_type'] || $row['art_type']) {
        if (empty($_SESSION['id'])) {
            // This is a staff-only attachment
            $helpbase->common->_error(_('You don\'t have access to this attachment.'));
        } elseif ($row['art_type'] == 2) {
            // Need permission to manage KB to access draft attachments
            $helpbase->admin->checkPermission('can_man_kb');
        }
    }
} else {
    // Ticket attachments
    
    // Attachmend ID and ticket tracking ID
    $att_id = intval($helpbase->common->_get('att_id', 0)) or die(_('This is not a valid ID'));
    $tic_id = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

    // Get attachment info
    $res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "attachments` WHERE `att_id`='{$att_id}' LIMIT 1");
    if ($helpbase->database->numRows($res) != 1) {
        $helpbase->common->_error(_('This is not a valid ID') . ' (att_id)');
    }
    $file = $helpbase->database->fetchAssoc($res);

    // Is ticket ID valid for this attachment?
    if ($file['ticket_id'] != $tic_id) {
        $helpbase->common->_error(_('Tracking ID not found'));
    }

    // Verify email address match if needed
    if (empty($_SESSION['id'])) {
        $helpbase->common->verifyEmailMatch($tic_id);
    }
}

// Path of the file on the server
$realpath = $hesk_settings['attach_dir'] . '/' . $file['saved_name'];

// Perhaps the file has been deleted?
if (!file_exists($realpath)) {
    $helpbase->common->_error(_('This file has been deleted from the server and is no longer available for download'));
}

// Send the file as an attachment to prevent malicious code from executing
header("Pragma: "); # To fix a bug in IE when running https
header("Cache-Control: "); # To fix a bug in IE when running https
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Length: ' . $file['size']);
header('Content-Disposition: attachment; filename=' . $file['real_name']);

// For larger files use chunks, smaller ones can be read all at once
$chunksize = 1048576; // = 1024 * 1024 (1 Mb)
if ($file['size'] > $chunksize) {
    $handle = fopen($realpath, 'rb');
    $buffer = '';
    while (!feof($handle)) {
        set_time_limit(300);
        $buffer = fread($handle, $chunksize);
        echo $buffer;
        flush();
    }
    fclose($handle);
} else {
    readfile($realpath);
}

unset($helpbase);

exit();
?>
