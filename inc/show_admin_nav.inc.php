<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Admin Navigation
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseAdminNav')){
    class HelpbaseAdminNav {
        private $home       = '';
        private $users      = '';
        private $categories = '';
        private $profile    = '';
        private $kbase      = '';
        private $helpbase   = null;
        
        public function __construct($parent) {
            $this->helpbase     = $parent;
            $this->home         = _('Home');
            $this->users        = _('Users');
            $this->categories   = _('Categories');
            $this->profile      = _('Profile');
            $this->kbase        = _('Knowledgebase');
        }

        public function render(){
            global $hesk_settings;
            
            $url = $this->helpbase->url;
?>
                        <div align="center">
                            <table class="admin-nav">
                                <!-- <tr>
                                    <td style="width:4px; height:4px"><img src="<?php echo $url; ?>img/header_up_left.png" width="4" height="4" alt="" /></td>
                                    <td style="background-image:url(<?php echo $url; ?>img/header_top.png); background-repeat:repeat-x; background-position:top; height:4px"></td>
                                    <td style="width:4px; height:4px"><img src="<?php echo $url; ?>img/header_up_right.png" width="4" height="4" alt="" /></td>
                                </tr> -->
                                <tr>
                                    <!-- <td style="width:4px; background-image:url(<?php echo $url; ?>img/header_left.png); background-repeat:repeat-y; background-position:left;"></td> -->
                                    <td>

                                        <!-- START MENU LINKS -->
                                        <table class="header">
                                            <tr>
                                                <td>
                                                    <table class="toolbar">
                                                        <tr>
                                                            <td>
                                                                <a href="admin_main.php"><img class="admin" src="<?php echo $url; ?>img/ico_home.gif" alt="<?php echo $this->home; ?>" title="<?php echo $this->home; ?>" /><br/><?php echo $this->home; ?></a>
                                                                <br/><img class="blank" src="<?php echo $url; ?>img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>
<?php
            if ($this->helpbase->admin->checkPermission('can_man_users', 0)) {
                echo '
                                                            <td>
                                                                <a href="manage_users.php"><img class="admin" src="' . $url . 'img/ico_users.gif" alt="' . $this->users . '" title="' . $this->users . '" />
                                                                    <br />' . $this->users . '
                                                                </a>
                                                                <br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            }

            if ($this->helpbase->admin->checkPermission('can_man_cat', 0)) {
                echo '
                                                            <td>
                                                                <a href="manage_categories.php"><img class="admin" src="' . $url . 'img/ico_categories.gif" alt="' . $this->categories . '" title="' . $this->categories . '" />
                                                                    <br />' . $this->categories . '
                                                                </a>
                                                                <br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            }

            if ($this->helpbase->admin->checkPermission('can_man_canned', 0)) {
                echo '
                                                            <td>
                                                                <a href="manage_canned.php"><img class="admin" src="' . $url . 'img/ico_canned.gif" alt="' . _('Canned') . '" title="' . _('Canned') . '" />
                                                                    <br />' . _('Canned') . '
                                                                </a><br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            }

            if ($hesk_settings['kb_enable']) {
                if ($this->helpbase->admin->checkPermission('can_man_kb', 0)) {
                    echo '
                                                            <td>
                                                                <a href="manage_knowledgebase.php"><img class="admin" src="' . $url . 'img/ico_kb.gif" alt="' . $this->kbase . '" title="' . $this->kbase . '" />
                                                                    <br />' . $this->kbase . '
                                                                </a><br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
                } else {
                    echo '
                                                            <td>
                                                                <a href="knowledgebase_private.php"><img class="admin" src="' . $url . 'img/ico_kb.gif" alt="' . $this->kbase . '" title="' . $this->kbase . '" />
                                                                    <br />' . $this->kbase . '
                                                                </a><br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
                }
            }

            if ($this->helpbase->admin->checkPermission('can_run_reports', 0)) {
                echo '
                                                            <td>
                                                                <a href="reports.php"><img class="admin" src="' . $url . 'img/ico_reports.gif" alt="' . _('Reports') . '"  title="' . _('Reports') . '" />
                                                                    <br />' . _('Reports') . '
                                                                </a><br /><img class = "blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            } elseif ($this->helpbase->admin->checkPermission('can_export', 0)) {
                echo '
                                                            <td>
                                                                <a href="export.php"><img class="admin" src="' . $url . 'img/ico_reports.gif" alt="' . _('Reports') . '"  title="' . _('Reports') . '" />
                                                                    <br />' . _('Reports') . '
                                                                </a><br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            }

            if ($this->helpbase->admin->checkPermission('can_man_settings', 0)) {
                $settings = _('Settings');
                echo '
                                                            <td>
                                                                <a href="admin_settings.php"><img class="admin" src="' . $url . 'img/ico_settings.gif" alt="' . $settings . '"  title="' . $settings . '" />
                                                                    <br />' . $settings . '
                                                                </a><br /><img class="blank" src="' . $url . 'img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>';
            }

            $num_mail   = $this->helpbase->admin->checkNewMail();
            $num_mail   = $num_mail ? '<b>' . $num_mail . '</b>' : 0;
?>
                                                            <td>
                                                                <a href="profile.php"><img class="admin" src="<?php echo $url; ?>img/ico_profile.gif" alt="<?php echo $this->profile; ?>" title="<?php echo $this->profile; ?>" />
                                                                    <br /><?php echo $this->profile; ?>
                                                                </a><br /><img class="blank" src="<?php echo $url; ?>img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>
                                                            <td>
                                                                <a href="mail.php"><img class="admin" src="<?php echo $url; ?>img/ico_mail.gif" alt="<?php echo _('Mail'); ?>" title="<?php echo _('Mail'); ?>" />
                                                                    <br /><?php echo _('Mail'); ?> (<?php echo $num_mail; ?>)
                                                                </a><br /><img class="blank" src="<?php echo $url; ?>img/blank.gif" alt="" />
                                                            </td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>
                                                            <td>
                                                                <a href="index.php?a=logout&amp;token=<?php echo $this->helpbase->common->token_echo(); ?>"><img class="admin" src="<?php echo $url; ?>img/ico_logout.gif" alt="<?php echo _('Logout'); ?>" title="<?php echo _('Logout'); ?>" />
                                                                    <br /><?php echo _('Logout'); ?>
                                                                </a><br /><img class="blank" src="<?php echo $url; ?>img/blank.gif" alt="" />
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END MENU LINKS -->

                                    </td>
                                    <!-- <td style="width:4px; background-image: url(<?php echo $url; ?>img/header_right.png); background-repeat:repeat-y; background-position:right;"></td> -->
                                </tr>
                                <!-- <tr>
                                    <td style="width:4px; height:4px"><img src="<?php echo $url; ?>img/header_bottom_left.png" width="4" height="4" alt="" /></td>
                                    <td style="background-image:url(<?php echo $url; ?>img/header_bottom.png); background-repeat:repeat-x; background-position:bottom; height:4px"></td>
                                    <td style="width:4px; height:4px"><img src="<?php echo $url; ?>img/header_bottom_right.png" width="4" height="4" alt="" /></td>
                                </tr> -->
                            </table>
                        </div>
<?php
            unset($num_mail);
        }
    }
}

?>