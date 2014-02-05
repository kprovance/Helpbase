<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Manage Canned Responses
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseManageCanned')) {
    class HelpbaseManageCanned {
        private $helpbase   = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_man_canned');

            /* What should we do? */
            if ($action = $helpbase->common->_request('a')) {
                if (true == $helpbase->demo_mode) {
                    $helpbase->common->process_messages(_('Sorry, this function has been disabled in DEMO mode!'), 'manage_canned.php', 'NOTICE');
                } elseif ($action == 'new') {
                    $this->new_saved();
                } elseif ($action == 'edit') {
                    $this->edit_saved();
                } elseif ($action == 'remove') {
                    $this->remove();
                } elseif ($action == 'order') {
                    $this->order_saved();
                }
            }
            
            $this->render();
        }
        
        private function render() {
            global $hesk_settings;
            
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

                                    function hb_insertTag(tag) {
                                        var text_to_insert = '%%' + tag + '%%';
                                        hb_insertAtCursor(document.form1.msg, text_to_insert);
                                        document.form1.msg.focus();
                                    }

                                    function hb_insertAtCursor(myField, myValue) {
                                        if (document.selection) {
                                            myField.focus();
                                            sel = document.selection.createRange();
                                            sel.text = myValue;
                                        } else if (myField.selectionStart || myField.selectionStart == '0') {
                                            var startPos = myField.selectionStart;
                                            var endPos = myField.selectionEnd;
                                            myField.value = myField.value.substring(0, startPos)
                                                + myValue
                                                + myField.value.substring(endPos, myField.value.length);
                                        } else {
                                            myField.value += myValue;
                                        }
                                    }
                                    //-->
                                </script>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
?>
                                <h3 style="padding-bottom:5px"><?php echo _('Canned responses'); ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo $this->helpbase->admin->makeJsString(_('Here you can add and manage canned responses. These are commonly used replies which are more or less the same for every customer. You should use canned responses to avoid typing the same reply to different customers numerous times.')); ?>')">?</a>]</h3>
                                &nbsp;<br />
<?php
            $result = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'std_replies` ORDER BY `reply_order` ASC');
            $options = '';
            $javascript_messages = '';
            $javascript_titles = '';

            $i = 1;
            $j = 0;
            $num = $this->helpbase->database->numRows($result);

            if ($num < 1) {
                echo '<p>' . _('No canned responses') . '</p>';
            } else {
?>
                                <div align="center">
                                    <table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
                                        <tr>
                                            <th class="admin_white" style="text-align:left"><b><i><?php echo _('Title'); ?></i></b></th>
                                            <th class="admin_white" style="width:80px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                                        </tr>
<?php
                while ($mysaved = $this->helpbase->database->fetchAssoc($result)) {
                    $j++;

                    if (isset($_SESSION['canned']['selcat2']) && $mysaved['id'] == $_SESSION['canned']['selcat2']) {
                        $color = 'admin_green';
                        unset($_SESSION['canned']['selcat2']);
                    } else {
                        $color = $i ? 'admin_white' : 'admin_gray';
                    }

                    $tmp = $i ? 'White' : 'Blue';
                    $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                    $i = $i ? 0 : 1;

                    $options .= '<option value="' . $mysaved['id'] . '"';
                    $options .= (isset($_SESSION['canned']['id']) && $_SESSION['canned']['id'] == $mysaved['id']) ? ' selected="selected" ' : '';
                    $options .= '>' . $mysaved['title'] . '</option>';


                    $javascript_messages.='myMsgTxt[' . $mysaved['id'] . ']=\'' . str_replace("\r\n", "\\r\\n' + \r\n'", addslashes($mysaved['message'])) . "';\n";
                    $javascript_titles.='myTitle[' . $mysaved['id'] . ']=\'' . addslashes($mysaved['title']) . "';\n";

                    echo '
                                        <tr>
                                            <td class="' . $color . '" style="text-align:left">' . $mysaved['title'] . '</td>
                                            <td class="' . $color . '" style="text-align:center; white-space:nowrap;">';
                    $move_up = _('Move up');
                    $move_down = _('Move down');
                    if ($num > 1) {
                        if ($j == 1) {
                            echo'
                                                <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" /> <a href="manage_canned.php?a=order&amp;replyid=' . $mysaved['id'] . '&amp;move=15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_down.png" width="16" height="16" alt="' . $move_down . '" title="' . $move_down . '" ' . $style . ' /></a>';
                        } elseif ($j == $num) {
                            echo'
                                                <a href="manage_canned.php?a=order&amp;replyid=' . $mysaved['id'] . '&amp;move=-15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_up.png" width="16" height="16" alt="' . $move_up . '" title="' . $move_up . '" ' . $style . ' /></a> <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
                        } else {
                            echo'
                                                <a href="manage_canned.php?a=order&amp;replyid=' . $mysaved['id'] . '&amp;move=-15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_up.png" width="16" height="16" alt="' . $move_up . '" title="' . $move_up . '" ' . $style . ' /></a>
                                                <a href="manage_canned.php?a=order&amp;replyid=' . $mysaved['id'] . '&amp;move=15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_down.png" width="16" height="16" alt="' . $move_down . '" title="' . $move_down . '" ' . $style . ' /></a>';
                        }
                    } else {
                        echo '';
                    }

                    echo '
                                                <a href="manage_canned.php?a=remove&amp;id=' . $mysaved['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="' . _('Remove') . '" title="' . _('Remove') . '" ' . $style . ' /></a>&nbsp;
                                            </td>
                                        </tr>';
                }
?>
                                    </table>
                                </div>
<?php
            }
?>
                                <script language="javascript" type="text/javascript">
                                    <!--
                                    var myMsgTxt = new Array();
                                    myMsgTxt[0] = '';
                                    var myTitle = new Array();
                                    myTitle[0] = '';
<?php
                                    echo $javascript_titles;
                                    echo $javascript_messages;
?>
                                    function setMessage(msgid) {
                                        if (document.getElementById) {
                                            document.getElementById('HeskMsg').innerHTML = '<textarea name="msg" rows="15" cols="70">' + myMsgTxt[msgid] + '</textarea>';
                                            document.getElementById('HeskTitle').innerHTML = '<input type="text" name="name" size="40" maxlength="50" value="' + myTitle[msgid] + '">';
                                        } else {
                                            document.form1.msg.value = myMsgTxt[msgid];
                                            document.form1.name.value = myTitle[msgid];
                                        }

                                        if (msgid == 0) {
                                            document.form1.a[0].checked = true;
                                        } else {
                                            document.form1.a[1].checked = true;
                                        }
                                    }
                                    //-->
                                </script>
                                <p>&nbsp;</p>
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                        <td class="roundcornerstop"></td>
                                        <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                    </tr>
                                    <tr>
                                        <td class="roundcornersleft">&nbsp;</td>
                                        <td>
                                            <form action="manage_canned.php" method="post" name="form1">
                                                <h3 align="center"><?php echo _('Add or Edit a canned response'); ?></h3>
                                                <div align="center">
                                                    <table border="0">
                                                        <tr>
                                                            <td>
<?php
            if ($num > 0) {
?>
                                                                <p>
                                                                    <label><input type="radio" name="a" value="new" <?php echo (!isset($_SESSION['canned']['what']) || $_SESSION['canned']['what'] != 'EDIT') ? 'checked="checked"' : ''; ?> /> <?php echo _('Create a new canned response'); ?></label><br />
                                                                    <label><input type="radio" name="a" value="edit" <?php echo (isset($_SESSION['canned']['what']) && $_SESSION['canned']['what'] == 'EDIT') ? 'checked="checked"' : ''; ?> /> <?php echo _('Edit selected canned response'); ?></label>:
                                                                    <select name="saved_replies" onchange="setMessage(this.value)"><option value="0"> - <?php echo _('Select / Empty'); ?> - </option><?php echo $options; ?></select>
                                                                </p>
<?php
            } else {
                echo '<p><input type="hidden" name="a" value="new" /> ' . _('Create a new canned response') . '</label></p>';
            }
?>
                                                                <p>
                                                                    <b><?php echo _('Title'); ?>:</b> 
                                                                    
<?php
            $can_name = '';
            if (isset($_SESSION['canned']['name'])) {
                $can_name = ' value="' . stripslashes($_SESSION['canned']['name']) . '" ';
            }
?>
                                                                    <span id="HeskTitle">
                                                                        <input type="text" name="name" size="40" maxlength="50" <?php echo $can_name; ?>/>
                                                                    </span>
                                                                </p>
                                                                <p><b><?php echo _('Message'); ?>:</b>
                                                                    <br />
<?php
            $can_msg = '';
            if (isset($_SESSION['canned']['msg'])) {
                $can_msg = stripslashes($_SESSION['canned']['msg']);
            }
?>
                                                                    <span id="HeskMsg">
                                                                        <textarea name="msg" rows="15" cols="70"><?php echo $can_msg; ?></textarea>
                                                                    </span>
                                                                    <br />

                                                                    <?php echo _('Insert special tag (will be replaced with customer info)'); ?>:<br />
                                                                    <a href="javascript:void(0)" onclick="hb_insertTag('HESK_NAME')"><?php echo _('Name'); ?></a> |
                                                                    <a href="javascript:void(0)" onclick="hb_insertTag('HESK_EMAIL')"><?php echo _('Email'); ?></a>
<?php
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use']) {
                    echo '
                                                                    | <a href="javascript:void(0)" onclick="hb_insertTag(\'HESK_' . $k . '\')">' . $v['name'] . '</a> ';
                }
            }
?>
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <p align="center">
                                                    <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                    <input type="submit" value="<?php echo _('Save response'); ?>" class="button blue small" />
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

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
        
        private function edit_saved() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $hesk_error_buffer = '';

            $id         = intval($this->helpbase->common->_post('saved_replies')) or $hesk_error_buffer .= '<li>' . _('Select the canned response you would like to edit') . '</li>';
            $savename   = $this->helpbase->common->_input($this->helpbase->common->_post('name')) or $hesk_error_buffer .= '<li>' . _('Please enter reply title') . '</li>';
            $msg        = $this->helpbase->common->_input($this->helpbase->common->_post('msg')) or $hesk_error_buffer .= '<li>' . _('Please enter reply message') . '</li>';

            $_SESSION['canned']['what'] = 'EDIT';
            $_SESSION['canned']['id'] = $id;
            $_SESSION['canned']['name'] = $savename;
            $_SESSION['canned']['msg'] = $msg;

            /* Any errors? */
            if (strlen($hesk_error_buffer)) {
                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'manage_canned.php?saved_replies=' . $id);
            }

            $result = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "std_replies` SET `title`='" . $this->helpbase->database->escape($savename) . "',`message`='" . $this->helpbase->database->escape($msg) . "' WHERE `id`='" . intval($id) . "' LIMIT 1");

            unset($_SESSION['canned']['what']);
            unset($_SESSION['canned']['id']);
            unset($_SESSION['canned']['name']);
            unset($_SESSION['canned']['msg']);

            $this->helpbase->common->process_messages(_('Your canned response has been saved for future use'), 'manage_canned.php?saved_replies=' . $id, 'SUCCESS');
        }

        private function new_saved() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $hesk_error_buffer  = '';
            $savename           = $this->helpbase->common->_input($this->helpbase->common->_post('name')) or $hesk_error_buffer .= '<li>' . _('Please enter reply title') . '</li>';
            $msg                = $this->helpbase->common->_input($this->helpbase->common->_post('msg')) or $hesk_error_buffer .= '<li>' . _('Please enter reply message') . '</li>';

            $_SESSION['canned']['what'] = 'NEW';
            $_SESSION['canned']['name'] = $savename;
            $_SESSION['canned']['msg']  = $msg;

            /* Any errors? */
            if (strlen($hesk_error_buffer)) {
                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'manage_canned.php');
            }

            /* Get the latest reply_order */
            $result = $this->helpbase->database->query('SELECT `reply_order` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'std_replies` ORDER BY `reply_order` DESC LIMIT 1');
            $row = $this->helpbase->database->fetchRow($result);
            $my_order = $row[0] + 10;

            $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "std_replies` (`title`,`message`,`reply_order`) VALUES ('" . $this->helpbase->database->escape($savename) . "','" . $this->helpbase->database->escape($msg) . "','" . intval($my_order) . "')");

            unset($_SESSION['canned']['what']);
            unset($_SESSION['canned']['name']);
            unset($_SESSION['canned']['msg']);

            $this->helpbase->common->process_messages(_('Your canned response has been saved for future use'), 'manage_canned.php', 'SUCCESS');
        }

        private function remove() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $mysaved = intval($this->helpbase->common->_get('id')) or $this->helpbase->common->_error(_('This is not a valid ID'));

            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "std_replies` WHERE `id`='" . intval($mysaved) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Internal script error: Canned response not found'));
            }

            $this->helpbase->common->process_messages(_('Selected canned response has been removed from the database'), 'manage_canned.php', 'SUCCESS');
        }

        private function order_saved() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $replyid = intval($this->helpbase->common->_get('replyid')) or $this->helpbase->common->_error(_('Missing canned response ID'));
            $_SESSION['canned']['selcat2'] = $replyid;

            $reply_move = intval($this->helpbase->common->_get('move'));

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "std_replies` SET `reply_order`=`reply_order`+" . intval($reply_move) . " WHERE `id`='" . intval($replyid) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Internal script error: Canned response not found'));
            }

            /* Update all category fields with new order */
            $result = $this->helpbase->database->query('SELECT `id` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'std_replies` ORDER BY `reply_order` ASC');

            $i = 10;
            while ($myreply = $this->helpbase->database->fetchAssoc($result)) {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "std_replies` SET `reply_order`=" . intval($i) . " WHERE `id`='" . intval($myreply['id']) . "' LIMIT 1");
                $i += 10;
            }

            header('Location: manage_canned.php');
            exit();
        }
    }
    
    new HelpbaseManageCanned;
}

?>
