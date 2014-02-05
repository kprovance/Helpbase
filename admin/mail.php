<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Staff Mail
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseAdminMail')) {
    class HelpbaseAdminMail {
        private $helpbase   = null;
        private $admins     = array();
        
        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->load_email_functions();

            $helpbase->admin->isLoggedIn();

            /* List of staff */
            $res = $helpbase->database->query("SELECT `id`,`name` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` ORDER BY `id` ASC");
            while ($row = $helpbase->database->fetchAssoc($res)) {
                $this->admins[$row['id']] = $row['name'];
            }

            /* What folder are we in? */
            $hesk_settings['mailtmp']['inbox'] = '<a href="mail.php"><img src="../img/inbox.png" width="16" height="16" alt="' . _('INBOX') . '" title="' . _('INBOX') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php">' . _('INBOX') . '</a>';
            $hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox"><img src="../img/outbox.png" width="16" height="16" alt="' . _('OUTBOX') . '" title="' . _('OUTBOX') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php?folder=outbox">' . _('OUTBOX') . '</a>';
            $hesk_settings['mailtmp']['new'] = '<a href="mail.php?a=new"><img src="../img/new_mail.png" width="16" height="16" alt="' . _('NEW MESSAGE') . '" title="' . _('NEW MESSAGE') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="mail.php?a=new">' . _('NEW MESSAGE') . '</a>';

            /* Get action */
            if ($action = $helpbase->common->_request('a')) {
                if (true == $helpbase->demo_mode && $action != 'new' && $action != 'read') {
                    $helpbase->common->process_messages(_('Sorry, this function has been disabled in DEMO mode!'), 'mail.php', 'NOTICE');
                }
            }

            /* Sub-page specific settings */
            if (isset($_GET['folder']) && $helpbase->common->_get('folder') == 'outbox') {
                $hesk_settings['mailtmp']['this'] = 'from';
                $hesk_settings['mailtmp']['other'] = 'to';
                $hesk_settings['mailtmp']['m_from'] = _('To:');
                $hesk_settings['mailtmp']['outbox'] = '<b><img src="../img/outbox.png" width="16" height="16" alt="' . _('OUTBOX') . '" title="' . _('OUTBOX') . '" border="0" style="border:none;vertical-align:text-bottom" /> ' . _('OUTBOX') . '</b>';
                $hesk_settings['mailtmp']['folder'] = 'outbox';
            } elseif ($action == 'new') {
                $hesk_settings['mailtmp']['new'] = '<b><img src="../img/new_mail.png" width="16" height="16" alt="' . _('NEW MESSAGE') . '" title="' . _('NEW MESSAGE') . '" border="0" style="border:none;vertical-align:text-bottom" /> ' . _('NEW MESSAGE') . '</b>';
                $_SESSION['hide']['list'] = 1;

                /* Do we have a recipient selected? */
                if (!isset($_SESSION['mail']['to']) && isset($_GET['id'])) {
                    $_SESSION['mail']['to'] = intval($helpbase->common->_get('id'));
                }
            } else {
                $hesk_settings['mailtmp']['this'] = 'to';
                $hesk_settings['mailtmp']['other'] = 'from';
                $hesk_settings['mailtmp']['m_from'] = _('From:');
                if ($action != 'read') {
                    $hesk_settings['mailtmp']['inbox'] = '<b><img src="../img/inbox.png" width="16" height="16" alt="' . _('INBOX') . '" title="' . _('INBOX') . '" border="0" style="border:none;vertical-align:text-bottom" /> ' . _('INBOX') . '</b>';
                    $hesk_settings['mailtmp']['folder'] = '';
                }
            }            
            
            $this->render($action);
            
            unset($helpbase);
        }
        
        private function render($action) {
            global $hesk_settings;
            
            /* What should we do? */
            switch ($action) {
                case 'send':
                    $this->mail_send();
                break;
            
                case 'mark_read':
                    $this->mail_mark_read();
                break;
            
                case 'mark_unread':
                    $this->mail_mark_unread();
                break;
            
                case 'delete':
                    $this->mail_delete();
                break;
            }

            /* Print header */
            $this->helpbase->header->render();

            /* Print main manage users page */
            $this->helpbase->admin_nav->render();
?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <script language="javascript" type="text/javascript">
                                <!--
                                function confirm_delete() {
                                    if (confirm('<?php echo addslashes(_('Are you sure you want to delete this canned response?')); ?>')) {
                                        return true;
                                    } else {
                                        return false;
                                    }
                                }
                                //-->
                            </script>
                            <h3 align="center"><?php echo _('Private messages'); ?></h3>
<?php
            /* Print sub-navigation */
            echo $hesk_settings['mailtmp']['inbox'] . ' | ' . $hesk_settings['mailtmp']['outbox'] . ' | ' . $hesk_settings['mailtmp']['new'] . '<br /><br />&nbsp;';

            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();

            /* Show a message? */
            if ($action == 'read') {
                $this->show_message();
            }

            /* Hide list of messages? */
            if (!isset($_SESSION['hide']['list'])) {
                $this->mail_list_messages();
            } // END hide list of messages

            /* Show new message form */
            $this->show_new_form();

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars('hide');
            $this->helpbase->common->cleanSessionVars('mail');

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function mail_delete() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $ids = $this->mail_get_ids();

            if ($ids) {
                foreach ($ids as $id) {
                    /* If both correspondents deleted the mail remove it from database, otherwise mark as deleted by this user */
                    $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` SET `deletedby`='" . intval($_SESSION['id']) . "' WHERE `id`='" . intval($id) . "' AND (`to`='" . intval($_SESSION['id']) . "' OR `from`='" . intval($_SESSION['id']) . "') AND `deletedby`=0 LIMIT 1");

                    if ($this->helpbase->database->affectedRows() != 1) {
                        $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` WHERE `id`='" . intval($id) . "' AND (`to`='" . intval($_SESSION['id']) . "' OR `from`='" . intval($_SESSION['id']) . "') AND `deletedby`!=0 LIMIT 1");
                    }
                }

                $this->helpbase->common->process_messages(_('Selected messages have been deleted'), 'NOREDIRECT', 'SUCCESS');
            }

            return true;
        }

        private function mail_mark_unread() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $ids = $this->mail_get_ids();

            if ($ids) {
                foreach ($ids as $id) {
                    $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` SET `read`='0' WHERE `id`='" . intval($id) . "' AND `to`='" . intval($_SESSION['id']) . "' LIMIT 1");
                }

                $this->helpbase->common->process_messages(_('Selected messages have been marked as unread'), 'NOREDIRECT', 'SUCCESS');
            }

            return true;
        }

        private function mail_mark_read() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $ids = $this->mail_get_ids();

            if ($ids) {
                foreach ($ids as $id) {
                    $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` SET `read`='1' WHERE `id`='" . intval($id) . "' AND `to`='" . intval($_SESSION['id']) . "' LIMIT 1");
                }

                $this->helpbase->common->process_messages(_('Selected messages have been marked as read'), 'NOREDIRECT', 'SUCCESS');
            }

            return true;
        }

        private function mail_get_ids() {
            global $hesk_settings;

            // Mail id as a query parameter?
            if ($id = $this->helpbase->common->_get('id', false)) {
                return array($id);
            }
            // Mail id as a post array?
            elseif (isset($_POST['id']) && is_array($_POST['id'])) {
                return array_map('intval', $_POST['id']);
            }
            // No valid ID parameter
            else {
                $this->helpbase->common->process_messages(_('No messages selected, nothing to change'), 'NOREDIRECT', 'NOTICE');
                return false;
            }
        }

        private function mail_send() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $hesk_error_buffer = '';

            /* Recipient */
            $_SESSION['mail']['to'] = intval($this->helpbase->common->_post('to'));

            /* Valid recipient? */
            if (empty($_SESSION['mail']['to'])) {
                $hesk_error_buffer .= '<li>' . _('Select message recipient') . '</li>';
            } elseif ($_SESSION['mail']['to'] == $_SESSION['id']) {
                $hesk_error_buffer .= '<li>' . _('Invalid message recipient') . '</li>';
            } else {
                $res = $this->helpbase->database->query("SELECT `name`,`email`,`notify_pm` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . intval($_SESSION['mail']['to']) . "' LIMIT 1");
                $num = $this->helpbase->database->numRows($res);
                if (!$num) {
                    $hesk_error_buffer .= '<li>' . _('Invalid message recipient') . '</li>';
                } else {
                    $pm_recipient = $this->helpbase->database->fetchAssoc($res);
                }
            }

            /* Subject */
            $_SESSION['mail']['subject'] = $this->helpbase->common->_input($this->helpbase->common->_post('subject')) or $hesk_error_buffer .= '<li>' . _('Enter private message subject') . '</li>';

            /* Message */
            $_SESSION['mail']['message'] = $this->helpbase->common->_input($this->helpbase->common->_post('message')) or $hesk_error_buffer .= '<li>' . _('Please enter your message') . '</li>';

            /* Any errors? */
            if (strlen($hesk_error_buffer)) {
                $_SESSION['hide']['list'] = 1;
                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'NOREDIRECT');
            } else {
                $_SESSION['mail']['message'] = $this->helpbase->common->makeURL($_SESSION['mail']['message']);
                $_SESSION['mail']['message'] = nl2br($_SESSION['mail']['message']);

                $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` (`from`,`to`,`subject`,`message`,`dt`,`read`) VALUES ('" . intval($_SESSION['id']) . "','" . intval($_SESSION['mail']['to']) . "','" . $this->helpbase->database->escape($_SESSION['mail']['subject']) . "','" . $this->helpbase->database->escape($_SESSION['mail']['message']) . "',NOW(),'0')");

                /* Notify receiver via e-mail? */
                if (isset($pm_recipient) && $pm_recipient['notify_pm']) {
                    $pm_id = $this->helpbase->database->insertID();

                    $pm = array(
                        'name'      => $this->helpbase->common->msgToPlain(addslashes($_SESSION['name']), 1, 1),
                        'subject'   => $this->helpbase->common->msgToPlain($_SESSION['mail']['subject'], 1, 1),
                        'message'   => $this->helpbase->common->msgToPlain($_SESSION['mail']['message'], 1, 1),
                        'id'        => $pm_id,
                    );

                    /* Format email subject and message for recipient */
                    $subject = $this->helpbase->email->getEmailSubject('new_pm', $pm, 0);
                    $message = $this->helpbase->email->getEmailMessage('new_pm', $pm, 1, 0);

                    /* Send e-mail */
                    $this->helpbase->email->mail($pm_recipient['email'], $subject, $message);
                }

                unset($_SESSION['mail']);

                $this->helpbase->common->process_messages(_('Your private message has been sent'), './mail.php', 'SUCCESS');
            }
        }

        private function show_message() {
            global $hesk_settings;

            $id = intval($this->helpbase->common->_get('id'));

            /* Get the message details */
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` WHERE `id`='" . intval($id) . "' AND `deletedby`!='" . intval($_SESSION['id']) . "' LIMIT 1");
            $num = $this->helpbase->database->numRows($res);

            if ($num) {
                $pm = $this->helpbase->database->fetchAssoc($res);

                /* Allowed to read the message? */
                if ($pm['to'] == $_SESSION['id']) {

                    if (!isset($_SESSION['mail']['subject'])) {
                        $_SESSION['mail']['subject'] = _('Re:') . ' ' . $pm['subject'];
                    }

                    if (!isset($_SESSION['mail']['to'])) {
                        $_SESSION['mail']['to'] = $pm['from'];
                    }
                } elseif ($pm['from'] == $_SESSION['id']) {

                    if (!isset($_SESSION['mail']['subject'])) {
                        $_SESSION['mail']['subject'] = _('Fwd:') . ' ' . $pm['subject'];
                    }

                    if (!isset($_SESSION['mail']['to'])) {
                        $_SESSION['mail']['to'] = $pm['to'];
                    }

                    $hesk_settings['mailtmp']['this'] = 'from';
                    $hesk_settings['mailtmp']['other'] = 'to';
                    $hesk_settings['mailtmp']['m_from'] = _('To:');
                    $hesk_settings['mailtmp']['outbox'] = '<b>' . _('OUTBOX') . '</b>';
                    $hesk_settings['mailtmp']['inbox'] = '<a href="mail.php">' . _('INBOX') . '</a>';
                    $hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox">' . _('OUTBOX') . '</a>';
                } else {
                    $this->helpbase->common->process_message(_('You don\'t have permission to read this message.'), 'mail.php');
                }

                /* Mark as read */
                if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read']) {
                    $res = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` SET `read`='1' WHERE `id`='" . intval($id) . "' LIMIT 1");
                }

                $pm['name'] = isset($this->admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id=' . $pm[$hesk_settings['mailtmp']['other']] . '">' . $this->admins[$pm[$hesk_settings['mailtmp']['other']]] . '</a>' : (($pm['from'] == 9999) ? '<a href="http://www.hesk.com" target="_blank">HESK.com</a>' : _('(User deleted)'));
                $pm['dt'] = $this->helpbase->admin->dateToString($pm['dt'], 0, 1);
?>

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                        <tr>
                                            <td valign="top">
                                                <table border="0">
                                                    <tr>
                                                        <td><b><?php echo $hesk_settings['mailtmp']['m_from']; ?></b></td>
                                                        <td><?php echo $pm['name']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><b><?php echo _('Date'); ?></b></td>
                                                        <td><?php echo $pm['dt']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><b><?php echo _('Subject:'); ?></b></td>
                                                        <td><?php echo $pm['subject']; ?></td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td style="text-align:right; vertical-align:top;">
<?php
                $folder = '&amp;folder=outbox';
                if ($pm['to'] == $_SESSION['id']) {
                    echo '
                                                <a href="mail.php?a=mark_unread&amp;id=' . $id . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/mail.png" width="16" height="16" alt="' . _('Mark as unread') . '" title="' . _('Mark as unread') . '" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a> ';
                    $folder = '';
                }
                echo '
                                                <a href="mail.php?a=delete&amp;id=' . $id . '&amp;token=' . $this->helpbase->common->token_echo(0) . $folder . '" onclick="return hb_confirmExecute(\'' . _('Delete this message') . '?\');"><img src="../img/delete.png" width="16" height="16" alt="' . _('Delete this message') . '" title="' . _('Delete this message') . '" class="optionWhiteOFF" onmouseover="this.className=\'optionWhiteON\'" onmouseout="this.className=\'optionWhiteOFF\'" /></a>';
?>
                                            </td>
                                        </tr>
                                    </table>
                                    <hr />
                                    <p><?php echo $pm['message']; ?></p>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <br />
                        <hr />
<?php
            }

            $_SESSION['hide']['list'] = 1;
        }

        private function mail_list_messages() {
            global $hesk_settings;

            $href = 'mail.php';
            $query = '';
            if ($hesk_settings['mailtmp']['folder'] == 'outbox') {
                $query .= 'folder=outbox&amp;';
            }
            $query .= 'page=';

            $maxresults = 30;

            $tmp = intval($this->helpbase->common->_post('page', 1));
            $page = ($tmp > 1) ? $tmp : 1;

            /* List of private messages */
            $res = $this->helpbase->database->query("SELECT COUNT(*) FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` WHERE `" . $this->helpbase->database->escape($hesk_settings['mailtmp']['this']) . "`='" . intval($_SESSION['id']) . "' AND `deletedby`!='" . intval($_SESSION['id']) . "'");
            $total = $this->helpbase->database->result($res, 0, 0);

            if ($total > 0) {

                $pages = ceil($total / $maxresults) or $pages = 1;
                if ($page > $pages) {
                    $page = $pages;
                }
                $limit_down = ($page * $maxresults) - $maxresults;

                $prev_page = ($page - 1 <= 0) ? 0 : $page - 1;
                $next_page = ($page + 1 > $pages) ? 0 : $page + 1;

                if ($pages > 1) {
                    echo _('Show page') . ': ';

                    /* List pages */
                    if ($pages >= 7) {
                        if ($page > 2) {
                            echo '
                            <a href="' . $href . '?' . $query . '1"><b>&laquo;</b></a> &nbsp; ';
                        }

                        if ($prev_page) {
                            echo '
                            <a href="' . $href . '?' . $query . $prev_page . '"><b>&lsaquo;</b></a> &nbsp; ';
                        }
                    }

                    for ($i = 1; $i <= $pages; $i++) {
                        if ($i <= ($page + 5) && $i >= ($page - 5)) {
                            if ($i == $page) {
                                echo ' <b>' . $i . '</b> ';
                            } else {
                                echo ' <a href="' . $href . '?' . $query . $i . '">' . $i . '</a> ';
                            }
                        }
                    }

                    if ($pages >= 7) {
                        if ($next_page) {
                            echo ' &nbsp; <a href="' . $href . '?' . $query . $next_page . '"><b>&rsaquo;</b></a> ';
                        }

                        if ($page < ($pages - 1)) {
                            echo ' &nbsp; <a href="' . $href . '?' . $query . $pages . '"><b>&raquo;</b></a>';
                        }
                    }

                    echo '<br />&nbsp;';
                } // end PAGES > 1
                // Get messages from the database
                $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "mail` WHERE `" . $this->helpbase->database->escape($hesk_settings['mailtmp']['this']) . "`='" . intval($_SESSION['id']) . "' AND `deletedby`!='" . intval($_SESSION['id']) . "' ORDER BY `id` DESC LIMIT " . intval($limit_down) . " , " . intval($maxresults) . " ");

                $folder = '';
                if ($hesk_settings['mailtmp']['folder'] == 'outbox') {
                    $folder = '?folder=outbox';
                }
?>
                <form action="mail.php<?php echo $folder; ?>" name="form1" method="post">
                    <div align="center">
                        <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                            <tr>
                                <th class="admin_white" style="width:1px"><input type="checkbox" name="checkall" value="2" onclick="hb_changeAll(this)" /></th>
                                <th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo _('Subject:'); ?></th>
                                <th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo $hesk_settings['mailtmp']['m_from']; ?></th>
                                <th class="admin_white" style="text-align:left; white-space:nowrap;"><?php echo _('Date'); ?></th>
                            </tr>
<?php
                $i = 0;
                while ($pm = $this->helpbase->database->fetchAssoc($res)) {
                    if ($i) {
                        $color = "admin_gray";
                        $i = 0;
                    } else {
                        $color = "admin_white";
                        $i = 1;
                    }

                    $pm['subject'] = '<a href="mail.php?a=read&amp;id=' . $pm['id'] . '">' . $pm['subject'] . '</a>';
                    if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read']) {
                        $pm['subject'] = '<b>' . $pm['subject'] . '</b>';
                    }
                    $pm['name'] = isset($this->admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id=' . $pm[$hesk_settings['mailtmp']['other']] . '">' . $this->admins[$pm[$hesk_settings['mailtmp']['other']]] . '</a>' : (($pm['from'] == 9999) ? '<a href="http://www.hesk.com" target="_blank">HESK.com</a>' : _('(User deleted)'));
                    $pm['dt'] = $this->helpbase->admin->dateToString($pm['dt'], 0);

                    echo <<<EOC
                            <tr>
                                <td class="$color" style="text-align:left; white-space:nowrap;"><input type="checkbox" name="id[]" value="$pm[id]" />&nbsp;</td>
                                <td class="$color">$pm[subject]</td>
                                <td class="$color">$pm[name]</td>
                                <td class="$color">$pm[dt]</td>
                            </tr>

EOC;
                }
?>
                        </table>
                    </div>
                    <p align="center">
                        <select name="a">
<?php
                if ($hesk_settings['mailtmp']['this'] == 'to') {
?>
                            <option value="mark_read" selected="selected"><?php echo _('Mark selected messages as read'); ?></option>
                            <option value="mark_unread"><?php echo _('Mark selected messages as unread'); ?></option>
<?php
                }
?>
                            <option value="delete"><?php echo _('Delete selected messages'); ?></option>
                        </select>
                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                        <input type="submit" value="<?php echo _('Execute'); ?>" onclick="Javascript:if (document.form1.a.value == 'delete')
                                    return hb_confirmExecute('<?php echo _('Delete selected messages'); ?>?');" class="button blue small" />
                    </p>
                </form>
                <p>&nbsp;</p>
<?php
            } // END if total > 0
            else {
                echo '<i>' . _('No private messages in this folder.') . '</i> <p>&nbsp;</p>';
            }
        }

        private function show_new_form() {
            global $hesk_settings;
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <form action="mail.php" method="post" name="form2">
                                        <h3 align="center"><?php echo _('New private message'); ?></h3>
                                        <div align="center">
                                            <table border="0">
                                                <tr>
                                                    <td>
                                                        <table border="0">
                                                            <tr>
                                                                <td><b><?php echo _('To:'); ?></b></td>
                                                                <td>
                                                                    <select name="to">
                                                                        <option value="" selected="selected"><?php echo _(' - - Click to Select - - '); ?></option>
<?php
            foreach ($this->admins as $k => $v) {
                if ($k != $_SESSION['id']) {
                    if (isset($_SESSION['mail']) && $k == $_SESSION['mail']['to']) {
                        echo '
                                                                        <option value="' . $k . '" selected="selected">' . $v . '</option>';
                    } else {
                        echo '
                                                                        <option value="' . $k . '">' . $v . '</option>';
                    }
                }
            }
?>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><b><?php echo _('Subject:'); ?></b></td>
                                                                <td>
<?php
            $subject = '';
            if (isset($_SESSION['mail']['subject'])) {
                $subject = ' value="' . stripslashes($_SESSION['mail']['subject']) . '" ';
            }
?>
                                                                    <input type="text" name="subject" size="40" maxlength="50" <?php echo $subject; ?>/>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                        <p><b><?php echo _('Message'); ?>:</b><br />
<?php
            $message = '';
            if (isset($_SESSION['mail']['message'])) {
                $message = stripslashes($_SESSION['mail']['message']);
            }
?>
                                                            <textarea name="message" rows="15" cols="70"><?php echo $message; ?></textarea>
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <p align="center">
                                            <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                            <input type="hidden" name="a" value="send" />
                                            <input type="submit" value="<?php echo _('Send message'); ?>" class="button blue small" />
                                        </p>
                                    </form>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
<?php
        }
    }
}

?>
