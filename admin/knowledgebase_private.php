<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Knowledge Base Private
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseKbPrivate')) {
    class HelpbaseKbPrivate {
        private $helpbase   = null;
        private $can_man_kb = false;
        private $artID      = '';
        private $article    = array();
        
        public function __construct() {
            global $hesk_settings;
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->load_kb_functions();

            $helpbase->admin->isLoggedIn();

            /* Is Knowledgebase enabled? */
            if (!$hesk_settings['kb_enable']) {
                $helpbase->common->_error(_('Knowledgebase is disabled'));
            }
            
            $this->render();
            
            unset($helpbase);
        }
        
        private function render() {
            global $hesk_settings;
            
            /* Can this user manage Knowledgebase or just view it? */
            $this->can_man_kb = $this->helpbase->admin->checkPermission('can_man_kb', 0);

            /* Any category ID set? */
            $catid = intval($this->helpbase->common->_get('category', 1));
            $this->artID = intval($this->helpbase->common->_get('article', 0));

            if (isset($_GET['search'])) {
                $query = $this->helpbase->common->_input($this->helpbase->common->_get('search'));
            } else {
                $query = 0;
            }

            $hesk_settings['kb_link'] = ($this->artID || $catid != 1 || $query) ? '<a href="knowledgebase_private.php" class="smaller">' . _('View Knowledgebase') . '</a>' : ($this->can_man_kb ? _('View Knowledgebase') : '');

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            if ($hesk_settings['kb_search'] && $query) {
                $this->kb_search($query);
            } elseif ($this->artID) {
                // Show drafts only to staff who can manage knowledgebase
                if ($this->can_man_kb) {
                    $result = $this->helpbase->database->query("SELECT t1.*, t2.`name` AS `cat_name`
                            FROM `" . $prefix . "kb_articles` AS `t1`
                            LEFT JOIN `" . $prefix . "kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                            WHERE `t1`.`id` = '{$this->artID}'
                            ");
                } else {
                    $result = $this->helpbase->database->query("SELECT t1.*, t2.`name` AS `cat_name`
                            FROM `" . $prefix . "kb_articles` AS `t1`
                            LEFT JOIN `" . $prefix . "kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                            WHERE `t1`.`id` = '{$this->artID}' AND `t1`.`type` IN ('0', '1')
                            ");
                }

                $this->article = $this->helpbase->database->fetchAssoc($result) or $this->helpbase->common->_error(_('Missing or invalid article ID!'));
                $this->show_kb_article($this->artID);
            } else {
                $this->show_kb_category($catid);
            }

            $this->helpbase->footer->render();

            exit();            
        }

        private function kb_header($kb_link, $catid = 1) {
            global $hesk_settings;

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="smaller">
<?php
            if ($this->can_man_kb) {
?>
                            <a href="manage_knowledgebase.php" class="smaller"><?php echo _('Manage Knowledgebase'); ?></a> &gt;
<?php
            }
?>
                            <?php echo $kb_link; ?><br />&nbsp;
                        </span>

                        <!-- SUB NAVIGATION -->
                        <?php $this->show_subnav('view', $catid); ?>
                        <!-- SUB NAVIGATION -->

                        <?php $this->helpbase->kb->searchLarge(1); ?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
        }

        private function kb_search($query) {
            global $hesk_settings;

            $this->helpbase->no_robots = true;

            /* Print header */
            $this->helpbase->header->render();
            $this->kb_header($hesk_settings['kb_link']);

            $res = $this->helpbase->database->query('SELECT t1.`id`, t1.`subject`, t1.`content`, t1.`rating` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_articles` AS t1 LEFT JOIN `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_categories` AS t2 ON t1.`catid` = t2.`id` ' . " WHERE t1.`type` IN ('0','1') AND MATCH(`subject`,`content`,`keywords`) AGAINST ('" . $this->helpbase->database->escape($query) . "') LIMIT " . intval($hesk_settings['kb_search_limit']));
            $num = $this->helpbase->database->numRows($res);
?>
                        <p>&raquo; <b><?php echo _('Search results'); ?> (<?php echo $num; ?>)</b></p>
<?php
            if ($num == 0) {
                echo '
                        <p><i>' . _('No matching articles found. Try browsing the knowledgebase or submit a new support ticket.') . '</i></p>';
                $this->show_kb_category(1, 1);
            } else {
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
                                        <table border="0" cellspacing="1" cellpadding="3" width="100%">
<?php
                while ($this->article = $this->helpbase->database->fetchAssoc($res)) {
                    $txt = $this->helpbase->kb->articleContentPreview($this->article['content']);

                    if ($hesk_settings['kb_rating']) {
                        $alt = $this->article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $this->article['rating'])) : _('Article not rated yet');
                        $rat = '<td width="1" valign="top"><img src="../img/star_' . ($this->helpbase->common->round_to_half($this->article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" border="0" style="vertical-align:text-bottom" /></td>';
                    } else {
                        $rat = '';
                    }

                    echo '
                                            <tr>
                                                <td>
                                                    <table border="0" width="100%" cellspacing="0" cellpadding="1">
                                                        <tr>
                                                            <td width="1" valign="top"><img src="../img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
                                                            <td valign="top"><a href="knowledgebase_private.php?article=' . $this->article['id'] . '">' . $this->article['subject'] . '</a></td>' . 
                                                            $rat .  '
                                                        </tr>
                                                    </table>
                                                    <table border="0" width="100%" cellspacing="0" cellpadding="1">
                                                        <tr>
                                                            <td width="1" valign="top"><img src="../img/blank.gif" width="16" height="10" style="vertical-align:middle" alt="" /></td>
                                                            <td>
                                                                <span class="article_list">' . $txt . '</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>';
                }
?>
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
                        <p>&nbsp;<br />&laquo; <a href="javascript:history.go(-1)"><?php echo _('Go back'); ?></a></p>
<?php
            }
        }

        private function show_kb_article($artid) {
            global $hesk_settings;

            // Print header
            $hesk_settings['tmp_title'] = $this->article['subject'];
            $this->helpbase->header->render();
            $this->kb_header($hesk_settings['kb_link'], $this->article['catid']);

            // Update views by 1
            $this->helpbase->database->query('UPDATE `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `views`=`views`+1 WHERE `id`='" . intval($artid) . "' LIMIT 1");

            echo '
                        <h1>' . $this->article['subject'] . '</h1>
                        <fieldset>
                            <legend>' . _('Solution') . '</legend>' . 
                            $this->article['content'];
            if (!empty($this->article['attachments'])) {
                echo '
                            <p><b>' . _('Attachments') . ':</b>
                                <br />';
                
                $att = explode(',', substr($this->article['attachments'], 0, -1));
                foreach ($att as $myatt) {
                    list($att_id, $att_name) = explode('#', $myatt);
                    echo '
                                <img src="../img/clip.png" width="16" height="16" alt="' . $att_name . '" style="align:text-bottom" /> <a href="../download_attachment.php?kb_att=' . $att_id . '" rel="nofollow">' . $att_name . '</a><br />';
                }
                echo '
                            </p>';
            }

            echo '
                        </fieldset>';

            if ($this->article['catid'] == 1) {
                $link = 'knowledgebase_private.php';
            } else {
                $link = 'knowledgebase_private.php?category=' . $this->article['catid'];
            }
?>
                        <fieldset>
                            <legend><?php echo _('Article details'); ?></legend>
                            <table border="0">
                                <tr>
                                    <td><?php echo _('Article ID'); ?>: </td>
                                    <td><?php echo $this->article['id']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo _('Category'); ?>: </td>
                                    <td><a href="<?php echo $link; ?>"><?php echo $this->article['cat_name']; ?></a></td>
                                </tr>
                                <tr>
                                    <td><?php echo _('Date added'); ?>: </td>
                                    <td><?php echo $this->helpbase->common->_date($this->article['dt']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo _('Views'); ?>: </td>
                                    <td><?php echo (isset($_GET['rated']) ? $this->article['views'] : $this->article['views'] + 1); ?></td>
                                </tr>
                            </table>
                        </fieldset>
<?php
            if (!isset($_GET['back'])) {
?>
                        <p>&nbsp;<br />&laquo; <a href="javascript:history.go(-1)"><?php echo _('Go back'); ?></a></p>
<?php
            } else {
?>
                        <p>&nbsp;</p>
<?php
            }
        }

        private function show_kb_category($catid, $is_search = 0) {
            global $hesk_settings;

            if ($is_search == 0) {
                /* Print header */
                $this->helpbase->header->render();
                $this->kb_header($hesk_settings['kb_link'], $catid);

                if ($catid == 1) {
                    echo _('Private categories and articles viewable to staff only are marked with *');
                }
            }

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            $res = $this->helpbase->database->query("SELECT * FROM `" . $prefix . "kb_categories` WHERE `id`='" . intval($catid) . "' LIMIT 1");
            $thiscat = $this->helpbase->database->fetchAssoc($res) or $this->helpbase->common->_error(_('Invalid category'));

            if ($thiscat['parent']) {
                $link = ($thiscat['parent'] == 1) ? 'knowledgebase_private.php' : 'knowledgebase_private.php?category=' . $thiscat['parent'];
                echo '
                        <span class="homepageh3">&raquo; ' . _('Category') . ': ' . $thiscat['name'] . '</span>
                        &nbsp;(<a href="javascript:history.go(-1)">' . _('Go back') . '</a>)';
            }

            $result = $this->helpbase->database->query("SELECT * FROM `" .$prefix . "kb_categories` WHERE `parent`='" . intval($catid) . "' ORDER BY `parent` ASC, `cat_order` ASC");
            if ($this->helpbase->database->numRows($result) > 0) {
?>
                        <p>&raquo; <b><?php echo _('Subcategories'); ?>:</b></p>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>

                                    <table border="0" cellspacing="1" cellpadding="3" width="100%">
<?php
                $per_col = $hesk_settings['kb_cols'];
                $i = 1;

                while ($cat = $this->helpbase->database->fetchAssoc($result)) {
                    if ($i == 1) {
                        echo '
                                        <tr>';
                    }

                    $private = ($cat['type'] == 1) ? ' *' : '';

                    echo '
                                            <td width="50%" valign="top">
                                                <table border="0">
                                                    <tr>
                                                        <td>
                                                            <img src="../img/folder.gif" width="20" height="20" alt="" style="vertical-align:middle" /><a href="knowledgebase_private.php?category=' . $cat['id'] . '">' . $cat['name'] . '</a>' . $private . '
                                                        </td>
                                                    </tr>';

                    /* Print most popular/sticky articles */
                    if ($hesk_settings['kb_numshow'] && $cat['articles']) {
                        $res = $this->helpbase->database->query("SELECT `id`,`subject`,`type` FROM `" . $prefix . "kb_articles` WHERE `catid`='" . intval($cat['id']) . "' AND `type` IN ('0','1') ORDER BY `sticky` DESC, `views` DESC, `art_order` ASC LIMIT " . (intval($hesk_settings['kb_numshow']) + 1));
                        $num = 1;
                        while ($art = $this->helpbase->database->fetchAssoc($res)) {
                            $private = ($art['type'] == 1) ? ' *' : '';
                            echo '
                                                    <tr>
                                                        <td>
                                                            <img src="../img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" />
                                                            <a href="knowledgebase_private.php?article=' . $art['id'] . '" class="article">' . $art['subject'] . '</a>' . $private . '
                                                        </td>
                                                    </tr>';

                            if ($num == $hesk_settings['kb_numshow']) {
                                break;
                            } else {
                                $num++;
                            }
                        }
                        if ($this->helpbase->database->numRows($res) > $hesk_settings['kb_numshow']) {
                            echo '
                                                    <tr>
                                                        <td>&raquo; <a href="knowledgebase_private.php?category=' . $cat['id'] . '"><i>' . _('More topics') . '</i></a></td>
                                                    </tr>';
                        }
                    }

                    echo '
                                                </table>
                                            </td>';

                    if ($i == $per_col) {
                        echo '
                                        </tr>';
                        $i = 0;
                    }
                    $i++;
                }

                /* Finish the table if needed */
                if ($i != 1) {
                    for ($j = 1; $j <= $per_col; $j++) {
                        echo '
                                        <td width="50%">&nbsp;</td>';
                        if ($i == $per_col) {
                            echo '
                                    </tr>';
                            break;
                        }
                        $i++;
                    }
                }
?>
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
<?php
            }
?>
                        <p>&raquo; <b><?php echo _('Articles in this category:'); ?></b></p>
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td>
<?php
            $res = $this->helpbase->database->query("SELECT `id`, `subject`, `content`, `rating`, `type` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='" . intval($catid) . "' AND `type` IN ('0','1') ORDER BY `sticky` DESC, `art_order` ASC");
            if ($this->helpbase->database->numRows($res) == 0) {
                echo '
                                    <p><i>' . _('No articles yet in this category') . '</i></p>';
            } else {
                echo '
                                    <div align="center"><table border="0" cellspacing="1" cellpadding="3" width="100%">
                                    ';
                while ($this->article = $this->helpbase->database->fetchAssoc($res)) {
                    $private = ($this->article['type'] == 1) ? ' *' : '';

                    $txt = $this->helpbase->kb->articleContentPreview($this->article['content']);

                    echo '
                                        <tr>
                                            <td>
                                                <table border="0" width="100%" cellspacing="0" cellpadding="1">
                                                    <tr>
                                                        <td width="1" valign="top"><img src="../img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
                                                        <td valign="top"><a href="knowledgebase_private.php?article=' . $this->article['id'] . '">' . $this->article['subject'] . '</a>' . $private . '</td>
                                                    </tr>
                                                </table>
                                                <table border="0" width="100%" cellspacing="0" cellpadding="1">
                                                    <tr>
                                                        <td width="1" valign="top"><img src="../img/blank.gif" width="16" height="10" style="vertical-align:middle" alt="" /></td>
                                                        <td><span class="article_list">' . $txt . '</span></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>';
                }
                echo '
                                    </table>
                                    </div>';
            }
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
<?php
        }

        private function show_subnav($hide = '', $catid = 1) {
            global $hesk_settings;

            if (!$this->can_man_kb) {
                echo '';
                return true;
            }

            $catid = intval($catid);

            $link['view'] = '<a href="knowledgebase_private.php"><img src="../img/view.png" width="16" height="16" alt="' . _('View Knowledgebase') . '" title="' . _('View Knowledgebase') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="knowledgebase_private.php">' . _('View Knowledgebase') . '</a> | ';
            $link['newa'] = '<a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '"><img src="../img/add_article.png" width="16" height="16" alt="' . _('New Article') . '" title="' . _('New Article') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_article&amp;catid=' . $catid . '">' . _('New Article') . '</a> | ';
            $link['newc'] = '<a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '"><img src="../img/add_category.png" width="16" height="16" alt="' . _('New Category') . '" title="' . _('New Category') . '" border="0" style="border:none;vertical-align:text-bottom" /></a> <a href="manage_knowledgebase.php?a=add_category&amp;parent=' . $catid . '">' . _('New Category') . '</a> | ';

            if ($hide && isset($link[$hide])) {
                $link[$hide] = preg_replace('/<a([^<]*)>/', '', $link[$hide]);
                $link[$hide] = str_replace('</a>', '', $link[$hide]);
            }
?>
                        <form style="margin:0px;padding:0px;" method="get" action="manage_knowledgebase.php">
<?php
                                echo $link['view'];
                                echo $link['newa'];
                                echo $link['newc'];
?>
                            <img src="../img/edit.png" width="16" height="16" alt="<?php echo _('Edit'); ?>" title="<?php echo _('Edit'); ?>" border="0" style="border:none;vertical-align:text-bottom" /></a> <input type="hidden" name="a" value="edit_article" /><?php echo _('Article ID'); ?>: <input type="text" name="id" size="3" <?php if ($this->artID) echo 'value="' . $this->artID . '"'; ?> /> <input type="submit" value="<?php echo _('Edit'); ?>" class="button blue small" />
                        </form>
<?php
        }
    }
    
    new HelpbaseKbPrivate;
}

?>
