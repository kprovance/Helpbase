<?php
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Suggest Articles 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

/* Print XML header */
header('Content-Type: text/html; charset=' . $helpbase->encoding);

/* Get the search query composed of the subject and message */
$query = $helpbase->common->_request('q') or die('');

/* Get relevant articles from the database */
$res = $helpbase->database->query('SELECT t1.`id`, t1.`subject`, t1.`content` FROM `' . $helpbase->database->escape($hesk_settings['db_pfix']) . 'kb_articles` AS t1 LEFT JOIN `' . $helpbase->database->escape($hesk_settings['db_pfix']) . "kb_categories` AS t2 ON t1.`catid` = t2.`id`  WHERE t1.`type`='0' AND t2.`type`='0' AND MATCH(`subject`,`content`,`keywords`) AGAINST ('" . $helpbase->database->escape($query) . "') LIMIT " . intval($hesk_settings['kb_search_limit']));
$num = $helpbase->database->numRows($res);

/* Solve some spacing issues */
if ($helpbase->common->isREQUEST('p')) {
    echo '&nbsp;<br />';
}

/* Return found articles */
?>
<div class="notice">
    <span style="font-size:12px;font-weight:bold"><?php echo _('Suggested knowledgebase articles'); ?>:</span><br />&nbsp;<br />
<?php
if (!$num) {
    echo '<i>' . _('No relevant articles found.') . '</i>';
} else {
    while ($article = $helpbase->database->fetchAssoc($res)) {
        $txt = strip_tags($article['content']);
        if (strlen($txt) > $hesk_settings['kb_substrart']) {
            $txt = substr($txt, 0, $hesk_settings['kb_substrart']) . '...';
        }

        echo '
			<a href="knowledgebase.php?article=' . $article['id'] . '&amp;suggest=1" target="_blank">' . $article['subject'] . '</a>
		    <br />' . $txt . '<br /><br />';
    }
}
?>
</div>

    <?php
    unset($helpbase);
    ?>