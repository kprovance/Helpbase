#!/usr/bin/php -q
<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Email
 * @subpackage  Pipe
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

$root = dirname(dirname(dirname(__FILE__))) . '/';

include_once($root . 'helpbase.class.php');
$helpbase = new HelpbaseCore(false);

// Is this feature enabled?
if (empty($hesk_settings['email_piping'])) {
    die(_('[HELPBASE] EMAIL PIPING IS DISABLED IN SETTINGS'));
}

// Email piping is enabled, get other required includes
require($helpbase->includes . 'pipe_functions.inc.php');
$pipe = new HelpbasePipe($helpbase);

// Parse the incoming email
$results = parser();

// Convert email into a ticket (or new reply)
$pipe->email2ticket($results);

unset ($pipe);
unset ($helpbase);

return NULL;
