<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Index
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseIndex')){
    class HelpbaseIndex {
        private $helpbase = null;
        
        public function __construct(){
            include_once('helpbase.class.php');
            $helpbase = new HelpbaseCore(false);
            $this->helpbase = $helpbase;

            // What should we do?
            $action = $helpbase->common->_request('a');

            switch ($action) {
                case 'add':
                    $helpbase->common->session_start();
                    $this->print_add_ticket();
                    break;
                case 'forgot_tid':
                    $helpbase->common->session_start();
                    $this->forgot_tid();
                    break;
                default:
                    $this->print_start();
            }

            // Print footer
            $helpbase->footer->render();

            unset($helpbase);
        }

        private function print_add_ticket() {
            global $hesk_settings;

            // Auto-focus first empty or error field
            $this->helpbase->autofocus = true;

            // Varibles for coloring the fields in case of errors
            if (!isset($_SESSION['iserror'])) {
                $_SESSION['iserror'] = array();
            }

            if (!isset($_SESSION['isnotice'])) {
                $_SESSION['isnotice'] = array();
            }

            if (!isset($_SESSION['c_category'])) {
                $_SESSION['c_category'] = 0;
            }

            $this->helpbase->common->cleanSessionVars('already_submitted');

            // Print header
            $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . _('Submit a ticket');
            $this->helpbase->header->render();
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                                <td class="headersm"><?php $this->helpbase->common->showTopBar(_('Submit a ticket')); ?></td>
                                <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                            </tr>
                        </table>
                        <table width="100%" border="0" cellspacing="0" cellpadding="3">
                            <tr>
                                <td>
                                    <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                                        <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                                        &gt; <?php echo _('Submit a ticket'); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            $this->helpbase->common->handle_messages();
?>
                        <form method="post" action="submit_ticket.php?submit=1" name="form1" enctype="multipart/form-data">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <!-- START FORM -->
                                        <p style="text-align:center"><?php echo _('<i>Use this form to submit a support request. Required fields are marked with</i>'); ?> <font class="important"> *</font></p>
                                        <!-- Contact info -->
                                        <table border="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top; width: 50%;">
                                                    <table>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Name'); ?>: <font class="important">*</font></td>
<?php
            $c_name = '';
            if (isset($_SESSION['c_name'])) {
                $c_name = stripslashes($this->helpbase->common->_input($_SESSION['c_name']));
            }

            $nameClass = '';
            if (in_array('name', $_SESSION['iserror'])) {
                $nameClass = ' class="isError" ';
            }
?>
                                                            <td width="70%"><input type="text" name="name" size="40" maxlength="30" value="<?php echo $c_name; ?>" <?php echo $nameClass; ?> /></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Company'); ?>: </td>

<?php
            $c_company = '';
            if (isset($_SESSION['c_company'])) {
                $c_company = stripslashes($this->helpbase->common->_input($_SESSION['c_company']));
            }

            $compClass = '';
            if (in_array('company', $_SESSION['iserror'])) {
                $compClass =  ' class="isError" ';
            }
?>

                                                            <td width="70%"><input type="text" name="company" size="40" maxlength="30" value="<?php echo $c_company; ?>" <?php echo $compClass; ?>/></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Email'); ?>: <font class="important">*</font></td>

<?php
            $c_email = '';
            if (isset($_SESSION['c_email'])) {
                $c_email = stripslashes($this->helpbase->common->_input($_SESSION['c_email']));
            }

            $emailClass = '';
            if (in_array('email', $_SESSION['iserror'])) {
                $emailClass = ' class="isError" ';
            } elseif (in_array('email', $_SESSION['isnotice'])) {
                $emailClass = ' class="isNotice" ';
            }

            $onTypo = '';
            if ($hesk_settings['detect_typos']) {
                $onTypo = ' onblur="Javascript:hb_suggestEmail(0)"';
            }
?>

                                                            <td width="70%"><input type="text" name="email" size="40" maxlength="255" value="<?php echo $c_email; ?>" <?php echo $emailClass; ?> <?php echo $onTypo; ?>/></td>
                                                        </tr>
<?php
            if ($hesk_settings['confirm_email']) {
?>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Confirm Email'); ?>: <font class="important">*</font></td>

<?php
                $c_email2 = '';
                if (isset($_SESSION['c_email2'])) {
                    $c_email2 = stripslashes($this->helpbase->common->_input($_SESSION['c_email2']));
                }

                $email2Class = '';
                if (in_array('email2', $_SESSION['iserror'])) {
                    $email2Class = ' class="isError" ';
                }

?>

                                                            <td width="70%"><input type="text" name="email2" size="40" maxlength="255" value="<?php echo $c_email2; ?>" <?php echo $email2Class; ?>/></td>
                                                        </tr>
<?php

            }
?>
                                                        <tr>
                                                            <td><br></td>
                                                            <td><div id="email_suggestions"></div></td>
                                                        </tr>
                                                        <tr>
                                                            <td></td>
                                                            <td><small><i>Please enter at least one phone number.</i></small></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Home phone'); ?>: </td>

<?php
            $c_phone = '';
            if (isset($_SESSION['c_homephone'])) {
                $c_phone = stripslashes($this->helpbase->common->_input($_SESSION['c_homephone']));
            }

            $phoneClass = '';
            if (in_array('homephone', $_SESSION['iserror'])) {
                $phoneClass = ' class="isError" ';
            }
?>
                                                            <td width="70%"><input type="text" name="homephone" size="40" maxlength="30" value="<?php echo $c_phone; ?>" <?php $phoneClass; ?>/></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Mobile phone'); ?>: </td>

<?php
            $c_mobile = '';
            if (isset($_SESSION['c_mobilephone'])) {
                $c_mobile = stripslashes($this->helpbase->common->_input($_SESSION['c_mobilephone']));
            }

            $mobileClass = '';
            if (in_array('mobilephone', $_SESSION['iserror'])) {
                $mobileClass = ' class="isError" ';
            }
?>
                                                            <td width="70%"><input type="text" name="mobilephone" size="40" maxlength="30" value="<?php echo $c_mobile; ?>" <?php echo $mobileClass; ?>/></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Work phone'); ?>: </td>

<?php
            $c_workphone = '';
            if (isset($_SESSION['c_workphone'])) {
                $c_workphone = stripslashes($this->helpbase->common->_input($_SESSION['c_workphone']));
            }

            $workphoneClass = '';
            if (in_array('workphone', $_SESSION['iserror'])) {
                $workphoneClass = ' class="isError" ';
            }
?>
                                                            <td width="70%"><input type="text" name="workphone" size="40" maxlength="30" value="<?php echo $c_workphone; ?>" <?php echo $workphoneClass; ?>/></td>
                                                        </tr>
                                                        <tr>
                                                            <td><br></td>
                                                        </tr>
                                                        
                                                        <!-- Department and priority -->
<?php
            // Get categories
            $res = $this->helpbase->database->query("SELECT `id`, `name` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `type`='0' ORDER BY `cat_order` ASC");

            if ($this->helpbase->database->numRows($res) == 1) {
                // Only 1 public category, no need for a select box
                $row = $this->helpbase->database->fetchAssoc($res);
                echo '                                  
                                                        <input type="hidden" name="category" value="' . $row['id'] . '" />';
            } elseif ($this->helpbase->database->numRows($res) < 1) {
                // No public categories, set it to default one
                echo '
                                                        <input type="hidden" name="category" value="3" />';
            } else {
                // Is the category ID preselected?
                if (!empty($_GET['catid'])) {
                    $_SESSION['c_category'] = intval($this->helpbase->common->_get('catid'));
                }

                $catClass = '';
                // List available categories
                if (in_array('category', $_SESSION['iserror'])) {
                    $catClass = ' class="isError" ';
                }
?>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Category'); ?>: <font class="important">*</font></td>
                                                            <td width="70%">
                                                                <select name="category" <?php echo $catClass; ?>>
<?php
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    echo '<option value="' . $row['id'] . '"' . (($_SESSION['c_category'] == $row['id']) ? ' selected="selected"' : '') . '>' . $row['name'] . '</option>';
                }
?>
                                                                </select>
                                                            </td>
                                                        </tr>
<?php
            }

            /* Can customer assign urgency? */
            $priClass = '';
            if ($hesk_settings['cust_urgency']) {
                if (in_array('priority', $_SESSION['iserror'])) {
                    $priClass = ' class="isError" ';
                }

                if (isset($_SESSION['c_priority'])){
                    $priSel3 = '';
                    $priSel2 = '';
                    $priSel1 = '';
                    
                    switch($_SESSION['c_priority']){
                        case 1:
                            $priSel1 = 'selected="selected"';
                        break;
                    
                        case 2:
                            $priSel2 = 'selected="selected"';
                        break;
                    
                        case 3:
                        default:
                            $priSel3 = 'selected="selected"';
                        break;                    
                    }
                }
?>
                                                        <tr>
                                                            <td style="text-align:right" width="250"><?php echo _('Priority'); ?>: <font class="important">*</font></td>
                                                            <td width="70%">
                                                                <select name="priority" <?php echo $priClass; ?>>
                                                                    <option value="3" <?php echo $priSel3; ?>><?php echo _('Low'); ?></option>
                                                                    <option value="2" <?php echo $priSel2; ?>><?php echo _('Medium'); ?></option>
                                                                    <option value="1" <?php echo $priSel1; ?>><?php echo _('High'); ?></option>
                                                                </select>
                                                            </td>
                                                        </tr>
<?php
            }
?>

                                                        <!-- START CUSTOM BEFORE -->
<?php
            /* custom fields BEFORE comments */
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place'] == 0) {
                    $v['req'] = $v['req'] ? '<font class="important">*</font>' : '';
                    if ($v['type'] == 'checkbox') {
                        $k_value = array();
                        if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"])) {
                            foreach ($_SESSION["c_$k"] as $myCB) {
                                $k_value[] = stripslashes($this->helpbase->common->_input($myCB));
                            }
                        }
                    } elseif (isset($_SESSION["c_$k"])) {
                        $k_value = stripslashes($this->helpbase->common->_input($_SESSION["c_$k"]));
                    } else {
                        $k_value = '';
                    }

                    switch ($v['type']) {
                        
                        /* Radio box */
                        case 'radio':
                            echo '                      <tr>
                                                            <td style="text-align:right" width="250" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                            <td width="70%">';

                            $options = explode('#HESK#', $v['value']);
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            foreach ($options as $option) {
                                if (strlen($k_value) == 0 || $k_value == $option) {
                                    $k_value = $option;
                                    $checked = 'checked="checked"';
                                } else {
                                    $checked = '';
                                }
                                echo '                          <label><input type="radio" name="' . $k . '" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                            }
                            echo '                          </td>
                                                        </tr>';
                        break;

                        /* Select drop-down box */
                        case 'select':
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '                      <tr>
                                                            <td style="text-align:right" width="250">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                            <td width="70%">
                                                                <select name="' . $k . '" ' . $cls . '>';
                            $options = explode('#HESK#', $v['value']);
                            foreach ($options as $option) {
                                if (strlen($k_value) == 0 || $k_value == $option) {
                                    $k_value = $option;
                                    $selected = 'selected="selected"';
                                } else {
                                    $selected = '';
                                }
                                echo '                              <option ' . $selected . '>' . $option . '</option>';
                            }
                            echo '                              </select>
                                                            </td>
                                                        </tr>';
                        break;

                        /* Checkbox */
                        case 'checkbox':
                            echo '
                                                        <tr>
                                                            <td style="text-align:right" width="250" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                            <td width="70%">';

                            $options = explode('#HESK#', $v['value']);
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            foreach ($options as $option) {
                                if (in_array($option, $k_value)) {
                                    $checked = 'checked="checked"';
                                } else {
                                    $checked = '';
                                }
                                echo '                          <label><input type="checkbox" name="' . $k . '[]" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                            }
                            echo '                          </td>
                                                        </tr>';
                        break;

                        /* Large text box */
                        case 'textarea':
                            $size = explode('#', $v['value']);
                            $size[0] = empty($size[0]) ? 5 : intval($size[0]);
                            $size[1] = empty($size[1]) ? 30 : intval($size[1]);

                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '
                                                        <tr>
                                                            <td style="text-align:right" width="250" valign="top">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                            <td width="70%"><textarea name="' . $k . '" rows="' . $size[0] . '" cols="' . $size[1] . '" ' . $cls . '>' . $k_value . '</textarea></td>
                                                        </tr>';
                        break;

                        /* Default text input */
                        default:
                            if (strlen($k_value) != 0) {
                                $v['value'] = $k_value;
                            }

                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '
                                                        <tr>
                                                            <td style="text-align:right" width="250">' . $v['name'] . ': ' . $v['req'] . '</td>
                                                            <td width="70%"><input type="text" name="' . $k . '" size="40" maxlength="' . $v['maxlen'] . '" value="' . $v['value'] . '" ' . $cls . ' /></td>
                                                        </tr>';
                    }
                }
            }
?>

                                                        <!-- END CUSTOM BEFORE -->

                                                    </table>
                                                </td>
                                                <td>&nbsp;&nbsp;&nbsp;</td>
                                                <td style="vertical-align: top">
                                                    <?php echo _('Subject'); ?>: <font class="important">*</font>
                                                    <br>
                                                    
<?php
            $c_subject = '';
            if (isset($_SESSION['c_subject'])) {
                $c_subject = stripslashes($this->helpbase->common->_input($_SESSION['c_subject']));
            }
            
            $subjectClass = '';
            if (in_array('subject', $_SESSION['iserror'])) {
                $subjectClass = ' class="isError" ';
            }            
?>
                                                    
                                                    <input type="text" name="subject" size="40" maxlength="40" value="<?php echo $c_subject; ?>" <?php echo $subjectClass; ?>/>
                                                    <br><br>
                                                        
<?php
            $msgClass = '';
            if (in_array('message', $_SESSION['iserror'])) {
                $msgClass = ' class="isError" ';
            }
            
            $c_msg = '';
            if (isset($_SESSION['c_message'])) {
                $c_msg = stripslashes($this->helpbase->common->_input($_SESSION['c_message']));
            }            
?>
                                                        
                                                    <?php echo _('Message'); ?>: <font class="important">*</font>
                                                    <br>
                                                    <textarea name="message" rows="12" cols="60" <?php echo $msgClass; ?> ><?php echo $c_msg;?></textarea>
                                                    <br><br>

                                                    <!-- START KNOWLEDGEBASE SUGGEST -->
<?php 
            if ($hesk_settings['kb_enable'] && $hesk_settings['kb_recommendanswers']) { 
?>
                                                    <div id="kb_suggestions" style="display:none">
                                                        <br />&nbsp;<br />
                                                        <img src="img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo _('Loading knowledgebase suggestions...'); ?></i>
                                                    </div>

                                                    <script language="Javascript" type="text/javascript">
                                                        <!-- hb_suggestKB(); //-->
                                                    </script>
                                                    <BR/><BR/>
<?php 
            } 
?>
                                                    <!-- END KNOWLEDGEBASE SUGGEST -->

                                                    <?php echo _('Device type'); ?>: <small>ie. Desktop, laptop, etc.</small><br/>
                                                
<?php
            $c_devicetype = '';
            if (isset($_SESSION['c_devicetype'])) {
                $c_devicetype = stripslashes($this->helpbase->common->_input($_SESSION['c_devicetype']));
            }

            $devtypeClass = '';
            if (in_array('devicetype', $_SESSION['iserror'])) {
                $devtypeClass = ' class="isError" ';
            }            
?>
                                                    <input type="text" name="devicetype" size="40" maxlength="30" value="<?php echo $c_devicetype; ?>" <?php echo $devtypeClass; ?>/>
                                                    <br/><br/>
                                                    <?php echo _('Device brand'); ?>: <br/>
                                                
<?php
            $c_devicebrand = '';
            if (isset($_SESSION['c_devicebrand'])) {
                $c_devicebrand = stripslashes($this->helpbase->common->_input($_SESSION['c_devicebrand']));
            }
            
            $devbrandClass = '';
            if (in_array('devicebrand', $_SESSION['iserror'])) {
                $devbrandClass = ' class="isError" ';
            }            
?>
                                                    <input type="text" name="devicebrand" size="40" maxlength="30" value="<?php echo$c_devicebrand; ?>" <?php echo $devbrandClass; ?>/>  <small>ie. Dell, Toshiba, HP, etc.</small>
                                                    <br/><br/>
                                                    <?php echo _('Device ID'); ?>: <small><i>If you have previously had your computer/device serviced with us, it may have a tag with an ID number on it.  Please enter it here if you have one.</i></small><br/>

<?php
            $c_deviceid = '';
            if (isset($_SESSION['c_deviceid'])) {
                $c_deviceid = stripslashes($this->helpbase->common->_input($_SESSION['c_deviceid']));
            }
            
            $devidClass = '';
            if (in_array('deviceid', $_SESSION['iserror'])) {
                $devidClass =  ' class="isError" ';
            }            
?>
                                                    <input type="text" name="deviceid" size="40" maxlength="30" value="<?php echo $c_deviceid; ?>" <?php echo $devidClass; ?>/>
                                                
                                                    <!-- START CUSTOM AFTER -->
<?php
            /* custom fields AFTER comments */
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                if ($v['use'] && $v['place']) {
                    $v['req'] = $v['req'] ? '<font class="important">*</font>' : '';

                    if ($v['type'] == 'checkbox') {
                        $k_value = array();
                        if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"])) {
                            foreach ($_SESSION["c_$k"] as $myCB) {
                                $k_value[] = stripslashes($this->helpbase->common->_input($myCB));
                            }
                        }
                    } elseif (isset($_SESSION["c_$k"])) {
                        $k_value = stripslashes($this->helpbase->common->_input($_SESSION["c_$k"]));
                    } else {
                        $k_value = '';
                    }

                    switch ($v['type']) {
                        /* Radio box */
                        
                        case 'radio':
                            echo '
                                                    ' . $v['name'] . ': ' . $v['req'] . '<br/>';

                            $options = explode('#HESK#', $v['value']);
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            foreach ($options as $option) {
                                if (strlen($k_value) == 0 || $k_value == $option) {
                                    $k_value = $option;
                                    $checked = 'checked="checked"';
                                } else {
                                    $checked = '';
                                }

                                echo '
                                                    <label><input type="radio" name="' . $k . '" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                            }
                            echo '
                                                    <br/><br/>';
                        break;

                        /* Select drop-down box */
                        case 'select':
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '
                                                    ' . $v['name'] . ': ' . $v['req'] . '<br/>
                                                    <select name="' . $k . '" ' . $cls . '>';

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
                                                    </select><br/><br/>';
                        break;

                        /* Checkbox */
                        case 'checkbox':
                            echo '
                                                    ' . $v['name'] . ': ' . $v['req'] . '<br/>';

                            $options = explode('#HESK#', $v['value']);
                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            foreach ($options as $option) {
                                if (in_array($option, $k_value)) {
                                    $checked = 'checked="checked"';
                                } else {
                                    $checked = '';
                                }
                                echo '
                                                    <label><input type="checkbox" name="' . $k . '[]" value="' . $option . '" ' . $checked . ' ' . $cls . ' /> ' . $option . '</label><br />';
                            }

                            echo '
                                                    <br/><br/>';
                        break;

                        /* Large text box */
                        case 'textarea':
                            $size = explode('#', $v['value']);
                            $size[0] = empty($size[0]) ? 5 : intval($size[0]);
                            $size[1] = empty($size[1]) ? 30 : intval($size[1]);

                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '
                                                    ' . $v['name'] . ': ' . $v['req'] . '<br/>
                                                    <textarea name="' . $k . '" rows="' . $size[0] . '" cols="' . $size[1] . '" ' . $cls . '>' . $k_value . '</textarea><br>
                                                    <br/>';
                        break;

                        /* Default text input */
                        default:
                            if (strlen($k_value) != 0) {
                                $v['value'] = $k_value;
                            }

                            $cls = in_array($k, $_SESSION['iserror']) ? ' class="isError" ' : '';
                            echo '
                                                    ' . $v['name'] . ': ' . $v['req'] . '<br>
                                                    <input type="text" name="' . $k . '" size="40" maxlength="' . $v['maxlen'] . '" value="' . $v['value'] . '" ' . $cls . ' /><br>
                                                    <br/>';
                    }
                }
            }
?>
            <!-- END CUSTOM AFTER -->

            <!-- attachments -->
<?php 
            if ($hesk_settings['attachments']['use']) { 
?>
                                                    <hr>
                                                    <?php echo _('Attachments'); ?>:<br>
<?php
                for ($i = 1; $i <= $hesk_settings['attachments']['max_number']; $i++) {
                    $cls = ($i == 1 && in_array('attachments', $_SESSION['iserror'])) ? ' class="isError" ' : '';
                    echo '<input type="file" name="attachment[' . $i . ']" size="50" ' . $cls . ' /><br><br>';
                }
?>
                                                    <a href="file_limits.php" target="_blank" onclick="Javascript:hb_window('file_limits.php', 250, 500);
                                                        return false;"><?php echo _('File upload limits'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
<?php 

            } 
?>
                                    </td>
                                </tr>
                            </table>
<?php 
            if ($hesk_settings['question_use'] || $hesk_settings['secimg_use']) { 
?>
                            <hr />

                            <!-- Security checks -->
                            <table border="0" width="100%">
<?php 
                if ($hesk_settings['question_use']) { 
?>
                                <tr>
                                    <td style="text-align:right;vertical-align:top" width="250"><?php echo _('SPAM Prevention:'); ?> <font class="important">*</font></td>
                                    <td width="70%">
<?php
                    $value = '';
                    if (isset($_SESSION['c_question'])) {
                        $value = stripslashes($this->helpbase->common->_input($_SESSION['c_question']));
                    }
                    $cls = in_array('question', $_SESSION['iserror']) ? ' class="isError" ' : '';
                    echo $hesk_settings['question_ask'] . '<br /><input type="text" name="question" size="20" value="' . $value . '" ' . $cls . '  />';
?>
                                        <br />&nbsp;
                                    </td>
                                </tr>
<?php
                }
                    
                if ($hesk_settings['secimg_use']) {
?>
                                <tr>
                                    <td style="text-align:right;vertical-align:top" width="250"><?php echo _('SPAM Prevention:'); ?> <font class="important">*</font><br><small><i>This little test is just to make sure you are a real person and not a spammer so that our oncall technician only receives mobile phone notifications for genuine service requests.</i></small></td>
                                    <td width="50%">
<?php
                        // SPAM prevention verified for this session
                    if (isset($_SESSION['img_verified'])) {
                        echo '<img src="' . $this->helpbase->img . 'success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> ' . _('Test passed');
                    } elseif ($hesk_settings['recaptcha_use']) {
                        // Not verified yet, should we use Recaptcha?
?>
                                        <script type="text/javascript">
                                            var RecaptchaOptions = {
                                                theme: '<?php echo ( isset($_SESSION['iserror']) && in_array('mysecnum', $_SESSION['iserror']) ) ? 'red' : 'white'; ?>',
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
                        require($this->helpbase->includes . 'recaptcha/recaptchalib.php');
                        echo recaptcha_get_html($hesk_settings['recaptcha_public_key'], null, $hesk_settings['recaptcha_ssl']);
                    } else {
                        $cls = in_array('mysecnum', $_SESSION['iserror']) ? ' class="isError" ' : '';
                        echo _('Type the number you see in the picture below.') . '<br />&nbsp;<br /><img src="print_sec_img.php?' . rand(10000, 99999) . '" width="250" height="40" alt="' . _('Security image') . '" title="' . _('Security image') . '" border="1" name="secimg" style="vertical-align:text-bottom" /> ' .
                        '<a href="javascript:void(0)" onclick="javascript:document.form1.secimg.src=\'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);"><img src="img/reload.png" height="24" width="24" alt="' . _('Reload image') . '" title="' . _('Reload image') . '" border="0" style="vertical-align:text-bottom" /></a>' .
                        '<br />&nbsp;<br /><input type="text" name="mysecnum" size="20" maxlength="5" ' . $cls . ' />';
                    }
?>
                                    </td>
                                </tr>
<?php 

                } 
?>
                            </table>
<?php 
            } 
?>
                            <!-- Submit -->
<?php 
            if ($hesk_settings['submit_notice']) { 
?>
                            <hr />
                            <div class="one_half">
                                <b><?php echo _('Before submitting please make sure of the following'); ?></b>
                                <ul>
                                    <li><?php echo _('All necessary information has been filled out'); ?>.</li>
                                    <li><?php echo _('All information is correct and error-free'); ?>.</li>
                                </ul>
                            </div>
                            <div class="one_half last">
                                <b><?php echo _('We have'); ?>:</b>
                                <ul>
                                    <li><?php echo $this->helpbase->common->htmlspecialchars($_SERVER['REMOTE_ADDR']) . ' ' . _('recorded as your IP Address'); ?></li>
                                    <li><?php echo _('recorded the time of your submission'); ?></li>
                                </ul>
                            </div>
                            <div class="clearboth"></div>
                            <p align="center"><input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                <input type="submit" value="<?php echo _('Submit ticket'); ?>" class="button blue small" />
                            </p>
<?php 
            } else { 
?>
                            &nbsp;<br />&nbsp;<br />
                            <table border="0" width="100%">
                                <tr>
                                    <td style="text-align:right" width="250">&nbsp;</td>
                                    <td width="70%"><input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                        <input type="submit" value="<?php echo _('Submit ticket'); ?>" class="button blue small" /><br />
                                        &nbsp;<br />&nbsp;
                                    </td>
                                </tr>
                            </table>
<?php 

            } 
?>
                        </form>
                        <!-- END FORM -->
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
            $this->helpbase->common->cleanSessionVars('iserror');
            $this->helpbase->common->cleanSessionVars('isnotice');
        }

        private function print_start() {
            global $hesk_settings;

            if ($hesk_settings['kb_enable']) {
                $this->helpbase->load_kb_functions();
            }

            /* Print header */
            $this->helpbase->header->render();
?>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                                <td class="headersm"><?php $this->helpbase->common->showTopBar($hesk_settings['hesk_title']); ?></td>
                                <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                            </tr>
                        </table>
                        <table width="100%" border="0" cellspacing="0" cellpadding="3">
                            <tr>
                                <td>
                                    <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
<?php
            echo $hesk_settings['hesk_title'];
?>
                                    </span>
                                </td>
<?php
            // Print small search box
            if ($hesk_settings['kb_enable']) {
                $this->helpbase->kb->searchSmall();
            }
?>

                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            // Print large search box
            if ($hesk_settings['kb_enable']) {
                $this->helpbase->kb->searchLarge();
            }

            // Knowledgebase disabled, print an empty line for formatting
            else {
                echo '&nbsp;';
            }
?>
                        <table border="0" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="50%">
                                    <!-- START SUBMIT -->
                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                            <td class="roundcornerstop"></td>
                                            <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                        </tr>
                                        <tr>
                                            <td class="roundcornersleft">&nbsp;</td>
                                            <td>
                                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td width="1"><img src="img/newticket.png" alt="" width="60" height="60" /></td>
                                                            <td>
                                                                <p>
                                                                    <b><a href="index.php?a=add"><?php echo _('Submit a ticket'); ?></a></b><br />
<?php
            echo _('Submit a new issue to a department');
?>
                                                                </p>
                                                            </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td class="roundcornersright">&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                            <td class="roundcornersbottom"></td>
                                            <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                        </tr>
                                    </table>
                                    <!-- END SUBMIT -->
                                </td>
                                <td width="1"><img src="img/blank.gif" width="5" height="1" alt="" /></td>
                                <td width="50%">
                                    <!-- START VIEW -->
                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                            <td class="roundcornerstop"></td>
                                            <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                        </tr>
                                        <tr>
                                            <td class="roundcornersleft">&nbsp;</td>
                                            <td>
                                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td width="1"><img src="img/existingticket.png" alt="" width="60" height="60" /></td>
                                                        <td>
                                                            <p>
                                                                <b><a href="ticket.php"><?php echo _('View existing ticket'); ?></a></b><br />
<?php
            echo _('View tickets you submitted in the past');
?>
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td class="roundcornersright">&nbsp;</td>
                                        </tr>
                                        <tr>
                                            <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                            <td class="roundcornersbottom"></td>
                                            <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                        </tr>
                                    </table>
                                    <!-- END VIEW -->
                                </td>
                            </tr>
                        </table>
<?php
            if ($hesk_settings['kb_enable']) {
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
                                    <p>
                                        <span class="homepageh3"><?php echo _('Knowledgebase'); ?></span>
                                    </p>
<?php
                /* Get list of top articles */
                $this->helpbase->kb->topArticles($hesk_settings['kb_index_popart']);

                /* Get list of latest articles */
                $this->helpbase->kb->latestArticles($hesk_settings['kb_index_latest']);
?>
                                    <p>&raquo; <b><a href="knowledgebase.php"><?php echo _('View entire Knowledgebase'); ?></a></b></p>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <br />
<?php
            } else {
                // Knowledgebase disabled, let's just print some blank lines so page looks better
?>
                        <p>&nbsp;</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;</p>
                        <p>&nbsp;</p>
<?php
            }

            // Show a link to admin panel?
            if ($hesk_settings['alink']) {
?>
                        <p style="text-align:center"><a href="<?php echo $hesk_settings['admin_dir']; ?>/" class="smaller"><?php echo _('Go to Administration Panel'); ?></a></p>
<?php
            }
        }

        private function forgot_tid() {
            global $hesk_settings;

            $this->helpbase->load_email_functions();

            $email = $this->helpbase->common->validateEmail($this->helpbase->common->_post('email'), 'ERR', 0) or $this->helpbase->common->process_messages(_('Please enter a valid email address'), 'ticket.php?remind=1');

            /* Prepare ticket statuses */
            $my_status = array(
                0   => _('New'),
                1   => _('Awaiting reply from staff'),
                2   => _('Awaiting reply from customer'),
                3   => _('Closed'),
                4   => _('On the bench'),
                5   => _('On hold'),
                6   => _('Waiting for payment'),
                7   => _('Waiting for bench'),
                8   => _('Service call'),
                9   => _('Remote support'),
                10  => _('Ready for pickup'),
            );

            // Get tickets from the database
            $res = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'tickets` FORCE KEY (`statuses`) WHERE ' . ($hesk_settings['open_only'] ? "`status` IN ('0','1','2','4','5') AND " : '') . ' ' . $this->helpbase->database->formatEmail($email) . ' ORDER BY `status` ASC, `lastchange` DESC ');

            $num = $this->helpbase->database->numRows($res);
            if ($num < 1) {
                if ($hesk_settings['open_only']) {
                    $this->helpbase->common->process_messages(_('No open tickets found for this email address.'), 'ticket.php?remind=1&e=' . $email);
                } else {
                    $this->helpbase->common->process_messages(_('No tickets with your email address were found'), 'ticket.php?remind=1&e=' . $email);
                }
            }

            $tid_list   = '';
            $name       = '';

            $email_param = $hesk_settings['email_view_ticket'] ? '&e=' . rawurlencode($email) : '';

            while ($my_ticket = $this->helpbase->database->fetchAssoc($res)) {
                $name = $name ? $name : $this->helpbase->common->msgToPlain($my_ticket['name'], 1, 0);
                $tid_list .= 
                    _('Tracking ID') . ': ' . $my_ticket['trackid'] . '<br>' . 
                    _('Subject') . ': ' . $this->helpbase->common->msgToPlain($my_ticket['subject'], 1, 0) . 
                    _('Status') . ': ' . $my_status[$my_ticket['status']] . 
                    $hesk_settings['hesk_url'] . '/ticket.php?track=' . $my_ticket['trackid'] . $email_param;
            }

            /* Get e-mail message for customer */
            $msg = $this->helpbase->email->getEmailMessage('forgot_ticket_id', '', 0, 0, 1);
            $msg = str_replace('%%NAME%%', $name, $msg);
            $msg = str_replace('%%NUM%%', $num, $msg);
            $msg = str_replace('%%LIST_TICKETS%%', $tid_list, $msg);
            $msg = str_replace('%%SITE_TITLE%%', $this->helpbase->common->msgToPlain($hesk_settings['site_title'], 1), $msg);
            $msg = str_replace('%%SITE_URL%%', $hesk_settings['site_url'], $msg);

            $subject = $this->helpbase->email->getEmailSubject('forgot_ticket_id');

            /* Send e-mail */
            $this->helpbase->email->mail($email, $subject, $msg);

            /* Show success message */
            $tmp = '<b>' . _('Tracking ID sent') . '!</b>';
            $tmp .= '<br />&nbsp;<br />' . _('An email with details about your tickets has been sent to your address') . '.';
            $tmp .= '<br />&nbsp;<br />' . _('Be sure to also check for the email inside your SPAM/Junk mailbox!');
            $this->helpbase->common->process_messages($tmp, 'ticket.php?e=' . $email, 'SUCCESS');
            exit();

            /* Print header */
            $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . _('Tracking ID sent');
            $this->helpbase->header->render();
?>
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
                    <td class="headersm"><?php $this->helpbase->common->showTopBar(_('Tracking ID sent')); ?></td>
                    <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
                </tr>
            </table>

            <table width="100%" border="0" cellspacing="0" cellpadding="3">
                <tr>
                    <td>
                        <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                            <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                            &gt; <?php echo _('Tracking ID sent'); ?>
                        </span>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
    <tr>
        <td>
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                    <td class="roundcornerstop"></td>
                    <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                </tr>
                <tr>
                    <td class="roundcornersleft">&nbsp;</td>
                    <td>
                        <p>&nbsp;</p>
                        <p align="center"><?php echo _('An email with details about your tickets has been sent to your address'); ?></p>
                        <p align="center"><b><?php echo _('Be sure to also check for the email inside your SPAM/Junk mailbox!'); ?></b></p>
                        <p>&nbsp;</p>
                        <p align="center"><a href="<?php echo $hesk_settings['hesk_url']; ?>"><?php echo $hesk_settings['hesk_title']; ?></a></p>
                        <p>&nbsp;</p>

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
        }
    }
}

new HelpbaseIndex;

?>

