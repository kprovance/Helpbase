<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Manage Categories
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseManageCategories')) {
    class HelpbaseManageCategories {
        private $helpbase   = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_man_cat');

            /* What should we do? */
            if ($action = $helpbase->common->_request('a')) {
                if ($action == 'linkcode') {
                    generate_link_code();
                } elseif (true == $helpbase->demo_mode) {
                    $helpbase->common->process_messages(_('Sorry, this function has been disabled in DEMO mode!'), 'manage_categories.php', 'NOTICE');
                } elseif ($action == 'new') {
                    new_cat();
                } elseif ($action == 'rename') {
                    rename_cat();
                } elseif ($action == 'remove') {
                    remove();
                } elseif ($action == 'order') {
                    order_cat();
                } elseif ($action == 'autoassign') {
                    toggle_autoassign();
                } elseif ($action == 'type') {
                    toggle_type();
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
                            <script language="Javascript" type="text/javascript">
                                <!--
                                function confirm_delete(){
                                        if (confirm('<?php echo addslashes(_('Are you sure you want to remove this category?')); ?>')) {
                                            return true;
                                        } else {
                                            return false;
                                        }
                                    }
                                //-->
                            </script>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
            $msg = _('Here you are able to manage categories. Categories are useful for categorizing tickets by relevance (for example &quot;Sales&quot;, &quot;Hardware problems&quot;, &quot;PHP/MySQL problems&quot; etc) and for assigning users to categories (for example that your sales person can only view tickets posted to &quot;Sales&quot; category)')
?>
                            <h3 style="padding-bottom:5px"><?php echo _('Manage categories'); ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo $this->helpbase->admin->makeJsString($msg); ?>')">?</a>]</h3>
                            &nbsp;
                            <br />
                            <div align="center">
                                <table border="0" cellspacing="1" cellpadding="3" class="white" width="100%">
                                    <tr>
                                        <th class="admin_white" style="white-space:nowrap;width:1px;"><b><i>&nbsp;<?php echo _('ID'); ?>&nbsp;</i></b></th>
                                        <th class="admin_white" style="text-align:left"><b><i>&nbsp;<?php echo _('Category name'); ?>&nbsp;</i></b></th>
                                        <th class="admin_white" style="white-space:nowrap;width:1px;"><b><i>&nbsp;<?php echo _('Tickets'); ?>&nbsp;</i></b></th>
                                        <th class="admin_white" style="text-align:left"><b><i>&nbsp;<?php echo _('Graph'); ?>&nbsp;</i></b></th>
                                        <th class="admin_white" style="width:100px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                                    </tr>
<?php
            /* Get number of tickets per category */
            $tickets_all = array();
            $tickets_total = 0;

            $res = $this->helpbase->database->query('SELECT COUNT(*) AS `cnt`, `category` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'tickets` GROUP BY `category`');
            while ($tmp = $this->helpbase->database->fetchAssoc($res)) {
                $tickets_all[$tmp['category']] = $tmp['cnt'];
                $tickets_total += $tmp['cnt'];
            }

            /* Get list of categories */
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` ORDER BY `cat_order` ASC");
            $options = '';

            $i = 1;
            $j = 0;
            $num = $this->helpbase->database->numRows($res);

            while ($mycat = $this->helpbase->database->fetchAssoc($res)) {
                $j++;

                if (isset($_SESSION['selcat2']) && $mycat['id'] == $_SESSION['selcat2']) {
                    $color = 'admin_green';
                    unset($_SESSION['selcat2']);
                } else {
                    $color = $i ? 'admin_white' : 'admin_gray';
                }

                $tmp = $i ? 'White' : 'Blue';
                $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                $i = $i ? 0 : 1;

                /* Number of tickets and graph width */
                $all = isset($tickets_all[$mycat['id']]) ? $tickets_all[$mycat['id']] : 0;
                $width_all = 0;
                if ($tickets_total && $all) {
                    $width_all = round(($all / $tickets_total) * 100);
                }

                /* Deleting category with ID 1 (default category) is not allowed */
                if ($mycat['id'] == 1) {
                    $remove_code = ' <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
                } else {
                    $remove_code = ' <a href="manage_categories.php?a=remove&amp;catid=' . $mycat['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="' . _('Remove') . '" title="' . _('Remove') . '" ' . $style . ' /></a>';
                }

                /* Is category private or public? */
                if ($mycat['type']) {
                    $msg = _('This category is PRIVATE (click to make public)');
                    $type_code = '<a href="manage_categories.php?a=type&amp;s=0&amp;catid=' . $mycat['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/private.png" width="16" height="16" alt="' . $msg . '" title="' . $msg . '" ' . $style . ' /></a>';
                } else {
                    $msg = _('This category is PUBLIC (click to make private)');
                    $type_code = '<a href="manage_categories.php?a=type&amp;s=1&amp;catid=' . $mycat['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/public.png" width="16" height="16" alt="' . $msg . '" title="' . $msg . '" ' . $style . ' /></a>';
                }

                /* Is auto assign enabled? */
                if ($hesk_settings['autoassign']) {
                    if ($mycat['autoassign']) {
                        $autoassign_code = '<a href="manage_categories.php?a=autoassign&amp;s=0&amp;catid=' . $mycat['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/autoassign_on.png" width="16" height="16" alt="' . _('Auto-assign of tickets enabled (click to disable)') . '" title="' . _('Auto-assign of tickets enabled (click to disable)') . '" ' . $style . ' /></a>';
                    } else {
                        $autoassign_code = '<a href="manage_categories.php?a=autoassign&amp;s=1&amp;catid=' . $mycat['id'] . '&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/autoassign_off.png" width="16" height="16" alt="' . _('Auto-assign of tickets disabled (click to enable)') . '" title="' . _('Auto-assign of tickets disabled (click to enable)') . '" ' . $style . ' /></a>';
                    }
                } else {
                    $autoassign_code = '';
                }

                $options .= '<option value="' . $mycat['id'] . '" ';
                $options .= (isset($_SESSION['selcat']) && $mycat['id'] == $_SESSION['selcat']) ? ' selected="selected" ' : '';
                $options .= '>' . $mycat['name'] . '</option>';

                echo '
                                    <tr>
                                        <td class="' . $color . '">' . $mycat['id'] . '</td>
                                        <td class="' . $color . '">' . $mycat['name'] . '</td>
                                        <td class="' . $color . '" style="text-align:center">' . $all . '</td>
                                        <td class="' . $color . '" width="1">
                                            <div class="progress-container" style="width: 160px" title="' . sprintf(_('%s of all tickets'), $width_all . '%') . '">
                                                <div style="width: ' . $width_all . '%;float:left;"></div>
                                            </div>
                                        </td>
                                        <td class="' . $color . '" style="text-align:center; white-space:nowrap;">
                                            <a href="Javascript:void(0)" onclick="Javascript:hb_window(\'manage_categories.php?a=linkcode&amp;catid=' . $mycat['id'] . '&amp;p=' . $mycat['type'] . '\',\'200\',\'500\')"><img src="../img/code' . ($mycat['type'] ? '_off' : '') . '.png" width="16" height="16" alt="' . _('Generate Direct Link') . '" title="' . _('Generate Direct Link') . '" ' . $style . ' /></a>
                                            ' . $autoassign_code . '
                                            ' . $type_code . ' ';

                $move_up = _('Move up');
                $move_down = _('Move down');
                if ($num > 1) {
                    if ($j == 1) {
                        echo'
                                            <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" /> <a href="manage_categories.php?a=order&amp;catid=' . $mycat['id'] . '&amp;move=15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_down.png" width="16" height="16" alt="' . $move_down . '" title="' . $move_down . '" ' . $style . ' /></a>';
                    } elseif ($j == $num) {
                        echo'
                                            <a href="manage_categories.php?a=order&amp;catid=' . $mycat['id'] . '&amp;move=-15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_up.png" width="16" height="16" alt="' . $move_up . '" title="' . $move_up . '" ' . $style . ' /></a> <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
                    } else {
                        echo'
                                            <a href="manage_categories.php?a=order&amp;catid=' . $mycat['id'] . '&amp;move=-15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_up.png" width="16" height="16" alt="' . $move_up . '" title="' . $move_up . '" ' . $style . ' /></a>
                                            <a href="manage_categories.php?a=order&amp;catid=' . $mycat['id'] . '&amp;move=15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_down.png" width="16" height="16" alt="' . $move_down . '" title="' . $move_down . '" ' . $style . ' /></a>';
                    }
                }
                                            echo $remove_code . '
                                        </td>
                                    </tr>';
            }
?>
                                </table>
                            </div>
                            <p>&nbsp;</p>
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
                                        
                                        <!-- CONTENT -->
                                        <form action="manage_categories.php" method="post">
                                            <h3>&raquo; <?php echo _('Add new category'); ?></h3>
                                            <p>
<?php
            $catname = '';
            if (isset($_SESSION['catname'])) {
                $catname = ' value="' . $this->helpbase->common->_input($_SESSION['catname']) . '" ';
            }
?>                        
                                                <b><?php echo _('Category name'); ?></b> (<?php echo _('max 40 chars'); ?>)<b>:</b><br /><input type="text" name="name" size="40" maxlength="40" <?php echo $catname; ?>/>
                                            </p>
                                            <p>
                                                <b><?php echo _('Options'); ?>:</b>
                                                <br />
<?php
            if ($hesk_settings['autoassign']) {
?>
                                                <label>
<?php
                $cat_ass = '';
                if (!isset($_SESSION['cat_autoassign']) || $_SESSION['cat_autoassign'] == 1) {
                    $cat_ass = 'checked="checked"';
                }
?>
                                                    <input type="checkbox" name="autoassign" value="Y" <?php echo $cat_ass; ?>/> <?php echo _('Auto-assign tickets in this category.'); ?>
                                                </label>
                                                <br />
<?php
            }
?>
                                                <label>
<?php
            $cat_type = '';
            if (isset($_SESSION['cat_type']) && $_SESSION['cat_type'] == 1) {
                $cat_type = 'checked="checked"';
            }
?>
                                                    <input type="checkbox" name="type" value="Y" <?php echo $cat_type; ?>/> <?php echo _('Make this category private (only staff can select it).'); ?>
                                                </label>
                                                <br />
                                            </p>
                                            <input type="hidden" name="a" value="new" />
                                            <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                            <input type="submit" value="<?php echo _('Create category'); ?>" class="button blue small" /></p>
                                        </form>
                                        <!-- END CONTENT -->
                                        
                                    </td>
                                    <td class="roundcornersright">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornersbottom"></td>
                                    <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                            </table>
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
                                        <!-- CONTENT -->
                                        <form action="manage_categories.php" method="post">
                                            <h3>&raquo; <?php echo _('Rename category'); ?></h3>
                                            <table border="0" style="margin-top:10px;">
                                                <tr>
                                                    <td><?php echo _('Old name:'); ?></td>
                                                    <td><select name="catid"><?php echo $options; ?></select></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo _('New name:'); ?></td>
                                                    <td>
<?php 
            $cat_name2 = '';
            if (isset($_SESSION['catname2'])) {
                $cat_name2 = ' value="' . $this->helpbase->common->_input($_SESSION['catname2']) . '" ';
            }
?>
                                                        <input type="text" name="name" size="40" maxlength="40" <?php echo $cat_name2; ?>/>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p>
                                                <input type="hidden" name="a" value="rename" />
                                                <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                <input type="submit" value="<?php echo _('Rename category'); ?>" class="button blue small" />
                                            </p>
                                        </form>
                                        <!-- END CONTENT -->
                                    </td>
                                    <td class="roundcornersright">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornersbottom"></td>
                                    <td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                            </table>
                            <!-- HR -->
                            <p>&nbsp;</p>
<?php
            $this->helpbase->footer->render();

            exit();
        }
        
        function generate_link_code() {
            global $hesk_settings;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
    <head>
        <title><?php echo _('Direct Category Link'); ?></title>
        <meta http-equiv="Content-Type" content="text/html;charset=<?php echo $this->helpbase->encoding; ?>" />
        <style type="text/css">
            body {
                margin:5px 5px;
                padding:0;
                background:#fff;
                color: black;
                font : 68.8%/1.5 Verdana, Geneva, Arial, Helvetica, sans-serif;
            }

            p {
                color : black;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-size: 1.0em;
            }
            
            h3 {
                color : #AF0000;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-weight: bold;
                font-size: 1.0em;
            }
        </style>
    </head>
    <body>
        <div style="text-align:center">
            <h3><?php echo _('Direct Category Link'); ?></h3>
<?php
            if (!empty($_GET['p'])) {
                echo '
            <p>&nbsp;
                <br />' . 
                _('Customers cannot select private categories, only staff can!') . '
                <br />&nbsp;
            </p>';
            } else {
?>
            <p><i><?php echo _('Use this link to preselect category in the &quot;Submit a ticket&quot; form.'); ?></i></p>
            <textarea rows="3" cols="50" onfocus="this.select()"><?php echo $hesk_settings['hesk_url'] . '/index.php?a=add&amp;catid=' . intval($this->helpbase->common->_get('catid')); ?></textarea>
<?php
            }
?>
            <p align="center"><a href="#" onclick="Javascript:window.close()"><?php echo _('Close window'); ?></a></p>
        </div>
    </body>
</html>
<?php
            unset($this->helpbase);

            exit();
        }

        function new_cat() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            /* Options */
            $_SESSION['cat_autoassign'] = $this->helpbase->common->_post('autoassign') == 'Y' ? 1 : 0;
            $_SESSION['cat_type']       = $this->helpbase->common->_post('type') == 'Y' ? 1 : 0;

            /* Category name */
            $catname = $this->helpbase->common->_input($this->helpbase->common->_post('name'), _('Please enter category name'), 'manage_categories.php');

            /* Do we already have a category with this name? */
            $res = $this->helpbase->database->query("SELECT `id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `name` LIKE '" . $this->helpbase->database->escape($this->helpbase->database->like($catname)) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($res) != 0) {
                $_SESSION['catname'] = $catname;
                $this->helpbase->common->process_messages(_('You already have a category with that name. Choose a unique name for each category.'), 'manage_categories.php');
            }

            /* Get the latest cat_order */
            $res = $this->helpbase->database->query("SELECT `cat_order` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` ORDER BY `cat_order` DESC LIMIT 1");
            $row = $this->helpbase->database->fetchRow($res);
            $my_order = $row[0] + 10;

            $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` (`name`,`cat_order`,`autoassign`,`type`) VALUES ('" . $this->helpbase->database->escape($catname) . "','" . intval($my_order) . "','" . intval($_SESSION['cat_autoassign']) . "','" . intval($_SESSION['cat_type']) . "')");

            $this->helpbase->common->cleanSessionVars('catname');
            $this->helpbase->common->cleanSessionVars('cat_autoassign');
            $this->helpbase->common->cleanSessionVars('cat_type');

            $_SESSION['selcat2'] = $this->helpbase->database->insertID();

            $this->helpbase->common->process_messages(sprintf(_('Category %s has been successfully added'), '<i>' . stripslashes($catname) . '</i>'), 'manage_categories.php', 'SUCCESS');
        }

        function rename_cat() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $_SERVER['PHP_SELF'] = 'manage_categories.php?catid=' . intval($this->helpbase->common->_post('catid'));

            $catid = $this->helpbase->common->isNumber($this->helpbase->common->_post('catid'), _('Please choose a category to be renamed'), $_SERVER['PHP_SELF']);
            $_SESSION['selcat'] = $catid;
            $_SESSION['selcat2'] = $catid;

            $catname = $this->helpbase->common->_input($this->helpbase->common->_post('name'), _('Please write new category name'), $_SERVER['PHP_SELF']);
            $_SESSION['catname2'] = $catname;

            $res = $this->helpbase->database->query("SELECT `id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `name` LIKE '" . $this->helpbase->database->escape($this->helpbase->database->like($catname)) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($res) != 0) {
                $old = $this->helpbase->database->fetchAssoc($res);
                if ($old['id'] == $catid) {
                    $this->helpbase->common->process_messages(_('No changes have been made'), $_SERVER['PHP_SELF'], 'NOTICE');
                } else {
                    $this->helpbase->common->process_messages(_('You already have a category with that name. Choose a unique name for each category.'), $_SERVER['PHP_SELF']);
                }
            }

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` SET `name`='" . $this->helpbase->database->escape($catname) . "' WHERE `id`='" . intval($catid) . "' LIMIT 1");

            unset($_SESSION['selcat']);
            unset($_SESSION['catname2']);

            $this->helpbase->common->process_messages(_('Selected category has been successfully renamed to') . ' <i>' . stripslashes($catname) . '</i>', $_SERVER['PHP_SELF'], 'SUCCESS');
        }

        function remove() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $_SERVER['PHP_SELF'] = 'manage_categories.php';

            $mycat = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error(_('No category ID'));
            if ($mycat == 1) {
                $this->helpbase->common->process_messages(_('You cannot delete the default category, you can only rename it'), $_SERVER['PHP_SELF']);
            }

            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='" . intval($mycat) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Internal script error: Category not found.'));
            }

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `category`=1 WHERE `category`='" . intval($mycat) . "'");

            $this->helpbase->common->process_messages(_('Selected category has been successfully removed from the database'), $_SERVER['PHP_SELF'], 'SUCCESS');
        }

        function order_cat() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $catid = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error(_('Missing category ID'));
            $_SESSION['selcat2'] = $catid;

            $cat_move = intval($this->helpbase->common->_get('move'));

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` SET `cat_order`=`cat_order`+" . intval($cat_move) . " WHERE `id`='" . intval($catid) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Internal script error: Category not found.'));
            }

            /* Update all category fields with new order */
            $res = $this->helpbase->database->query("SELECT `id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` ORDER BY `cat_order` ASC");

            $i = 10;
            while ($mycat = $this->helpbase->database->fetchAssoc($res)) {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` SET `cat_order`=" . intval($i) . " WHERE `id`='" . intval($mycat['id']) . "' LIMIT 1");
                $i += 10;
            }

            header('Location: manage_categories.php');
            exit();
        }

        function toggle_autoassign() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $catid = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error(_('Missing category ID'));
            $_SESSION['selcat2'] = $catid;

            if (intval($this->helpbase->common->_get('s'))) {
                $autoassign = 1;
                $tmp = _('Auto-assign has been enabled for selected category');
            } else {
                $autoassign = 0;
                $tmp = _('Auto-assign has been disabled for selected category');
            }

            /* Update auto-assign settings */
            $res = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` SET `autoassign`='" . intval($autoassign) . "' WHERE `id`='" . intval($catid) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->process_messages(_('Internal script error') . ': ' . _('Category not found'), './manage_categories.php');
            }

            $this->helpbase->common->process_messages($tmp, './manage_categories.php', 'SUCCESS');
        }

        function toggle_type() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $catid = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error(_('Missing category ID'));
            $_SESSION['selcat2'] = $catid;

            if (intval($this->helpbase->common->_get('s'))) {
                $type = 1;
                $tmp = _('Category type changed to PRIVATE');
            } else {
                $type = 0;
                $tmp = _('Category type changed to PUBLIC');
            }

            /* Update auto-assign settings */
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` SET `type`='{$type}' WHERE `id`='" . intval($catid) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->process_messages(_('Internal script error') . ': ' . _('Category not found'), './manage_categories.php');
            }

            $this->helpbase->common->process_messages($tmp, './manage_categories.php', 'SUCCESS');
        }
    }
    
    new HelpbaseManageCategories;
}

?>
