<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if(!class_exists('HelpbaseAdminIndex')) {
    class HelpbaseAdminIndex {
        private $helpbase = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            /* What should we do? */
            $action = $helpbase->common->_request('a');

            switch ($action) {
                case 'do_login':
                    $this->do_login();
                break;

                case 'login':
                    $this->print_login();
                break;

                case 'kb_logout':
                    $this->kb_logout();
                break;

                default:
                    $helpbase->admin->autoLogin();
                    $this->print_login();
            }
            
            /* Print footer */
            $helpbase->footer->render();

            unset ($helpbase);

            exit();
        }

        private function do_login() {
            global $hesk_settings;

            $hesk_error_buffer = array();

            $user = $this->helpbase->common->_input($this->helpbase->common->_post('user'));
            if (empty($user)) {
                $myerror = $hesk_settings['list_users'] ? _('Please select your username') : _('Please enter username (login)');
                $hesk_error_buffer['user'] = $myerror;
            }
            $this->helpbase->user = $user;

            $pass = $this->helpbase->common->_input($this->helpbase->common->_post('pass'));
            if (empty($pass)) {
                $hesk_error_buffer['pass'] = _('Please enter your password');
            }

            if ($hesk_settings['secimg_use'] == 2 && !isset($_SESSION['img_a_verified'])) {
                // Using ReCaptcha?
                if ($hesk_settings['recaptcha_use']) {
                    require_once($this->helpbase->includes . 'recaptcha/recaptchalib.php');

                    $resp = recaptcha_check_answer(
                            $hesk_settings['recaptcha_private_key'], 
                            $_SERVER['REMOTE_ADDR'], 
                            $this->helpbase->common->_post('recaptcha_challenge_field', ''), 
                            $this->helpbase->common->_post('recaptcha_response_field', '')
                            );

                    if ($resp->is_valid) {
                        $_SESSION['img_a_verified'] = true;
                    } else {
                        $hesk_error_buffer['mysecnum'] = _('Incorrect SPAM Prevention answer, please try again.');
                    }
                }
                // Using PHP generated image
                else {
                    $mysecnum = intval($this->helpbase->common->_post('mysecnum', 0));

                    if (empty($mysecnum)) {
                        $hesk_error_buffer['mysecnum'] = _('Please enter the security number');
                    } else {
                        require($this->helpbase->includes . 'secimg.inc.php');
                        $sc = new PJ_SecurityImage($hesk_settings['secimg_sum']);
                        if (isset($_SESSION['checksum']) && $sc->checkCode($mysecnum, $_SESSION['checksum'])) {
                            $_SESSION['img_a_verified'] = true;
                        } else {
                            $hesk_error_buffer['mysecnum'] = _('Wrong security number');
                        }
                    }
                }
            }

            /* Any missing fields? */
            if (count($hesk_error_buffer) != 0) {
                $_SESSION['a_iserror'] = array_keys($hesk_error_buffer);

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                $hesk_error_buffer = _('Please correct the following errors:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'NOREDIRECT');
                $this->print_login();
                exit();
            } elseif (isset($_SESSION['img_a_verified'])) {
                unset($_SESSION['img_a_verified']);
            }

            /* User entered all required info, now lets limit brute force attempts */
            $this->helpbase->common->limitBfAttempts();

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            $result = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "users` WHERE `user` = '" . $this->helpbase->database->escape($user) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($result) != 1) {
                $this->helpbase->common->session_stop();
                $_SESSION['a_iserror'] = array('user', 'pass');
                $this->helpbase->common->process_messages(_('Wrong username'), 'NOREDIRECT');
                $this->print_login();
                exit();
            }

            $res = $this->helpbase->database->fetchAssoc($result);
            foreach ($res as $k => $v) {
                $_SESSION[$k] = $v;
            }

            /* Check password */
            if ($this->helpbase->admin->pass2Hash($pass) != $_SESSION['pass']) {
                $this->helpbase->common->session_stop();
                $_SESSION['a_iserror'] = array('pass');
                $this->helpbase->common->process_messages( _('Wrong password.'), 'NOREDIRECT' );
                $this->print_login();
                exit();
            }

            $pass_enc = $this->helpbase->admin->pass2Hash($_SESSION['pass'] . strtolower($user) . $_SESSION['pass']);

            /* Check if default password */
            if ($_SESSION['pass'] == '499d74967b28a841c98bb4baaabaad699ff3c079') {
                $this->helpbase->common->process_messages(_('Please change the default password on your <a href="profile.php">Profile</a> page!'), 'NOREDIRECT', 'NOTICE');
            }

            unset($_SESSION['pass']);

            /* Login successful, clean brute force attempts */
            $this->helpbase->common->cleanBfAttempts();

            /* Regenerate session ID (security) */
            $this->helpbase->common->session_regenerate_id();

            /* Remember username? */
            if ($hesk_settings['autologin'] && $this->helpbase->common->_post('remember_user') == 'AUTOLOGIN') {
                setcookie('hesk_username', "$user", strtotime('+1 year'));
                setcookie('hesk_p', "$pass_enc", strtotime('+1 year'));
            } elseif ($this->helpbase->common->_post('remember_user') == 'JUSTUSER') {
                setcookie('hesk_username', "$user", strtotime('+1 year'));
                setcookie('hesk_p', '');
            } else {
                // Expire cookie if set otherwise
                setcookie('hesk_username', '');
                setcookie('hesk_p', '');
            }

            /* Close any old tickets here so Cron jobs aren't necessary */
            if ($hesk_settings['autoclose']) {
                $revision = sprintf(_('<li class="smaller">%s | closed by %s</li>'), $this->helpbase->common->_date(), _('(automatically)'));
                $this->helpbase->database->query("UPDATE `" . $prefix . "tickets` SET `status`='3', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "')  WHERE `status` = '2' AND `lastchange` <= '" . $this->helpbase->database->escape(date('Y-m-d H:i:s', time() - $hesk_settings['autoclose'] * 86400)) . "'");
            }

            /* Redirect to the destination page */
            if ($this->helpbase->common->isREQUEST('goto')) {
                $url = $this->helpbase->common->_request('goto');
                $url = str_replace('&amp;', '&', $url);

                /* goto parameter can be set to the local domain only */
                $myurl = parse_url($hesk_settings['hesk_url']);
                $goto = parse_url($url);

                if (isset($myurl['host']) && isset($goto['host'])) {
                    if (str_replace('www.', '', strtolower($myurl['host'])) != str_replace('www.', '', strtolower($goto['host']))) {
                        $url = 'admin_main.php';
                    }
                }

                header('Location: ' . $url);
            } else {
                header('Location: admin_main.php');
            }
            exit();
        }

        private function print_login() {
            global $hesk_settings;

            $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . _('Staff login');
            $this->helpbase->header->render();

            if ($this->helpbase->common->isREQUEST('notice')) {
                $this->helpbase->common->process_messages(_('Your session has expired, please login using the form below.'), 'NOREDIRECT');
            }

            if (!isset($_SESSION['a_iserror'])) {
                $_SESSION['a_iserror'] = array();
            }
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="3"><img src="../img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                                <td class="headersm"><?php echo _('Login'); ?></td>
                                <td width="3"><img src="../img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                            </tr>
                        </table>
                        <table width="100%" border="0" cellspacing="0" cellpadding="3">
                            <tr>
                                <td>
                                    <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                                        <?php echo _('Staff login'); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                    <br />
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
?>
                    <br />
                    <div align="center">
                        <table border="0" cellspacing="0" cellpadding="0" width="<?php echo ($hesk_settings['secimg_use'] == 2) ? '60' : '50'; ?>% ">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
                                    <form action="index.php" method="post" name="form1">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="60" style="text-align:center"><img src="../img/login.png" alt="" width="24" height="24" /></td>
                                                <td>
                                                    <p><b><?php echo _('Staff login'); ?></a></b></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td>&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td><?php echo _('Username'); ?>:
                                                    <br />
<?php
            $cls = in_array('user', $_SESSION['a_iserror']) ? ' class="isError" ' : '';

            if ('' == $this->helpbase->user) {
                $savedUser = $this->helpbase->user;
            } else {
                $savedUser = $this->helpbase->common->htmlspecialchars($this->helpbase->common->get_cookie('hesk_username'));
            }

            $is_1 = '';
            $is_2 = '';
            $is_3 = '';

            $remember_user = $this->helpbase->common->_post('remember_user');

            if ($hesk_settings['autologin'] && (isset($_COOKIE['hesk_p']) || $remember_user == 'AUTOLOGIN')) {
                $is_1 = 'checked="checked"';
            } elseif (isset($_COOKIE['hesk_username']) || $remember_user == 'JUSTUSER') {
                $is_2 = 'checked="checked"';
            } else {
                $is_3 = 'checked="checked"';
            }

            if ($hesk_settings['list_users']) {
                echo '<select name="user" ' . $cls . '>';
                $res = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'users` ORDER BY `user` ASC');
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $sel = (strtolower($savedUser) == strtolower($row['user'])) ? 'selected="selected"' : '';
                    echo '<option value="' . $row['user'] . '" ' . $sel . '>' . $row['user'] . '</option>';
                }
                echo '</select>';
            } else {
                echo '<input type="text" name="user" size="35" value="' . $savedUser . '" ' . $cls . ' />';
            }
?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td>&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                
<?php
            $isError = '';
            if (in_array('pass', $_SESSION['a_iserror'])) {
                $isError = ' class="isError" ';
            }
?>
                                                <td><?php echo _('Password'); ?>:<br /><input type="password" name="pass" size="35" <?php echo $isError; ?>/></td>
                                            </tr>
<?php
            if ($hesk_settings['secimg_use'] == 2) {
?>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td>
                                                    <hr />
<?php
                // SPAM prevention verified for this session
                if (isset($_SESSION['img_a_verified'])) {
                    echo '
                                                    <img src="' . $this->helpbase->url . 'img/success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> ' . _('Test passed');
                } elseif ($hesk_settings['recaptcha_use']) { // Not verified yet, should we use Recaptcha?
?>
                                                    <script type="text/javascript">
                                                        var RecaptchaOptions = {
                                                            theme: '<?php echo ( isset($_SESSION['a_iserror']) && in_array('mysecnum', $_SESSION['a_iserror']) ) ? 'red' : 'white'; ?>',
                                                            custom_translations: {
                                                                visual_challenge: "<?php echo $this->helpbase->common->slashJS(_('Get a visual challenge')); ?>",
                                                                audio_challenge: "<?php echo $this->helpbase->common->slashJS(_('Get an audio challenge')); ?>",
                                                                refresh_btn: "<?php echo $this->helpbase->common->slashJS(_('Get a new challenge')); ?>",
                                                                instructions_visual: "<?php echo $this->helpbase->common->slashJS(_('Type the two words:')); ?>",
                                                                instructions_context: "<?php echo $this->helpbase->common->slashJS(_('Type the words in the boxes:')); ?>",
                                                                instructions_audio: "<?php echo $this->helpbase->common->slashJS(_('Type what you hear:')); ?>",
                                                                help_btn: "<?php echo $this->helpbase->common->slashJS(_('Help')); ?>",
                                                                play_again: "<?php echo $this->helpbase->common->slashJS(_('Play sound again')); ?>",
                                                                cant_hear_this: "<?php echo $this->helpbase->common->slashJS(_('Download sound as MP3')); ?>",
                                                                incorrect_try_again: "<?php echo $this->helpbase->common->slashJS(_('Incorrect. Try again.')); ?>",
                                                                image_alt_text: "<?php echo $this->helpbase->common->slashJS(_('reCAPTCHA challenge image')); ?>",
                                                            },
                                                        };
                                                    </script>
<?php
                    require_once($this->helpbase->includes . 'recaptcha/recaptchalib.php');
                    echo recaptcha_get_html($hesk_settings['recaptcha_public_key'], null, $hesk_settings['recaptcha_ssl']);
                } else {  // At least use some basic PHP generated image (better than nothing)
                    $cls = in_array('mysecnum', $_SESSION['a_iserror']) ? ' class="isError" ' : '';

                    echo _('Type the number you see in the picture below.') . '<br />&nbsp;<br /><img src="' . $this->helpbase->url . 'print_sec_img.php?' . rand(10000, 99999) . '" width="150" height="40" alt="' . _('Security image') . '" title="' . _('Security image') . '" border="1" name="secimg" style="vertical-align:text-bottom" /> ' .
                    '<a href="javascript:void(0)" onclick="javascript:document.form1.secimg.src=\'' . $this->helpbase->url . 'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);"><img src="' . $this->helpbase->url . 'img/reload.png" height="24" width="24" alt="' . _('Reload image') . '" title="' . _('Reload image') . '" border="0" style="vertical-align:text-bottom" /></a>' .
                    '<br />&nbsp;<br /><input type="text" name="mysecnum" size="20" maxlength="5" ' . $cls . ' />';
                }
?>
                                                    <hr />
                                                </td>
                                            </tr>
<?php
            } else {
?>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td>&nbsp;</td>
                                            </tr>
<?php
            }

            if ($hesk_settings['autologin']) {
?>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td><label><input type="radio" name="remember_user" value="AUTOLOGIN" <?php echo $is_1; ?> /> <?php echo _('Log me on automatically each visit'); ?></label><br />
                                                    <label><input type="radio" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?> /> <?php echo _('Remember just my username'); ?></label><br />
                                                    <label><input type="radio" name="remember_user" value="NOTHANKS" <?php echo $is_3; ?> /> <?php echo _('No, thanks'); ?></label></td>
                                            </tr>
<?php
            } else {
?>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td><label><input type="checkbox" name="remember_user" value="JUSTUSER" <?php echo $is_2; ?> /> <?php echo _('Remember my username'); ?></label></td>
                                            </tr>
<?php
            } // End if $hesk_settings['autologin']
?>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td>&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td width="60">&nbsp;</td>
                                                <td><input type="submit" value="<?php echo _('Click here to login'); ?>" class="button small blue" />
                                                    <input type="hidden" name="a" value="do_login" />
<?php
            if ($this->helpbase->common->isREQUEST('goto') && $url = $this->helpbase->common->_request('goto')) {
                echo '
                                                    <input type="hidden" name="goto" value="' . $url . '" />';
            }
?>
                                                    <br />&nbsp;
                                                </td>
                                            </tr>
                                        </table>
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
                    </div>
                    <p>&nbsp;</p>
<?php
            $this->helpbase->common->cleanSessionVars('a_iserror');

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function kb_logout() {
            global $hesk_settings;

            if (!$this->helpbase->common->token_check('GET', 0)) {
                $this->print_login();
                exit();
            }

            /* Delete from Who's online database */
            if ($hesk_settings['online']) {
                require($this->helpbase->includes . 'users_online.inc.php');
                $usersOnline = new HelpbaseUsersOnline($this->helpbase);
                $usersOnline->setOffline($_SESSION['id']);
                unset ($usersOnline);
            }
            /* Destroy session and cookies */
            $this->helpbase->common->session_stop();

            /* If we're using the security image for admin login start a new session */
            if ($hesk_settings['secimg_use'] == 2) {
                $this->helpbase->common->session_start();
            }

            /* Show success message and reset the cookie */
            $this->helpbase->common->process_messages(_('You have been successfully logged out!'), 'NOREDIRECT', 'SUCCESS');
            setcookie('hesk_p', '');

            /* Print the login form */
            $this->print_login();
            exit();
        }        
    }
    
    new HelpbaseAdminIndex;
}

?>
