<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Attachments
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

/* * *************************
  Function hesk_uploadFiles()
 * ************************* */

function hesk_uploadFile($i) {
    global $hesk_settings, $trackingID, $hesk_error_buffer;

    /* Return if name is empty */
    if (empty($_FILES['attachment']['name'][$i])) {
        return '';
    }

    /* Parse the name */
    $file_realname = cleanFileName($_FILES['attachment']['name'][$i]);

    /* Check file extension */
    $ext = strtolower(strrchr($file_realname, "."));
    if (!in_array($ext, $hesk_settings['attachments']['allowed_types'])) {
        return hesk_fileError(sprintf(_('Files ending with <b>%s</b> are not accepted (%s)'), $ext, $file_realname));
    }

    /* Check file size */
    if ($_FILES['attachment']['size'][$i] > $hesk_settings['attachments']['max_size']) {
        return hesk_fileError(sprintf(_('Your file %s is too large'), $file_realname));
    } else {
        $file_size = $_FILES['attachment']['size'][$i];
    }

    /* Generate a random file name */
    $useChars = 'AEUYBDGHJLMNPQRSTVWXZ123456789';
    $tmp = uniqid();
    for ($j = 1; $j < 10; $j++) {
        $tmp .= $useChars{mt_rand(0, 29)};
    }

    if (true == $helpbase->article_attach) {
        $file_name = substr(md5($tmp . $file_realname), 0, 200) . $ext;
    } else {
        $file_name = substr($trackingID . '_' . md5($tmp . $file_realname), 0, 200) . $ext;
    }

    // Does the temporary file exist? If not, probably server-side configuration limits have been reached
    // Uncomment this for debugging purposes
    /*
      if ( ! file_exists($_FILES['attachment']['tmp_name'][$i]) )
      {
      return hesk_fileError(_('File upload failed, try with a smaller or no file attachment.'));
      }
     */

    /* If upload was successful let's create the headers */
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'][$i], dirname(dirname(__FILE__)) . '/' . $hesk_settings['attach_dir'] . '/' . $file_name)) {
        return hesk_fileError(_('Cannot move file to the attachments folder'));
    }

    $info = array(
        'saved_name' => $file_name,
        'real_name' => $file_realname,
        'size' => $file_size
    );

    return $info;
}

// End hesk_uploadFile()

function hesk_fileError($error) {
    global $hesk_settings, $trackingID;
    global $hesk_error_buffer;

    $hesk_error_buffer['attachments'] = $error;

    return false;
}

// End hesk_fileError()

function hesk_removeAttachments($attachments) {
    global $hesk_settings, $helpbase;

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)) . '/' . $hesk_settings['attach_dir'] . '/';

    foreach ($attachments as $myatt) {
        $helpbase->common->unlink($hesk_settings['server_path'] . $myatt['saved_name']);
    }

    return true;
}

// End hesk_removeAttachments()
