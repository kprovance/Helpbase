<?php
/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Knowledge Base Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseKbInc')) {

    class HelpbaseKbInc {

        private $helpbase = null;

        public function __construct($parent) {
            $this->helpbase = $parent;
        }

        public function articleContentPreview($txt) {
            global $hesk_settings;

            // Strip HTML tags
            $txt = strip_tags($txt);

            // If text is larger than article preview length, shorten it
            if (strlen($txt) > $hesk_settings['kb_substrart']) {
                // The quick but not 100% accurate way (number of chars displayed may be lower than the limit)
                return substr($txt, 0, $hesk_settings['kb_substrart']) . '...';

                // If you want a more accurate, but also slower way, use this instead
                // return $this->helpbase->common->htmlentities( substr( $this->helpbase->common->html_entity_decode($txt), 0, $hesk_settings['kb_substrart'] ) ) . '...';
            }

            return $txt;
        }

        public function topArticles($how_many, $index = 1) {
            global $hesk_settings;

            // Index page or KB main page?
            if ($index) {
                // Disabled?
                if (!$hesk_settings['kb_index_popart']) {
                    return true;
                }

                // Show title in italics
                $font_weight = 'i';
            } else {
                // Disabled?
                if (!$hesk_settings['kb_popart']) {
                    return true;
                }

                // Show title in bold
                $font_weight = 'b';

                // Print a line for spacing
                echo '
                                    <hr />';
            }
?>

                                    <table border="0" width="100%">
                                        <tr>
                                            <td>&raquo; <<?php echo $font_weight; ?>><?php echo _('Top Knowledgebase articles:'); ?></<?php echo $font_weight; ?>></td>

<?php
            /* Show number of views? */
            if ($hesk_settings['kb_views']) {
                echo '
                                            <td style="text-align:right"><i>' . _('Views') . '</i></td>';
            }
?>

                                        </tr>
                                    </table>
<?php
            /* Get list of articles from the database */
            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $sql = "SELECT `t1`.* FROM `" . $prefix . "kb_articles` AS `t1`
                    LEFT JOIN `" . $prefix . "kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                    WHERE `t1`.`type`='0' AND `t2`.`type`='0'
                    ORDER BY `t1`.`sticky` DESC, `t1`.`views` DESC, `t1`.`art_order` ASC LIMIT " . intval($how_many);

            $res = $this->helpbase->database->query($sql);


            /* If no results found end here */
            if ($this->helpbase->database->numRows($res) == 0) {
                echo '
                                    <p><i>' . _('No articles yet') . '</i><br />&nbsp;</p>';
                return true;
            }

            /* We have some results, print them out */
?>
                                    <div align="center">
                                        <table border="0" cellspacing="1" cellpadding="3" width="100%">
<?php
            while ($article = $this->helpbase->database->fetchAssoc($res)) {
                echo '
                                            <tr>
                                                <td>
                                                    <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td width="1" valign="top"><img src="' . $this->helpbase->url . 'img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
                                                            <td valign="top">&nbsp;<a href="' . $this->helpbase->url . 'knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>';

                if ($hesk_settings['kb_views']) {
                    echo '
                                                            <td valign="top" style="text-align:right" width="200">' . $article['views'] . '</td>';
                }

                echo '
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>';
            }
?>
                                        </table>
                                    </div>
                                    &nbsp;
<?php
        }

        public function latestArticles($how_many, $index = 1) {
            global $hesk_settings;

            // Index page or KB main page?
            if ($index) {
                // Disabled?
                if (!$hesk_settings['kb_index_latest']) {
                    return true;
                }

                // Show title in italics
                $font_weight = 'i';
            } else {
                // Disabled?
                if (!$hesk_settings['kb_latest']) {
                    return true;
                }

                // Show title in bold
                $font_weight = 'b';

                // Print a line for spacing if we don't show popular articles
                if (!$hesk_settings['kb_popart']) {
                    echo '
                                    <hr />';
                }
            }
?>
                                    <table border="0" width="100%">
                                        <tr>
                                            <td>&raquo; <<?php echo $font_weight; ?>><?php echo _('Latest Knowledgebase articles:'); ?></<?php echo $font_weight; ?>></td>
<?php
            /* Show number of views? */
            if ($hesk_settings['kb_date']) {
                echo '
                                            <td style="text-align:right"><i>' . _('Date added') . '</i></td>';
            }
?>
                                        </tr>
                                    </table>
<?php
            /* Get list of articles from the database */
            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $sql = "SELECT `t1`.* FROM `" . $prefix . "kb_articles` AS `t1`
                    LEFT JOIN `" . $prefix . "kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
                    WHERE `t1`.`type`='0' AND `t2`.`type`='0'
                    ORDER BY `t1`.`dt` DESC LIMIT " . intval($how_many);

            $res = $this->helpbase->database->query($sql);

            /* If no results found end here */
            if ($this->helpbase->database->numRows($res) == 0) {
                echo '
                                    <p><i>' . _('No articles yet') . '</i><br />&nbsp;</p>';
                return true;
            }

            /* We have some results, print them out */
?>
                                    <div align="center">
                                        <table border="0" cellspacing="1" cellpadding="3" width="100%">
<?php
            while ($article = $this->helpbase->database->fetchAssoc($res)) {
                echo '
                                            <tr>
                                                <td>
                                                    <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td width="1" valign="top"><img src="' . $this->helpbase->url . 'img/article_text.png" width="16" height="16" border="0" alt="" style="vertical-align:middle" /></td>
                                                            <td valign="top">&nbsp;<a href="' . $this->helpbase->url . 'knowledgebase.php?article=' . $article['id'] . '">' . $article['subject'] . '</a></td>';
                if ($hesk_settings['kb_date']) {
                    echo '
                                                            <td valign="top" style="text-align:right" width="200">' . $this->helpbase->common->_date($article['dt']) . '</td>';
                }

                echo '
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>';
            }
?>
                                        </table>
                                    </div>
                                    &nbsp;
<?php
        }

        public function searchLarge($admin = '') {
            global $hesk_settings;

            if ($hesk_settings['kb_search'] != 2) {
                return '';
            }

            $action = $admin ? 'knowledgebase_private.php' : 'knowledgebase.php';
?>
                        <br />
                        <div style="text-align:center">
                            <form action="<?php echo $action; ?>" method="get" style="display: inline; margin: 0;" name="searchform">
                                <span class="largebold"><?php echo _('Search help:'); ?></span>
                                <input type="text" name="search" class="searchfield" />
                                <input type="submit" value="<?php echo _('Search'); ?>" title="<?php echo _('Search'); ?>" class="searchbutton" /><br />
                            </form>
                        </div>
                        <br />

                        <!-- START KNOWLEDGEBASE SUGGEST -->
                        <div id="kb_suggestions" style="display:none">
                            <img src="<?php echo $this->helpbase->url; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo _('Loading knowledgebase suggestions...'); ?></i>
                        </div>

                        <script language="Javascript" type="text/javascript">
                            <!-- hb_suggestKBsearch(<?php echo $admin; ?>); //-->
                        </script>
                        <!-- END KNOWLEDGEBASE SUGGEST -->
                        <br />
<?php
        }

        public function searchSmall() {
            global $hesk_settings;

            if ($hesk_settings['kb_search'] != 1) {
                return '';
            }
?>
                                <td style="text-align:right" valign="top" width="300">
                                <div style="display:inline;">
                                    <form action="knowledgebase.php" method="get" style="display: inline; margin: 0;">
                                        <input type="text" name="search" class="searchfield sfsmall" />
                                        <input type="submit" value="<?php echo _('Search'); ?>" title="<?php echo _('Search'); ?>" class="searchbutton sbsmall" />
                                    </form>
                                </div>
                            </td>
<?php
        }

        public function detect_bots() {
            $botlist = array(
                'googlebot',
                'msnbot',
                'slurp',
                'alexa',
                'teoma',
                'froogle',
                'gigabot',
                'inktomi',
                'looksmart',
                'firefly',
                'nationaldirectory',
                'ask jeeves',
                'tecnoseek',
                'infoseek',
                'webfindbot',
                'girafabot',
                'crawl',
                'www.galaxy.com',
                'scooter',
                'appie',
                'fast',
                'webbug',
                'spade',
                'zyborg',
                'rabaz',
                'baiduspider',
                'feedfetcher-google',
                'technoratisnoop',
                'rankivabot',
                'mediapartners-google',
                'crawler',
                'spider',
                'robot',
                'bot/',
                'bot-',
                'voila'
            );

            if (!isset($_SERVER['HTTP_USER_AGENT'])) {
                return false;
            }

            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

            foreach ($botlist as $bot) {
                if (strpos($ua, $bot) !== false) {
                    return true;
                }
            }

            return false;
        }

    }

}