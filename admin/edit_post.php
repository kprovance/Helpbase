<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Edit Post
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if(!class_exists('HelpbaseEditPost')) {
    class HelpbaseEditPost {
        private $helpbase   = null;
        private $is_reply   = false;
        private $ticket     = array();
        private $trackingID = '';
        
        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');
            $helpbase->admin->checkPermission('can_edit_tickets');

            /* Ticket ID */
            $this->trackingID = $helpbase->common->cleanID() or die(_('Internal script error') . ': ' . _('No tracking ID'));

            $this->is_reply = false;
            $tmpvar = array();

            /* Get ticket info */
            $result = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid`='" . $helpbase->database->escape($this->trackingID) . "' LIMIT 1");
            if ($helpbase->database->numRows($result) != 1) {
                $helpbase->common->_error(_('Ticket not found! Please make sure you have entered the correct tracking ID!'));
            }
            $this->ticket = $helpbase->database->fetchAssoc($result);

            // Demo mode
            if (true == $helpbase->demo_mode) {
                $this->ticket['email'] = 'hidden@demo.com';
            }

            /* Is this user allowed to view tickets inside this category? */
            $helpbase->admin->okCategory($this->ticket['category']);

            if ($helpbase->common->isREQUEST('reply')) {
                $tmpvar['id'] = intval($helpbase->common->_request('reply')) or die(_('This is not a valid ID'));

                $result = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `id`='{$tmpvar['id']}' AND `replyto`='" . intval($this->ticket['id']) . "' LIMIT 1");
                if ($helpbase->database->numRows($result) != 1) {
                    $helpbase->common->_error(_('This is not a valid ID'));
                }
                $reply = $helpbase->database->fetchAssoc($result);
                $this->ticket['message'] = $reply['message'];
                $this->is_reply = true;
            }

            if (isset($_POST['save'])) {
                /* A security check */
                $helpbase->common->token_check('POST');

                $hesk_error_buffer = array();

                if (true == $this->is_reply) {
                    $tmpvar['message'] = $helpbase->common->_input($helpbase->common->_post('message')) or $hesk_error_buffer[] = _('Please enter your message');

                    if (count($hesk_error_buffer)) {
                        $myerror = '<ul>';
                        foreach ($hesk_error_buffer as $error) {
                            $myerror .= "<li>$error</li>\n";
                        }
                        $myerror .= '</ul>';
                        $helpbase->common->_error($myerror);
                    }

                    $tmpvar['message'] = $helpbase->common->makeURL($tmpvar['message']);
                    $tmpvar['message'] = nl2br($tmpvar['message']);

                    $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "replies` SET `message`='" . $helpbase->database->escape($tmpvar['message']) . "' WHERE `id`='" . intval($tmpvar['id']) . "' AND `replyto`='" . intval($this->ticket['id']) . "' LIMIT 1");
                } else {
                    $tmpvar['name']         = $helpbase->common->_input($helpbase->common->_post('name')) or $hesk_error_buffer[] = _('Please enter your name');
                    $tmpvar['company']      = $helpbase->common->_input($helpbase->common->_post('company'));
                    $tmpvar['email']        = $helpbase->common->validateEmail($helpbase->common->_post('email'), 'ERR', 0) or $hesk_error_buffer[] = _('Please enter a valid email address');
                    $tmpvar['homephone']    = $helpbase->common->_input($helpbase->common->_post('homephone'));
                    $tmpvar['mobilephone']  = $helpbase->common->_input($helpbase->common->_post('mobilephone'));
                    $tmpvar['workphone']    = $helpbase->common->_input($helpbase->common->_post('workphone'));
                    $tmpvar['devicetype']   = $helpbase->common->_input($helpbase->common->_post('devicetype'));
                    $tmpvar['devicebrand']  = $helpbase->common->_input($helpbase->common->_post('devicebrand'));
                    $tmpvar['deviceid']     = $helpbase->common->_input($helpbase->common->_post('deviceid'));
                    $tmpvar['subject']      = $helpbase->common->_input($helpbase->common->_post('subject')) or $hesk_error_buffer[] = _('Please enter your ticket subject');
                    $tmpvar['message']      = $helpbase->common->_input($helpbase->common->_post('message')) or $hesk_error_buffer[] = _('Please enter your message');

                    // Demo mode
                    if (true == $helpbase->demo_mode) {
                        $tmpvar['email'] = 'hidden@demo.com';
                    }

                    if (count($hesk_error_buffer)) {
                        $myerror = '<ul>';
                        foreach ($hesk_error_buffer as $error) {
                            $myerror .= "<li>$error</li>\n";
                        }
                        $myerror .= '</ul>';
                        $helpbase->common->_error($myerror);
                    }

                    $tmpvar['message'] = $helpbase->common->makeURL($tmpvar['message']);
                    $tmpvar['message'] = nl2br($tmpvar['message']);

                    foreach ($hesk_settings['custom_fields'] as $k => $v) {
                        if ($v['use'] && isset($_POST[$k])) {
                            if (is_array($_POST[$k])) {
                                $tmpvar[$k] = '';
                                foreach ($_POST[$k] as $myCB) {
                                    $tmpvar[$k] .= ( is_array($myCB) ? '' : $helpbase->common->_input($myCB) ) . '<br />';
                                }
                                $tmpvar[$k] = substr($tmpvar[$k], 0, -6);
                            } else {
                                $tmpvar[$k] = $helpbase->common->makeURL(nl2br($helpbase->common->_input($_POST[$k])));
                            }
                        } else {
                            $tmpvar[$k] = '';
                        }
                    }

                    $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET
                            `name`='" . $helpbase->database->escape($tmpvar['name']) . "',
                            `company`='" . $helpbase->database->escape($tmpvar['company']) . "',
                            `email`='" . $helpbase->database->escape($tmpvar['email']) . "',
                            `homephone`='" . $helpbase->database->escape($tmpvar['homephone']) . "',
                            `mobilephone`='" . $helpbase->database->escape($tmpvar['mobilephone']) . "',
                            `workphone`='" . $helpbase->database->escape($tmpvar['workphone']) . "',
                            `devicetype`='" . $helpbase->database->escape($tmpvar['devicetype']) . "',
                            `devicebrand`='" . $helpbase->database->escape($tmpvar['devicebrand']) . "',
                            `deviceid`='" . $helpbase->database->escape($tmpvar['deviceid']) . "',
                            `subject`='" . $helpbase->database->escape($tmpvar['subject']) . "',
                            `message`='" . $helpbase->database->escape($tmpvar['message']) . "',
                            `custom1`='" . $helpbase->database->escape($tmpvar['custom1']) . "',
                            `custom2`='" . $helpbase->database->escape($tmpvar['custom2']) . "',
                            `custom3`='" . $helpbase->database->escape($tmpvar['custom3']) . "',
                            `custom4`='" . $helpbase->database->escape($tmpvar['custom4']) . "',
                            `custom5`='" . $helpbase->database->escape($tmpvar['custom5']) . "',
                            `custom6`='" . $helpbase->database->escape($tmpvar['custom6']) . "',
                            `custom7`='" . $helpbase->database->escape($tmpvar['custom7']) . "',
                            `custom8`='" . $helpbase->database->escape($tmpvar['custom8']) . "',
                            `custom9`='" . $helpbase->database->escape($tmpvar['custom9']) . "',
                            `custom10`='" . $helpbase->database->escape($tmpvar['custom10']) . "',
                            `custom11`='" . $helpbase->database->escape($tmpvar['custom11']) . "',
                            `custom12`='" . $helpbase->database->escape($tmpvar['custom12']) . "',
                            `custom13`='" . $helpbase->database->escape($tmpvar['custom13']) . "',
                            `custom14`='" . $helpbase->database->escape($tmpvar['custom14']) . "',
                            `custom15`='" . $helpbase->database->escape($tmpvar['custom15']) . "',
                            `custom16`='" . $helpbase->database->escape($tmpvar['custom16']) . "',
                            `custom17`='" . $helpbase->database->escape($tmpvar['custom17']) . "',
                            `custom18`='" . $helpbase->database->escape($tmpvar['custom18']) . "',
                            `custom19`='" . $helpbase->database->escape($tmpvar['custom19']) . "',
                            `custom20`='" . $helpbase->database->escape($tmpvar['custom20']) . "'
                            WHERE `id`='" . intval($this->ticket['id']) . "' LIMIT 1");
                }

                unset($tmpvar);
                $helpbase->common->cleanSessionVars('tmpvar');

                $helpbase->common->process_messages(_('Changes to the selecting post have been saved'), 'admin_ticket.php?track=' . $this->trackingID . '&refresh=' . mt_rand(10000, 99999), 'SUCCESS');
            }

            $this->ticket['message'] = $helpbase->common->msgToPlain($this->ticket['message'], 0, 0);            
            
            $this->render();
            
            unset ($helpbase);
        }
        
        private function render() {
            global $hesk_settings;
            
            /* Print header */
            $this->helpbase->header->render();

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
                    <p>
                        <span class="smaller"><a href="admin_ticket.php?track=<?php echo $this->trackingID; ?>&amp;refresh=<?php echo mt_rand(10000, 99999); ?>" class="smaller"><?php echo _('Ticket') . ' ' . $this->trackingID; ?></a> &gt;
                            <?php echo _('Edit post'); ?>
                        </span>
                    </p>
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                            <td class="roundcornerstop"></td>
                            <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                        </tr>
                        <tr>
                            <td class="roundcornersleft">&nbsp;</td>
                            <td>
                                <h3 align="center"><?php echo _('Edit post'); ?></h3>
                                <form method="post" action="edit_post.php" name="form1">
<?php
            /* If it's not a reply edit all the fields */
            if (!$this->is_reply) {
?>
                                    <br />
                                    <div align="center">
                                        <table border="0" cellspacing="1">
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Subject'); ?>: </td>
                                                <td><input type="text" name="subject" size="40" maxlength="40" value="<?php echo $this->ticket['subject']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Name'); ?>: </td>
                                                <td><input type="text" name="name" size="40" maxlength="30" value="<?php echo $this->ticket['name']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Company'); ?>: </td>
                                                <td><input type="text" name="company" size="40" maxlength="30" value="<?php echo $this->ticket['company']; ?>" /></td>
                                            </tr>                
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Email'); ?>: </td>
                                                <td><input type="text" name="email" size="40" maxlength="255" value="<?php echo $this->ticket['email']; ?>" /></td>
                                            </tr>
                                            <tr><td><hr></td></tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Home phone'); ?>: </td>
                                                <td><input type="text" name="homephone" size="40" maxlength="255" value="<?php echo $this->ticket['homephone']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Mobile phone'); ?>: </td>
                                                <td><input type="text" name="mobilephone" size="40" maxlength="255" value="<?php echo $this->ticket['mobilephone']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Work phone'); ?>: </td>
                                                <td><input type="text" name="workphone" size="40" maxlength="255" value="<?php echo $this->ticket['workphone']; ?>" /></td>
                                            </tr>
                                            <tr><td><hr></td></tr>
<?php
                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    if ($v['use']) {
                        $k_value = $this->ticket[$k];

                        if ($v['type'] == 'checkbox') {
                            $k_value = explode('<br />', $k_value);
                        }

                        switch ($v['type']) {
                            /* Radio box */
                            case 'radio':
                                echo '
                                            <tr>
                                                <td style="text-align:right" valign="top">' . $v['name'] . ': </td>
                                                <td>';

                                $options = explode('#HESK#', $v['value']);

                                foreach ($options as $option) {
                                    if (strlen($k_value) == 0 || $k_value == $option) {
                                        $k_value = $option;
                                        $checked = 'checked="checked"';
                                    } else {
                                        $checked = '';
                                    }
                                    echo '
                                                    <label>
                                                        <input type="radio" name="' . $k . '" value="' . $option . '" ' . $checked . ' /> ' . $option . '
                                                    </label>
                                                    <br />';
                                }
                                echo '
                                                </td>
                                            </tr>';
                            break;

                            /* Select drop-down box */
                            case 'select':
                                echo '
                                            <tr>
                                                <td style="text-align:right">' . $v['name'] . ': </td>
                                                <td>
                                                    <select name="' . $k . '">';

                                $options = explode('#HESK#', $v['value']);

                                foreach ($options as $option) {
                                    if (strlen($k_value) == 0 || $k_value == $option) {
                                        $k_value = $option;
                                        $selected = 'selected="selected"';
                                    } else {
                                        $selected = '';
                                    }

                                    echo '
                                                        <option ' . $selected . '>' . $option . '</option>';
                                }
                                echo '
                                                    </select>
                                                </td>
                                            </tr>';
                            break;

                            /* Checkbox */
                            case 'checkbox':
                                echo '
                                            <tr>
                                                <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': </td>
                                                <td width="80%">';

                                $options = explode('#HESK#', $v['value']);

                                foreach ($options as $option) {
                                    if (in_array($option, $k_value)) {
                                        $checked = 'checked="checked"';
                                    } else {
                                        $checked = '';
                                    }

                                    echo '
                                                    <label>
                                                        <input type="checkbox" name="' . $k . '[]" value="' . $option . '" ' . $checked . ' /> ' . $option . '
                                                    </label>
                                                    <br />';
                                }

                                echo '
                                                </td>
                                            </tr>';
                            break;

                            /* Large text box */
                            case 'textarea':
                                $size = explode('#', $v['value']);
                                $size[0] = empty($size[0]) ? 5 : intval($size[0]);
                                $size[1] = empty($size[1]) ? 30 : intval($size[1]);

                                $k_value = $this->helpbase->common->msgToPlain($k_value, 0, 0);

                                echo '
                                            <tr>
                                                <td style="text-align:right" valign="top">' . $v['name'] . ': </td>
                                                <td>
                                                    <textarea name="' . $k . '" rows="' . $size[0] . '" cols="' . $size[1] . '">' . $k_value . '</textarea>
                                                </td>
                                            </tr>';
                            break;

                            /* Default text input */
                            default:
                                if (strlen($k_value) != 0) {
                                    $k_value = $this->helpbase->common->msgToPlain($k_value, 0, 0);
                                    $v['value'] = $k_value;
                                }
                                echo '
                                            <tr>
                                                <td style="text-align:right">' . $v['name'] . ': </td>
                                                <td>
                                                    <input type="text" name="' . $k . '" size="40" maxlength="' . $v['maxlen'] . '" value="' . $v['value'] . '" />
                                                </td>
                                            </tr>';
                        }
                    }
                }
?>
                                            <tr><td><hr></td></tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Device type'); ?>: </td>
                                                <td><input type="text" name="devicetype" size="40" maxlength="255" value="<?php echo $this->ticket['devicetype']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Device brand'); ?>: </td>
                                                <td><input type="text" name="devicebrand" size="40" maxlength="255" value="<?php echo $this->ticket['devicebrand']; ?>" /></td>
                                            </tr>
                                            <tr>
                                                <td style="text-align:right"><?php echo _('Device ID'); ?>: </td>
                                                <td><input type="text" name="deviceid" size="40" maxlength="255" value="<?php echo $this->ticket['deviceid']; ?>" /></td>
                                            </tr>
                                        </table>
                                    </div>
<?php
            }
?>
                                    <p style="text-align:center">&nbsp;<br /><?php echo _('Message'); ?>:
                                        <br />
                                        <textarea name="message" rows="12" cols="60"><?php echo $this->ticket['message']; ?></textarea>
                                    </p>

                                    <p style="text-align:center">
                                        <input type="hidden" name="save" value="1" /><input type="hidden" name="track" value="<?php echo $this->trackingID; ?>" />
                                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
<?php
            if (true == $this->is_reply) {
?>
                                        <input type="hidden" name="reply" value="<?php echo $tmpvar['id']; ?>" />
<?php
            }
?>
                                        <input type="submit" value="<?php echo _('Save changes'); ?>" class="button blue small" />
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
                    <p style="text-align:center"><a href="javascript:history.go(-1)"><?php echo _('Go back'); ?></a></p>
                    <p>&nbsp;</p>
<?php
            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
    }
    
    new HelpbaseEditPost;
}

?>
