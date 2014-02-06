<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Manage Users
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseManageUsers')) {
    class HelpbaseManageUsers {
        private $helpbase = null;
        
        public function __construct() {
            
        }
    }
}

include_once('../helpbase.class.php');
$helpbase = new HelpbaseCore(true);

$helpbase->admin->isLoggedIn();

/* Check permissions for this feature */
$helpbase->admin->checkPermission('can_man_users');

/* Possible user features */
$hesk_settings['features'] = array(
    'can_view_tickets', /* User can read tickets */
    'can_reply_tickets', /* User can reply to tickets */
    'can_del_tickets', /* User can delete tickets */
    'can_edit_tickets', /* User can edit tickets */
    'can_merge_tickets', /* User can merge tickets */
    'can_del_notes', /* User can delete ticket notes posted by other staff members */
    'can_change_cat', /* User can move ticke to a new category/department */
    'can_man_kb', /* User can manage knowledgebase articles and categories */
    'can_man_users', /* User can create and edit staff accounts */
    'can_man_cat', /* User can manage categories/departments */
    'can_man_canned', /* User can manage canned responses */
    'can_man_settings', /* User can manage help desk settings */
    'can_add_archive', /* User can mark tickets as "Tagged" */
    'can_assign_self', /* User can assign tickets to himself/herself */
    'can_assign_others', /* User can assign tickets to other staff members */
    'can_view_unassigned', /* User can view unassigned tickets */
    'can_view_ass_others', /* User can view tickets that are assigned to other staff */
    'can_run_reports', /* User can run reports and see statistics (only allowed categories and self) */
    'can_run_reports_full', /* User can run reports and see statistics (unrestricted) */
    'can_export', /* User can export own tickets to Excel */
    'can_view_online', /* User can view what staff members are currently online */
);

/* Set default values */
$default_userdata = array(
    'name'          => '',
    'email'         => '',
    'user'          => '',
    'signature'     => '',
    'isadmin'       => 1,
    'categories'    => array('1'),
    'features'      => array(
        'can_view_tickets', 
        'can_reply_tickets', 
        'can_change_cat', 
        'can_assign_self', 
        'can_view_unassigned', 
        'can_view_online'
    ),
    'signature'     => '',
    'cleanpass'     => '',
);

/* A list of all categories */
$hesk_settings['categories'] = array();
$res = $helpbase->database->query('SELECT `id`,`name` FROM `' . $helpbase->database->escape($hesk_settings['db_pfix']) . 'categories` ORDER BY `cat_order` ASC');
while ($row = $helpbase->database->fetchAssoc($res)) {
    if ($helpbase->admin->okCategory($row['id'], 0)) {
        $hesk_settings['categories'][$row['id']] = $row['name'];
    }
}

/* Non-admin users may not create users with more permissions than they have */
if (!$_SESSION['isadmin']) {
    /* Can't create admin users */
    $_POST['isadmin'] = 0;

    /* Can only add features he/she has access to */
    $hesk_settings['features'] = array_intersect(explode(',', $_SESSION['heskprivileges']), $hesk_settings['features']);

    /* Can user modify auto-assign setting? */
    if ($hesk_settings['autoassign'] && (!$helpbase->admin->checkPermission('can_assign_self', 0) || !$helpbase->admin->checkPermission('can_assign_others', 0) )) {
        $hesk_settings['autoassign'] = 0;
    }
}

/* Use any set values, default otherwise */
foreach ($default_userdata as $k => $v) {
    if (!isset($_SESSION['userdata'][$k])) {
        $_SESSION['userdata'][$k] = $v;
    }
}

$_SESSION['userdata'] = $helpbase->common->stripArray($_SESSION['userdata']);

/* What should we do? */
if ($action = $helpbase->common->_request('a')) {
    if ($action == 'reset_form') {
        $_SESSION['edit_userdata'] = TRUE;
        header('Location: ./manage_users.php');
    } elseif ($action == 'edit') {
        edit_user();
    } elseif (true == $helpbase->demo_mode) {
        $helpbase->common->process_messages(_('Sorry, this function has been disabled in DEMO mode!'), 'manage_users.php', 'NOTICE');
    } elseif ($action == 'new') {
        new_user();
    } elseif ($action == 'save') {
        update_user();
    } elseif ($action == 'remove') {
        remove();
    } elseif ($action == 'autoassign') {
        toggle_autoassign();
    } else {
        $helpbase->common->_error(_('Invalid action'));
    }
} else {

    /* If one came from the Edit page make sure we reset user values */

    if (isset($_SESSION['save_userdata'])) {
        $_SESSION['userdata'] = $default_userdata;
        unset($_SESSION['save_userdata']);
    }
    if (isset($_SESSION['edit_userdata'])) {
        $_SESSION['userdata'] = $default_userdata;
        unset($_SESSION['edit_userdata']);
    }

    /* Print header */
    $helpbase->header->render();

    /* Print main manage users page */
    $helpbase->admin_nav->render();
    ?>

    </td>
    </tr>
    <tr>
        <td>

            <script language="Javascript" type="text/javascript"><!--
            function confirm_delete()
                {
                    if (confirm('<?php echo addslashes(_('Are you sure you want to remove this user?')); ?>')) {
                        return true;
                    }
                    else {
                        return false;
                    }
                }
    //-->
            </script>

            <?php
            /* This will handle error, success and notice messages */
            $helpbase->common->handle_messages();
            $msg = _('Here you are able to manage users who can login to the admin panel and answer tickets. Administrators can view/edit tickets in any category and have access to all functions of the admin panel (manage users, manage categories, ...) while other users may only view and reply to tickets within their categories.')
            ?>

            <h3 style="padding-bottom:5px"><?php echo _('Manage users'); ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo $helpbase->admin->makeJsString($msg); ?>')">?</a>]</h3>

            &nbsp;<br />

            <div align="center">
                <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                    <tr>
                        <th class="admin_white" style="text-align:left"><b><i><?php echo _('Name'); ?></i></b></th>
                        <th class="admin_white" style="text-align:left"><b><i><?php echo _('Email'); ?></i></b></th>
                        <th class="admin_white" style="text-align:left"><b><i><?php echo _('Username'); ?></i></b></th>
                        <th class="admin_white" style="text-align:center;white-space:nowrap;width:1px;"><b><i><?php echo _('Administrator'); ?></i></b></th>
                        <?php
                        /* Is user rating enabled? */
                        if ($hesk_settings['rating']) {
                            ?>
                            <th class="admin_white" style="text-align:center;white-space:nowrap;width:1px;"><b><i><?php echo _('Rating'); ?></i></b></th>
                            <?php
                        }
                        ?>
                        <th class="admin_white" style="width:100px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                    </tr>

                    <?php
                    $res = $helpbase->database->query('SELECT * FROM `' . $helpbase->database->escape($hesk_settings['db_pfix']) . 'users` ORDER BY `id` ASC');

                    $i = 1;
                    $cannot_manage = array();

                    while ($myuser = $helpbase->database->fetchAssoc($res)) {

                        if (!compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']), explode(',', $myuser['heskprivileges']))) {
                            $cannot_manage[$myuser['id']] = array('name' => $myuser['name'], 'user' => $myuser['user'], 'email' => $myuser['email']);
                            continue;
                        }

                        if (isset($_SESSION['seluser']) && $myuser['id'] == $_SESSION['seluser']) {
                            $color = 'admin_green';
                            unset($_SESSION['seluser']);
                        } else {
                            $color = $i ? 'admin_white' : 'admin_gray';
                        }

                        $tmp = $i ? 'White' : 'Blue';
                        $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                        $i = $i ? 0 : 1;

                        /* User online? */
                        if ($hesk_settings['online']) {
                            if (isset($hesk_settings['users_online'][$myuser['id']])) {
                                $myuser['name'] = '<img src="../img/online_on.png" width="16" height="16" alt="' . _('Online') . '" title="' . _('Online') . '" style="vertical-align:text-bottom" /> ' . $myuser['name'];
                            } else {
                                $myuser['name'] = '<img src="../img/online_off.png" width="16" height="16" alt="' . _('Offline') . '" title="' . _('Offline') . '" style="vertical-align:text-bottom" /> ' . $myuser['name'];
                            }
                        }

                        /* To edit yourself go to "Profile" page, not here. */
                        if ($myuser['id'] == $_SESSION['id']) {
                            $edit_code = '<a href="profile.php"><img src="../img/edit.png" width="16" height="16" alt="' . _('Edit') . '" title="' . _('Edit') . '" ' . $style . ' /></a>';
                        } else {
                            $edit_code = '<a href="manage_users.php?a=edit&amp;id=' . $myuser['id'] . '"><img src="../img/edit.png" width="16" height="16" alt="' . _('Edit') . '" title="' . _('Edit') . '" ' . $style . ' /></a>';
                        }

                        if ($myuser['isadmin']) {
                            $myuser['isadmin'] = '<font class="open">' . _('YES') . '</font>';
                        } else {
                            $myuser['isadmin'] = '<font class="resolved">' . _('NO') . '</font>';
                        }

                        /* Deleting user with ID 1 (default administrator) is not allowed */
                        if ($myuser['id'] == 1) {
                            $remove_code = ' <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />';
                        } else {
                            $remove_code = ' <a href="manage_users.php?a=remove&amp;id=' . $myuser['id'] . '&amp;token=' . $helpbase->common->token_echo(0) . '" onclick="return confirm_delete();"><img src="../img/delete.png" width="16" height="16" alt="' . _('Remove') . '" title="' . _('Remove') . '" ' . $style . ' /></a>';
                        }

                        /* Is auto assign enabled? */
                        if ($hesk_settings['autoassign']) {
                            if ($myuser['autoassign']) {
                                $autoassign_code = '<a href="manage_users.php?a=autoassign&amp;s=0&amp;id=' . $myuser['id'] . '&amp;token=' . $helpbase->common->token_echo(0) . '"><img src="../img/autoassign_on.png" width="16" height="16" alt="' . _('Auto-assign of tickets enabled (click to disable)') . '" title="' . _('Auto-assign of tickets enabled (click to disable)') . '" ' . $style . ' /></a>';
                            } else {
                                $autoassign_code = '<a href="manage_users.php?a=autoassign&amp;s=1&amp;id=' . $myuser['id'] . '&amp;token=' . $helpbase->common->token_echo(0) . '"><img src="../img/autoassign_off.png" width="16" height="16" alt="' . _('Auto-assign of tickets disabled (click to enable)') . '" title="' . _('Auto-assign of tickets disabled (click to enable)') . '" ' . $style . ' /></a>';
                            }
                        } else {
                            $autoassign_code = '';
                        }

                        echo <<<EOC
<tr>
<td class="$color">$myuser[name]</td>
<td class="$color"><a href="mailto:$myuser[email]">$myuser[email]</a></td>
<td class="$color">$myuser[user]</td>
<td class="$color">$myuser[isadmin]</td>

EOC;

                        if ($hesk_settings['rating']) {
                            $alt = $myuser['rating'] ? sprintf(_('User rated %s/5.0 (%s votes)'), sprintf("%01.1f", $myuser['rating']), ($myuser['ratingneg'] + $myuser['ratingpos'])) : _('User not rated yet');
                            echo '<td class="' . $color . '" style="text-align:center; white-space:nowrap;"><img src="../img/star_' . ($helpbase->common->round_to_half($myuser['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" title="' . $alt . '" border="0" style="vertical-align:text-bottom" />&nbsp;</td>';
                        }

                        echo <<<EOC
<td class="$color" style="text-align:center">$autoassign_code $edit_code $remove_code</td>
</tr>

EOC;
                    } // End while
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

                        <h3 align="center"><?php echo _('Add new user'); ?></h3>

                        <p align="center"><?php echo _('Required fields are marked with'); ?> <font class="important">*</font></p>

                        <form name="form1" action="manage_users.php" method="post">

                            <!-- Contact info -->
                            <table border="0" width="100%">
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Real name'); ?>: <font class="important">*</font></td>
                                    <td align="left"><input type="text" name="name" size="40" maxlength="50" value="<?php echo $_SESSION['userdata']['name']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Email'); ?>: <font class="important">*</font></td>
                                    <td align="left"><input type="text" name="email" size="40" maxlength="255" value="<?php echo $_SESSION['userdata']['email']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Username'); ?>: <font class="important">*</font></td>
                                    <td><input type="text" name="user" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['user']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Password'); ?>: <font class="important">*</font></td>
                                    <td><input type="password" name="newpass" autocomplete="off" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['cleanpass']; ?>" onkeyup="javascript:hb_checkPassword(this.value)" />
                                    </td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Confirm password'); ?>: <font class="important">*</font></td>
                                    <td><input type="password" name="newpass2" autocomplete="off" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['cleanpass']; ?>" />
                                    </td>
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
                                <tr>
                                    <td valign="top" width="200" style="text-align:right"><?php echo _('Administrator'); ?>: <font class="important">*</font></td>
                                    <td valign="top">

                                        <?php
                                        /* Only administrators can create new administrator accounts */
                                        if ($_SESSION['isadmin']) {
                                            ?>
                                            <label><input type="radio" name="isadmin" value="1" onchange="Javascript:hb_toggleLayerDisplay('options')" <?php if ($_SESSION['userdata']['isadmin']) echo 'checked="checked"'; ?> /> <?php echo _('YES') . ' ' . _('(access to all features and categories)'); ?></label><br />
                                            <label><input type="radio" name="isadmin" value="0" onchange="Javascript:hb_toggleLayerDisplay('options')" <?php if (!$_SESSION['userdata']['isadmin']) echo 'checked="checked"'; ?> /> <?php echo _('NO') . ' ' . _('(you can limit features and categories)'); ?></label>

                                            <?php
                                        } else {
                                            echo _('NO') . ' ' . _('(you can limit features and categories)');
                                        }
                                        ?>
                                        <div id="options" style="display: <?php echo ($_SESSION['isadmin'] && $_SESSION['userdata']['isadmin']) ? 'none' : 'block'; ?>;">
                                            <table width="100%" border="0">
                                                <tr>
                                                    <td valign="top" width="100" style="text-align:right;white-space:nowrap;"><?php echo _('Categories'); ?>: <font class="important">*</font></td>
                                                    <td valign="top">
                                                        <?php
                                                        foreach ($hesk_settings['categories'] as $catid => $catname) {
                                                            echo '<label><input type="checkbox" name="categories[]" value="' . $catid . '" ';
                                                            if (in_array($catid, $_SESSION['userdata']['categories'])) {
                                                                echo ' checked="checked" ';
                                                            }
                                                            echo ' />' . $catname . '</label><br /> ';
                                                        }
                                                        ?>
                                                        &nbsp;
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td valign="top" width="100" style="text-align:right;white-space:nowrap;"><?php echo _('Features'); ?>: <font class="important">*</font></td>
                                                    <td valign="top">
                                                        <?php
                                                        foreach ($hesk_settings['features'] as $k) {
                                                            echo '<label><input type="checkbox" name="features[]" value="' . $k . '" ';
                                                            if (in_array($k, $_SESSION['userdata']['features'])) {
                                                                echo ' checked="checked" ';
                                                            }
                                                            echo ' />' . getUserRole($k) . '</label><br /> ';
                                                        }
                                                        ?>
                                                        &nbsp;
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                    </td>
                                </tr>
                                <?php
                                if ($hesk_settings['autoassign']) {
                                    ?>
                                    <tr>
                                        <td width="200" style="text-align:right"><?php echo _('Options'); ?>:</td>
                                        <td><label><input type="checkbox" name="autoassign" value="Y" <?php
                                                if (!isset($_SESSION['userdata']['autoassign']) || $_SESSION['userdata']['autoassign'] == 1) {
                                                    echo 'checked="checked"';
                                                }
                                                ?> /> <?php echo _('Auto-assign tickets to this user.'); ?></label></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <tr>
                                    <td valign="top" width="200" style="text-align:right"><?php echo _('Signature (max 255 chars)'); ?>:</td>
                                    <td><textarea name="signature" rows="6" cols="40"><?php echo $_SESSION['userdata']['signature']; ?></textarea><br />
                                        <?php echo _('HTML code is not allowed. Links will be clickable.'); ?></td>
                                </tr>
                            </table>

                            <!-- Submit -->
                            <p align="center"><input type="hidden" name="a" value="new" />
                                <input type="hidden" name="token" value="<?php $helpbase->common->token_echo(); ?>" />
                                <input type="submit" value="<?php echo _('Create user'); ?>" class="button blue small" />
                                |
                                <a href="manage_users.php?a=reset_form"><?php echo _('Reset form data'); ?></a></p>

                        </form>

                        <script language="Javascript" type="text/javascript"><!--
                        hb_checkPassword(document.form1.newpass.value);
    //-->
                        </script>

                        <p>&nbsp;</p>

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

            <?php
            
            $helpbase->footer->render();
            
            unset($helpbase);
            
            exit();
        } // End else


        /*         * * START FUNCTIONS ** */

        function getUserRole($role) {
            switch ($role) {
                case 'can_view_tickets':        return 'View tickets'; break;
                case 'can_reply_tickets':       return 'Reply to tickets'; break;
                case 'can_del_tickets':         return 'Delete tickets'; break;
                case 'can_edit_tickets':        return 'Edit ticket replies'; break;
                case 'can_merge_tickets':       return 'Merge tickets'; break;
                case 'can_del_notes':           return 'Delete any ticket notes'; break;
                case 'can_change_cat':          return 'Change ticket category'; break;
                case 'can_man_kb':              return 'Manage knowledgebase'; break;
                case 'can_man_users':           return 'Manage users'; break;
                case 'can_man_cat':             return 'Manage categories'; break;
                case 'can_man_canned':          return 'Manage canned responses'; break;
                case 'can_man_settings':        return 'Manage help desk settings'; break;
                case 'can_add_archive':         return 'Can tag tickets'; break;
                case 'can_assign_self':         return 'Can assign tickets to self'; break;
                case 'can_assign_others':       return 'Can assign tickets to others'; break;
                case 'can_view_unassigned':     return 'Can view unassigned tickets'; break;
                case 'can_view_ass_others':     return 'Can view tickets assigned to others'; break;
                case 'can_run_reports':         return 'Can run reports (own)'; break;
                case 'can_run_reports_full':    return 'Can run reports (all)'; break;
                case 'can_export':              return 'Can export tickets'; break;
                case 'can_view_online':         return 'Can view online staff members'; break;
            }
        }
        
        function compare_user_permissions($compare_id, $compare_isadmin, $compare_categories, $compare_features) {
            global $hesk_settings;

            /* Comparing myself? */
            if ($compare_id == $_SESSION['id']) {
                return true;
            }

            /* Admins have full access, no need to compare */
            if ($_SESSION['isadmin']) {
                return true;
            } elseif ($compare_isadmin) {
                return false;
            }

            /* Compare categories */
            foreach ($compare_categories as $catid) {
                if (!array_key_exists($catid, $hesk_settings['categories'])) {
                    return false;
                }
            }

            /* Compare features */
            foreach ($compare_features as $feature) {
                if (!in_array($feature, $hesk_settings['features'])) {
                    return false;
                }
            }

            return true;
        }

// END compare_user_permissions()

        function edit_user() {
            global $hesk_settings, $default_userdata, $helpbase;

            $id = intval($helpbase->common->_get('id')) or $helpbase->common->_error(_('Internal script error') . ': ' . _('No valid user ID'));

            /* To edit self fore using "Profile" page */
            if ($id == $_SESSION['id']) {
                $helpbase->common->process_messages(_('Use Profile page to edit your settings.'), 'profile.php', 'NOTICE');
            }

            $_SESSION['edit_userdata'] = TRUE;

            if (!isset($_SESSION['save_userdata'])) {
                $res = $helpbase->database->query("SELECT `user`,`pass`,`isadmin`,`name`,`email`,`signature`,`categories`,`autoassign`,`heskprivileges` AS `features` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . intval($id) . "' LIMIT 1");
                $_SESSION['userdata'] = $helpbase->database->fetchAssoc($res);

                /* Store original username for display until changes are saved successfully */
                $_SESSION['original_user'] = $_SESSION['userdata']['user'];

                /* A few variables need special attention... */
                if ($_SESSION['userdata']['isadmin']) {
                    $_SESSION['userdata']['features'] = $default_userdata['features'];
                    $_SESSION['userdata']['categories'] = $default_userdata['categories'];
                } else {
                    $_SESSION['userdata']['features'] = explode(',', $_SESSION['userdata']['features']);
                    $_SESSION['userdata']['categories'] = explode(',', $_SESSION['userdata']['categories']);
                }
                $_SESSION['userdata']['cleanpass'] = '';
            }

            /* Make sure we have permission to edit this user */
            if (!compare_user_permissions($id, $_SESSION['userdata']['isadmin'], $_SESSION['userdata']['categories'], $_SESSION['userdata']['features'])) {
                $helpbase->common->process_messages(_('You don\'t have permission to edit this user.'), 'manage_users.php');
            }

            /* Print header */
            $helpbase->header->render();

            /* Print main manage users page */
            $helpbase->admin_nav->render();
            ?>

        </td>
    </tr>
    <tr>
        <td>

            <?php
            /* This will handle error, success and notice messages */
            $helpbase->common->handle_messages();
            ?>

            <p class="smaller">&nbsp;<a href="manage_users.php" class="smaller"><?php echo _('Manage users'); ?></a> &gt; <?php echo _('Editing user') . ' ' . $_SESSION['original_user']; ?></p>

            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                    <td class="roundcornerstop"></td>
                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                </tr>
                <tr>
                    <td class="roundcornersleft">&nbsp;</td>
                    <td>

                        <h3 align="center"><?php echo _('Editing user') . ' ' . $_SESSION['original_user']; ?></h3>

                        <p align="center"><?php echo _('Required fields are marked with'); ?> <font class="important">*</font></p>

                        <form name="form1" method="post" action="manage_users.php">

                            <!-- Contact info -->
                            <table border="0" width="100%">
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Real name'); ?>: <font class="important">*</font></td>
                                    <td align="left"><input type="text" name="name" size="40" maxlength="50" value="<?php echo $_SESSION['userdata']['name']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Email'); ?>: <font class="important">*</font></td>
                                    <td align="left"><input type="text" name="email" size="40" maxlength="255" value="<?php echo $_SESSION['userdata']['email']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Username'); ?>: <font class="important">*</font></td>
                                    <td><input type="text" name="user" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['user']; ?>" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Password'); ?>:</td>
                                    <td><input type="password" name="newpass" autocomplete="off" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['cleanpass']; ?>" onkeyup="javascript:hb_checkPassword(this.value)" /></td>
                                </tr>
                                <tr>
                                    <td width="200" style="text-align:right"><?php echo _('Confirm password'); ?>:</td>
                                    <td><input type="password" name="newpass2" autocomplete="off" size="40" maxlength="20" value="<?php echo $_SESSION['userdata']['cleanpass']; ?>" /></td>
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
                                <tr>
                                    <td valign="top" width="200" style="text-align:right"><?php echo _('Administrator'); ?>: <font class="important">*</font></td>
                                    <td valign="top">

                                        <?php
                                        /* Only administrators can create new administrator accounts */
                                        if ($_SESSION['isadmin']) {
                                            ?>
                                            <label><input type="radio" name="isadmin" value="1" onchange="Javascript:hb_toggleLayerDisplay('options')" <?php if ($_SESSION['userdata']['isadmin']) echo 'checked="checked"'; ?> /> <?php echo _('YES') . ' ' . _('(access to all features and categories)'); ?></label><br />
                                            <label><input type="radio" name="isadmin" value="0" onchange="Javascript:hb_toggleLayerDisplay('options')" <?php if (!$_SESSION['userdata']['isadmin']) echo 'checked="checked"'; ?> /> <?php echo _('NO') . ' ' . _('(you can limit features and categories)'); ?></label>
                                            <?php
                                        }
                                        else {
                                            echo _('NO') . ' ' . _('(you can limit features and categories)');
                                        }
                                        ?>
                                        <div id="options" style="display: <?php echo ($_SESSION['isadmin'] && $_SESSION['userdata']['isadmin']) ? 'none' : 'block'; ?>;">
                                            <table width="100%" border="0">
                                                <tr>
                                                    <td valign="top" width="100" style="text-align:right;white-space:nowrap;"><?php echo _('Categories'); ?>: <font class="important">*</font></td>
                                                    <td valign="top">
                                                        <?php
                                                        foreach ($hesk_settings['categories'] as $catid => $catname) {
                                                            echo '<label><input type="checkbox" name="categories[]" value="' . $catid . '" ';
                                                            if (in_array($catid, $_SESSION['userdata']['categories'])) {
                                                                echo ' checked="checked" ';
                                                            }
                                                            echo ' />' . $catname . '</label><br /> ';
                                                        }
                                                        ?>
                                                        &nbsp;
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td valign="top" width="100" style="text-align:right;white-space:nowrap;"><?php echo _('Features'); ?>: <font class="important">*</font></td>
                                                    <td valign="top">
                                                        <?php
                                                        foreach ($hesk_settings['features'] as $k) {
                                                            echo '<label><input type="checkbox" name="features[]" value="' . $k . '" ';
                                                            if (in_array($k, $_SESSION['userdata']['features'])) {
                                                                echo ' checked="checked" ';
                                                            }
                                                            echo ' />' . getUserRole($k) . '</label><br /> ';
                                                        }
                                                        ?>
                                                        &nbsp;
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                    </td>
                                </tr>
                                <?php
                                if ($hesk_settings['autoassign']) {
                                    ?>
                                    <tr>
                                        <td width="200" style="text-align:right"><?php echo _('Options'); ?>:</td>
                                        <td><label><input type="checkbox" name="autoassign" value="Y" <?php
                                                if (isset($_SESSION['userdata']['autoassign']) && $_SESSION['userdata']['autoassign'] == 1) {
                                                    echo 'checked="checked"';
                                                }
                                                ?> /> <?php echo _('Auto-assign tickets to this user.'); ?></label></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <tr>
                                    <td valign="top" width="200" style="text-align:right"><?php echo _('Signature (max 255 chars)'); ?>:</td>
                                    <td><textarea name="signature" rows="6" cols="40"><?php echo $_SESSION['userdata']['signature']; ?></textarea><br />
                                        <?php echo _('HTML code is not allowed. Links will be clickable.'); ?></td>
                                </tr>
                            </table>

                            <!-- Submit -->
                            <p align="center"><input type="hidden" name="a" value="save" />
                                <input type="hidden" name="userid" value="<?php echo $id; ?>" />
                                <input type="hidden" name="token" value="<?php $helpbase->common->token_echo(); ?>" />
                                <input type="submit" value="<?php echo _('Save changes'); ?>" class="button blue small" />
                                |
                                <a href="manage_users.php"><?php echo _('Discard Changes'); ?></a></p>

                        </form>

                        <script language="Javascript" type="text/javascript"><!--
                        hb_checkPassword(document.form1.newpass.value);
    //-->
                        </script>

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
            
            $helpbase->footer->render();
            
            unset($helpbase);
            
            exit();
        }

// End edit_user()

        function new_user() {
            global $helpbase, $hesk_settings;

            /* A security check */
            $helpbase->common->token_check('POST');

            $myuser = hesk_validateUserInfo();

            /* Can view unassigned tickets? */
            if (in_array('can_view_unassigned', $myuser['features'])) {
                $sql_where = '';
                $sql_what = '';
            } else {
                $sql_where = ' , `notify_new_unassigned`, `notify_reply_unassigned` ';
                $sql_what = " , '0', '0' ";
            }

            /* Categories and Features will be stored as a string */
            $myuser['categories'] = implode(',', $myuser['categories']);
            $myuser['features'] = implode(',', $myuser['features']);

            /* Check for duplicate usernames */
            $result = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `user` = '" . $helpbase->database->escape($myuser['user']) . "' LIMIT 1");
            if ($helpbase->database->numRows($result) != 0) {
                $helpbase->common->process_messages(_('User with this username already exists, choose a different username.'), 'manage_users.php');
            }

            /* Admins will have access to all features and categories */
            if ($myuser['isadmin']) {
                $myuser['categories'] = '';
                $myuser['features'] = '';
            }

            $helpbase->database->query("INSERT INTO `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` (`user`,`pass`,`isadmin`,`name`,`email`,`signature`,`categories`,`autoassign`,`heskprivileges` $sql_where) VALUES (
	'" . $helpbase->database->escape($myuser['user']) . "',
	'" . $helpbase->database->escape($myuser['pass']) . "',
	'" . intval($myuser['isadmin']) . "',
	'" . $helpbase->database->escape($myuser['name']) . "',
	'" . $helpbase->database->escape($myuser['email']) . "',
	'" . $helpbase->database->escape($myuser['signature']) . "',
	'" . $helpbase->database->escape($myuser['categories']) . "',
	'" . intval($myuser['autoassign']) . "',
	'" . $helpbase->database->escape($myuser['features']) . "'
	$sql_what )");

            $_SESSION['seluser'] = $helpbase->database->insertID();

            unset($_SESSION['userdata']);

            $helpbase->common->process_messages(sprintf(_('New user %s with password %s has been successfully added'), $myuser['user'], $myuser['cleanpass']), './manage_users.php', 'SUCCESS');
        }

// End new_user()

        function update_user() {
            global $hesk_settings, $helpbase;

            /* A security check */
            $helpbase->common->token_check('POST');

            $_SESSION['save_userdata'] = TRUE;

            $tmp = intval($helpbase->common->_post('userid')) or $helpbase->common->_error(_('Internal script error') . ': ' . _('No valid user ID'));

            /* To edit self fore using "Profile" page */
            if ($tmp == $_SESSION['id']) {
                $helpbase->common->process_messages(_('Use Profile page to edit your settings.'), 'profile.php', 'NOTICE');
            }

            $_SERVER['PHP_SELF'] = './manage_users.php?a=edit&id=' . $tmp;
            $myuser = hesk_validateUserInfo(0, $_SERVER['PHP_SELF']);
            $myuser['id'] = $tmp;

            /* If can't view assigned changes this */
            if (in_array('can_view_unassigned', $myuser['features'])) {
                $sql_where = "";
            } else {
                $sql_where = " , `notify_new_unassigned`='0', `notify_reply_unassigned`='0' ";
            }

            /* Check for duplicate usernames */
            $res = $helpbase->database->query("SELECT `id`,`isadmin`,`categories`,`heskprivileges` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `user` = '" . $helpbase->database->escape($myuser['user']) . "' LIMIT 1");
            if ($helpbase->database->numRows($res) == 1) {
                $tmp = $helpbase->database->fetchAssoc($res);

                /* Duplicate? */
                if ($tmp['id'] != $myuser['id']) {
                    $helpbase->common->process_messages(_('User with this username already exists, choose a different username.'), $_SERVER['PHP_SELF']);
                }

                /* Do we have permission to edit this user? */
                if (!compare_user_permissions($tmp['id'], $tmp['isadmin'], explode(',', $tmp['categories']), explode(',', $tmp['heskprivileges']))) {
                    $helpbase->common->process_messages(_('You don\'t have permission to edit this user.'), 'manage_users.php');
                }
            }

            /* Admins will have access to all features and categories */
            if ($myuser['isadmin']) {
                $myuser['categories'] = '';
                $myuser['features'] = '';
            }
            /* Not admin */ else {
                /* Categories and Features will be stored as a string */
                $myuser['categories'] = implode(',', $myuser['categories']);
                $myuser['features'] = implode(',', $myuser['features']);

                /* Unassign tickets from categories that the user had access before but doesn't anymore */
                $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `owner`=0 WHERE `owner`='" . intval($myuser['id']) . "' AND `category` NOT IN (" . $myuser['categories'] . ")");
            }

            $helpbase->database->query(
                    "UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET
    `user`='" . $helpbase->database->escape($myuser['user']) . "',
    `name`='" . $helpbase->database->escape($myuser['name']) . "',
    `email`='" . $helpbase->database->escape($myuser['email']) . "',
    `signature`='" . $helpbase->database->escape($myuser['signature']) . "'," . ( isset($myuser['pass']) ? "`pass`='" . $helpbase->database->escape($myuser['pass']) . "'," : '' ) . "
    `categories`='" . $helpbase->database->escape($myuser['categories']) . "',
    `isadmin`='" . intval($myuser['isadmin']) . "',
    `autoassign`='" . intval($myuser['autoassign']) . "',
    `heskprivileges`='" . $helpbase->database->escape($myuser['features']) . "'
    $sql_where
    WHERE `id`='" . intval($myuser['id']) . "' LIMIT 1");

            unset($_SESSION['save_userdata']);
            unset($_SESSION['userdata']);

            $helpbase->common->process_messages(_('This user profile has been successfully updated.'), $_SERVER['PHP_SELF'], 'SUCCESS');
        }

// End update_profile()

        function hesk_validateUserInfo($pass_required = 1, $redirect_to = './manage_users.php') {
            global $hesk_settings, $helpbase;

            $hesk_error_buffer = '';

            $myuser['name']         = $helpbase->common->_input($helpbase->common->_post('name')) or $hesk_error_buffer .= '<li>' . _('Please enter user real name') . '</li>';
            $myuser['email']        = $helpbase->common->validateEmail($helpbase->common->_post('email'), 'ERR', 0) or $hesk_error_buffer .= '<li>' . _('Please enter a valid email address') . '</li>';
            $myuser['user']         = $helpbase->common->_input($helpbase->common->_post('user')) or $hesk_error_buffer .= '<li>' . _('Please enter username (login)') . '</li>';
            $myuser['isadmin']      = empty($_POST['isadmin']) ? 0 : 1;
            $myuser['signature']    = $helpbase->common->_input($helpbase->common->_post('signature'));
            $myuser['autoassign']   = $helpbase->common->_post('autoassign') == 'Y' ? 1 : 0;

            /* If it's not admin at least one category and fature is required */
            $myuser['categories'] = array();
            $myuser['features'] = array();

            if ($myuser['isadmin'] == 0) {
                if (empty($_POST['categories']) || !is_array($_POST['categories'])) {
                    $hesk_error_buffer .= '<li>' . _('Please assign user to at least one category!') . '</li>';
                } else {
                    foreach ($_POST['categories'] as $tmp) {
                        if (is_array($tmp)) {
                            continue;
                        }

                        if ($tmp = intval($tmp)) {
                            $myuser['categories'][] = $tmp;
                        }
                    }
                }

                if (empty($_POST['features']) || !is_array($_POST['features'])) {
                    $hesk_error_buffer .= '<li>' . _('Please assign at least one feature to this user!') . '</li>';
                } else {
                    foreach ($_POST['features'] as $tmp) {
                        if (in_array($tmp, $hesk_settings['features'])) {
                            $myuser['features'][] = $tmp;
                        }
                    }
                }
            }

            if (strlen($myuser['signature']) > 255) {
                $hesk_error_buffer .= '<li>' . _('User signature is too long! Please limit the signature to 255 chars') . '</li>';
            }

            /* Password */
            $myuser['cleanpass'] = '';

            $newpass = $helpbase->common->_input($helpbase->common->_post('newpass'));
            $passlen = strlen($newpass);

            if ($pass_required || $passlen > 0) {
                /* At least 5 chars? */
                if ($passlen < 5) {
                    $hesk_error_buffer .= '<li>' . _('Password must be at least 5 chars long') . '</li>';
                }
                /* Check password confirmation */ else {
                    $newpass2 = $helpbase->common->_input($helpbase->common->_post('newpass2'));

                    if ($newpass != $newpass2) {
                        $hesk_error_buffer .= '<li>' . _('The two passwords entered are not the same!') . '</li>';
                    } else {
                        $myuser['pass'] = $helpbase->admin->pass2Hash($newpass);
                        $myuser['cleanpass'] = $newpass;
                    }
                }
            }

            /* Save entered info in session so we don't loose it in case of errors */
            $_SESSION['userdata'] = $myuser;

            /* Any errors */
            if (strlen($hesk_error_buffer)) {
                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $helpbase->common->process_messages($hesk_error_buffer, $redirect_to);
            }

            return $myuser;
        }

// End hesk_validateUserInfo()

        function remove() {
            global $hesk_settings, $helpbase;

            /* A security check */
            $helpbase->common->token_check();

            $myuser = intval($helpbase->common->_get('id')) or $helpbase->common->_error(_('No valid user ID'));

            /* You can't delete the default user */
            if ($myuser == 1) {
                $helpbase->common->process_messages(_('You cannot delete the default administrator!'), './manage_users.php');
            }

            /* You can't delete your own account (the one you are logged in) */
            if ($myuser == $_SESSION['id']) {
                $helpbase->common->process_messages(_('You cannot delete the profile you are logged in as!'), './manage_users.php');
            }

            /* Un-assign all tickets for this user */
            $res = $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` SET `owner`=0 WHERE `owner`='" . intval($myuser) . "'");

            /* Delete user info */
            $res = $helpbase->database->query("DELETE FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` WHERE `id`='" . intval($myuser) . "'");
            if ($helpbase->database->affectedRows() != 1) {
                $helpbase->common->process_messages(_('Internal script error') . ': ' . _('User not found'), './manage_users.php');
            }

            $helpbase->common->process_messages(_('New user %s with password %s has been successfully added'), './manage_users.php', 'SUCCESS');
        }

// End remove()

        function toggle_autoassign() {
            global $hesk_settings, $helpbase;

            /* A security check */
            $helpbase->common->token_check();

            $myuser = intval($helpbase->common->_get('id')) or $helpbase->common->_error(_('No valid user ID'));
            $_SESSION['seluser'] = $myuser;

            if (intval($helpbase->common->_get('s'))) {
                $autoassign = 1;
                $tmp = _('Auto-assign has been enabled for selected user');
            } else {
                $autoassign = 0;
                $tmp = _('Auto-assign has been disabled for selected user');
            }

            /* Update auto-assign settings */
            $res = $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET `autoassign`='{$autoassign}' WHERE `id`='" . intval($myuser) . "'");
            if ($helpbase->database->affectedRows() != 1) {
                $helpbase->common->process_messages(_('Internal script error') . ': ' . _('User not found'), './manage_users.php');
            }

            $helpbase->common->process_messages($tmp, './manage_users.php', 'SUCCESS');
        }

// End toggle_autoassign()
        ?>
