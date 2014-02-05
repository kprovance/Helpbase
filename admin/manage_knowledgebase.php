<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Manage Knowledge Base
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if(!class_exists('HelpbaseManageKb')) {
    class HelpbaseManageKb {
        private $helpbase   = null;
        private $treeMenu   = null;
        private $listBox    = null;

        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            // Check for POST requests larger than what the server can handle
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
                $helpbase->common->_error(_('You probably tried to submit more data than this server accepts.<br /><br />Please try submitting the form again with smaller or no attachments.'));
            }

            $helpbase->admin->isLoggedIn();

            /* Check permissions for this feature */
            if (!$helpbase->admin->checkPermission('can_man_kb', 0)) {
                /* This person can't manage the knowledgebase, but can read it */
                header('Location: knowledgebase_private.php');
                exit();
            }

            /* Is Knowledgebase enabled? */
            if (!$hesk_settings['kb_enable']) {
                $helpbase->common->_error(_('Knowledgebase is disabled'));
            }

            /* This will tell the header to include WYSIWYG editor Javascript */
            $helpbase->wysiwyg = true;

            /* What should we do? */
            if ($action = $helpbase->common->_request('a')) {
                if ($action == 'add_article') {
                    $this->add_article();
                } elseif ($action == 'add_category') {
                    $this->add_category();
                } elseif ($action == 'manage_cat') {
                    $this->manage_category();
                } elseif ($action == 'edit_article') {
                    $this->edit_article();
                } elseif ($action == 'import_article') {
                    $this->import_article();
                } elseif ($action == 'list_private') {
                    $this->list_private();
                } elseif ($action == 'list_draft') {
                    $this->list_draft();
                } elseif (true == $helpbase->demo_mode) {
                    $helpbase->common->process_messages(_('Sorry, this function has been disabled in DEMO mode!'), 'manage_knowledgebase.php', 'NOTICE');
                } elseif ($action == 'new_article') {
                    $this->new_article();
                } elseif ($action == 'new_category') {
                    $this->new_category();
                } elseif ($action == 'remove_article') {
                    $this->remove_article();
                } elseif ($action == 'save_article') {
                    $this->save_article();
                } elseif ($action == 'order_article') {
                    $this->order_article();
                } elseif ($action == 'order_cat') {
                    $this->order_category();
                } elseif ($action == 'edit_category') {
                    $this->edit_category();
                } elseif ($action == 'remove_kb_att') {
                    $this->remove_kb_att();
                } elseif ($action == 'sticky') {
                    $this->toggle_sticky();
                } elseif ($action == 'update_count') {
                    $this->update_count(1);
                }
            }

            // Part of a trick to prevent duplicate article submissions by reloading pages
            $helpbase->common->cleanSessionVars('article_submitted');

            $this->render();
        }

        private function render(){
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
<?php
            /* This will handle error, success and notice messages */
            #$this->helpbase->common->handle_messages();
            // Get number of sub-categories for each parent category
            $parent = array(0 => 1);
            $result = $this->helpbase->database->query('SELECT `parent`, COUNT(*) AS `num` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` GROUP BY `parent`');
            while ($row = $this->helpbase->database->fetchAssoc($result)) {
                $parent[$row['parent']] = $row['num'];
            }
            $parent_copy = $parent;

            //print_r($parent);
            // Get Knowledgebase structure
            $kb_cat = array();
            $result = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `parent` ASC, `cat_order` ASC');
            while ($cat = $this->helpbase->database->fetchAssoc($result)) {
                // Can this category be moved at all?
                if (
                        $cat['id'] == 1 || // Main category cannot be moved
                        !isset($parent[$cat['parent']]) || // if the parent category isn't set
                        $parent[$cat['parent']] < 2         // Less than 2 articles in category
                ) {
                    $cat['move_up'] = false;
                    $cat['move_down'] = false;
                } else {
                    $cat['move_up'] = true;
                    $cat['move_down'] = true;
                }

                    $kb_cat[] = $cat;
            }

            //print_r($kb_cat);

            /* Translate main category "Knowledgebase" if needed */
            $kb_cat[0]['name'] = _('Knowledgebase');

            require($this->helpbase->includes . 'treemenu/TreeMenu.php');
            $icon = 'folder.gif';
            $expandedIcon = 'folder-expanded.gif';
            $menu = new HTML_TreeMenu();

            $thislevel = array('0');
            $nextlevel = array();
            $i = 1;
            $j = 1;

            if (isset($_SESSION['KB_CATEGORY'])) {
                $selected_catid = intval($_SESSION['KB_CATEGORY']);
            } else {
                $selected_catid = 0;
            }

            while (count($kb_cat) > 0) {
                foreach ($kb_cat as $k => $cat) {
                    if (in_array($cat['parent'], $thislevel)) {
                        $arrow = ($i - 2) % 10;
                        $arrow = $arrow == 0 ? '' : $arrow;

                        $up = $cat['parent'];
                        $my = $cat['id'];
                        $type = $cat['type'] ? '*' : '';
                        $selected = ($selected_catid == $my) ? 1 : 0;
                        $cls = (isset($_SESSION['newcat']) && $_SESSION['newcat'] == $my) ? ' class="kbCatListON"' : '';

                        $text = str_replace('\\', '\\\\', '<span id="c_' . $my . '"' . $cls . '><a href="manage_knowledgebase.php?a=manage_cat&catid=' . $my . '">' . $cat['name'] . '</a>') . $type . '</span> (<span class="kb_published">' . $cat['articles'] . '</span>, <span class="kb_private">' . $cat['articles_private'] . '</span>, <span class="kb_draft">' . $cat['articles_draft'] . '</span>) ';                  /* ' */

                        $text_short = $cat['name'] . $type . ' (' . $cat['articles'] . ', ' . $cat['articles_private'] . ', ' . $cat['articles_draft'] . ')';

                        // Generate KB menu icons
                        $menu_icons = '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $my . '" onclick="document.getElementById(\'option' . $j . '\').selected=true;return true;"><img src="../img/add_article.png" width="16" height="16" alt="' . _('New Article') . '" title="' . _('New Article') . '" class="optionWhiteNbOFF" onmouseover="this.className=\'optionWhiteNbON\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListON\'" onmouseout="this.className=\'optionWhiteNbOFF\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListOFF\'" /></a>  '
                                . '<a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $my . '" onclick="document.getElementById(\'option' . $j . '_2\').selected=true;return true;"><img src="../img/add_category.png" width="16" height="16" alt="' . _('New Category') . '" title="' . _('New Category') . '" class="optionWhiteNbOFF" onmouseover="this.className=\'optionWhiteNbON\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListON\'" onmouseout="this.className=\'optionWhiteNbOFF\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListOFF\'" /></a>  '
                                . '<a href="manage_knowledgebase.php?a=manage_cat&amp;catid=' . $my . '"><img src="../img/manage.png" width="16" height="16" alt="' . _('Manage') . '" title="' . _('Manage') . '" class="optionWhiteNbOFF" onmouseover="this.className=\'optionWhiteNbON\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListON\'" onmouseout="this.className=\'optionWhiteNbOFF\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListOFF\'" /></a> '
                        ;

                        // Can this category be moved up?
                        $move_up = _('Move up');
                        $move_down = _('Move down');

                        if ($cat['move_up'] == false || ($cat['move_up'] && $parent_copy[$cat['parent']] == $parent[$cat['parent']])) {
                            $menu_icons .= '<img src="../img/blank.gif" width="16" height="16" alt="" class="optionWhiteNbOFF" /> ';
                        } else {
                            $menu_icons .= '<a href="manage_knowledgebase.php?a=order_cat&amp;catid=' . $my . '&amp;move=-15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_up' . $arrow . '.png" width="16" height="16" alt="' . $move_up . '" title="' . $move_up . '" class="optionWhiteNbOFF" onmouseover="this.className=\'optionWhiteNbON\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListON\'" onmouseout="this.className=\'optionWhiteNbOFF\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListOFF\'" /></a> ';
                        }

                        // Can this category be moved down?
                        if ($cat['move_down'] == false || ($cat['move_down'] && $parent_copy[$cat['parent']] == 1)) {
                            $menu_icons .= '<img src="../img/blank.gif" width="16" height="16" alt="" class="optionWhiteNbOFF" /> ';
                        } else {
                            $menu_icons .= '<a href="manage_knowledgebase.php?a=order_cat&amp;catid=' . $my . '&amp;move=15&amp;token=' . $this->helpbase->common->token_echo(0) . '"><img src="../img/move_down' . $arrow . '.png" width="16" height="16" alt="' . $move_down . '" title="' . $move_down . '" class="optionWhiteNbOFF" onmouseover="this.className=\'optionWhiteNbON\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListON\'" onmouseout="this.className=\'optionWhiteNbOFF\';document.getElementById(\'c_' . $my . '\').className=\'kbCatListOFF\'" /></a> ';
                        }

                        if (isset($node[$up])) {
                            $node[$my] = &$node[$up]->addItem(new HTML_TreeNode(array('hesk_selected' => $selected, 'text' => $text, 'text_short' => $text_short, 'menu_icons' => $menu_icons, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true)));
                        } else {
                            $node[$my] = new HTML_TreeNode(array('hesk_selected' => $selected, 'text' => $text, 'text_short' => $text_short, 'menu_icons' => $menu_icons, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true));
                        }

                        $nextlevel[] = $cat['id'];
                        $parent_copy[$cat['parent']] --;
                        $j++;
                        unset($kb_cat[$k]);
                    }
                }

                $thislevel = $nextlevel;
                $nextlevel = array();

                /* Break after 20 recursions to avoid hang-ups in case of any problems */
                if ($i > 20) {
                    break;
                }
                $i++;
            }

            $menu->addItem($node[1]);

            // Create the presentation class
            $this->treeMenu = & $this->helpbase->common->ref_new(new HTML_TreeMenu_DHTML($menu, array('images' => '../img', 'defaultClass' => 'treeMenuDefault', 'isDynamic' => true)));
            $this->listBox = & $this->helpbase->common->ref_new(new HTML_TreeMenu_Listbox($menu));

            /* Hide new article and new category forms by default */
            if (!isset($_SESSION['hide'])) {
                $_SESSION['hide'] = array(
                    //'treemenu' => 1,
                    'new_article' => 1,
                    'new_category' => 1,
                );
            }

            /* Hide tree menu? */
            if (!isset($_SESSION['hide']['treemenu'])) {
?>
                            <h3><?php echo _('Manage Knowledgebase'); ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo $this->helpbase->admin->makeJsString(_('Knowledgebase is a collection of answers to frequently asked questions (FAQ) and articles which provide self-help resources to your customers.  A comprehensive and well-written knowledgebase can drastically reduce the number of support tickets you receive and save a lot of your time. You can arrange articles into categories and sub categories.')); ?>')">?</a>]</h3>

                            <!-- SUB NAVIGATION -->
                            <?php $this->show_subnav(); ?>
                            <!-- SUB NAVIGATION -->

                            <!-- SHOW THE CATEGORY TREE -->
                            <?php $this->show_treeMenu(); ?>
                            <!-- SHOW THE CATEGORY TREE -->

                            <p>&nbsp;</p>
                            <h3><?php echo _('&raquo; Knowledgebase tools'); ?></h3>
                            <p>
                                <img src="../img/view.png" width="16" height="16" border="0" alt="" style="padding:3px" class="optionWhiteNbOFF" /> <a href="manage_knowledgebase.php?a=list_private"><?php echo _('List private articles'); ?></a><br >
                                <img src="../img/view.png" width="16" height="16" border="0" alt="" style="padding:3px" class="optionWhiteNbOFF" /> <a href="manage_knowledgebase.php?a=list_draft"><?php echo _('List article drafts'); ?></a><br />
                                <img src="../img/manage.png" width="16" height="16" border="0" alt="" style="padding:3px" class="optionWhiteNbOFF" /> <a href="manage_knowledgebase.php?a=update_count"><?php echo _('Verify and update category article count'); ?></a><br />
                                <img src="../img/link.png" width="16" height="16" border="0" alt="" style="padding:3px" class="optionWhiteNbOFF" /> <a href="http://support.mozilla.com/en-US/kb/how-to-write-knowledge-base-articles" rel="nofollow" target="_blank"><?php echo _('How to write good knowledgebase articles?'); ?></a>
                            </p>
                            &nbsp;
                            <br />
<?php
            } // END hide treemenu

            /* Hide article form? */
            if (!isset($_SESSION['hide']['new_article'])) {
                if (isset($_SESSION['new_article'])) {
                    $_SESSION['new_article'] = $this->helpbase->common->stripArray($_SESSION['new_article']);
                } elseif (isset($_GET['type'])) {
                    $_SESSION['new_article']['type'] = intval($this->helpbase->common->_get('type'));
                    if ($_SESSION['new_article']['type'] != 1 && $_SESSION['new_article']['type'] != 2) {
                        $_SESSION['new_article']['type'] = 0;
                    }
                }
?>
                            <span class="smaller"><a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt; <?php echo _('New knowledgebase article'); ?></span>

                            <!-- SUB NAVIGATION -->
                            <?php $catid = $this->show_subnav('newa'); ?>
                            <!-- SUB NAVIGATION -->

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <div align="center">
                                            <table border="0">
                                                <tr>
                                                    <td>
<?php
                if ($hesk_settings['kb_wysiwyg']) {
?>
                                                        <script type="text/javascript">
                                                            tinyMCE.init({
                                                                mode: "exact",
                                                                elements: "content",
                                                                theme: "advanced",
                                                                convert_urls: false,
                                                                theme_advanced_buttons1: "cut,copy,paste,|,undo,redo,|,formatselect,fontselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull",
                                                                theme_advanced_buttons2: "sub,sup,|,charmap,|,bullist,numlist,|,outdent,indent,insertdate,inserttime,preview,|,forecolor,backcolor,|,hr,removeformat,visualaid,|,link,unlink,anchor,image,cleanup,code",
                                                                theme_advanced_buttons3: "",
                                                                theme_advanced_toolbar_location: "top",
                                                                theme_advanced_toolbar_align: "left",
                                                                theme_advanced_statusbar_location: "bottom",
                                                                theme_advanced_resizing: true
                                                            });
                                                        </script>
<?php
                }
?>
                                                        <form action="manage_knowledgebase.php" method="post" name="form1" enctype="multipart/form-data">
                                                            <h3 align="center"><a name="new_article"></a><?php echo _('New knowledgebase article'); ?></h3>
                                                            <br />
                                                            <table border="0">
                                                                <tr>
                                                                    <td><b><?php echo _('Category'); ?>:</b></td>
                                                                    <td>
                                                                        <select name="catid"><?php $this->listBox->printMenu(); ?></select>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td valign="top"><b><?php echo _('Type'); ?>:</b></td>
                                                                    <td>
<?php
                $checked = '';
                if (!isset($_SESSION['new_article']['type']) || (isset($_SESSION['new_article']['type']) && $_SESSION['new_article']['type'] == 0)) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="radio" name="type" value="0" <?php echo $checked;?>/> <b><i><?php echo _('Published'); ?></i></b>
                                                                        </label>
                                                                        <br />
                                                                        <?php echo _('The article is viewable to everyone in the knowledgebase.'); ?>
                                                                        <br />&nbsp;<br />
<?php
                $checked = '';
                if (isset($_SESSION['new_article']['type']) && $_SESSION['new_article']['type'] == 1) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="radio" name="type" value="1" <?php echo $checked; ?> /> <b><i><?php echo _('Private'); ?></i></b>
                                                                        </label>
                                                                        <br />
                                                                        <?php echo _('Private articles can only be read by staff.'); ?><br />&nbsp;<br />
<?php
                $checked = '';
                if (isset($_SESSION['new_article']['type']) && $_SESSION['new_article']['type'] == 2) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="radio" name="type" value="2" <?php echo $checked; ?> /> <b><i><?php echo _('Draft'); ?></i></b>
                                                                        </label>
                                                                        <br />
                                                                        <?php echo _('The article is saved but not yet published. It can only be read by staff<br /> who has permission to manage knowledgebase articles.'); ?><br />&nbsp;
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td><b><?php echo _('Subject'); ?>:</b></td>
<?php
                $checked = '';
                if (isset($_SESSION['new_article']['subject'])) {
                    $checked = 'value="' . $_SESSION['new_article']['subject'] . '"';
                }
?>
                                                                    <td>
                                                                        <input type="text" name="subject" size="70" maxlength="255" <?php echo $checked; ?> />
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td valign="top"><b><?php echo _('Options'); ?>:</b></td>
                                                                    <td>
<?php
                $checked = '';
                if (!empty($_SESSION['new_article']['sticky'])) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="checkbox" name="sticky" value="Y" <?php echo $checked; ?> /> <i><?php echo _('Make this article &quot;Sticky&quot;'); ?></i></label><br />
                                                                    </td>
                                                                </tr>
                                                            </table>
<?php
                $displayType = $hesk_settings['kb_wysiwyg'] ? 'none' : 'block';
                $displayWarn = 'none';
?>
                                                            <p>&nbsp;<br /><b><?php echo _('Contents'); ?>:</b></p>
                                                            <span id="contentType" style="display:<?php echo $displayType; ?>">
<?php
                $checked = '';
                if (!isset($_SESSION['new_article']['html']) || (isset($_SESSION['new_article']['html']) && $_SESSION['new_article']['html'] == 0)) {
                    echo 'checked="checked"';
                }
?>
                                                                <label>
                                                                    <input type="radio" name="html" value="0" <?php echo $checked; ?> onclick="javascript:document.getElementById('kblinks').style.display = 'none'" /> <?php echo _('This is plain text (links will be clickable)'); ?>
                                                                </label>
                                                                <br />
<?php
                $display = 'none';
                $checked = '';
                if (isset($_SESSION['new_article']['html']) && $_SESSION['new_article']['html'] == 1) {
                    $checked = 'checked="checked"';
                    $displayWarn = 'block';
                }
?>
                                                                <label>
                                                                    <input type="radio" name="html" value="1" <?php echo $checked; ?> onclick="javascript:document.getElementById('kblinks').style.display = 'block'" /> <?php echo _('This is HTML code (I will enter valid (X)HTML code)'); ?>
                                                                </label>
                                                                <br />
                                                                <span id="kblinks" style="display:<?php echo $displayWarn; ?>"><i><?php echo _('<i><span class="notice"><b>Warning!</b></span><br />Enter valid code without &lt;head&gt; and &lt;body&gt; tags, just content!</i>'); ?></i></span>
                                                            </span>
<?php
                $content = '';
                if (isset($_SESSION['new_article']['content'])) {
                    $content = $_SESSION['new_article']['content'];
                }
?>
                                                            <p>
                                                                <textarea name="content" rows="25" cols="70" id="content"><?php echo $content; ?></textarea>
                                                            </p>
                                                            <p>
                                                                &nbsp;
                                                                <br />
                                                                <b><?php echo _('Keywords'); ?>:</b> <?php echo _('(optional - separate by space, comma or new line)'); ?>
                                                            </p>
<?php
                $keywords = '';
                if (isset($_SESSION['new_article']['keywords'])) {
                    $keywords = $_SESSION['new_article']['keywords'];
                }
?>
                                                            <p>
                                                                <textarea name="keywords" rows="3" cols="70" id="keywords"><?php echo $keywords; ?></textarea>
                                                            </p>

                                                            <p>
                                                                &nbsp;
                                                                <br />
                                                                <b><?php echo _('Attachments'); ?></b> (<a href="Javascript:void(0)" onclick="Javascript:hb_window('../file_limits.php', 250, 500); return false;"><?php echo _('File upload limits'); ?></a>)
                                                                <br />
                                                                <input type="file" name="attachment[1]" size="50" /><br />
                                                                <input type="file" name="attachment[2]" size="50" /><br />
                                                                <input type="file" name="attachment[3]" size="50" /><br />&nbsp;
                                                            </p>

                                                            <p align="center"><input type="hidden" name="a" value="new_article" />
                                                                <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                                <input type="submit" value="<?php echo _('Save article'); ?>" class="button blue small" />
                                                                | <a href="manage_knowledgebase.php?a=manage_cat&amp;catid=<?php echo $catid; ?>"><?php echo _('Cancel'); ?></a>
                                                            </p>
                                                        </form>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
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
            } // END hide article

            /* Hide new category form? */
            if (!isset($_SESSION['hide']['new_category'])) {

                if (isset($_SESSION['new_category'])) {
                    $_SESSION['new_category'] = $this->helpbase->common->stripArray($_SESSION['new_category']);
                }
?>
                            <span class="smaller"><a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt; <?php echo _('New knowledgebase category'); ?></span>

                            <!-- SUB NAVIGATION -->
                            <?php $this->show_subnav('newc'); ?>
                            <!-- SUB NAVIGATION -->

                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <div align="center">
                                            <table border="0">
                                                <tr>
                                                    <td>
                                                        <form action="manage_knowledgebase.php" method="post" name="form2">
                                                            <h3 align="center"><a name="new_category"></a><?php echo _('New knowledgebase category'); ?></h3>
                                                            <br />

                                                            <table border="0">
                                                                <tr>
                                                                    <td><b><?php echo _('Category title'); ?>:</b></td>
                                                                    <td><input type="text" name="title" size="70" maxlength="255" /></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><b><?php echo _('Parent category'); ?>:</b></td>
                                                                    <td><select name="parent"><?php $this->listBox->printMenu() ?></select></td>
                                                                </tr>
                                                                <tr>
                                                                    <td valign="top"><b><?php echo _('Type'); ?>:</b></td>
                                                                    <td>
<?php
                $checked = '';
                if (!isset($_SESSION['new_category']['type']) || (isset($_SESSION['new_category']['type']) && $_SESSION['new_category']['type'] == 0)) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="radio" name="type" value="0" <?php echo $checked; ?> /> <b><i><?php echo _('Published'); ?></i></b>
                                                                        </label>
                                                                        <br />
                                                                        <?php echo _('The category is viewable to everyone in the knowledgebase.'); ?>
                                                                        <br />&nbsp;<br />
<?php
                $checked = '';
                if (isset($_SESSION['new_category']['type']) && $_SESSION['new_category']['type'] == 1) {
                    $checked = 'checked="checked"';
                }
?>
                                                                        <label>
                                                                            <input type="radio" name="type" value="1" <?php echo $checked; ?> /> <b><i><?php echo _('Private'); ?></i></b>
                                                                        </label>
                                                                        <br />
                                                                        <?php echo _('The category can only be read by staff.'); ?>
                                                                    </td>
                                                                </tr>
                                                            </table>

                                                            <p align="center"><input type="hidden" name="a" value="new_category" />
                                                                <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                                <input type="submit" value="<?php echo _('Add category'); ?>" class="button blue small" />
                                                                | <a href="manage_knowledgebase.php"><?php echo _('Cancel'); ?></a>
                                                            </p>
                                                        </form>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
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
                /* Show the treemenu? */
                if (isset($_SESSION['hide']['cat_treemenu'])) {
                    echo ' &nbsp; ';
                    $this->show_treeMenu();
                }
            } // END hide new category form

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars(array(
                'hide',
                'new_article',
                'new_category',
                'KB_CATEGORY',
                'manage_cat',
                'edit_article',
                'newcat'
            ));
?>
                            <p>&nbsp;</p>
<?php

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function list_draft() {
            global $hesk_settings;

            $catid = 1;
            $kb_cat = $this->helpbase->admin->getCategoriesArray(1);

            /* Translate main category "Knowledgebase" if needed */
            $kb_cat[0]['name'] = _('Knowledgebase');

            /* Print header */
            $this->helpbase->header->render();

            /* Print main manage users page */
            $this->helpbase->admin_nav->render();
?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="smaller"><a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt; <?php echo _('Manage knowledgebase category'); ?></span>

                            <!-- SUB NAVIGATION -->
                            <?php $this->show_subnav('', $catid);
?>
                            <!-- SUB NAVIGATION -->
<?php
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `type`='2' ORDER BY `catid` ASC, `id` ASC");
            $num = $this->helpbase->database->numRows($res);

            if ($num == 0) {
                echo '<p>' . _('No article drafts in the knowledgebase.') . ' &nbsp; <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=2"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=2"><b>' . _('Insert an Article') . '</b></a></p>';
            } else {
?>
                            <div style="float:right">
                                <?php echo '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=2"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=2"><b>' . _('Insert an Article') . '</b></a>'; ?>
                            </div>
                            <h3 style="padding-bottom:5px;">&raquo; <?php echo _('Article drafts'); ?></h3>
                            <div align="center">
                                <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                                    <tr>
                                        <th class="admin_white">&nbsp;</th>
                                        <th class="admin_white"><b><i><?php echo _('Subject'); ?></i></b></th>
                                        <th class="admin_white"><b><i><?php echo _('Category'); ?></i></b></th>
                                        <th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                                    </tr>
<?php
                $i = 1;
                $j = 1;

                while ($article = $this->helpbase->database->fetchAssoc($res)) {
                    if (isset($_SESSION['artord']) && $article['id'] == $_SESSION['artord']) {
                        $color = 'admin_green';
                        unset($_SESSION['artord']);
                    } else {
                        $color = $i ? 'admin_white' : 'admin_gray';
                    }

                    $tmp = $i ? 'White' : 'Blue';
                    $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                    $i = $i ? 0 : 1;

                    $type = _('Draft');
?>
                                    <tr>
                                        <td class="<?php echo $color; ?>"><?php echo $j; ?>.</td>
                                        <td class="<?php echo $color; ?>"><?php echo $article['subject']; ?></td>
                                        <td class="<?php echo $color; ?>"><?php echo $kb_cat[$article['catid']]; ?></td>
                                        <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;">
                                            <a href="knowledgebase_private.php?article=<?php echo $article['id']; ?>&amp;back=1<?php if ($article['type'] == 2) { echo '&amp;draft=1'; } ?>" target="_blank"><img src="../img/article_text.png" width="16" height="16" alt="<?php echo _('View this article'); ?>" title="<?php echo _('View this article'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=edit_article&amp;id=<?php echo $article['id']; ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo _('Edit'); ?>" title="<?php echo _('Edit'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=remove_article&amp;id=<?php echo $article['id']; ?>&amp;token=<?php $this->helpbase->common->token_echo(); ?>" onclick="return hb_confirmExecute('<?php echo _('Are you sure you want to delete selected article?'); ?>');"><img src="../img/delete.png" width="16" height="16" alt="<?php echo _('Delete'); ?>" title="<?php echo _('Delete'); ?>" <?php echo $style; ?> /></a>&nbsp;
                                        </td>
                                    </tr>
<?php
                    $j++;
                } // End while
?>
                                </table>
                            </div>
<?php
            }
            echo '
                            &nbsp;<br />&nbsp;';

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars(array('hide', 'manage_cat', 'edit_article'));

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function list_private() {
            global $hesk_settings;

            $catid = 1;
            $kb_cat = $this->helpbase->admin->getCategoriesArray(1);

            /* Translate main category "Knowledgebase" if needed */
            $kb_cat[0]['name'] = _('Knowledgebase');

            /* Get list of private categories */
            $private_categories = array();
            $res = $this->helpbase->database->query("SELECT `id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` WHERE `type`='1'");
            $num = $this->helpbase->database->numRows($res);
            if ($num) {
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $private_categories[] = intval($row['id']);
                }
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
                            <span class="smaller"><a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt; <?php echo _('Manage knowledgebase category'); ?></span>

                            <!-- SUB NAVIGATION -->
                            <?php $this->show_subnav('', $catid); ?>
                            <!-- SUB NAVIGATION -->
<?php
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `type`='1' " . (count($private_categories) ? " OR `catid` IN('" . implode("','", $private_categories) . "') " : '') . " ORDER BY `catid` ASC, `id` ASC");
            $num = $this->helpbase->database->numRows($res);

            if ($num == 0) {
                echo '<p>' . _('No private articles in the knowledgebase.') . ' &nbsp; <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=1"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=1"><b>' . _('Insert an Article') . '</b></a></p>';
            } else {
?>
                            <div style="float:right">
                                <?php echo '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=1"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '&amp;type=1"><b>' . _('Insert an Article') . '</b></a>'; ?>
                            </div>

                            <h3 style="padding-bottom:5px;">&raquo; <?php echo _('Private articles'); ?></h3>
                            <div align="center">
                                <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                                    <tr>
                                        <th class="admin_white">&nbsp;</th>
                                        <th class="admin_white"><b><i><?php echo _('Subject'); ?></i></b></th>
                                        <th class="admin_white"><b><i><?php echo _('Category'); ?></i></b></th>
                                        <th class="admin_white"><b><i><?php echo _('Views'); ?></i></b></th>
<?php
                if ($hesk_settings['kb_rating']) {
?>
                                        <th class="admin_white" style="white-space:nowrap" nowrap="nowrap" width="130"><b><i><?php echo _('Rating') . ' (' . _('Votes') . ')'; ?></i></b></th>
<?php
                }
?>
                                        <th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                                    </tr>
<?php
                $i = 1;
                $j = 1;

                while ($article = $this->helpbase->database->fetchAssoc($res)) {

                    if (isset($_SESSION['artord']) && $article['id'] == $_SESSION['artord']) {
                        $color = 'admin_green';
                        unset($_SESSION['artord']);
                    } else {
                        $color = $i ? 'admin_white' : 'admin_gray';
                    }

                    $tmp = $i ? 'White' : 'Blue';
                    $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                    $i = $i ? 0 : 1;

                    $type = _('Private');

                    if ($hesk_settings['kb_rating']) {
                        $alt = $article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $article['rating'])) : _('Article not rated yet');
                        $rat = '<td class="' . $color . '" style="white-space:nowrap;"><img src="../img/star_' . ($this->helpbase->common->round_to_half($article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" title="' . $alt . '" border="0" style="vertical-align:text-bottom" /> (' . $article['votes'] . ') </td>';
                    } else {
                        $rat = '';
                    }
?>
                                    <tr>
                                        <td class="<?php echo $color; ?>"><?php echo $j; ?>.</td>
                                        <td class="<?php echo $color; ?>"><?php echo $article['subject']; ?></td>
                                        <td class="<?php echo $color; ?>"><?php echo $kb_cat[$article['catid']]; ?></td>
                                        <td class="<?php echo $color; ?>"><?php echo $article['views']; ?></td>
                                        <?php echo $rat; ?>
                                        <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;">
<?php
                    $param = '';
                    if ($article['type'] == 2) {
                        $param = '&amp;draft=1';
                    }
?>
                                            <a href="knowledgebase_private.php?article=<?php echo $article['id']; ?>&amp;back=1<?php echo $param; ?>" target="_blank"><img src="../img/article_text.png" width="16" height="16" alt="<?php echo _('View this article'); ?>" title="<?php echo _('View this article'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=edit_article&amp;id=<?php echo $article['id']; ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo _('Edit'); ?>" title="<?php echo _('Edit'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=remove_article&amp;id=<?php echo $article['id']; ?>&amp;token=<?php $this->helpbase->common->token_echo(); ?>" onclick="return hb_confirmExecute('<?php echo _('Are you sure you want to delete selected article?'); ?>');"><img src="../img/delete.png" width="16" height="16" alt="<?php echo _('Delete'); ?>" title="<?php echo _('Delete'); ?>" <?php echo $style; ?> /></a>&nbsp;
                                        </td>
                                    </tr>
<?php
                    $j++;
                } // End while
?>
                                </table>
                            </div>
<?php
            }

            echo '
                            &nbsp;<br />&nbsp;';

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars(array('hide', 'manage_cat', 'edit_article'));

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function import_article() {
            global $hesk_settings;

            $_SESSION['hide'] = array(
                'treemenu' => 1,
                //'new_article' => 1,
                'new_category' => 1,
            );

            $_SESSION['KB_CATEGORY'] = 1;

            // Get ticket ID
            $trackingID = $this->helpbase->common->cleanID();
            if (empty($trackingID)) {
                return false;
            }

            // Get ticket info
            $res = $this->helpbase->database->query("SELECT `id`,`category`,`subject`,`message`,`owner` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE `trackid`='" . $this->helpbase->database->escape($trackingID) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($res) != 1) {
                return false;
            }
            $ticket = $this->helpbase->database->fetchAssoc($res);

            // Permission to view this ticket?
            if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && !$this->helpbase->admin->checkPermission('can_view_ass_others', 0)) {
                return false;
            }

            if (!$ticket['owner'] && !$this->helpbase->admin->checkPermission('can_view_unassigned', 0)) {
                return false;
            }

            // Is this user allowed to view tickets inside this category?
            if (!$this->helpbase->admin->okCategory($ticket['category'], 0)) {
                return false;
            }

            // Set article contents
            if ($hesk_settings['kb_wysiwyg']) {
                // With WYSIWYG editor
                $_SESSION['new_article'] = array(
                    'html'      => 1,
                    'subject'   => $ticket['subject'],
                    'content'   => $this->helpbase->common->htmlspecialchars($ticket['message']),
                );
            } else {
                // Without WYSIWYG editor *
                $_SESSION['new_article'] = array(
                    'html'      => 0,
                    'subject'   => $ticket['subject'],
                    'content'   => $this->helpbase->common->msgToPlain($ticket['message']),
                );
            }

            // Get messages from replies to the ticket
            $res = $this->helpbase->database->query("SELECT `message` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "replies` WHERE `replyto`='" . intval($ticket['id']) . "' ORDER BY `id` ASC");

            while ($reply = $this->helpbase->database->fetchAssoc($res)) {
                if ($hesk_settings['kb_wysiwyg']) {
                    $_SESSION['new_article']['content'] .= "<br /><br />" . $this->helpbase->common->htmlspecialchars($reply['message']);
                } else {
                    $_SESSION['new_article']['content'] .= "\n\n" . $this->helpbase->common->msgToPlain($reply['message']);
                }
            }

            $this->helpbase->common->process_messages(_('You are importing a <i>private ticket</i> into a <i>public article</i>.<br /><br />Make sure you delete any private or sensitive information from the article subject and message!'), 'NOREDIRECT', 'NOTICE');
        }

        private function add_article() {
            global $hesk_settings;

            $_SESSION['hide'] = array(
                'treemenu' => 1,
                //'new_article' => 1,
                'new_category' => 1,
            );

            $_SESSION['KB_CATEGORY'] = intval($this->helpbase->common->_get('catid', 1));
        }

        private function add_category() {
            global $hesk_settings;

            $_SESSION['hide'] = array(
                'treemenu' => 1,
                'new_article' => 1,
                //'new_category' => 1,
                'cat_treemenu' => 1,
            );

            $_SESSION['KB_CATEGORY'] = intval($this->helpbase->common->_get('parent', 1));
        }

        private function remove_kb_att() {
            global $hesk_settings;

            // A security check
            $this->helpbase->common->token_check();

            $att_id = intval($this->helpbase->common->_get('kb_att')) or $this->helpbase->common->_error(_('Invalid attachment ID!'));
            $id = intval($this->helpbase->common->_get('id', 1));

            // Get attachment details
            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` WHERE `att_id`='" . intval($att_id) . "'");

            // Does the attachment exist?
            if ($this->helpbase->database->numRows($res) != 1) {
                $this->helpbase->common->process_messages(_('Invalid attachment ID!'), 'manage_knowledgebase.php');
            }

            $att = $this->helpbase->database->fetchAssoc($res);

            // Delete the file if it exists
            hesk_unlink($this->helpbase->dir . $hesk_settings['attach_dir'] . '/' . $att['saved_name']);

            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` WHERE `att_id`='" . intval($att_id) . "'");

            $res = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `id`='" . intval($id) . "'");
            $art = $this->helpbase->database->fetchAssoc($res);

            // Make log entry
            $revision = sprintf(_('<li class="smaller">%s | attachment %s deleted by %s</li>'), $this->helpbase->common->_date(), $att['real_name'], $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

            // Remove attachment from article
            $art['attachments'] = str_replace($att_id . '#' . $att['real_name'] . ',', '', $art['attachments']);

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `attachments`='" . $this->helpbase->database->escape($art['attachments']) . "', `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "') WHERE `id`='" . intval($id) . "' LIMIT 1");

            $this->helpbase->common->process_messages(_('Selected attachment has been removed'), 'manage_knowledgebase.php?a=edit_article&id=' . $id, 'SUCCESS');
        }

        private function edit_category() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $_SESSION['hide'] = array(
                'article_list' => 1,
            );

            $hesk_error_buffer = array();

            $catid  = intval($this->helpbase->common->_post('catid')) or $this->helpbase->common->_error('Invalid category');
            $title  = $this->helpbase->common->_input($this->helpbase->common->_post('title')) or $hesk_error_buffer[] = _('Enter category title!');
            $parent = intval($this->helpbase->common->_post('parent', 1));
            $type   = empty($_POST['type']) ? 0 : 1;

            /* Category can't be it's own parent */
            if ($parent == $catid) {
                $hesk_error_buffer[] = _('Category can\'t be it\'s own parent category!');
            }

            /* Any errors? */
            if (count($hesk_error_buffer)) {
                $_SESSION['manage_cat'] = array(
                    'type' => $type,
                    'parent' => $parent,
                    'title' => $title,
                );

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, './manage_knowledgebase.php?a=manage_cat&catid=' . $catid);
            }

            /* Delete category or just update it? */
            if ($this->helpbase->common->_post('dodelete') == 'Y') {
                // Delete contents
                if ($this->helpbase->common->_post('movearticles') == 'N') {
                    // Delete all articles and all subcategories
                    $this->delete_category_recursive($catid);
                }
                // Move contents
                else {
                    // -> Update category of articles in the category we are deleting
                    $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `catid`=" . intval($parent) . " WHERE `catid`='" . intval($catid) . "'");

                    // -> Update parent category of subcategories
                    $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `parent`=" . intval($parent) . " WHERE `parent`='" . intval($catid) . "'");

                    // -> Update article counts to make sure they are correct
                    $this->update_count();
                }

                // Now delete the category
                $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` WHERE `id`='" . intval($catid) . "' LIMIT 1");

                $_SESSION['hide'] = array(
                    //'treemenu'    => 1,
                    'new_article'   => 1,
                    'new_category'  => 1,
                );

                $this->helpbase->common->process_messages(_('The selected knowledgebase category has been deleted.'), './manage_knowledgebase.php', 'SUCCESS');
            }

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `name`='" . $this->helpbase->database->escape($title) . "',`parent`=" . intval($parent) . ",`type`='" . intval($type) . "' WHERE `id`='" . intval($catid) . "' LIMIT 1");

            unset($_SESSION['hide']);

            $this->helpbase->common->process_messages(_('Your changes to the selected category have been saved successfully'), './manage_knowledgebase.php?a=manage_cat&catid=' . $catid, 'SUCCESS');
        }

        private function save_article() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $hesk_error_buffer = array();

            $id         = intval($this->helpbase->common->_post('id')) or $this->helpbase->common->_error(_('Missing or invalid article ID!'));
            $catid      = intval($this->helpbase->common->_post('catid', 1));
            $type       = intval($this->helpbase->common->_post('type'));
            $type       = ($type < 0 || $type > 2) ? 0 : $type;
            $html       = $hesk_settings['kb_wysiwyg'] ? 1 : (empty($_POST['html']) ? 0 : 1);
            $now        = $this->helpbase->common->_date();
            $old_catid  = intval($this->helpbase->common->_post('old_catid'));
            $old_type   = intval($this->helpbase->common->_post('old_type'));
            $old_type   = ($old_type < 0 || $old_type > 2) ? 0 : $old_type;

            $subject = $this->helpbase->common->_input($this->helpbase->common->_post('subject')) or $hesk_error_buffer[] = _('Enter article subject!');

            if ($html) {
                if (empty($_POST['content'])) {
                    $hesk_error_buffer[] = _('Write article contents!');
                }

                $content = $this->helpbase->admin->getHTML($this->helpbase->common->_post('content'));
            } else {
                $content = $this->helpbase->common->_input($this->helpbase->common->_post('content')) or $hesk_error_buffer[] = _('Write article contents!');
                $content = nl2br($content);
                $content = $this->helpbase->common->makeURL($content);
            }

            $sticky = isset($_POST['sticky']) ? 1 : 0;

            $keywords = $this->helpbase->common->_input($this->helpbase->common->_post('keywords'));

            $extra_sql = '';
            if ($this->helpbase->common->_post('resetviews') == 'Y') {
                $extra_sql .= ',`views`=0 ';
            }
            if ($this->helpbase->common->_post('resetvotes') == 'Y') {
                $extra_sql .= ',`votes`=0, `rating`=0 ';
            }

            /* Article attachments */
            $this->helpbase->article_attach = true;

            $this->helpbase->load_posting_functions();

            require_once($this->helpbase->includes . 'attachments.inc.php');
            $attachments = array();
            for ($i = 1; $i <= 3; $i++) {
                $att = hesk_uploadFile($i);
                if (!empty($att)) {
                    $attachments[$i] = $att;
                }
            }
            $myattachments = '';

            /* Any errors? */
            if (count($hesk_error_buffer)) {
                if ($hesk_settings['attachments']['use']) {
                    hesk_removeAttachments($attachments);
                }

                $_SESSION['edit_article'] = array(
                    'type'          => $type,
                    'html'          => $html,
                    'subject'       => $subject,
                    'content'       => $this->helpbase->common->_input($this->helpbase->common->_post('content')),
                    'keywords'      => $keywords,
                    'catid'         => $catid,
                    'sticky'        => $sticky,
                    'resetviews'    => (isset($_POST['resetviews']) ? 'Y' : 0),
                    'resetvotes'    => (isset($_POST['resetvotes']) ? 'Y' : 0),
                );

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, './manage_knowledgebase.php?a=edit_article&id=' . $id);
            }

            /* Add to database */
            if (!empty($attachments)) {
                foreach ($attachments as $myatt) {
                    $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` (`saved_name`,`real_name`,`size`) VALUES ('" . $this->helpbase->database->escape($myatt['saved_name']) . "', '" . $this->helpbase->database->escape($myatt['real_name']) . "', '" . intval($myatt['size']) . "')");
                    $myattachments .= $this->helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
                }

                $extra_sql .= ", `attachments` = CONCAT(`attachments`, '" . $myattachments . "') ";
            }

            /* Update article in the database */
            $revision = sprintf(_('<li class="smaller">%s | modified by %s</li>'), $now, $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET
                                                `catid`=" . intval($catid) . ",
                                                `subject`='" . $this->helpbase->database->escape($subject) . "',
                                                `content`='" . $this->helpbase->database->escape($content) . "',
                                                `keywords`='" . $this->helpbase->database->escape($keywords) . "' $extra_sql ,
                                                `type`='" . intval($type) . "',
                                                `html`='" . intval($html) . "',
                                                `sticky`='" . intval($sticky) . "',
                                                `history`=CONCAT(`history`,'" . $this->helpbase->database->escape($revision) . "')
                                              WHERE `id`='" . intval($id) . "' LIMIT 1");

            $_SESSION['artord'] = $id;

            // Update proper category article count
            // (just do them all to be sure, don't compliate...)
            $this->update_count();

            // Update article order
            $this->update_article_order($catid);

            $this->helpbase->common->process_messages(_('Your changes to the selected article have been saved successfully'), './manage_knowledgebase.php?a=manage_cat&catid=' . $catid, 'SUCCESS');
        }

        private function edit_article() {
            global $hesk_settings;

            $hesk_error_buffer = array();

            $id = intval($this->helpbase->common->_get('id')) or $this->helpbase->common->process_messages(_('Missing or invalid article ID!'), './manage_knowledgebase.php');

            /* Get article details */
            $result = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `id`='" . intval($id) . "' LIMIT 1");
            if ($this->helpbase->database->numRows($result) != 1) {
                $this->helpbase->common->process_messages(_('Missing or invalid article ID!'), './manage_knowledgebase.php');
            }
            $article = $this->helpbase->database->fetchAssoc($result);

            if ($hesk_settings['kb_wysiwyg'] || $article['html']) {
                $article['content'] = $this->helpbase->common->htmlspecialchars($article['content']);
            } else {
                $article['content'] = $this->helpbase->common->msgToPlain($article['content']);
            }

            $catid = $article['catid'];

            if (isset($_SESSION['edit_article'])) {
                $_SESSION['edit_article']   = $this->helpbase->common->stripArray($_SESSION['edit_article']);
                $article['type']            = $_SESSION['edit_article']['type'];
                $article['html']            = $_SESSION['edit_article']['html'];
                $article['subject']         = $_SESSION['edit_article']['subject'];
                $article['content']         = $_SESSION['edit_article']['content'];
                $article['keywords']        = $_SESSION['edit_article']['keywords'];
                $article['catid']           = $_SESSION['edit_article']['catid'];
                $article['sticky']          = $_SESSION['edit_article']['sticky'];
            }

            /* Get categories */
            $result = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `parent` ASC, `cat_order` ASC');
            $kb_cat = array();

            while ($cat = $this->helpbase->database->fetchAssoc($result)) {
                $kb_cat[] = $cat;
                if ($cat['id'] == $article['catid']) {
                    $this_cat = $cat;
                    $this_cat['parent'] = $article['catid'];
                }
            }

            /* Translate main category "Knowledgebase" if needed */
            $kb_cat[0]['name'] = _('Knowledgebase');

            require($this->helpbase->includes . 'treemenu/TreeMenu.php');

            $icon           = $this->helpbase->url . 'img/folder.gif';
            $expandedIcon   = $this->helpbase->url . 'img/folder-expanded.gif';
            $menu           = new HTML_TreeMenu();

            $thislevel = array('0');
            $nextlevel = array();
            $i = 1;
            $j = 1;

            while (count($kb_cat) > 0) {
                foreach ($kb_cat as $k => $cat) {
                    if (in_array($cat['parent'], $thislevel)) {
                        $up = $cat['parent'];
                        $my = $cat['id'];
                        $type = $cat['type'] ? '*' : '';

                        $text_short = $cat['name'] . $type . ' (' . $cat['articles'] . ', ' . $cat['articles_private'] . ', ' . $cat['articles_draft'] . ')';

                        if (isset($node[$up])) {
                            $node[$my] = &$node[$up]->addItem(new HTML_TreeNode(array('hesk_parent' => $this_cat['parent'], 'text' => 'Text', 'text_short' => $text_short, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true)));
                        } else {
                            $node[$my] = new HTML_TreeNode(array('hesk_parent' => $this_cat['parent'], 'text' => 'Text', 'text_short' => $text_short, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true));
                        }

                        $nextlevel[] = $cat['id'];
                        $j++;
                        unset($kb_cat[$k]);
                    }
                }

                $thislevel = $nextlevel;
                $nextlevel = array();

                /* Break after 20 recursions to avoid hang-ups in case of any problems */

                if ($i > 20) {
                    break;
                }
                $i++;
            }

            $menu->addItem($node[1]);

            // Create the presentation class
            $this->listBox = & $this->helpbase->common->ref_new(new HTML_TreeMenu_Listbox($menu));

            /* Print header */
            $this->helpbase->header->render();

            /* Print main manage users page */
            $this->helpbase->admin_nav->render();
?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="smaller">
                                    <a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt;
                                    <a href="manage_knowledgebase.php?a=manage_cat&amp;catid=<?php echo $catid; ?>" class="smaller"><?php echo _('Manage knowledgebase category'); ?></a> &gt; <?php echo _('Edit article'); ?>
                                </span>
                                <br />&nbsp;
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
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
                                            <div align="center">
                                                <table border="0">
                                                    <tr>
                                                        <td>
                                                            <h3 align="center"><?php echo _('Edit article'); ?></h3>
                                                            <br />
<?php
            if ($hesk_settings['kb_wysiwyg']) {
?>
                                                            <script type="text/javascript">
                                                                tinyMCE.init({
                                                                    mode: "exact",
                                                                    elements: "content",
                                                                    theme: "advanced",
                                                                    convert_urls: false,
                                                                    theme_advanced_buttons1: "cut,copy,paste,|,undo,redo,|,formatselect,fontselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull",
                                                                    theme_advanced_buttons2: "sub,sup,|,charmap,|,bullist,numlist,|,outdent,indent,insertdate,inserttime,preview,|,forecolor,backcolor,|,hr,removeformat,visualaid,|,link,unlink,anchor,image,cleanup,code",
                                                                    theme_advanced_buttons3: "",
                                                                    theme_advanced_toolbar_location: "top",
                                                                    theme_advanced_toolbar_align: "left",
                                                                    theme_advanced_statusbar_location: "bottom",
                                                                    theme_advanced_resizing: true
                                                                });
                                                            </script>
<?php
            }
?>
                                                            <form action="manage_knowledgebase.php" method="post" name="form1" enctype="multipart/form-data">
                                                                <table border="0">
                                                                    <tr>
                                                                        <td><b><?php echo _('Category'); ?>:</b></td>
                                                                        <td><select name="catid"><?php $this->listBox->printMenu() ?></select></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td valign="top"><b><?php echo _('Type'); ?>:</b></td>
                                                                        <td>
<?php
            $checked = '';
            if ($article['type'] == 0) {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="radio" name="type" value="0" <?php echo $checked; ?> /> <b><i><?php echo _('Published'); ?></i></b>
                                                                            </label>
                                                                            <br />
                                                                            <?php echo _('The article is viewable to everyone in the knowledgebase.'); ?>
                                                                            <br />&nbsp;<br />
<?php
            $checked = '';
            if ($article['type'] == 1) {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="radio" name="type" value="1" <?php echo $checked; ?> /> <b><i><?php echo _('Private'); ?></i></b>
                                                                            </label>
                                                                            <br />
                                                                            <?php echo _('Private articles can only be read by staff.'); ?>
                                                                            <br />&nbsp;<br />
<?php
            $checked = '';
            if ($article['type'] == 2) {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="radio" name="type" value="2" <?php echo $checked; ?> /> <b><i><?php echo _('Draft'); ?></i></b>
                                                                            </label>
                                                                            <br />
                                                                            <?php echo _('The article is saved but not yet published. It can only be read by staff<br /> who has permission to manage knowledgebase articles.'); ?><br />&nbsp;
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><b><?php echo _('Subject'); ?>:</b></td>
                                                                        <td><input type="text" name="subject" size="70" maxlength="255" value="<?php echo $article['subject']; ?>" /></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td valign="top"><b><?php echo _('Options'); ?>:</b></td>
                                                                        <td>
<?php
            $checked = '';
            if ($article['sticky']) {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="checkbox" name="sticky" value="Y" <?php echo $checked; ?> /> <i><?php echo _('Make this article &quot;Sticky&quot;'); ?></i>
                                                                            </label>
                                                                            <br />
<?php
            $checked = '';
            if (isset($_SESSION['edit_article']['resetviews']) && $_SESSION['edit_article']['resetviews'] == 'Y') {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="checkbox" name="resetviews" value="Y" <?php echo $checked; ?> /> <i><?php echo _('Reset views'); ?></i>
                                                                            </label>
                                                                            <br />
<?php
            $checked = '';
            if (isset($_SESSION['edit_article']['resetvotes']) && $_SESSION['edit_article']['resetvotes'] == 'Y') {
                $checked = 'checked="checked"';
            }
?>
                                                                            <label>
                                                                                <input type="checkbox" name="resetvotes" value="Y" <?php $checked; ?> /> <i><?php echo _('Reset votes (ratings)'); ?></i>
                                                                            </label>
                                                                        </td>
                                                                    </tr>
                                                                </table>
<?php
            $displayType = $hesk_settings['kb_wysiwyg'] ? 'none' : 'block';
            $displayWarn = $article['html'] ? 'block' : 'none';
?>
                                                                <p><b><?php echo _('Contents'); ?>:</b></p>
                                                                <span id="contentType" style="display:<?php echo $displayType; ?>">
<?php
            $checked = '';
            if (!$article['html']) {
                $checked = 'checked="checked"';
            }
?>
                                                                    <label>
                                                                        <input type="radio" name="html" value="0" <?php echo $checked; ?> onclick="javascript:document.getElementById('kblinks').style.display = 'none'" /> <?php echo _('This is plain text (links will be clickable)'); ?>
                                                                    </label>
                                                                    <br />
<?php
            $checked = '';
            if ($article['html']) {
                $checked = 'checked="checked"';
            }
?>
                                                                    <label>
                                                                        <input type="radio" name="html" value="1" <?php echo $checked; ?> onclick="javascript:document.getElementById('kblinks').style.display = 'block'" /> <?php echo _('This is HTML code (I will enter valid (X)HTML code)'); ?>
                                                                    </label>
                                                                    <span id="kblinks" style="display:<?php echo $displayWarn; ?>"><i><?php echo _('<i><span class="notice"><b>Warning!</b></span><br />Enter valid code without &lt;head&gt; and &lt;body&gt; tags, just content!</i>'); ?></i></span>
                                                                </span>
                                                                <p>
                                                                    <textarea name="content" rows="25" cols="70" id="content"><?php echo $article['content']; ?></textarea>
                                                                </p>
                                                                <p>&nbsp;
                                                                    <br /><b><?php echo _('Keywords'); ?>:</b> <?php echo _('(optional - separate by space, comma or new line)'); ?>
                                                                </p>
                                                                <p>
                                                                    <textarea name="keywords" rows="3" cols="70" id="keywords"><?php echo $article['keywords']; ?></textarea>
                                                                </p>
                                                                <p>&nbsp;
                                                                    <br />
                                                                    <b><?php echo _('Attachments'); ?></b> (<a href="Javascript:void(0)" onclick="Javascript:hb_window('../file_limits.php', 250, 500); return false;"><?php echo _('File upload limits'); ?></a>)
                                                                    <br />
<?php
            if (!empty($article['attachments'])) {
                $att = explode(',', substr($article['attachments'], 0, -1));
                foreach ($att as $myatt) {
                    list($att_id, $att_name) = explode('#', $myatt);

                    $tmp = 'White';
                    $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';

                    echo '
                                                                    <a href="manage_knowledgebase.php?a=remove_kb_att&amp;id=' . $id . '&amp;kb_att=' . $att_id . '&amp;token=' . $this->helpbase->common->token_echo(0) . '" onclick="return hb_confirmExecute(\'' . _('Delete selected attachment?') . '\');"><img src="../img/delete.png" width="16" height="16" alt="' . _('Delete this attachment') . '" title="' . _('Delete this attachment') . '" ' . $style . ' /></a> ';
                    echo '
                                                                    <a href="../download_attachment.php?kb_att=' . $att_id . '"><img src="../img/clip.png" width="16" height="16" alt="' . _('Download') . ' ' . $att_name . '" title="' . _('Download') . ' ' . $att_name . '" ' . $style . ' /></a> ';
                    echo '
                                                                    <a href="../download_attachment.php?kb_att=' . $att_id . '">' . $att_name . '</a><br />';
                }
                echo '
                                                                    <br />';
            }
?>
                                                                    <input type="file" name="attachment[1]" size="50" /><br />
                                                                    <input type="file" name="attachment[2]" size="50" /><br />
                                                                    <input type="file" name="attachment[3]" size="50" /><br />&nbsp;
                                                                </p>
                                                                <p align="center"><input type="hidden" name="a" value="save_article" />
                                                                    <input type="hidden" name="id" value="<?php echo $id; ?>" />
                                                                    <input type="hidden" name="old_type" value="<?php echo $article['type']; ?>" />
                                                                    <input type="hidden" name="old_catid" value="<?php echo $catid; ?>" />
                                                                    <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                                    <input type="submit" value="<?php echo _('Save article'); ?>" class="button blue small" />
                                                                    | <a href="manage_knowledgebase.php?a=manage_cat&amp;catid=<?php echo $catid; ?>"><?php echo _('Cancel'); ?></a>
                                                                </p>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
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
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                        <td class="roundcornerstop"></td>
                                        <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                    </tr>
                                    <tr>
                                        <td class="roundcornersleft">&nbsp;</td>
                                        <td>

                                            <h3><?php echo _('Revision history'); ?></h3>

                                            <ul><?php echo $article['history']; ?></ul>

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
            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars('edit_article');

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function manage_category() {
            global $hesk_settings;

            $catid = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error('Invalid category');

            $result = $this->helpbase->database->query('SELECT * FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `parent` ASC, `cat_order` ASC');
            $kb_cat = array();

            while ($cat = $this->helpbase->database->fetchAssoc($result)) {
                $kb_cat[] = $cat;
                if ($cat['id'] == $catid) {
                    $this_cat = $cat;
                }
            }

            if (isset($_SESSION['manage_cat'])) {
                $_SESSION['manage_cat'] = $this->helpbase->common->stripArray($_SESSION['manage_cat']);
                $this_cat['type']       = $_SESSION['manage_cat']['type'];
                $this_cat['parent']     = $_SESSION['manage_cat']['parent'];
                $this_cat['name']       = $_SESSION['manage_cat']['title'];
            }

            /* Translate main category "Knowledgebase" if needed */
            $kb_cat[0]['name'] = _('Knowledgebase');

            require($this->helpbase->includes . 'treemenu/TreeMenu.php');
            $icon = $this->helpbase->url . 'img/folder.gif';
            $expandedIcon = $this->helpbase->url . 'img/folder-expanded.gif';
            $menu = new HTML_TreeMenu();

            $thislevel = array('0');
            $nextlevel = array();
            $i = 1;
            $j = 1;

            while (count($kb_cat) > 0) {
                foreach ($kb_cat as $k => $cat) {
                    if (in_array($cat['parent'], $thislevel)) {
                        $up = $cat['parent'];
                        $my = $cat['id'];
                        $type = $cat['type'] ? '*' : '';

                        $text_short = $cat['name'] . $type . ' (' . $cat['articles'] . ', ' . $cat['articles_private'] . ', ' . $cat['articles_draft'] . ')';

                        if (isset($node[$up])) {
                            $node[$my] = &$node[$up]->addItem(new HTML_TreeNode(array('hesk_parent' => $this_cat['parent'], 'text' => 'Text', 'text_short' => $text_short, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true)));
                        } else {
                            $node[$my] = new HTML_TreeNode(array('hesk_parent' => $this_cat['parent'], 'text' => 'Text', 'text_short' => $text_short, 'hesk_catid' => $cat['id'], 'hesk_select' => 'option' . $j, 'icon' => $icon, 'expandedIcon' => $expandedIcon, 'expanded' => true));
                        }

                        $nextlevel[] = $cat['id'];
                        $j++;
                        unset($kb_cat[$k]);
                    }
                }

                $thislevel = $nextlevel;
                $nextlevel = array();

                /* Break after 20 recursions to avoid hang-ups in case of any problems */

                if ($i > 20) {
                    break;
                }
                $i++;
            }

            $menu->addItem($node[1]);

            // Create the presentation class
            $this->listBox = & $this->helpbase->common->ref_new(new HTML_TreeMenu_Listbox($menu));

            /* Print header */
            $this->helpbase->header->render();

            /* Print main manage users page */
            $this->helpbase->admin_nav->render();
?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="smaller"><a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt; <?php echo _('Manage knowledgebase category'); ?></span>

                                <!-- SUB NAVIGATION -->
                                <?php $this->show_subnav('', $catid); ?>
                                <!-- SUB NAVIGATION -->
<?php
            if (!isset($_SESSION['hide']['article_list'])) {
?>
                                <h3><?php echo _('Category'); ?>: <span class="black"><?php echo $this_cat['name']; ?></span></h3>
                                &nbsp;
                                <br />
<?php
                $result = $this->helpbase->database->query("SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$catid}' ORDER BY `sticky` DESC, `art_order` ASC");
                $num = $this->helpbase->database->numRows($result);

                if ($num == 0) {
                    echo '<p>' . _('There are no articles in this category.') . ' &nbsp; <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><b>' . _('Insert an Article') . '</b></a></p>';
                } else {
                    /* Get number of sticky articles */
                    $res2 = $this->helpbase->database->query("SELECT COUNT(*) FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$catid}' AND `sticky` = '1' ");
                    $num_sticky = $this->helpbase->database->result($res2);

                    $num_nosticky = $num - $num_sticky;
?>
                                <div style="float:right">
                                    <?php echo '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><img src="../img/add_article.png" width="16" height="16" alt="' . _('Insert an Article') . '" title="' . _('Insert an Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><b>' . _('Insert an Article') . '</b></a>'; ?>
                                </div>
                                <h3 style="padding-bottom:5px;">&raquo; <?php echo _('Articles in this category'); ?></h3>
                                <div align="center">
                                <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                                    <tr>
                                        <th class="admin_white">&nbsp;</th>
                                        <th class="admin_white"><b><i><?php echo _('Subject'); ?></i></b></th>
                                        <th class="admin_white"><b><i><?php echo _('Type'); ?></i></b></th>
                                        <th class="admin_white"><b><i><?php echo _('Views'); ?></i></b></th>
<?php
                    if ($hesk_settings['kb_rating']) {
?>
                                        <th class="admin_white" style="white-space:nowrap" nowrap="nowrap" width="130"><b><i><?php echo _('Rating') . ' (' . _('votes') . ')'; ?></i></b></th>
<?php
                    }
?>
                                        <th class="admin_white" style="width:120px"><b><i>&nbsp;<?php echo _('Options'); ?>&nbsp;</i></b></th>
                                    </tr>
<?php
                    $i = 1;
                    $j = 1;
                    $k = 1;
                    $previous_sticky = 1;
                    $num = $num_sticky;

                    while ($article = $this->helpbase->database->fetchAssoc($result)) {

                        if ($previous_sticky != $article['sticky']) {
                            $k = 1;
                            $num = $num_nosticky;
                            $previous_sticky = $article['sticky'];
                        }

                        if (isset($_SESSION['artord']) && $article['id'] == $_SESSION['artord']) {
                            $color = 'admin_green';
                            unset($_SESSION['artord']);
                        } elseif ($article['sticky']) {
                            $color = 'admin_yellow';
                        } else {
                            $color = $i ? 'admin_white' : 'admin_gray';
                        }

                        $tmp = $i ? 'White' : 'Blue';
                        $style = 'class="option' . $tmp . 'OFF" onmouseover="this.className=\'option' . $tmp . 'ON\'" onmouseout="this.className=\'option' . $tmp . 'OFF\'"';
                        $i = $i ? 0 : 1;

                        switch ($article['type']) {
                            case '1':
                                $type = '
                                    <span class="kb_private">' . _('Private') . '</span>';
                                break;
                            case '2':
                                $type = '
                                    <span class="kb_draft">' . _('Draft') . '</span>';
                                break;
                            default:
                                $type = '
                                    <span class="kb_published">' . _('Published') . '</span>';
                        }

                        if ($hesk_settings['kb_rating']) {
                            $alt = $article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $article['rating'])) : _('Article not rated yet');
                            $rat = '
                                    <td class="' . $color . '" style="white-space:nowrap;"><img src="../img/star_' . ($this->helpbase->common->round_to_half($article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" title="' . $alt . '" border="0" style="vertical-align:text-bottom" /> (' . $article['votes'] . ') </td>';
                        } else {
                            $rat = '';
                        }
?>
                                    <tr>
                                        <td class="<?php echo $color; ?>"><?php echo $j; ?>.</td>
                                        <td class="<?php echo $color; ?>"><?php echo $article['subject']; ?></td>
                                        <td class="<?php echo $color; ?>"><?php echo $type; ?></td>
                                        <td class="<?php echo $color; ?>"><?php echo $article['views']; ?></td>
                                        <?php echo $rat; ?>
                                        <td class="<?php echo $color; ?>" style="text-align:center; white-space:nowrap;">
<?php
                        if ($num > 1) {
                            if ($k == 1) {
?>
                                            <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />
                                            <a href="manage_knowledgebase.php?a=order_article&amp;id=<?php echo $article['id']; ?>&amp;catid=<?php echo $catid; ?>&amp;move=15&amp;token=<?php $this->helpbase->common->token_echo(); ?>"><img src="../img/move_down.png" width="16" height="16" alt="<?php echo $move_down; ?>" title="<?php echo $move_down; ?>" <?php echo $style; ?> /></a>
<?php
                            } elseif ($k == $num) {
?>
                                            <a href="manage_knowledgebase.php?a=order_article&amp;id=<?php echo $article['id']; ?>&amp;catid=<?php echo $catid; ?>&amp;move=-15&amp;token=<?php $this->helpbase->common->token_echo(); ?>"><img src="../img/move_up.png" width="16" height="16" alt="<?php echo $move_up; ?>" title="<?php echo $move_up; ?>" <?php echo $style; ?> /></a>
                                            <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;" />
<?php
                            } else {
?>
                                            <a href="manage_knowledgebase.php?a=order_article&amp;id=<?php echo $article['id']; ?>&amp;catid=<?php echo $catid; ?>&amp;move=-15&amp;token=<?php $this->helpbase->common->token_echo(); ?>"><img src="../img/move_up.png" width="16" height="16" alt="<?php echo $move_up; ?>" title="<?php echo $move_up; ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=order_article&amp;id=<?php echo $article['id']; ?>&amp;catid=<?php echo $catid; ?>&amp;move=15&amp;token=<?php $this->helpbase->common->token_echo(); ?>"><img src="../img/move_down.png" width="16" height="16" alt="<?php echo $move_down; ?>" title="<?php echo $move_down; ?>" <?php echo $style; ?> /></a>
<?php
                            }
                        } elseif ($num_sticky > 1 || $num_nosticky > 1) {
                            echo '
                                            <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;vertical-align:text-bottom;" /> <img src="../img/blank.gif" width="16" height="16" alt="" style="padding:3px;border:none;vertical-align:text-bottom;" />';
                        }

                        $stat = '';
                        if (!$article['sticky']) {
                            $stat = '_off';
                        }
?>
                                            <a href="manage_knowledgebase.php?a=sticky&amp;s=<?php echo $article['sticky'] ? 0 : 1 ?>&amp;id=<?php echo $article['id']; ?>&amp;catid=<?php echo $catid; ?>&amp;token=<?php $this->helpbase->common->token_echo(); ?>"><img src="../img/sticky<?php echo $stat; ?>.png" width="16" height="16" alt="<?php echo $article['sticky'] ? _('Change article to &quot;Normal&quot;') : _('Change article to &quot;Sticky&quot;'); ?>" title="<?php echo $article['sticky'] ? _('Change article to &quot;Sticky&quot;') : _('Change article to &quot;Sticky&quot;'); ?>" <?php echo $style; ?> /></a>
<?php
                        $param = '';
                        if ($article['type'] == 2) {
                            $param = '&amp;draft=1';
                        }
?>
                                            <a href="knowledgebase_private.php?article=<?php echo $article['id']; ?>&amp;back=1<?php echo $param; ?>" target="_blank"><img src="../img/article_text.png" width="16" height="16" alt="<?php echo _('View this article'); ?>" title="<?php echo _('View this article'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=edit_article&amp;id=<?php echo $article['id']; ?>"><img src="../img/edit.png" width="16" height="16" alt="<?php echo _('Edit'); ?>" title="<?php echo _('Edit'); ?>" <?php echo $style; ?> /></a>
                                            <a href="manage_knowledgebase.php?a=remove_article&amp;id=<?php echo $article['id']; ?>&amp;token=<?php $this->helpbase->common->token_echo(); ?>" onclick="return hb_confirmExecute('<?php echo _('Are you sure you want to delete selected article?'); ?>');"><img src="../img/delete.png" width="16" height="16" alt="<?php echo _('Delete'); ?>" title="<?php echo _('Delete'); ?>" <?php echo $style; ?> /></a>&nbsp;</td>
                                        </tr>
<?php
                        $j++;
                        $k++;
                    } // End while
?>
                                    </table>
                                </div>
<?php
                }
            } // END if hide article list

            /* Manage Category (except the default one) */
            if ($catid != 1) {
?>
                                &nbsp;<br />
                                &nbsp;<br />
                                <div style="float:right">
                                    <?php echo '<a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '"><img src="../img/add_category.png" width="16" height="16" alt="' . _('New Category') . '" title="' . _('New Category') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '"><b>' . _('New Category') . '</b></a>'; ?>
                                </div>
                                <h3 style="padding-bottom:5px;">&raquo; <?php echo _('Category Settings'); ?></h3>
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                        <td class="roundcornerstop"></td>
                                        <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                    </tr>
                                    <tr>
                                        <td class="roundcornersleft">&nbsp;</td>
                                        <td>
                                            <form action="manage_knowledgebase.php" method="post" name="form1" onsubmit="Javascript:return hb_deleteIfSelected('dodelete', '<?php echo addslashes(_('Are you sure you want to delete this category?')); ?>')">
                                                <div align="center">
                                                    <table border="0">
                                                        <tr>
                                                            <td>
                                                                <table border="0">
                                                                    <tr>
                                                                        <td><b><?php echo _('Category title'); ?>:</b></td>
                                                                        <td><input type="text" name="title" size="70" maxlength="255" value="<?php echo $this_cat['name']; ?>" /></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><b><?php echo _('Parent category'); ?>:</b></td>
                                                                        <td><select name="parent"><?php $this->listBox->printMenu(); ?></select></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td valign="top"><b><?php echo _('Type'); ?>:</b></td>
                                                                        <td>
<?php
                $checked = '';
                if (!$this_cat['type']) {
                    $checked = 'checked="checked"';
                }
?>
                                                                            <label>
                                                                                <input type="radio" name="type" value="0" <?php echo $checked; ?> /> <b><i><?php echo _('Published'); ?></i></b>
                                                                            </label>
                                                                            <br />
                                                                            <?php echo _('The category is viewable to everyone in the knowledgebase.'); ?><br />&nbsp;<br />
                                                                            <label>
<?php
                $checked = '';
                if ($this_cat['type']) {
                    $checked = 'checked="checked"';
                }
?>                                                                                
                                                                                <input type="radio" name="type" value="1" <?php echo $checked; ?> /> <b><i><?php echo _('Private'); ?></i></b>
                                                                            </label>
                                                                            <br />
                                                                            <?php echo _('The category can only be read by staff.'); ?><br />&nbsp;
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td valign="top"><b><?php echo _('Options'); ?>:</b></td>
                                                                        <td>
                                                                            <label><input type="checkbox" name="dodelete" id="dodelete" value="Y" onclick="Javascript:hb_toggleLayerDisplay('deleteoptions')" /> <i><?php echo _('Delete category'); ?></i></label>
                                                                            <div id="deleteoptions" style="display: none;">
                                                                                &nbsp;&nbsp;&nbsp;&nbsp;<label><input type="radio" name="movearticles" value="Y" checked="checked" /> <?php echo _('Move articles to parent category'); ?></label><br />
                                                                                &nbsp;&nbsp;&nbsp;&nbsp;<label><input type="radio" name="movearticles" value="N" /> <?php echo _('Delete articles in this category'); ?></label>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <p align="center"><input type="hidden" name="a" value="edit_category" />
                                                    <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                                    <input type="hidden" name="catid" value="<?php echo $catid; ?>" /><input type="submit" value="<?php echo _('Save changes'); ?>" class="button blue small" />
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
            } // END if $catid != 1

            echo '
                                &nbsp;<br />&nbsp;';

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars(array('hide', 'manage_cat', 'edit_article'));

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }

        private function new_category() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check('POST');

            $_SESSION['hide'] = array(
                'treemenu'          => 1,
                'new_article'       => 1,
                //'new_category'    => 1,
            );

            $parent = intval($this->helpbase->common->_post('parent', 1));
            $type = empty($_POST['type']) ? 0 : 1;

            $_SESSION['KB_CATEGORY'] = $parent;
            $_SERVER['PHP_SELF'] = 'manage_knowledgebase.php';

            /* Check that title is valid */
            $title = $this->helpbase->common->_input($this->helpbase->common->_post('title'));
            if (!strlen($title)) {
                $_SESSION['new_category'] = array(
                    'type' => $type,
                );

                $this->helpbase->common->process_messages(_('Enter category title!'), $_SERVER['PHP_SELF']);
            }

            /* Get the latest reply_order */
            $res = $this->helpbase->database->query('SELECT `cat_order` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `cat_order` DESC LIMIT 1');
            $row = $this->helpbase->database->fetchRow($res);
            $my_order = $row[0] + 10;

            $result = $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` (`name`,`parent`,`cat_order`,`type`) VALUES ('" . $this->helpbase->database->escape($title) . "','" . intval($parent) . "','" . intval($my_order) . "','" . intval($type) . "')");

            $_SESSION['newcat'] = $this->helpbase->database->insertID();

            $_SESSION['hide'] = array(
                'treemenu'      => 1,
                'new_article'   => 1,
                //'new_category' => 1,
                'cat_treemenu'  => 1,
            );

            $this->helpbase->common->process_messages(_('A new category has been successfully added to the knowledgebase'), $_SERVER['PHP_SELF'], 'SUCCESS');
        }

        private function new_article() {
            global $hesk_settings;

            /* A security check */
            # $this->helpbase->common->token_check('POST');

            $_SESSION['hide'] = array(
                'treemenu' => 1,
                //'new_article' => 1,
                'new_category' => 1,
            );

            $hesk_error_buffer = array();

            $catid = intval($this->helpbase->common->_post('catid', 1));
            $type = empty($_POST['type']) ? 0 : ($this->helpbase->common->_post('type') == 2 ? 2 : 1);
            $html = $hesk_settings['kb_wysiwyg'] ? 1 : (empty($_POST['html']) ? 0 : 1);
            $now = $this->helpbase->common->_date();

            // Prevent submitting duplicate articles by reloading manage_knowledgebase.php page
            if (isset($_SESSION['article_submitted'])) {
                header('Location:manage_knowledgebase.php?a=manage_cat&catid=' . $catid);
                exit();
            }

            $_SESSION['KB_CATEGORY'] = $catid;

            $subject = $this->helpbase->common->_input($this->helpbase->common->_post('subject')) or $hesk_error_buffer[] = _('Enter article subject!');

            if ($html) {
                if (empty($_POST['content'])) {
                    $hesk_error_buffer[] = _('Write article contents!');
                }

                $content = $this->helpbase->admin->getHTML($this->helpbase->common->_post('content'));
            } else {
                $content = $this->helpbase->common->_input($this->helpbase->common->_post('content')) or $hesk_error_buffer[] = _('Write article contents!');
                $content = nl2br($content);
                $content = $this->helpbase->common->makeURL($content);
            }

            $sticky = isset($_POST['sticky']) ? 1 : 0;

            $keywords = $this->helpbase->common->_input($this->helpbase->common->_post('keywords'));

            /* Article attachments */
            $this->helpbase->article_attach = true;

            $this->helpbase->load_posting_functions();

            require_once($this->helpbase->includes . 'attachments.inc.php');
            $attachments = array();
            for ($i = 1; $i <= 3; $i++) {
                $att = hesk_uploadFile($i);
                if (!empty($att)) {
                    $attachments[$i] = $att;
                }
            }
            $myattachments = '';

            /* Any errors? */
            if (count($hesk_error_buffer)) {
                // Remove any successfully uploaded attachments
                if ($hesk_settings['attachments']['use']) {
                    hesk_removeAttachments($attachments);
                }

                $_SESSION['new_article'] = array(
                    'type'      => $type,
                    'html'      => $html,
                    'subject'   => $subject,
                    'content'   => $this->helpbase->common->_input($this->helpbase->common->_post('content')),
                    'keywords'  => $keywords,
                    'sticky'    => $sticky,
                );

                $tmp = '';
                foreach ($hesk_error_buffer as $error) {
                    $tmp .= "<li>$error</li>\n";
                }
                $hesk_error_buffer = $tmp;

                $hesk_error_buffer = _('Required information missing:') . '<br /><br /><ul>' . $hesk_error_buffer . '</ul>';
                $this->helpbase->common->process_messages($hesk_error_buffer, 'manage_knowledgebase.php');
            }

            $revision = sprintf(_('<li class="smaller">%s | submitted by %s</li>'), $now, $_SESSION['name'] . ' (' . $_SESSION['user'] . ')');

            /* Add to database */
            if (!empty($attachments)) {
                foreach ($attachments as $myatt) {
                    $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` (`saved_name`,`real_name`,`size`) VALUES ('" . $this->helpbase->database->escape($myatt['saved_name']) . "','" . $this->helpbase->database->escape($myatt['real_name']) . "','" . intval($myatt['size']) . "')");
                    $myattachments .= $this->helpbase->database->insertID() . '#' . $myatt['real_name'] . ',';
                }
            }

            /* Get the latest reply_order */
            $res = $this->helpbase->database->query("SELECT `art_order` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='" . intval($catid) . "' AND `sticky` = '" . intval($sticky) . "' ORDER BY `art_order` DESC LIMIT 1");
            $row = $this->helpbase->database->fetchRow($res);
            $my_order = $row[0] + 10;

            /* Insert article into database */
            $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` (`catid`,`dt`,`author`,`subject`,`content`,`keywords`,`type`,`html`,`sticky`,`art_order`,`history`,`attachments`) VALUES (
                                                '" . intval($catid) . "',
                                                NOW(),
                                                '" . intval($_SESSION['id']) . "',
                                                '" . $this->helpbase->database->escape($subject) . "',
                                                '" . $this->helpbase->database->escape($content) . "',
                                                '" . $this->helpbase->database->escape($keywords) . "',
                                                '" . intval($type) . "',
                                                '" . intval($html) . "',
                                                '" . intval($sticky) . "',
                                                '" . intval($my_order) . "',
                                                '" . $this->helpbase->database->escape($revision) . "',
                                                '" . $this->helpbase->database->escape($myattachments) . "'
                                                )");

            $_SESSION['artord'] = $this->helpbase->database->insertID();

            // Update category article count
            if ($type == 0) {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `articles`=`articles`+1 WHERE `id`='" . intval($catid) . "'");
            } else if ($type == 1) {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `articles_private`=`articles_private`+1 WHERE `id`='" . intval($catid) . "'");
            } else {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `articles_draft`=`articles_draft`+1 WHERE `id`='" . intval($catid) . "'");
            }

            unset($_SESSION['hide']);

            $_SESSION['article_submitted'] = 1;

            $this->helpbase->common->process_messages(_('A new knowledgebase article has been successfully added'), 'NOREDIRECT', 'SUCCESS');
            $_GET['catid'] = $catid;
            $this->manage_category();
        }

        private function remove_article() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $id = intval($this->helpbase->common->_get('id')) or $this->helpbase->common->_error(_('Missing or invalid article ID!'));

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            /* Get article details */
            $result = $this->helpbase->database->query("SELECT `catid`, `type`, `attachments` FROM `" . $prefix . "kb_articles` WHERE `id`='" . intval($id) . "' LIMIT 1");

            if ($this->helpbase->database->numRows($result) != 1) {
                $this->helpbase->common->_error(_('Missing or invalid article ID!'));
            }

            $article = $this->helpbase->database->fetchAssoc($result);
            $catid = intval($article['catid']);

            $result = $this->helpbase->database->query("DELETE FROM `" . $prefix . "kb_articles` WHERE `id`='" . intval($id) . "' LIMIT 1");

            // Remove any attachments
            $this->delete_kb_attachments($article['attachments']);

            // Update category article count
            if ($article['type'] == 0) {
                $this->helpbase->database->query("UPDATE `" . $prefix . "kb_categories` SET `articles`=`articles`-1 WHERE `id`='{$catid}'");
            } else if ($article['type'] == 1) {
                $this->helpbase->database->query("UPDATE `" . $prefix . "kb_categories` SET `articles_private`=`articles_private`-1 WHERE `id`='{$catid}'");
            } else {
                $this->helpbase->database->query("UPDATE `" . $prefix . "kb_categories` SET `articles_draft`=`articles_draft`-1 WHERE `id`='{$catid}'");
            }

            $this->helpbase->common->process_messages(_('Selected knowledgebase article has been successfully deleted'), './manage_knowledgebase.php?a=manage_cat&catid=' . $catid, 'SUCCESS');
        }

        private function order_category() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $catid = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error('Invalid category');
            $move = intval($this->helpbase->common->_get('move'));

            $_SESSION['newcat'] = $catid;

            $result = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `cat_order`=`cat_order`+" . intval($move) . " WHERE `id`='" . intval($catid) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error('Invalid category');
            }

            $this->update_category_order();

            header('Location: manage_knowledgebase.php');
            exit();
        }

        private function order_article() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $id     = intval($this->helpbase->common->_get('id')) or $this->helpbase->common->_error(_('Missing or invalid article ID!'));
            $catid  = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error('Invalid category');
            $move   = intval($this->helpbase->common->_get('move'));

            $_SESSION['artord'] = $id;

            $result = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `art_order`=`art_order`+" . intval($move) . " WHERE `id`='" . intval($id) . "' LIMIT 1");
            if ($this->helpbase->database->affectedRows() != 1) {
                $this->helpbase->common->_error(_('Missing or invalid article ID!'));
            }

            /* Update article order */
            $this->update_article_order($catid);

            header('Location: manage_knowledgebase.php?a=manage_cat&catid=' . $catid);
            exit();
        }

        private function show_treeMenu() {
            global $hesk_settings;
?>
                            <script src="<?php echo $this->helpbase->url; ?>inc/treemenu/TreeMenu_v25.js" language="JavaScript" type="text/javascript"></script>
                            <h3 style="padding-bottom:5px;">&raquo; <?php echo _('Knowledgebase structure'); ?></h3>
                            <div align="center">
                                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                        <td class="roundcornerstop"></td>
                                        <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                    </tr>
                                    <tr>
                                        <td class="roundcornersleft">&nbsp;</td>
                                        <td>
<?php
                                            $this->treeMenu->printMenu();
?>
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
                            &nbsp;
                            <br />
                            <img src="../img/add_article.png" width="16" height="16" alt="<?php echo _('New Article'); ?>" title="<?php echo _('New Article'); ?>" style="padding:1px" class="optionWhiteNbOFF" /> = <?php echo _('add a new article to the selected category.'); ?><br />
                            <img src="../img/add_category.png" width="16" height="16" alt="<?php echo _('New Category'); ?>" title="<?php echo _('New Category'); ?>" style="padding:1px" class="optionWhiteNbOFF" /> = <?php echo _('create a new sub-category inside the selected category.'); ?><br />
                            <img src="../img/manage.png" width="16" height="16" alt="<?php echo _('Manage'); ?>" title="<?php echo _('Manage'); ?>" style="padding:1px" class="optionWhiteNbOFF" /> = <?php echo 'manage selected category (edit, delete, manage articles).'; ?><br />
                            <img src="../img/blank.gif" width="1" height="16" alt="" style="padding:1px" class="optionWhiteNbOFF" />(<span class="kb_published">1</span>, <span class="kb_private">2</span>, <span class="kb_draft">3</span>) = <?php echo _('number of public, private and draft articles in category.'); ?></span><br />
<?php
        }

        private function show_subnav($hide = '', $catid = 1) {
            global $hesk_settings;

            // If a category is selected, use it as default for articles and parents
            if (isset($_SESSION['KB_CATEGORY'])) {
                $catid = intval($_SESSION['KB_CATEGORY']);
            }

            $link['view'] = '<a href="knowledgebase_private.php"><img src="../img/view.png" width="16" height="16" alt="' . _('View Knowledgebase') . '" title="' . _('View Knowledgebase') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="knowledgebase_private.php">' . _('View Knowledgebase') . '</a> | ';
            $link['newa'] = '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><img src="../img/add_article.png" width="16" height="16" alt="' . _('New Article') . '" title="' . _('New Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '">' . _('New Article') . '</a> | ';
            $link['newc'] = '<a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '"><img src="../img/add_category.png" width="16" height="16" alt="' . _('New Category') . '" title="' . _('New Category') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '">' . _('New Category') . '</a> | ';

            if ($hide && isset($link[$hide])) {
                $link[$hide] = preg_replace('#<a([^<]*)>#', '', $link[$hide]);
                $link[$hide] = str_replace('</a>', '', $link[$hide]);
            }
?>

                            <form style="margin:0px;padding:0px;" method="get" action="manage_knowledgebase.php">
                                <p>
<?php
                                    echo $link['view'];
                                    echo $link['newa'];
                                    echo $link['newc'];
?>
                                    <img src="../img/edit.png" width="16" height="16" alt="<?php echo _('Edit'); ?>" title="<?php echo _('Edit'); ?>" border="0" style="border:none;vertical-align:text-bottom" /></a> <input type="hidden" name="a" value="edit_article" /><?php echo _('Article ID'); ?>: <input type="text" name="id" size="3" /> <input type="submit" value="<?php echo _('Edit'); ?>" class="button blue small" />
                                </p>
                            </form>
                            &nbsp;
                            <br />
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();

            return $catid;
        }

        private function toggle_sticky() {
            global $hesk_settings;

            /* A security check */
            $this->helpbase->common->token_check();

            $id     = intval($this->helpbase->common->_get('id')) or $this->helpbase->common->_error(_('Missing or invalid article ID!'));
            $catid  = intval($this->helpbase->common->_get('catid')) or $this->helpbase->common->_error('Invalid category');
            $sticky = empty($_GET['s']) ? 0 : 1;

            $_SESSION['artord'] = $id;

            /* Update article "sticky" status */
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `sticky`='" . intval($sticky) . " ' WHERE `id`='" . intval($id) . "' LIMIT 1");

            /* Update article order */
            $this->update_article_order($catid);

            $tmp = $sticky ? _('Article marked as &quot;Sticky&quot;') : _('Article marked as &quot;Normal&quot;');
            $this->helpbase->common->process_messages($tmp, './manage_knowledgebase.php?a=manage_cat&catid=' . $catid, 'SUCCESS');
        }

        private function update_article_order($catid) {
            global $hesk_settings;

            /* Get list of current articles ordered by sticky and article order */
            $res = $this->helpbase->database->query("SELECT `id`, `sticky` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='" . intval($catid) . "' ORDER BY `sticky` DESC, `art_order` ASC");

            $i = 10;
            $previous_sticky = 1;

            while ($article = $this->helpbase->database->fetchAssoc($res)) {

                /* Different count for sticky and non-sticky articles */
                if ($previous_sticky != $article['sticky']) {
                    $i = 10;
                    $previous_sticky = $article['sticky'];
                }

                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `art_order`=" . intval($i) . " WHERE `id`='" . intval($article['id']) . "' LIMIT 1");
                $i += 10;
            }

            return true;
        }

        private function update_category_order() {
            global $hesk_settings;

            /* Get list of current articles ordered by sticky and article order */
            $res = $this->helpbase->database->query('SELECT `id`, `parent` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` ORDER BY `parent` ASC, `cat_order` ASC');

            $i = 10;

            while ($category = $this->helpbase->database->fetchAssoc($res)) {
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `cat_order`=" . intval($i) . " WHERE `id`='" . intval($category['id']) . "' LIMIT 1");
                $i += 10;
            }

            return true;
        }

        private function update_count($show_success = 0) {
            global $hesk_settings;

            $update_these = array();

            // Get a count of all articles grouped by category and type
            $res = $this->helpbase->database->query('SELECT `catid`, `type`, COUNT(*) AS `num` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_articles` GROUP BY `catid`, `type`');
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                switch ($row['type']) {
                    case 0:
                        $update_these[$row['catid']]['articles'] = $row['num'];
                        break;
                    case 1:
                        $update_these[$row['catid']]['articles_private'] = $row['num'];
                        break;
                    default:
                        $update_these[$row['catid']]['articles_draft'] = $row['num'];
                }
            }

            // Set all article counts to 0
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `articles`=0, `articles_private`=0, `articles_draft`=0");

            // Now update categories that have articles with correct values
            foreach ($update_these as $catid => $value) {
                $value['articles'] = isset($value['articles']) ? $value['articles'] : 0;
                $value['articles_private'] = isset($value['articles_private']) ? $value['articles_private'] : 0;
                $value['articles_draft'] = isset($value['articles_draft']) ? $value['articles_draft'] : 0;
                $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` SET `articles`={$value['articles']}, `articles_private`={$value['articles_private']}, `articles_draft`={$value['articles_draft']} WHERE `id`='{$catid}' LIMIT 1");
            }

            // Show a success message?
            if ($show_success) {
                $this->helpbase->common->process_messages(_('Article count has been verified'), 'NOREDIRECT', 'SUCCESS');
            }

            return true;
        }

        private function delete_category_recursive($catid) {
            global $hesk_settings;

            $catid = intval($catid);

            // Don't allow infinite loops... just in case
            $hesk_settings['recursive_loop'] = isset($hesk_settings['recursive_loop']) ? $hesk_settings['recursive_loop'] + 1 : 1;
            if ($hesk_settings['recursive_loop'] > 20) {
                return false;
            }

            // Make sure any attachments are deleted
            $result = $this->helpbase->database->query("SELECT `attachments` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$catid}'");
            while ($article = $this->helpbase->database->fetchAssoc($result)) {
                $this->delete_kb_attachments($article['attachments']);
            }

            // Remove articles from database
            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$catid}'");

            // Delete all sub-categories
            $result = $this->helpbase->database->query("SELECT `id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` WHERE `parent`='{$catid}'");
            while ($cat = $this->helpbase->database->fetchAssoc($result)) {
                $this->delete_category_recursive($cat['id']);
            }

            return true;
        }

        private function delete_kb_attachments($attachments) {
            global $hesk_settings;

            // If nothing to delete just return
            if (empty($attachments)) {
                return true;
            }

            // Do the delete
            $att = explode(',', substr($attachments, 0, -1));
            foreach ($att as $myatt) {
                list($att_id, $att_name) = explode('#', $myatt);

                // Get attachment saved name
                $result = $this->helpbase->database->query("SELECT `saved_name` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` WHERE `att_id`='" . intval($att_id) . "' LIMIT 1");

                if ($this->helpbase->database->numRows($result) == 1) {
                    $file = $this->helpbase->database->fetchAssoc($result);
                    $this->helpbase->common->unlink($this->helpbase->dir . $hesk_settings['attach_dir'] . '/' . $file['saved_name']);
                }

                $result = $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_attachments` WHERE `att_id`='" . intval($att_id) . "' LIMIT 1");
            }

            return true;
        }
    }

    new HelpbaseManageKb;
}

?>
