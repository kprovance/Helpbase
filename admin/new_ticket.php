<?php
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  New Ticket
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
define('EXECUTING', true);

if (!class_exists('HelpbaseNewTicket')) {

    class HelpbaseNewTicket {

        private $helpbase   = null;
        private $admins     = array();

        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            // Auto-focus first empty or error field
            $helpbase->autofocus = true;

            $helpbase->admin->isLoggedIn();

            /* List of users */
            $this->admins = array();
            $result = $helpbase->database->query("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` ORDER BY `id` ASC");
            while ($row = $helpbase->database->fetchAssoc($result)) {
                /* Is this an administrator? */
                if ($row['isadmin']) {
                    $this->admins[$row['id']] = $row['name'];
                    continue;
                }

                /* Not admin, is user allowed to view tickets? */
                if (strpos($row['heskprivileges'], 'can_view_tickets') !== false) {
                    $this->admins[$row['id']] = $row['name'];
                    continue;
                }
            }            
            
            $this->render();
        }

        private function render() {
            global $hesk_settings;

            /* Varibles for coloring the fields in case of errors */
            if (!isset($_SESSION['iserror'])) {
                $_SESSION['iserror'] = array();
            }

            if (!isset($_SESSION['isnotice'])) {
                $_SESSION['isnotice'] = array();
            }             
            
            /* Print header */
            $this->helpbase->header->render();

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
            ?>

            </td>
            </tr>
            <tr>
                <td>

            <?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
            ?>

                    <p class="smaller">&nbsp;<a href="admin_main.php" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a> &gt; <?php echo _('Insert a new ticket'); ?></p>

                    <p><?php echo _('Use this form to create a new ticket in a customer\'s name. Enter <i>customer</i> information in the form (customer name, customer email, ...) and NOT your name!  Ticket will be created as if the customer submitted it.'); ?><br />&nbsp;</p>

                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                            <td class="roundcornerstop"></td>
                            <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                        </tr>
                        <tr>
                            <td class="roundcornersleft">&nbsp;</td>
                            <td>

                                <h3 align="center"><?php echo _('Insert a new ticket'); ?></h3>

                                <p align="center"><?php echo _('Required fields are marked with'); ?> <font class="important">*</font></p>

                                <!-- START FORM -->

                                <form method="post" action="<?php echo $this->helpbase->url; ?>admin/admin_submit_ticket.php" name="form1" enctype="multipart/form-data">

                                    <!-- Contact info -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Name'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><input type="text" name="name" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_name'])) {
                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_name']));
                    } ?>" <?php if (in_array('name', $_SESSION['iserror'])) {
                        echo ' class="isError" ';
                    } ?> /></td>
                                        </tr>
                                        <tr>

                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Company'); ?>: </td>
                                            <td width="80%"><input type="text" name="company" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_company'])) {
                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_company']));
                    } ?>" <?php if (in_array('company', $_SESSION['iserror'])) {
                        echo ' class="isError" ';
                    } ?> /></td>
                                        </tr>

                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Email'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><input type="text" name="email" size="40" maxlength="255" value="<?php if (isset($_SESSION['as_email'])) {
                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_email']));
                    } ?>" <?php if (in_array('email', $_SESSION['iserror'])) {
                        echo ' class="isError" ';
                    } elseif (in_array('email', $_SESSION['isnotice'])) {
                        echo ' class="isNotice" ';
                    } ?> <?php if ($hesk_settings['detect_typos']) {
                        echo ' onblur="Javascript:hb_suggestEmail(1)"';
                    } ?> /></td>
                                        </tr>

                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Home phone'); ?>: </td>
                                            <td width="80%"><input type="text" name="homephone" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_homephone'])) {
                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_homephone']));
                    } ?>" <?php if (in_array('homephone', $_SESSION['iserror'])) {
                        echo ' class="isError" ';
                    } ?> /></td>
                                        </tr>

                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Mobile phone'); ?>: </td>
                                            <td width="80%"><input type="text" name="mobilephone" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_mobilephone'])) {
                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_mobilephone']));
                    } ?>" <?php if (in_array('mobilephone', $_SESSION['iserror'])) {
                                                        echo ' class="isError" ';
                                                    } ?> /></td>
                                        </tr>

                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Work phone'); ?>: </td>
                                            <td width="80%"><input type="text" name="workphone" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_workphone'])) {
                                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_workphone']));
                                                    } ?>" <?php if (in_array('workphone', $_SESSION['iserror'])) {
                                                        echo ' class="isError" ';
                                                    } ?> /></td>
                                        </tr>
                                    </table>

                                    <div id="email_suggestions"></div> 

                                    <hr />

                                    <!-- Department and priority -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Category'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><select name="category" <?php if (in_array('category', $_SESSION['iserror'])) {
                                                        echo ' class="isError" ';
                                                    } elseif (in_array('category', $_SESSION['isnotice'])) {
                                                        echo ' class="isNotice" ';
                                                    } ?> >
            <?php
            if (!empty($_GET['catid'])) {
                $_SESSION['as_category'] = intval($this->helpbase->common->_get('catid'));
            }

            $result = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'categories` ORDER BY `cat_order` ASC');
            while ($row = $this->helpbase->database->fetchAssoc($result)) {
                if (isset($_SESSION['as_category']) && $_SESSION['as_category'] == $row['id']) {
                    $selected = ' selected="selected"';
                } else {
                    $selected = '';
                }
                echo '<option value="' . $row['id'] . '"' . $selected . '>' . $row['name'] . '</option>';
            }
            ?>
                                                </select></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Priority'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><select name="priority" <?php if (in_array('priority', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> >
                                                    <option value="3" <?php if (isset($_SESSION['as_priority']) && $_SESSION['as_priority'] == 3) {
                                        echo 'selected="selected"';
                                    } ?>><?php echo _('Low'); ?></option>
                                                    <option value="2" <?php if (isset($_SESSION['as_priority']) && $_SESSION['as_priority'] == 2) {
                                        echo 'selected="selected"';
                                    } ?>><?php echo _('Medium'); ?></option>
                                                    <option value="1" <?php if (isset($_SESSION['as_priority']) && $_SESSION['as_priority'] == 1) {
                                        echo 'selected="selected"';
                                    } ?>><?php echo _('High'); ?></option>
                                                    <option value="0" <?php if (isset($_SESSION['as_priority']) && $_SESSION['as_priority'] == 0) {
                                        echo 'selected="selected"';
                                    } ?>><?php echo _(' * Critical * '); ?></option>
                                                </select></td>
                                        </tr>
                                    </table>

                                    <hr />

                                    <!-- START CUSTOM BEFORE -->
                                    <?php
                                    /* custom fields BEFORE comments */

                                    $print_table = 0;

                                    foreach ($hesk_settings['custom_fields'] as $k => $v) {
                                        if ($v['use'] && $v['place'] == 0) {
                                            if ($print_table == 0) {
                                                echo '<table border="0" width="100%">';
                                                $print_table = 1;
                                            }

                                            # $v['req'] = $v['req'] ? '<font class="important">*</font>' : '';
                                            # Staff doesn't need to fill in required custom fields
                                            $v['req'] = '';

                                            if ($v['type'] == 'checkbox') {
                                                $k_value = array();
                                                if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"])) {
                                                    foreach ($_SESSION["as_$k"] as $myCB) {
                                                        $k_value[] = stripslashes($this->helpbase->common->_input($myCB));
                                                    }
                                                }
                                            } elseif (isset($_SESSION["as_$k"])) {
                                                $k_value = stripslashes($this->helpbase->common->_input($_SESSION["as_$k"]));
                                            } else {
                                                $k_value = '';
                                            }

                                            switch ($v['type']) {
                                                /* Radio box */
                                                case 'radio':
                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%">';

                                                    $options = explode('#HESK#', $v['value']);
                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    foreach ($options as $option) {

                                                        if (strlen($k_value) == 0 || $k_value == $option) {
                                                            $k_value = $option;
                                                            $checked = 'checked="checked"';
                                                        } else {
                                                            $checked = '';
                                                        }

                                                        echo '<label><input type="radio" name="' . $k . '" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                                                    }

                                                    echo '</td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Select drop-down box */
                                                case 'select':

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%"><select name="' . $k . '" ' . $cls . '>';

                                                    $options = explode('#HESK#', $v['value']);

                                                    foreach ($options as $option) {

                                                        if (strlen($k_value) == 0 || $k_value == $option) {
                                                            $k_value = $option;
                                                            $selected = 'selected="selected"';
                                                        } else {
                                                            $selected = '';
                                                        }

                                                        echo '<option ' . $selected . '>' . $option . '</option>';
                                                    }

                                                    echo '</select></td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Checkbox */
                                                case 'checkbox':
                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%">';

                                                    $options = explode('#HESK#', $v['value']);
                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    foreach ($options as $option) {

                                                        if (in_array($option, $k_value)) {
                                                            $checked = 'checked="checked"';
                                                        } else {
                                                            $checked = '';
                                                        }

                                                        echo '<label><input type="checkbox" name="' . $k . '[]" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                                                    }

                                                    echo '</td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Large text box */
                                                case 'textarea':
                                                    $size = explode('#', $v['value']);
                                                    $size[0] = empty($size[0]) ? 5 : intval($size[0]);
                                                    $size[1] = empty($size[1]) ? 30 : intval($size[1]);

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                    <td width="80%"><textarea name="' . $k . '" rows="' . $size[0] . '" cols="' . $size[1] . '" ' . $cls . '>' . $k_value . '</textarea></td>
                                                    </tr>
                                    ';
                                                    break;

                                                /* Default text input */
                                                default:
                                                    if (strlen($k_value) != 0) {
                                                        $v['value'] = $k_value;
                                                    }

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                    <td width="80%"><input type="text" name="' . $k . '" size="40" maxlength="' . $v['maxlen'] . '" value="' . $v['value'] . '" ' . $cls . ' /></td>
                                                    </tr>
                                                    ';
                                            }
                                        }
                                    }

                                    /* If table was started we need to close it */
                                    if ($print_table) {
                                        echo '</table> <hr />';
                                        $print_table = 0;
                                    }
                                    ?>
                                    <!-- END CUSTOM BEFORE -->

                                    <!-- Device data -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Device type'); ?>: </td>
                                            <td width="80%"><input type="text" name="devicetype" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_devicetype'])) {
                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_devicetype']));
                                    } ?>" <?php if (in_array('devicetype', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Device brand'); ?>: </td>
                                            <td width="80%"><input type="text" name="devicebrand" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_devicebrand'])) {
                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_devicebrand']));
                                    } ?>" <?php if (in_array('devicebrand', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Device ID'); ?>: </td>
                                            <td width="80%"><input type="text" name="deviceid" size="40" maxlength="30" value="<?php if (isset($_SESSION['as_deviceid'])) {
                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_deviceid']));
                                    } ?>" <?php if (in_array('deviceid', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> /></td>
                                        </tr>            
                                    </table>
                                    <hr>

                                    <!-- ticket info -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="150"><?php echo _('Subject'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><input type="text" name="subject" size="40" maxlength="40" value="<?php if (isset($_SESSION['as_subject'])) {
                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_subject']));
                                    } ?>" <?php if (in_array('subject', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="150" valign="top"><?php echo _('Message'); ?>: <font class="important">*</font></td>
                                            <td width="80%"><textarea name="message" rows="12" cols="60" <?php if (in_array('message', $_SESSION['iserror'])) {
                                        echo ' class="isError" ';
                                    } ?> ><?php if (isset($_SESSION['as_message'])) {
                                        echo stripslashes($this->helpbase->common->_input($_SESSION['as_message']));
                                    } ?></textarea></td>
                                        </tr>
                                    </table>

                                    <hr />

                                    <!-- START CUSTOM AFTER -->
                                    <?php
                                    /* custom fields AFTER comments */
                                    $print_table = 0;

                                    foreach ($hesk_settings['custom_fields'] as $k => $v) {
                                        if ($v['use'] && $v['place']) {
                                            if ($print_table == 0) {
                                                echo '<table border="0" width="100%">';
                                                $print_table = 1;
                                            }

                                            # $v['req'] = $v['req'] ? '<font class="important">*</font>' : '';
                                            # Staff doesn't need to fill in required custom fields
                                            $v['req'] = '';

                                            if ($v['type'] == 'checkbox') {
                                                $k_value = array();
                                                if (isset($_SESSION["as_$k"]) && is_array($_SESSION["as_$k"])) {
                                                    foreach ($_SESSION["as_$k"] as $myCB) {
                                                        $k_value[] = stripslashes($this->helpbase->common->_input($myCB));
                                                    }
                                                }
                                            } elseif (isset($_SESSION["as_$k"])) {
                                                $k_value = stripslashes($this->helpbase->common->_input($_SESSION["as_$k"]));
                                            } else {
                                                $k_value = '';
                                            }


                                            switch ($v['type']) {
                                                /* Radio box */
                                                case 'radio':
                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%">';

                                                    $options = explode('#HESK#', $v['value']);
                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    foreach ($options as $option) {

                                                        if (strlen($k_value) == 0 || $k_value == $option) {
                                                            $k_value = $option;
                                                            $checked = 'checked="checked"';
                                                        } else {
                                                            $checked = '';
                                                        }

                                                        echo '<label><input type="radio" name="' . $k . '" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                                                    }

                                                    echo '</td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Select drop-down box */
                                                case 'select':

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%"><select name="' . $k . '" ' . $cls . '>';

                                                    $options = explode('#HESK#', $v['value']);

                                                    foreach ($options as $option) {

                                                        if (strlen($k_value) == 0 || $k_value == $option) {
                                                            $k_value = $option;
                                                            $selected = 'selected="selected"';
                                                        } else {
                                                            $selected = '';
                                                        }

                                                        echo '<option ' . $selected . '>' . $option . '</option>';
                                                    }

                                                    echo '</select></td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Checkbox */
                                                case 'checkbox':
                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                    <td width="80%">';

                                                    $options = explode('#HESK#', $v['value']);
                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    foreach ($options as $option) {

                                                        if (in_array($option, $k_value)) {
                                                            $checked = 'checked="checked"';
                                                        } else {
                                                            $checked = '';
                                                        }

                                                        echo '<label><input type="checkbox" name="' . $k . '[]" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                                                    }

                                                    echo '</td>
                                                    </tr>
                                                    ';
                                                    break;

                                                /* Large text box */
                                                case 'textarea':
                                                    $size = explode('#', $v['value']);
                                                    $size[0] = empty($size[0]) ? 5 : intval($size[0]);
                                                    $size[1] = empty($size[1]) ? 30 : intval($size[1]);

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                    <td width="80%"><textarea name="' . $k . '" rows="' . $size[0] . '" cols="' . $size[1] . '" ' . $cls . '>' . $k_value . '</textarea></td>
                                                    </tr>
                                    ';
                                                    break;

                                                /* Default text input */
                                                default:
                                                    if (strlen($k_value) != 0) {
                                                        $v['value'] = $k_value;
                                                    }

                                                    $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';

                                                    echo '
                                                    <tr>
                                                    <td style="text-align:right" width="150">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                    <td width="80%"><input type="text" name="' . $k . '" size="40" maxlength="' . $v['maxlen'] . '" value="' . $v['value'] . '" ' . $cls . ' /></td>
                                                    </tr>
                                                    ';
                                            }
                                        }
                                    }

                                    /* If table was started we need to close it */
                                    if ($print_table) {
                                        echo '</table> <hr />';
                                        $print_table = 0;
                                    }
                                    ?>
                                    <!-- END CUSTOM AFTER -->

                                                <?php
                                                /* attachments */
                                                if ($hesk_settings['attachments']['use']) {
                                                    ?>
                                        <table border="0" width="100%">
                                            <tr>
                                                <td style="text-align:right" width="150" valign="top"><?php echo _('Attachments'); ?>:</td>
                                                <td width="80%" valign="top">
                                                    <?php
                                                    for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                                                        $cls = ($i == 1 && in_array('attachments', $_SESSION['iserror'])) ? ' class="isError" ' : '';
                                                        echo '<input type="file" name="attachment[' . $i . ']" size="50" ' . $cls . ' /><br />';
                                                    }
                                                    ?>
                                                    <a href="Javascript:void(0)" onclick="Javascript:hb_window('../file_limits.php', 250, 500);
                                return false;"><?php echo _('File upload limits'); ?></a>
                                                </td>
                                            </tr>
                                        </table>

                                        <hr />
                <?php
            }
            ?>

                                    <!-- Admin options -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="150" valign="top"><b><?php echo _('Options'); ?>:</b></td>
                                            <td width="80%">
                                                <label><input type="checkbox" name="notify" value="1" <?php echo (!isset($_SESSION['as_notify']) || !empty($_SESSION['as_notify'])) ? 'checked="checked"' : ''; ?> /> <?php echo _('Send email notification to the customer'); ?></label><br />
                                                <label><input type="checkbox" name="show" value="1" <?php echo (!isset($_SESSION['as_show']) || !empty($_SESSION['as_show'])) ? 'checked="checked"' : ''; ?> /> <?php echo _('Show the ticket after submission'); ?></label><br />
                                                <hr />
                                            </td>
                                        </tr>

                                                <?php
                                                if ($this->helpbase->admin->checkPermission('can_assign_others', 0)) {
                                                    ?>
                                            <tr>
                                                <td style="text-align:right" width="150" valign="top"><b><?php echo _('Owner'); ?>:</b></td>
                                                <td width="80%">
                                                        <?php echo _('Assign this ticket to'); ?> <select name="owner" <?php if (in_array('owner', $_SESSION['iserror'])) {
                                                        echo ' class="isError" ';
                                                    } ?>>
                                                        <option value="-1"> &gt; <?php echo _('Unassigned'); ?> &lt; </option>
                                                        <?php
                                                        if ($hesk_settings['autoassign']) {
                                                            echo '<option value="-2"> &gt; ' . _('Auto-assign') . ' &lt; </option>';
                                                        }

                                                        $owner = isset($_SESSION['as_owner']) ? intval($_SESSION['as_owner']) : 0;

                                                        foreach ($this->admins as $k => $v) {
                                                            if ($k == $owner) {
                                                                echo '<option value="' . $k . '" selected="selected">' . $v . '</option>';
                                                            } else {
                                                                echo '<option value="' . $k . '">' . $v . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php
                                        } elseif ($this->helpbase->admin->checkPermission('can_assign_self', 0)) {
                                            $checked = (!isset($_SESSION['as_owner']) || !empty($_SESSION['as_owner'])) ? 'checked="checked"' : '';
                                            ?>
                                            <tr>
                                                <td style="text-align:right" width="150" valign="top"><b><?php echo _('Owner'); ?>:</b></td>
                                                <td width="80%">
                                                    <label><input type="checkbox" name="assing_to_self" value="1" <?php echo $checked; ?> /> <?php echo _('Assign this ticket to myself'); ?></label><br />
                                                </td>
                                            </tr>
                <?php
            }
            ?>
                                    </table>

                                    <hr />

                                    <!-- Submit -->
                                    <p align="center">
                                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                        <input type="submit" value="<?php echo _('Submit ticket'); ?>" class="button blue small" />
                                    </p>

                                </form>

                                <!-- END FORM -->

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
            $this->helpbase->common->cleanSessionVars('iserror');
            $this->helpbase->common->cleanSessionVars('isnotice');

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();            
        }
    }
    new HelpbaseNewTicket;
}

?>
