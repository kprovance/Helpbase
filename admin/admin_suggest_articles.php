<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Suggest Articles
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseSuggestArticle')) {
    class HelpbaseSuggestArticle {
        private $helpbase = null;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();  
            
            $this->render();
        }
        
        private function render() {
            global $hesk_settings;

            /* Print XML header */
            header('Content-Type: text/html; charset=' . $this->helpbase->encoding );

            /* Get the search query composed of the subject and message */
            $query = $this->helpbase->common->_request('q') or die('');

            /* Get relevant articles from the database, include private ones */
            $res = $this->helpbase->database->query("SELECT `id`, `subject`, `content` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "kb_articles` WHERE `type` IN ('0','1') AND MATCH(`subject`,`content`,`keywords`) AGAINST ('" . $this->helpbase->database->escape($query) . "') LIMIT " . intval($hesk_settings['kb_search_limit']));
            $num = $this->helpbase->database->numRows($res);

            /* Solve some spacing issues */
            if ($this->helpbase->common->isREQUEST('p')) {
                echo '&nbsp;<br />';
            }

            /* Return found articles */
?>
                        <div class="notice">
                            <span style="font-size:12px;font-weight:bold"><?php echo _('Suggested knowledgebase articles'); ?>:</span>
                            <br />&nbsp;<br />
<?php
            if (!$num) {
                echo '
                            <i>' . _('No relevant articles found.') . '</i>';
            } else {
                while ($article = $this->helpbase->database->fetchAssoc($res)) {
                    $txt = strip_tags($article['content']);
                    if (strlen($txt) > $hesk_settings['kb_substrart']) {
                        $txt = substr($txt, 0, $hesk_settings['kb_substrart']) . '...';
                    }

                    echo '
                            <a href="knowledgebase_private.php?article=' . $article['id'] . '&amp;suggest=1" target="_blank">' . $article['subject'] . '</a>
                            <br />' . $txt . '<br /><br />';
                }
            }
?>
                        </div>
<?php
            unset($this->helpbase);
            exit();
        }
    }
    
    new HelpbaseSuggestArticle;
}

?>
