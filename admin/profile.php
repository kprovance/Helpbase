<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Manage Profile
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseProfile')){
    class HelpbaseProfile {
        private $can_view_tickets       = false;
        private $can_reply_tickets      = false;
        private $can_view_unassigned    = false;

        public $warn_password           = false;

        public function __construct(){
            global $helpbase, $hesk_settings;

            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);

            $helpbase->admin->isLoggedIn();

            /* Check permissions */
            $this->can_view_tickets       = $helpbase->admin->checkPermission('can_view_tickets', 0);
            $this->can_reply_tickets      = $helpbase->admin->checkPermission('can_reply_tickets', 0);
            $this->can_view_unassigned    = $helpbase->admin->checkPermission('can_view_unassigned', 0);

            /* Update profile? */
            if (!empty($_POST['action'])) {
                // Demo mode
                if (true == $helpbase->demo_mode) {
                    $helpbase->common->process_messages(_('Saving changes has been disabled in DEMO mode'), 'profile.php', 'NOTICE');
                }

                // Update profile
                $this->update_profile();
            } else {
                $res = $helpbase->database->query('SELECT * FROM `' . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id` = '" . intval($_SESSION['id']) . "' LIMIT 1");
                $tmp = $helpbase->database->fetchAssoc($res);

                foreach ($tmp as $k => $v) {
                    if ($k == 'pass') {
                        if ($v == '499d74967b28a841c98bb4baaabaad699ff3c079') {
                            $this->warn_password = true;
                        }
                        continue;
                    } elseif ($k == 'categories') {
                        continue;
                    }
                    $_SESSION['new'][$k] = $v;
                }
            }
            if (!isset($_SESSION['new']['user'])) {
                $_SESSION['new']['user'] = '';
            }

            //echo serialize($_SESSION);
            
            $this->render();
        }

        public function render(){

            global $hesk_settings, $helpbase;

            /* Print header */
            $helpbase->header->render();

            /* Print admin navigation */
            $helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $helpbase->common->handle_messages();

            if (true == $this->warn_password) {
                $helpbase->common->show_notice(_('Change your password, you are using the default one!'), '<span class="important">' . _('Security') . '</span>');
            }
?>
                        <h3 align="center"><?php echo _('Profile for'); ?><b> <?php echo $_SESSION['new']['user']; ?></b></h3>
                        <p align="center"><?php echo _('Required fields are marked with'); ?> <span class="important">*</span></p>
<?php
            if ($hesk_settings['can_sel_lang']) {
                /* Update preferred language in the database? */
                if (isset($_GET['save_language'])) {
                    $newlang = $helpbase->common->_input($helpbase->common->_get('language'));

                    /* Only update if it's a valid language */
                    if (isset($hesk_settings['languages'][$newlang])) {
                        $newlang = ($newlang == $helpbase->common->language) ? "NULL" : "'" . $helpbase->database->escape($newlang) . "'";
                        $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET `language`=$newlang WHERE `id`='" . intval($_SESSION['id']) . "' LIMIT 1");
                    }
                }

                $str = '<form method="get" action="profile.php" style="margin:0;padding:0;border:0;white-space:nowrap;">';
                $str .= '<input type="hidden" name="save_language" value="1" />';
                $str .= '<p>' . _('Preferred Language') . ': ';

                if (!isset($_GET)) {
                    $_GET = array();
                }

                foreach ($_GET as $k => $v) {
                    if ($k == 'language' || $k == 'save_language') {
                        continue;
                    }
                    $str .= '<input type="hidden" name="' . $helpbase->common->htmlentities($k) . '" value="' . $helpbase->common->htmlentities($v) . '" />';
                }

                $str .= '<select name="language" onchange="this.form.submit()">';
                $str .= $helpbase->common->listLanguages(0);
                $str .= '</select>';
?>
                    <script language="javascript" type="text/javascript">
                        document.write('<?php echo str_replace(array('"', '<', '=', '>'), array('\42', '\74', '\75', '\76'), $str . '</p></form>'); ?>');
                    </script>
                    <noscript>
                    <?php
                    echo $str . '<input type="submit" value="' . _('Go') . '" /></p></form>';
                    ?>
                    </noscript>
                    <?php
            }
?>

                        <form method="post" action="profile.php" name="form1">
                            <br />
                            <span class="section">&raquo; <?php echo _('Profile information'); ?></span>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>

                                        <!-- Contact info -->
                                        <table border="0">
                                            <tr>
                                                <td style="text-align:right" width="200"><?php echo _('Name'); ?>: <font class="important">*</font></td>
                                                <td><input type="text" name="name" size="30" maxlength="50" value="<?php echo $_SESSION['new']['name']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right" width="200"><?php echo _('Email'); ?>: <font class="important">*</font></td>
                                                <td><input type="text" name="email" size="30" maxlength="255" value="<?php echo $_SESSION['new']['email']; ?>" /></td>
                                            </tr>
<?php
            // Let admins change their username
            if ($_SESSION['isadmin']) {
?>
                                            <tr>
                                                <td style="text-align:right" width="200"><?php echo _('Username'); ?>: <font class="important">*</font></td>
                                                <td><input type="text" name="user" size="30" maxlength="50" value="<?php echo $_SESSION['new']['user']; ?>" autocomplete="off" /></td>
                                            </tr>
<?php
            }
?>
                                            <tr>
                                                <td style="text-align:right" width="200"><?php echo _('New password'); ?>: </td>
                                                <td><input type="password" name="newpass" size="30" maxlength="20" onkeyup="javascript:hb_checkPassword(this.value)" autocomplete="off" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right" width="200"><?php echo _('Confirm password'); ?>: </td>
                                                <td><input type="password" name="newpass2" size="30" maxlength="20" autocomplete="off" /></td>
                                            </tr>
                                            <tr>
                                                <td width="200" style="text-align:right"><?php echo _('Password Strength'); ?>:</td>
                                                <td>
                                                    <div style="border: 1px solid gray; width: 100px;">
                                                        <div id="progressBar"
                                                             style="font-size: 1px; height: 14px; width: 0px; border: 1px solid white;">
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
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
                            <span class="section">&raquo; <?php echo _('Signature'); ?></span>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>

                                        <!-- signature -->
                                        <table border="0">
                                            <tr>
                                                <td style="text-align:right" valign="top" width="200"><?php echo _('Signature (max 255 chars)'); ?>:</td>
                                                <td><textarea name="signature" rows="6" cols="40"><?php echo $_SESSION['new']['signature']; ?></textarea>
                                                    <br />
                                                    <?php echo _('HTML code is not allowed. Links will be clickable.'); ?>
                                                </td>
                                            </tr>
                                        </table>
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
<?php
            if ($this->can_reply_tickets) {
?>
                            <span class="section">&raquo; <?php echo _('Preferences'); ?></span>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>

                                        <table border="0">
                                            <tr>
                                                <td style="text-align:right" valign="top" width="200"><?php echo _('After replying to a ticket'); ?>:</td>
                                                <td>
<?php
                $checked = '';
                if (!$_SESSION['new']['afterreply']) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label><input type="radio" name="afterreply" value="0" <?php echo $checked ?>/>
                                                        <?php echo _('Show the ticket I just replied to'); ?>
                                                    </label>
                                                    <br />
<?php
                $checked = '';
                if ($_SESSION['new']['afterreply'] == 1) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label><input type="radio" name="afterreply" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('Return to main administration page'); ?>
                                                    </label>
                                                    <br />
<?php
                $checked = '';
                if ($_SESSION['new']['afterreply'] == 2) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label><input type="radio" name="afterreply" value="2" <?php echo $checked; ?>/>
                                                        <?php echo _('Open next ticket that needs my reply'); ?>
                                                    </label>
                                                    <br />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right" valign="top" width="200"><?php echo _('Time worked'); ?>:</td>
                                                <td>
<?php
                $checked = '';
                if (!empty($_SESSION['new']['autostart'])) {
                    $checked = 'checked="checked"';
                }

?>
                                                    <label><input type="checkbox" name="autostart" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('Automatically start timer when I open a ticket'); ?>
                                                    </label>
                                                    <br />
                                                </td>
                                            </tr>
                                        </table>
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
<?php
            }
?>
                            <span class="section">&raquo; <?php echo _('Notifications'); ?></span>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <p><?php echo _('The help desk will send an email notification when:'); ?></p>
                                        <table border="0">
                                            <tr>
                                                <td>
<?php
            if ($this->can_view_tickets) {
                if ($this->can_view_unassigned) {
                    $checked = '';
                    if (!empty($_SESSION['new']['notify_new_unassigned'])) {
                        $checked = 'checked="checked"';
                    }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_new_unassigned" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('A new ticket is submitted with owner:'); ?> <?php echo _('Unassigned'); ?>
                                                    </label>
                                                    <br />
<?php
                } else {
?>
                                                    <input type="hidden" name="notify_new_unassigned" value="0" />
<?php
                }

                $checked = '';
                if (!empty($_SESSION['new']['notify_new_my'])) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_new_my" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('A new ticket is submitted with owner:'); ?> <?php echo _('Assigned to me'); ?>
                                                    </label>
                                                    <br />
                                                    <hr />
<?php
                if ($this->can_view_unassigned) {
                    $checked = '';
                    if (!empty($_SESSION['new']['notify_reply_unassigned'])) {
                        $checked = 'checked="checked"';
                    }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_reply_unassigned" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('Client responds to a ticket with owner:'); ?> <?php echo _('Unassigned'); ?>
                                                    </label>
                                                    <br />
<?php
                } else {
?>
                                                    <input type="hidden" name="notify_reply_unassigned" value="0" />
<?php
                }

                $checked = '';
                if (!empty($_SESSION['new']['notify_reply_my'])) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_reply_my" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('Client responds to a ticket with owner:'); ?> <?php echo _('Assigned to me'); ?>
                                                    </label>
                                                    <br />
                                                    <hr />
<?php
                $checked = '';
                if (!empty($_SESSION['new']['notify_assigned'])) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_assigned" value="1" <?php echo $checked; ?>/>
                                                        <?php echo _('A ticket is assigned to me'); ?>
                                                    </label>
                                                    <br />
<?php
                $checked = '';
                if (!empty($_SESSION['new']['notify_note'])) {
                    $checked = 'checked="checked"';
                }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_note" value="1" <?php echo $checked; ?> />
                                                        <?php echo _('Someone added a note to a ticket assigned to me'); ?>
                                                    </label>
                                                    <br />
<?php
            }

            $checked = '';
            if (!empty($_SESSION['new']['notify_pm'])) {
                $checked = 'checked="checked"';
            }
?>
                                                    <label>
                                                        <input type="checkbox" name="notify_pm" value="1" <?php echo $checked; ?> />
                                                        <?php echo _('A private message is sent to me'); ?>
                                                    </label>
                                                    <br />
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="roundcornersright">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornersbottom"></td>
                                    <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                            </table>

                            <!-- Submit -->
                            <p align="center"><input type="hidden" name="action" value="update" />
                                <input type="hidden" name="token" value="<?php $helpbase->common->token_echo(); ?>" />
                                <input type="submit" value="<?php echo _('Update profile'); ?>" class="button blue small" /></p>
                            <p>&nbsp;</p>
                        </form>
<?php

            $helpbase->footer->render();

            unset($helpbase);

            exit();
        }

        private function update_profile() {
            global $hesk_settings, $helpbase;

            /* A security check */
            $helpbase->common->token_check('POST');

            $sql_pass           = '';
            $sql_username       = '';
            $hesk_error_buffer  = '';

            $_SESSION['new']['name']        = $helpbase->common->_input($helpbase->common->_post('name')) or $hesk_error_buffer .= '<li>' . _('Please enter your name') . '</li>';
            $_SESSION['new']['email']       = $helpbase->common->validateEmail($helpbase->common->_post('email'), 'ERR', 0) or $hesk_error_buffer = '<li>' . _('Please enter a valid email address') . '</li>';
            $_SESSION['new']['signature']   = $helpbase->common->_input($helpbase->common->_post('signature'));

            /* Signature */
            if (strlen($_SESSION['new']['signature']) > 255) {
                $hesk_error_buffer .= '<li>' . _('User signature is too long! Please limit the signature to 255 chars') . '</li>';
            }

            /* Admins can change username */
            if ($_SESSION['isadmin']) {
                $_SESSION['new']['user'] = $helpbase->common->_input($helpbase->common->_post('user')) or $hesk_error_buffer .= '<li>' . _('Please enter username (login)') . '</li>';

                /* Check for duplicate usernames */
                $result = $helpbase->database->query("SELECT `id` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `user`='" . $helpbase->database->escape($_SESSION['new']['user']) . "' AND `id`!='" . intval($_SESSION['id']) . "' LIMIT 1");
                if ($helpbase->database->numRows($result) != 0) {
                    $hesk_error_buffer .= '<li>' . _('User with this username already exists, choose a different username.') . '</li>';
                } else {
                    $sql_username = ",`user`='" . $helpbase->database->escape($_SESSION['new']['user']) . "'";
                }
            }

            /* Change password? */
            $newpass = $helpbase->common->_input($helpbase->common->_post('newpass'));
            $passlen = strlen($newpass);
            if ($passlen > 0) {
                /* At least 5 chars? */
                if ($passlen < 5) {
                    $hesk_error_buffer .= '<li>' . _('Password must be at least 5 chars long') . '</li>';
                }
                /* Check password confirmation */ else {
                    $newpass2 = $helpbase->common->_input($helpbase->common->_post('newpass2'));

                    if ($newpass != $newpass2) {
                        $hesk_error_buffer .= '<li>' . _('The two passwords entered are not the same!') . '</li>';
                    } else {
                        $v = $helpbase->admin->pass2Hash($newpass);
                        if ($v == '499d74967b28a841c98bb4baaabaad699ff3c079') {
                            $this->warn_password = true;
                        }
                        $sql_pass = ',`pass`=\'' . $v . '\'';
                    }
                }
            }

            /* After reply */
            $_SESSION['new']['afterreply'] = intval($helpbase->common->_post('afterreply'));
            if ($_SESSION['new']['afterreply'] != 1 && $_SESSION['new']['afterreply'] != 2) {
                $_SESSION['new']['afterreply'] = 0;
            }

            /* Auto-start ticket timer */
            $_SESSION['new']['autostart'] = isset($_POST['autostart']) ? 1 : 0;

            /* Notifications */
            $_SESSION['new']['notify_new_unassigned']   = empty($_POST['notify_new_unassigned']) || !$this->can_view_unassigned ? 0 : 1;
            $_SESSION['new']['notify_new_my']           = empty($_POST['notify_new_my']) ? 0 : 1;
            $_SESSION['new']['notify_reply_unassigned'] = empty($_POST['notify_reply_unassigned']) || !$this->can_view_unassigned ? 0 : 1;
            $_SESSION['new']['notify_reply_my']         = empty($_POST['notify_reply_my']) ? 0 : 1;
            $_SESSION['new']['notify_assigned']         = empty($_POST['notify_assigned']) ? 0 : 1;
            $_SESSION['new']['notify_note']             = empty($_POST['notify_note']) ? 0 : 1;
            $_SESSION['new']['notify_pm']               = empty($_POST['notify_pm']) ? 0 : 1;

            /* Any errors? */
            if (strlen($hesk_error_buffer)) {
                /* Process the session variables */
                $_SESSION['new'] = $helpbase->common->stripArray($_SESSION['new']);

                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $helpbase->common->process_messages($hesk_error_buffer, 'NOREDIRECT');
            } else {
                /* Update database */
                $helpbase->database->query(
                    "UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET
                        `name`='" . $helpbase->database->escape($_SESSION['new']['name']) . "',
                        `email`='" . $helpbase->database->escape($_SESSION['new']['email']) . "',
                        `signature`='" . $helpbase->database->escape($_SESSION['new']['signature']) . "'
                        $sql_username
                        $sql_pass ,
                        `afterreply`='" . intval($_SESSION['new']['afterreply']) . "' ,
                        `autostart`='" . intval($_SESSION['new']['autostart']) . "' ,
                        `notify_new_unassigned`='" . intval($_SESSION['new']['notify_new_unassigned']) . "' ,
                        `notify_new_my`='" . intval($_SESSION['new']['notify_new_my']) . "' ,
                        `notify_reply_unassigned`='" . intval($_SESSION['new']['notify_reply_unassigned']) . "' ,
                        `notify_reply_my`='" . intval($_SESSION['new']['notify_reply_my']) . "' ,
                        `notify_assigned`='" . intval($_SESSION['new']['notify_assigned']) . "' ,
                        `notify_pm`='" . intval($_SESSION['new']['notify_pm']) . "',
                        `notify_note`='" . intval($_SESSION['new']['notify_note']) . "'
                    WHERE `id`='" . intval($_SESSION['id']) . "' LIMIT 1"
                );

                /* Process the session variables */
                $_SESSION['new'] = $helpbase->common->stripArray($_SESSION['new']);

                /* Update session variables */
                foreach ($_SESSION['new'] as $k => $v) {
                    $_SESSION[$k] = $v;
                }
                unset($_SESSION['new']);

                $helpbase->common->process_messages(_('This profile has been successfully updated.'), 'profile.php', 'SUCCESS');
            }
        }
    }
}

new HelpbaseProfile;

?>
