<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  KnowledgeBase 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

$helpbase->load_kb_functions();

/* Is Knowledgebase enabled? */
if (!$hesk_settings['kb_enable']) {
    $helpbase->common->_error(_('Knowledgebase is disabled'));
}

/* Rating? */
if (isset($_GET['rating'])) {
    // Detect and block robots
    if ($helpbase->kb->detect_bots()) {
        ?>
        <html>
            <head>
                <meta name="robots" content="noindex, nofollow">
            </head>
            <body>
            </body>
        </html>
        <?php
    }

    // Rating
    $rating = intval($helpbase->common->_get('rating'));

    // Rating value may only be 1 or 5
    if ($rating != 1 && $rating != 5) {
        die(_('Invalid attempt!'));
    }

    // Article ID
    $artid = intval($helpbase->common->_get('id', 0)) or die(_('Missing or invalid article ID!'));

    // Check cookies for already rated, rate and set cookie if not already
    $_COOKIE['hesk_kb_rate'] = $helpbase->common->get_cookie('hesk_kb_rate');

    if (strpos($_COOKIE['hesk_kb_rate'], 'a' . $artid . '%') === false) {
        // Update rating, make sure it's a public article in a public category
        $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` AS `t1`
					LEFT JOIN `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` AS `t2` ON t1.`catid` = t2.`id`
					SET `rating`=((`rating`*`votes`)+{$rating})/(`votes`+1), t1.`votes`=t1.`votes`+1
					WHERE t1.`id`='{$artid}' AND t1.`type`='0' AND t2.`type`='0'
					");
    }

    setcookie('hesk_kb_rate', $_COOKIE['hesk_kb_rate'] . 'a' . $artid . '%', time() + 2592000);
    header('Location: knowledgebase.php?article=' . $artid . '&rated=1');
    exit();
}

/* Any category ID set? */
$catid = intval($helpbase->common->_get('category', 1));
$artid = intval($helpbase->common->_get('article', 0));

if (isset($_GET['search'])) {
    $query = $helpbase->common->_input($helpbase->common->_get('search'));
} else {
    $query = 0;
}

$hesk_settings['kb_link'] = ($artid || $catid != 1 || $query) ? '<a href="knowledgebase.php" class="smaller">' . _('Knowledgebase') . '</a>' : _('Knowledgebase');

if ($hesk_settings['kb_search'] && $query) {
    hesk_kb_search($query);
} elseif ($artid) {
    // Get article from DB, make sure that article and category are public
    $result = $helpbase->database->query("SELECT t1.*, t2.`name` AS `cat_name`
							FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` AS `t1`
							LEFT JOIN `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
							WHERE `t1`.`id` = '{$artid}'
							AND `t1`.`type` = '0'
							AND `t2`.`type` = '0'
                            ");

    $article = $helpbase->database->fetchAssoc($result) or $helpbase->common->_error(_('Missing or invalid article ID!'));
    hesk_show_kb_article($artid);
} else {
    hesk_show_kb_category($catid);
}

$helpbase->footer->render();

unset($helpbase);

exit();


/* * * START FUNCTIONS ** */

function hesk_kb_header($kb_link) {
    global $hesk_settings, $helpbase;
    
    ?>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td width="3"><img src="img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
            <td class="headersm"><?php $helpbase->common->showTopBar(_('Knowledgebase')); ?></td>
            <td width="3"><img src="img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
        </tr>
    </table>

    <table width="100%" border="0" cellspacing="0" cellpadding="3">
        <tr>
            <td valign="top">
                <span class="smaller"><a href="<?php echo $hesk_settings['site_url']; ?>" class="smaller"><?php echo $hesk_settings['site_title']; ?></a> &gt;
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="smaller"><?php echo $hesk_settings['hesk_title']; ?></a>
                    &gt; <?php echo $kb_link; ?></span>
            </td>

    <?php
    /* Print small search box */
    $helpbase->kb->searchSmall();
    ?>

        </tr>
    </table>

    </td>
    </tr>
    <tr>
        <td>

            <?php
            /* Print large search box */
            $helpbase->kb->searchLarge();
        }

// END hesk_kb_header()

        function hesk_kb_search($query) {
            global $hesk_settings, $helpbase;

            $helpbase->no_robots = true;

            /* Print header */
            $hesk_settings['tmp_title'] = _('Search results') . ': ' . substr($helpbase->common->htmlspecialchars(stripslashes($query)), 0, 20);
            $helpbase->header->render();
            hesk_kb_header($hesk_settings['kb_link']);

            $res = $helpbase->database->query('SELECT t1.`id`, t1.`subject`, t1.`content`, t1.`rating` FROM `' . $helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_articles` AS t1
    					LEFT JOIN `' . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` AS t2 ON t1.`catid` = t2.`id`
						WHERE t1.`type`='0' AND t2.`type`='0' AND  MATCH(`subject`,`content`,`keywords`) AGAINST ('" . $helpbase->database->escape($query) . "') LIMIT " . intval($hesk_settings['kb_search_limit']));
            $num = $helpbase->database->numRows($res);
            ?>
            <p>&raquo; <b><?php echo _('Search results'); ?> (<?php echo $num; ?>)</b></p>

            <?php
            if ($num == 0) {
                echo '<p><i>' . _('No matching articles found. Try browsing the knowledgebase or submit a new support ticket.') . '</i></p>
        <p>&nbsp;</p>
        ';
                hesk_show_kb_category(1, 1);
            } else {
                ?>
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornerstop"></td>
                        <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                    <tr>
                        <td class="roundcornersleft">&nbsp;</td>
                        <td>
                            <div align="center">
                                <table border="0" cellspacing="1" cellpadding="3" width="100%">
                <?php
                while ($article = $helpbase->database->fetchAssoc($res)) {
                    $txt = $helpbase->kb->articleContentPreview($article['content']);

                    if ($hesk_settings['kb_rating']) {
                        $alt = $article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $article['rating'])) : _('Article not rated yet');
                        $rat = '<td width="1" valign="top"><img src="img/star_' . ($helpbase->common->round_to_half($article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" border="0" style="vertical-align:text-bottom" /></td>';
                    } else {
                        $rat = '';
                    }

                    echo '
				<tr>
				<td>
	                <table border="0" width="100%" cellspacing="0" cellpadding="1">
	                <tr>
	                <td width="1" valign="top"><img src="img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
	                <td valign="top"><a href="knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>
	                ' . $rat . '
                    </tr>
	                </table>
	                <table border="0" width="100%" cellspacing="0" cellpadding="1">
	                <tr>
	                <td width="1" valign="top"><img src="img/blank.gif" width="16" height="10" style="vertical-align:middle" alt="" /></td>
	                <td><span class="article_list">' . $txt . '</span></td>
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
                        <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornersbottom"></td>
                        <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                </table>

                <p>&nbsp;<br />&laquo; <a href="javascript:history.go(-1)"><?php echo _('Go back'); ?></a></p>
                                    <?php
                                } // END else
                            }

// END hesk_kb_search()

                            function hesk_show_kb_article($artid) {
                                global $hesk_settings, $article;

                                // Print header
                                $hesk_settings['tmp_title'] = $article['subject'];
                                $helpbase->header->render();
                                hesk_kb_header($hesk_settings['kb_link']);

                                // Update views by 1 - exclude known bots and reloads because of ratings
                                if (!isset($_GET['rated']) && !$helpbase->kb->detect_bots()) {
                                    $helpbase->database->query("UPDATE `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` SET `views`=`views`+1 WHERE `id`='" . intval($artid) . "' LIMIT 1");
                                }

                                echo '<h1>' . $article['subject'] . '</h1>

    <fieldset>
	<legend>' . _('Solution') . '</legend>
    ' . $article['content'];

                                if (!empty($article['attachments'])) {
                                    echo '<p><b>' . _('Attachments') . ':</b><br />';
                                    $att = explode(',', substr($article['attachments'], 0, -1));
                                    foreach ($att as $myatt) {
                                        list($att_id, $att_name) = explode('#', $myatt);
                                        echo '<img src="img/clip.png" width="16" height="16" alt="' . $att_name . '" style="align:text-bottom" /> <a href="download_attachment.php?kb_att=' . $att_id . '" rel="nofollow">' . $att_name . '</a><br />';
                                    }
                                    echo '</p>';
                                }

                                if ($hesk_settings['kb_rating'] && strpos($helpbase->common->get_cookie('hesk_kb_rate'), 'a' . $artid . '%') === false) {
                                    echo '
	    <div id="rating" class="rate" align="right">&nbsp;<br />' . _('Was this article helpful?') . '
			<a href="Javascript:void(0)" onclick="Javascript:window.location=\'knowledgebase.php?rating=5&amp;id=' . $article['id'] . '\'" rel="nofollow">' . strtolower(_('YES')) . '</a> /
	        <a href="Javascript:void(0)" onclick="Javascript:window.location=\'knowledgebase.php?rating=1&amp;id=' . $article['id'] . '\'" rel="nofollow">' . strtolower(_('NO')) . '</a>
	    </div>
        ';
                                }
                                echo '</fieldset>';

                                if ($article['catid'] == 1) {
                                    $link = 'knowledgebase.php';
                                } else {
                                    $link = 'knowledgebase.php?category=' . $article['catid'];
                                }
                                ?>

            <fieldset>
                <legend><?php echo _('Article details'); ?></legend>
                <table border="0">
                    <tr>
                        <td><?php echo _('Article ID'); ?>: </td>
                        <td><?php echo $article['id']; ?></td>
                    </tr>
                    <tr>
                        <td><?php echo _('Category'); ?>: </td>
                        <td><a href="<?php echo $link; ?>"><?php echo $article['cat_name']; ?></a></td>
                    </tr>

            <?php
            if ($hesk_settings['kb_date']) {
                ?>
                        <tr>
                            <td><?php echo _('Date added'); ?>: </td>
                            <td><?php echo $helpbase->common->_date($article['dt']); ?></td>
                        </tr>
                <?php
            }

            if ($hesk_settings['kb_views']) {
                ?>
                        <tr>
                            <td><?php echo _('Views'); ?>: </td>
                            <td><?php echo (isset($_GET['rated']) ? $article['views'] : $article['views'] + 1); ?></td>
                        </tr>
        <?php
    }

    if ($hesk_settings['kb_rating']) {
        $alt = $article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $article['rating'])) : _('Article not rated yet');
        echo '
        <tr>
        <td>' . _('Rating') . ' (' . _('Votes') . '):</td>
        <td><img src="img/star_' . ($helpbase->common->round_to_half($article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" title="' . $alt . '" border="0" style="vertical-align:text-bottom" /> (' . $article['votes'] . ')</td>
        </tr>
        ';
    }
    ?>
                </table>
            </fieldset>

                    <?php
                    if (!isset($_GET['suggest'])) {
                        ?>
                <p>&nbsp;<br />&laquo; <a href="javascript:history.go(<?php echo isset($_GET['rated']) ? '-2' : '-1'; ?>)"><?php echo _('Go back'); ?></a></p>
                        <?php
                    } else {
                        ?>
                <p>&nbsp;</p>
                        <?php
                    }
                }

// END hesk_show_kb_article()

                function hesk_show_kb_category($catid, $is_search = 0) {
                    global $hesk_settings, $helpbase;

                    $res = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` WHERE `id`='{$catid}' AND `type`='0' LIMIT 1");
                    $thiscat = $helpbase->database->fetchAssoc($res) or $helpbase->common->_error('Invalid category');

                    if ($is_search == 0) {
                        /* Print header */
                        $hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $helpbase->common->htmlspecialchars($thiscat['name']);
                        $helpbase->header->render();
                        hesk_kb_header($hesk_settings['kb_link']);

                        if ($catid == 1) {
                            echo _('Knowledgebase is a categorized collection of answers to frequently asked questions (FAQ) and articles. You can read articles in this category or select a subcategory that you are interested in.') . ' &nbsp;';
                        }
                    }

                    if ($thiscat['parent']) {
                        $link = ($thiscat['parent'] == 1) ? 'knowledgebase.php' : 'knowledgebase.php?category=' . $thiscat['parent'];
                        echo '<span class="homepageh3">&raquo; ' . _('Category') . ': ' . $thiscat['name'] . '</span>
        &nbsp;(<a href="javascript:history.go(-1)">' . _('Go back') . '</a>)
		';
                    }

                    $result = $helpbase->database->query("SELECT * FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` WHERE `parent`='{$catid}' AND `type`='0' ORDER BY `parent` ASC, `cat_order` ASC");
                    if ($helpbase->database->numRows($result) > 0) {
                        ?>

                <p>&raquo; <b><?php echo _('Subcategories'); ?>:</b></p>

                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornerstop"></td>
                        <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                    <tr>
                        <td class="roundcornersleft">&nbsp;</td>
                        <td>

                            <table border="0" cellspacing="1" cellpadding="3" width="100%">

                <?php
                $per_col = $hesk_settings['kb_cols'];
                $i = 1;

                while ($cat = $helpbase->database->fetchAssoc($result)) {

                    if ($i == 1) {
                        echo '<tr>';
                    }

                    echo '
		    <td width="50%" valign="top">
			<table border="0">
			<tr><td><img src="img/folder.gif" width="20" height="20" alt="" style="vertical-align:middle" /><a href="knowledgebase.php?category=' . $cat['id'] . '">' . $cat['name'] . '</a></td></tr>
			';

                    /* Print most popular/sticky articles */
                    if ($hesk_settings['kb_numshow'] && $cat['articles']) {
                        $res = $helpbase->database->query("SELECT `id`,`subject` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$cat['id']}' AND `type`='0' ORDER BY `sticky` DESC, `views` DESC, `art_order` ASC LIMIT " . (intval($hesk_settings['kb_numshow']) + 1));
                        $num = 1;
                        while ($art = $helpbase->database->fetchAssoc($res)) {
                            echo '
		            <tr>
		            <td><img src="img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" />
		            <a href="knowledgebase.php?article=' . $art['id'] . '" class="article">' . $art['subject'] . '</a></td>
		            </tr>';

                            if ($num == $hesk_settings['kb_numshow']) {
                                break;
                            } else {
                                $num++;
                            }
                        }
                        if ($helpbase->database->numRows($res) > $hesk_settings['kb_numshow']) {
                            echo '<tr><td>&raquo; <a href="knowledgebase.php?category=' . $cat['id'] . '"><i>' . _('More topics') . '</i></a></td></tr>';
                        }
                    }

                    echo '
			</table>
		    </td>
			';

                    if ($i == $per_col) {
                        echo '</tr>';
                        $i = 0;
                    }
                    $i++;
                }
                /* Finish the table if needed */
                if ($i != 1) {
                    for ($j = 1; $j <= $per_col; $j++) {
                        echo '<td width="50%">&nbsp;</td>';
                        if ($i == $per_col) {
                            echo '</tr>';
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
                        <td><img src="img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                        <td class="roundcornersbottom"></td>
                        <td width="7" height="7"><img src="img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                    </tr>
                </table>

                                <?php
                            } // END if NumRows > 0
                            ?>

            <p>&raquo; <b><?php echo _('Articles in this category:'); ?></b></p>

            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="7" height="7"><img src="img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                    <td class="roundcornerstop"></td>
                    <td><img src="img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                </tr>
                <tr>
                    <td class="roundcornersleft">&nbsp;</td>
                    <td>

                            <?php
                            $res = $helpbase->database->query("SELECT `id`, `subject`, `content`, `rating` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `catid`='{$catid}' AND `type`='0' ORDER BY `sticky` DESC, `art_order` ASC");
                            if ($helpbase->database->numRows($res) == 0) {
                                echo '<p><i>' . _('No articles yet in this category') . '</i></p>';
                            } else {
                                echo '<div align="center"><table border="0" cellspacing="1" cellpadding="3" width="100%">';
                                while ($article = $helpbase->database->fetchAssoc($res)) {
                                    $txt = $helpbase->kb->articleContentPreview($article['content']);

                                    if ($hesk_settings['kb_rating']) {
                                        $alt = $article['rating'] ? sprintf(_('Article rated %s/5.0'), sprintf("%01.1f", $article['rating'])) : _('Article not rated yet');
                                        $rat = '<td width="1" valign="top"><img src="img/star_' . ($helpbase->common->round_to_half($article['rating']) * 10) . '.png" width="85" height="16" alt="' . $alt . '" title="' . $alt . '" border="0" style="vertical-align:text-bottom" /></td>';
                                    } else {
                                        $rat = '';
                                    }

                                    echo '
				<tr>
				<td>
	                <table border="0" width="100%" cellspacing="0" cellpadding="1">
	                <tr>
	                <td width="1" valign="top"><img src="img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
	                <td valign="top"><a href="knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>
	                ' . $rat . '
                    </tr>
	                </table>
	                <table border="0" width="100%" cellspacing="0" cellpadding="1">
	                <tr>
	                <td width="1" valign="top"><img src="img/blank.gif" width="16" height="10" style="vertical-align:middle" alt="" /></td>
	                <td><span class="article_list">' . $txt . '</span></td>
                    </tr>
	                </table>
	            </td>
				</tr>';
                                }
                                echo '</table></div>';
                            }
                            ?>

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
    /* On the main KB page print out top and latest articles if needed */
    if ($catid == 1) {
        /* Get list of top articles */
        $helpbase->kb->topArticles($hesk_settings['kb_popart'], 0);

        /* Get list of latest articles */
        $helpbase->kb->latestArticles($hesk_settings['kb_latest'], 0);
    }
}

// END hesk_show_kb_category()
?>
