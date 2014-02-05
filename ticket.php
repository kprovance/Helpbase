<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if(!class_exists('HelpbaseTickets')) {
    class HelpbaseTickets {
        private $checked        = '';
        private $display        = 'none';
        private $error_buffer   = array();
        private $dbResult       = '';
        private $tracking_id    = '';
        private $ticket         = '';
        private $category       = '';
        private $replies        = '';
        private $unread_replies = array();
        private $my_email       = '';
        private $reply_form     = false;

        public function __construct(){
            global $helpbase, $hesk_settings;

            include_once('helpbase.class.php');
            $helpbase = new HelpbaseCore(false);
            $helpbase->no_robots = true;

            /* Was this accessed by the form or link? */
            $is_form = isset($_GET['f']) ? 1 : 0;

            /* Get the tracking ID */
            $this->tracking_id = $helpbase->common->cleanID();

            /* Email required to view ticket? */
            $this->my_email = $helpbase->common->getCustomerEmail(1);

            /* A message from ticket reminder? */
            if (!empty($_GET['remind'])) {
                $this->display = 'block';
                $this->print_form();
            }

            /* Any errors? Show the form */
            if ($is_form) {
                if (empty($this->tracking_id)) {
                    $this->error_buffer[] = _('Enter your ticket tracking ID.');
                }

                if ($hesk_settings['email_view_ticket'] && empty($this->my_email)) {
                    $this->error_buffer[] = _('Please enter a valid email address');
                }

                $tmp = count($this->error_buffer);
                if ($tmp == 1) {
                    $this->error_buffer = implode('', $this->error_buffer);
                    $helpbase->common->process_messages($this->error_buffer, 'NOREDIRECT');
                    $this->print_form();
                } elseif ($tmp == 2) {
                    $this->error_buffer = _('Please correct the following errors:') . '<br /><br /><ul><li>' . $this->error_buffer[0] . '</li><li>' . $this->error_buffer[1] . '</li></ul>';
                    $helpbase->common->process_messages($this->error_buffer, 'NOREDIRECT');
                    $this->print_form();
                }
            } elseif (empty($this->tracking_id) || ( $hesk_settings['email_view_ticket'] && empty($this->my_email) )) {
                $this->print_form();
            }

            /* Limit brute force attempts */
            $helpbase->common->limitBfAttempts();

            /* Get ticket info */
            $dbPrefix = $hesk_settings['db_pfix'];
            $dbEscape = $helpbase->database->escape($dbPrefix);
            //echo $dbPrefix;
            //echo $dbEscape;

            $res = $helpbase->database->query("SELECT `t1`.* , `t2`.name AS `repliername` FROM `" . $dbEscape . "tickets` AS `t1` LEFT JOIN `" . $dbEscape . "users` AS `t2` ON `t1`.`replierid` = `t2`.`id` WHERE `trackid`='" . $helpbase->database->escape($this->tracking_id) . "' LIMIT 1");

            /* Ticket found? */
            if ($helpbase->database->numRows($res) != 1) {
                /* Ticket not found, perhaps it was merged with another ticket? */
                $res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `merged` LIKE '%#" . $helpbase->database->escape($this->tracking_id) . "#%' LIMIT 1");

                if ($helpbase->database->numRows($res) == 1) {
                    /* OK, found in a merged ticket. Get info */
                    $this->ticket = $helpbase->database->fetchAssoc($res);

                    /* If we require e-mail to view tickets check if it matches the one from merged ticket */
                    if ($helpbase->common->verifyEmailMatch($this->ticket['trackid'], $this->my_email, $this->ticket['email'], 0)) {
                        $helpbase->common->process_messages(sprintf(_('Ticket %s has been merged with this ticket (%s).'), $this->tracking_id, $this->ticket['trackid']), 'NOREDIRECT', 'NOTICE');
                        $this->tracking_id = $this->ticket['trackid'];
                    } else {
                        $helpbase->common->process_messages(sprintf(_('Ticket %s has been merged with ticket %s'), $this->tracking_id, $this->ticket['trackid']) . '<br /><br />' . sprintf(_('To access ticket %s enter associated email address.'), $this->ticket['trackid']), 'NOREDIRECT', 'NOTICE');
                        $this->tracking_id = $this->ticket['trackid'];
                        $this->print_form();
                    }
                } else {
                    /* Nothing found, error out */
                    $helpbase->common->process_messages(_('Ticket not found! Please make sure you have entered the correct tracking ID!'), 'NOREDIRECT');
                    $this->print_form();
                }
            } else {
                /* We have a match, get ticket info */
                $this->ticket = $helpbase->database->fetchAssoc($res);

                /* If we require e-mail to view tickets check if it matches the one in database */
                $helpbase->common->verifyEmailMatch($this->tracking_id, $this->my_email, $this->ticket['email']);
            }

            /* Ticket exists, clean brute force attempts */
            $helpbase->common->cleanBfAttempts();

            /* Remember email address? */
            if ($is_form) {
                if (!empty($_GET['r'])) {
                    setcookie('hesk_myemail', $this->my_email, strtotime('+1 year'));
                    $this->checked = ' checked="checked" ';
                } elseif (isset($_COOKIE['hesk_myemail'])) {
                    setcookie('hesk_myemail', '');
                }
            }

            /* Set last replier name */
            if ($this->ticket['lastreplier']) {
                if (empty($this->ticket['repliername'])) {
                    $this->ticket['repliername'] = _('Staff');
                }
            } else {
                $this->ticket['repliername'] = $this->ticket['name'];
            }

            /* Get category name and ID */
            $this->dbResult = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='" . intval($this->ticket['category']) . "' LIMIT 1");

            /* If this category has been deleted use the default category with ID 1 */
            if ($helpbase->database->numRows($this->dbResult) != 1) {
                $this->dbResult = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='1' LIMIT 1");
            }

            $this->category = $helpbase->database->fetchAssoc($this->dbResult);

            /* Get replies */
            $this->dbResult = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `replyto`='" . intval($this->ticket['id']) . "' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC'));
            $this->replies = $helpbase->database->numRows($this->dbResult);

            // Demo mode
            if (true == $helpbase->demo_mode) {
                $this->ticket['email'] = 'hidden@demo.com';
            }

            // Render HTML
            $this->render();
        }

        private function render(){
            global $helpbase, $hesk_settings;

            /* Print header */
            $helpbase->header->render();
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                                <td class="headersm"><?php $helpbase->common->showTopBar(_('Case Tracking ID') . ': ' . $this->tracking_id); ?></td>
                                <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                            </tr>
                        </table>

                        <table width="100%" border="0" cellspacing="0" cellpadding="3">
                            <tr>
                                <td>
                                    <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                                        <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                                        &gt; <?php echo _('Your ticket'); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $helpbase->common->handle_messages();

            /*
             * If the ticket has been reopened by customer:
             * - show the "Add a reply" form on top
             * - and ask them why the form has been reopened
             */
            if (isset($_SESSION['force_form_top'])) {
                $this->printCustomerReplyForm(1);
                echo ' <p>&nbsp;</p> ';

                unset($_SESSION['force_form_top']);
            }
?>

                        <h3 style="text-align:center"><?php echo $this->ticket['subject']; ?></h3>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <!-- START TICKET HEAD -->

                                    <table border="0" cellspacing="1" cellpadding="1">
<?php
            if ($hesk_settings['sequential']) {
                echo '
                                        <tr>
                                            <td>' . _('Tracking ID') . ': </td>
                                            <td>' . $this->tracking_id . ' (' . _('Ticket number') . ': ' . $this->ticket['id'] . ')</td>
                                        </tr>';
            } else {
                echo '
                                        <tr>
                                            <td>' . _('Tracking ID') . ': </td>
                                            <td>' . $this->tracking_id . '</td>
                                        </tr>';
            }

            echo '
                                        <tr>
                                            <td>' . _('Ticket status') . ': </td>
                                            <td>';
            $random = rand(10000, 99999);

            switch ($this->ticket['status']) {
                case 0:
                    echo '
                                                <font class="open">' . _('New') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 1:
                    echo '
                                                <font class="replied">' . _('Awaiting reply from staff') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 2:
                    echo '
                                                <font class="waitingreply">' . _('Awaiting reply from customer') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 4:
                    echo '
                                                <font class="inprogress">' . _('On the bench') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 5:
                    echo '
                                                <font class="onhold">' . _('On hold') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 6:
                    echo '
                                                <font class="waitforpayment">' . _('Waiting for payment') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 7:
                    echo '
                                                <font class="waitingforbench">' . _('Waiting for bench') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 8:
                    echo '
                                                <font class="servicecall">' . _('Service call') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 9:
                    echo '
                                                <font class="remotesupport">' . _('Remote support') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                case 10:
                    echo '
                                                <font class="readyforpickup">' . _('Ready for pickup') . '</font>'; // [<a href="change_status.php?track='.$this->tracking_id.$hesk_settings['e_query'].'&amp;s=3&amp;refresh='.$random.'&amp;token='.$helpbase->common->token_echo(0).'">'.$hesklang['close_action'].'</a>]';
                break;

                default:
                    echo '
                                                <font class="resolved">' . _('Closed') . '</font>';

                    if ($this->ticket['locked'] != 1 && $hesk_settings['custopen']) {
                        echo '
                                                [<a href="change_status.php?track=' . $this->tracking_id . $hesk_settings['e_query'] . '&amp;s=2&amp;refresh=' . $random . '&amp;token=' . $helpbase->common->token_echo(0) . '">' . _('Open ticket') . '</a>]';
                    }
            }

            echo '
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Created on') . ': </td>
                                            <td>' . $helpbase->common->_date($this->ticket['dt']) . '</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Updated') . ': </td>
                                            <td>' . $helpbase->common->_date($this->ticket['lastchange']) . '</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Last replier') . ': </td>
                                            <td>' . $this->ticket['repliername'] . '</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Category') . ': </td>
                                            <td>' . $this->category['name'] . '</td>
                                        </tr>
                                        <tr>
                                            <td>' . _('Replies') . ': </td>
                                            <td>' . $this->replies . '</td>
                                        </tr>';

            if ($hesk_settings['cust_urgency']) {
                echo '
                                        <tr>
                                            <td>' . _('Priority') . ': </td>
                                            <td>';
                if ($this->ticket['priority'] == 0) {
                    echo '
                                                <font class="critical">' . _(' * Critical * ') . '</font>';
                } elseif ($this->ticket['priority'] == 1) {
                    echo '
                                                <font class="important">' . _('High') . '</font>';
                } elseif ($this->ticket['priority'] == 2) {
                    echo '
                                                <font class="medium">' . _('Medium') . '</font>';
                } else {
                    echo _('Low');
                }

                echo '
                                            </td>
                                        </tr>';
            }
?>
                                    </table>
                                    <!-- END TICKET HEAD -->

                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
<?php
            // Print "Submit a reply" form?
            if ($this->ticket['locked'] != 1 && $this->ticket['status'] != 3 && $hesk_settings['reply_top'] == 1) {
                $this->printCustomerReplyForm();
            }
?>
                        <br />
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <!-- START TICKET REPLIES -->
                                    <table border="0" cellspacing="1" cellpadding="1" width="100%">

<?php
            if ($hesk_settings['new_top']) {
                $i = $this->printCustomerTicketReplies() ? 0 : 1;
            } else {
                $i = 1;
            }

            /* Make sure original message is in correct color if newest are on top */
            $color = $i ? 'class="ticketalt"' : 'class="ticketrow"';
?>

                                        <tr>
                                            <td <?php echo $color; ?>>
                                                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                    <tr>
                                                        <td valign="top">
                                                            <table border="0" cellspacing="1">
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Date'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $helpbase->common->_date($this->ticket['dt']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Name'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $this->ticket['name']; ?></td>
                                                                </tr>
<?php
            if (isset($this->ticket['company'])) {
?>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Company'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $this->ticket['company']; ?></td>
                                                                </tr>
<?php
            };
?>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Email'); ?>:</td>
                                                                    <td class="tickettd"><?php echo str_replace(array('@', '.'), array(' (at) ', ' (dot) '), $this->ticket['email']); ?></td>
                                                                </tr>
<?php
            if (isset($this->ticket['homephone'])){
?>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Home phone'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $this->ticket['homephone']; ?></td>
                                                                </tr>
<?php
            };

            if (isset($this->ticket['mobilephone'])) {
?>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Mobile phone'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $this->ticket['mobilephone']; ?></td>
                                                                </tr>
<?php
            };

            if (isset($this->ticket['workphone'])) {
?>
                                                                <tr>
                                                                    <td class="tickettd"><?php echo _('Work phone'); ?>:</td>
                                                                    <td class="tickettd"><?php echo $this->ticket['workphone']; ?></td>
                                                                </tr>
<?php
            };
?>
                                                            </table>
                                                        </td>
                                                        <td style="text-align:right; vertical-align:top;">
                                                            <?php echo $this->getCustomerButtons($i); ?>
                                                        </td>
                                                    </tr>
                                                </table>
<?php
            /* custom fields before message */
            $print_table = 0;
            $myclass = ' class="tickettd"';

            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place'] == 0) {
                    if ($print_table == 0) {
                        echo '
                                                <br>
                                                <table border="0" cellspacing="1" cellpadding="2">';
                        $print_table = 1;
                    }

                    if ($this->ticket[$k]){
                        echo '
                                                    <tr>
                                                        <td valign="top" ' . $myclass . '>' . $v['name'] . ':</td>
                                                        <td valign="top" ' . $myclass . '>' . $this->ticket[$k] . '</td>
                                                    </tr>';
                    };
                }
            }

            if ($print_table) {
                echo '
                                                </table>
                                                <br>';
            }
?>
                                                <!-- Device data -->
                                                <table border="0" cellspacing="1" cellpadding="2">
<?php
            if (isset($this->ticket['devicetype'])){
?>
                                                    <tr>
                                                        <td class="tickettd"><?php echo _('Device type'); ?>:</td>
                                                        <td class="tickettd"><?php echo $this->ticket['devicetype']; ?></td>
                                                    </tr>
<?php
            };

            if (isset($this->ticket['devicebrand'])) {
?>
                                                    <tr>
                                                        <td class="tickettd"><?php echo _('Device brand'); ?>:</td>
                                                        <td class="tickettd"><?php echo $this->ticket['devicebrand']; ?></td>
                                                    </tr>
<?php
            };

            if (isset($this->ticket['deviceid'])){
?>
                                                    <tr>
                                                        <td class="tickettd"><?php echo _('Device ID'); ?>:</td>
                                                        <td class="tickettd"><?php echo $this->ticket['deviceid']; ?></td>
                                                    </tr>
<?php
            };
?>
                                                </table>
                                                <br>
                                                <p><b><?php echo _('Message'); ?>:</b></p>
                                                <p><?php echo $this->ticket['message']; ?><br />&nbsp;</p>
<?php
            /* custom fields after message */
            $print_table = 0;
            $myclass = 'class="tickettd"';

            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place']) {
                    if ($print_table == 0) {
                        echo '
                                                <table border="0" cellspacing="1" cellpadding="2">';
                        $print_table = 1;
                    }
                    echo '
                                                    <tr>
                                                        <td valign="top" ' . $myclass . '>' . $v['name'] . ':</td>
                                                        <td valign="top" ' . $myclass . '>' . $this->ticket[$k] . '</td>
                                                    </tr>';
                }
            }

            if ($print_table) {
                echo '
                                                </table>';
            }

            /* Print attachments */
            $this->listAttachments($this->ticket['attachments'], $i);
?>
                                            </td>
                                        </tr>
<?php
            if (!$hesk_settings['new_top']) {
                $this->printCustomerTicketReplies();
            }
?>
                                    </table>
                                    <!-- END TICKET REPLIES -->

                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
<?php
            /* Print "Submit a reply" form? */
            if ($this->ticket['locked'] != 1 && $this->ticket['status'] != 3 && !$hesk_settings['reply_top']) {
                $this->printCustomerReplyForm();
            }

            /* If needed update unread replies as read for staff to know */
            if (count($this->unread_replies)) {
                $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` SET `read` = '1' WHERE `id` IN ('" . implode("','", $this->unread_replies) . "')");
            }

            /* Clear unneeded session variables */
            $helpbase->common->cleanSessionVars('ticket_message');

            $helpbase->footer->render();

            unset($helpbase);
            
            exit();
        }

        private function print_form() {
            global $helpbase, $hesk_settings ;

            /* Print header */
            $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . _('View ticket');
            $helpbase->header->render();

?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                                <td class="headersm"><?php $helpbase->common->showTopBar(_('View ticket')); ?></td>
                                <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                            </tr>
                        </table>

                        <table width="100%" border="0" cellspacing="0" cellpadding="3">
                            <tr>
                                <td><span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                                        <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                                        &gt; <?php echo _('View ticket'); ?></span></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        &nbsp;<br />
<?php
            /* This will handle error, success and notice messages */
            $helpbase->common->handle_messages();
?>
                        <div align="center">
                            <table border="0" cellspacing="0" cellpadding="0" width="50%">
                                <tr>
                                    <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <form action="ticket.php" method="get" name="form2">
                                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td width="1"><img src="img/existingticket.png" alt="" width="60" height="60" /></td>
                                                    <td>
                                                        <p><b><?php echo _('View existing ticket'); ?></a></b></p>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="1">&nbsp;</td>
                                                    <td>&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td width="1">&nbsp;</td>
                                                    <td>
                                                        <?php echo _('Ticket tracking ID'); ?>:
                                                        <br />
                                                        <input type="text" name="track" maxlength="20" size="35" value="<?php echo $this->tracking_id; ?>" />
                                                        <br />&nbsp;
                                                    </td>
                                                </tr>
<?php
            $tmp = '';
            if ($hesk_settings['email_view_ticket']) {
                $tmp = 'document.form1.email.value=document.form2.e.value;';
?>
                                                <tr>
                                                    <td width="1">&nbsp;</td>
                                                    <td>
                                                        <?php echo _('Email'); ?>:
                                                        <br />
                                                        <input type="text" name="e" size="35" value="<?php echo $this->my_email; ?>" />
                                                        <br />&nbsp;<br />
                                                        <label>
                                                            <input type="checkbox" name="r" value="Y" <?php echo $this->checked; ?> /> <?php echo _('Remember my email address'); ?>
                                                        </label>
                                                        <br />&nbsp;
                                                    </td>
                                                </tr>
<?php
            }
?>
                                                <tr>
                                                    <td width="1">&nbsp;</td>
                                                    <td><input type="submit" value="<?php echo _('View ticket'); ?>" class="button blue small" /><input type="hidden" name="Refresh" value="<?php echo rand(10000, 99999); ?>"><input type="hidden" name="f" value="1"></td>
                                                </tr>
                                                <tr>
                                                    <td width="1">&nbsp;</td>
                                                    <td>&nbsp;
                                                        <br />&nbsp;
                                                        <br />
                                                        <a href="Javascript:void(0)" onclick="javascript:hb_toggleLayerDisplay('forgot');<?php echo $tmp; ?>"><?php echo _('Forgot tracking ID?'); ?></a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                        &nbsp;
                                        <div id="forgot" class="notice" style="display: <?php echo $this->display; ?>;">
                                            <form action="index.php" method="post" name="form1">
                                                <p><b><?php echo _('Forgot tracking ID?'); ?></b><br />&nbsp;<br /><?php echo _('Not to worry, simply enter your <b>Email address</b> and we will send you your tracking ID right away:'); ?>
                                                    <br />
                                                    <input type="text" name="email" size="35" value="<?php echo $this->my_email; ?>" />
                                                    <input type="hidden" name="a" value="forgot_tid" />
                                                    <br />&nbsp;<br />
                                                    <input type="submit" value="<?php echo _('Send me my tracking ID'); ?>" class="button blue small" />
                                                </p>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="roundcornersright">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornersbottom"></td>
                                    <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                            </table>
                        </div>
                        <p>&nbsp;</p>
<?php
            $helpbase->footer->render();

            unset($helpbase);
            
            exit();
        }

        private function printCustomerReplyForm($reopen = 0) {
            global $helpbase, $hesk_settings;

            // Already printed?
            if (true == $this->reply_form) {
                return '';
            }
?>
                        <br />
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>

                                    <h3 style="text-align:center"><?php echo _('Add reply'); ?></h3>

                                    <form method="post" action="reply_ticket.php" enctype="multipart/form-data">
                                        <p align="center"><?php echo _('Message'); ?>: <span class="important">*</span>
                                            <br />
<?php 
            $ticket_msg = '';
            if (isset($_SESSION['ticket_message'])) {
                $ticket_msg = stripslashes($helpbase->common->_input($_SESSION['ticket_message']));
            } 
?>                                            
                                            <textarea name="message" rows="12" cols="60"><?php echo $ticket_msg; ?></textarea>
                                        </p>
<?php
            /* attachments */
            if ($hesk_settings['attachments']['use']) {
?>
                                        <p align="center">
<?php
                                            echo _('Attachments') . ' (<a href="file_limits.php" target="_blank" onclick="Javascript:hb_window(\'file_limits.php\',250,500);return false;">' . _('File upload limits') . '</a>):<br />';
                for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                    echo '
                                            <input type="file" name="attachment[' . $i . ']" size="50" /><br />';
                }
?>
                                            &nbsp;
                                        </p>
<?php
            }
?>
                                        <p align="center">
                                            <input type="hidden" name="token" value="<?php $helpbase->common->token_echo(); ?>" />
                                            <input type="hidden" name="orig_track" value="<?php echo $this->tracking_id; ?>" />
<?php
            if ($hesk_settings['email_view_ticket']) {
                echo '
                                            <input type="hidden" name="e" value="' . $this->my_email . '" />';
            }
            if ($reopen) {
                echo '
                                            <input type="hidden" name="reopen" value="1" />';
            }
?>
                                            <input type="submit" value="<?php echo _('Submit reply'); ?>" class="button blue small" />
                                        </p>
                                    </form>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
<?php
            // Make sure the form is only printed once per page
            $this->reply_form = true;
        }

        private function printCustomerTicketReplies() {
            global $helpbase, $hesk_settings, $reply;

            $i = $hesk_settings['new_top'] ? 0 : 1;

            while ($reply = $helpbase->database->fetchAssoc($this->dbResult)) {
                if ($i) {
                    $color = 'class="ticketrow"';
                    $i = 0;
                } else {
                    $color = 'class="ticketalt"';
                    $i = 1;
                }

                /* Store unread reply IDs for later */
                if ($reply['staffid'] && !$reply['read']) {
                    $this->unread_replies[] = $reply['id'];
                }

                $reply['dt'] = $helpbase->common->_date($reply['dt']);
?>
                        <tr>
                            <td <?php echo $color; ?>>
                                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td valign="top">
                                            <table border="0" cellspacing="1">
                                                <tr>
                                                    <td><?php echo _('Date'); ?>:</td>
                                                    <td><?php echo $reply['dt']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo _('Name'); ?>:</td>
                                                    <td><?php echo $reply['name']; ?></td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td style="text-align:right; vertical-align:top;">
                                            <?php echo $this->getCustomerButtons($i); ?>
                                        </td>
                                    </tr>
                                </table>
                                <p><b><?php echo _('Message'); ?>:</b></p>
                                <p><?php echo $reply['message']; ?></p>
<?php
                /* Attachments */
                $this->listAttachments($reply['attachments'], $i);

                /* Staff rating */
                if ($hesk_settings['rating'] && $reply['staffid']) {
                    if ($reply['rating'] == 1) {
                        echo '
                                <p class="rate">' . _('Rated as <i>not helpful</i>') . '</p>';
                    } elseif ($reply['rating'] == 5) {
                        echo '
                                <p class="rate">' . _('Rated as <i>helpful</i>') . '</p>';
                    } else {
                        echo '
                                <div id="rating' . $reply['id'] . '" class="rate">
                                    ' . _('Was this reply helpful?') . '
                                    <a href="Javascript:void(0)" onclick="Javascript:hb_rate(\'rate.php?rating=5&amp;id=' . $reply['id'] . '&amp;track=' . $this->tracking_id . '\',\'rating' . $reply['id'] . '\')">' . strtolower(_('YES')) . '</a> /
                                    <a href="Javascript:void(0)" onclick="Javascript:hb_rate(\'rate.php?rating=1&amp;id=' . $reply['id'] . '&amp;track=' . $this->tracking_id . '\',\'rating' . $reply['id'] . '\')">' . strtolower(_('NO')) . '</a>
                                </div>';
                    }
                }
?>
                            </td>
                        </tr>
<?php
            }

            return $i;
        }

        private function listAttachments($attachments = '', $white = 1) {
            global $hesk_settings;

            /* Attachments disabled or not available */
            if (!$hesk_settings['attachments']['use'] || !strlen($attachments)) {
                return false;
            }

            /* Style and mousover/mousout */
            $tmp = $white ? 'White' : 'Blue';
            $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';

            /* List attachments */
            echo '
                                <p><b>' . _('Attachments') . ':</b><br />';
            
            $att = explode(',', substr($attachments, 0, -1));
            foreach ($att as $myatt) {
                list($att_id, $att_name) = explode('#', $myatt);

                echo '
                                    <a href="download_attachment.php?att_id=' . $att_id . '&amp;track=' . $this->tracking_id . $hesk_settings['e_query'] . '"><img src="img/clip.png" width="16" height="16" alt="' . _('Download') . ' ' . $att_name . '" title="' . _('Download') . ' ' . $att_name . '" ' . $style . ' /></a>
                                    <a href="download_attachment.php?att_id=' . $att_id . '&amp;track=' . $this->tracking_id . $hesk_settings['e_query'] . '">' . $att_name . '</a>'
                        . '         <br />';
            }
            echo '
                                </p>';

            return true;
        }

        private function getCustomerButtons($white = 1) {
            global $hesk_settings;

            $options = '';

            /* Style and mousover/mousout */
            $tmp = $white ? 'White' : 'Blue';
            $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';

            /* Print ticket button */
            $options .= '<a href="print.php?track=' . $this->tracking_id . $hesk_settings['e_query'] . '"><img src="img/print.png" width="16" height="16" alt="' . _('Printer friendly version') . '" title="' . _('Printer friendly version') . '" ' . $style . ' /></a> ';

            /* Return generated HTML */
            return $options;
        }

    }
}

new HelpbaseTickets;

?>
