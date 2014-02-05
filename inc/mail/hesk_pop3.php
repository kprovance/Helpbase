#!/usr/bin/php -q
<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Email 
 * @subpackage  POP3
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
if (empty($hesk_settings['pop3'])) {
    die(_('[HELPBASE] POP3 FETCHING IS DISABLED IN SETTINGS'));
}

// Email piping is enabled, get other required includes
require($helpbase->includes . 'pipe_functions.inc.php');
$pipe = new HelpbasePipe($helpbase);

// Get POP3 class
require($helpbase->includes . 'mail/pop3.php');

// Uncomment when using SASL authentication mechanisms
# require($helpbase->includes . 'mail/sasl/sasl.php');
// If a pop3 wrapper is registered un register it, we need our custom wrapper
if (in_array('pop3', stream_get_wrappers())) {
    stream_wrapper_unregister('pop3');
}

// Register the pop3 stream handler class
stream_wrapper_register('pop3', 'pop3_stream');

// Setup required variables
$pop3                                   = new pop3_class;
$pop3->hostname                         = $hesk_settings['pop3_host_name'];
$pop3->port                             = $hesk_settings['pop3_host_port'];
$pop3->tls                              = $hesk_settings['pop3_tls'];
$pop3->debug                            = 0;
$pop3->join_continuation_header_lines   = 1;

// Connect to POP3
if (($error = $pop3->Open()) == "") {
    echo $hesk_settings['debug_mode'] ? "<pre>Connected to the POP3 server &quot;" . $pop3->hostname . "&quot;.</pre>\n" : '';

    // Authenticate
    if (($error = $pop3->Login($hesk_settings['pop3_user'], $helpbase->common->htmlspecialchars_decode($hesk_settings['pop3_password']))) == "") {
        echo $hesk_settings['debug_mode'] ? "<pre>User &quot;" . $hesk_settings['pop3_user'] . "&quot; logged in.</pre>\n" : '';

        // Get number of messages and total size
        if (($error = $pop3->Statistics($messages, $size)) == "") {
            echo $hesk_settings['debug_mode'] ? "<pre>There are $messages messages in the mail box with a total of $size bytes.</pre>\n" : '';

            // If we have any messages, process them
            if ($messages > 0) {
                for ($message = 1; $message <= $messages; $message++) {
                    echo $hesk_settings['debug_mode'] ? "<pre>Parsing message $message of $messages.</pre>\n" : '';

                    $pop3->GetConnectionName($connection_name);
                    $message_file = 'pop3://' . $connection_name . '/' . $message;

                    // Parse the incoming email
                    $results = parser($message_file);

                    // Convert email into a ticket (or new reply)
                    if ($id = $pipe->email2ticket($results, 1)) {
                        echo $hesk_settings['debug_mode'] ? "<pre>Ticket $id created/updated.</pre>\n" : '';
                    } else {
                        echo $hesk_settings['debug_mode'] ? "<pre>Ticket NOT inserted - may be duplicate, blocked or an error.</pre>\n" : '';
                    }

                    // Queue message to be deleted on connection close
                    if (!$hesk_settings['pop3_keep']) {
                        $pop3->DeleteMessage($message);
                    }

                    echo $hesk_settings['debug_mode'] ? "<br /><br />\n\n" : '';
                }
            }

            // Disconnect from the server - this also deletes queued messages
            if ($error == "" && ($error = $pop3->Close()) == "") {
                echo $hesk_settings['debug_mode'] ? "<pre>Disconnected from the POP3 server &quot;" . $pop3->hostname . "&quot;.</pre>\n" : '';
            }
        }
    }
}

// Any error messages?
if ($error != '') {
    echo "<h2>Error: " . $helpbase->common->htmlspecialchars($error) . "</h2>";
}

unset ($pipe);
unset ($helpbase);

return NULL;
