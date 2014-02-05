<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Session Test 2 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['test'])) {
    $_SESSION['test'] = 'FALSE';
}
echo '$_SESSION[\'test\'] is set to: <b></b>' . $_SESSION['test'];
exit();
?>