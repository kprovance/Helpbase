<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Print Security Image 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

require($helpbase->includes . 'secimg.inc.php');

$helpbase->common->session_start();

$_SESSION['secnum']   = rand(10000,99999);
$_SESSION['checksum'] = sha1($_SESSION['secnum'] . $hesk_settings['secimg_sum']);

/* This will make sure the security image is not cached */
header("expires: -1");
header("cache-control: no-cache, no-store, must-revalidate, max-age=-1");
header("cache-control: post-check=0, pre-check=0", false);
header("pragma: no-store,no-cache");

$sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
$sc->printImage($_SESSION['secnum']);

unset($helpbase);

exit();
?>
