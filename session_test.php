<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Session Test 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
error_reporting(E_ALL);

if (session_start()) {
    $_SESSION['test'] = 'TRUE';
    echo 'Session started successfully!<br>
    <a href="session_test2.php">CLICK HERE FOR PAGE 2</a>';
} else {
    echo "Session NOT started, check your server setup!";
}

exit();
?>