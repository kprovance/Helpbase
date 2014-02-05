<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Users Online
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseUsersOnline')) {

    class HelpbaseUsersOnline {

        private $helpbase = null;

        public function __construct($parent) {
            $this->helpbase = $parent;
            $this->helpbase->users_online = $this;
        }

        public function initOnline($user_id) {
            global $hesk_settings;

            /* Set user to online */
            $this->setOnline($user_id);

            /* Can this user view online staff? */
            if ($this->helpbase->admin->checkPermission('can_view_online', 0)) {
                $hesk_settings['users_online'] = $this->listOnline();
                $this->helpbase->show_online = true;
            }

            return true;
        }

        public function printOnline() {
            global $hesk_settings;

            echo '
                        &nbsp;
                        <br />
                        &nbsp;
                        <div class="online">
                            <table border="0">
                                <tr>
                                    <td valign="top"><img src="../img/online_on.png" width="16" height="16" alt="' . _('Users Online') . '" title="' . _('Users Online') . '" style="vertical-align:text-bottom" /></td>
                                    <td>';
            $i = '';
            foreach ($hesk_settings['users_online'] as $tmp) {
                $i .= '
                                    <span class="online" ' . ($tmp['isadmin'] ? 'style="font-style:italic;"' : '') . '>';
                $i .= ($tmp['id'] == $_SESSION['id']) ? $tmp['name'] : '<a href="mail.php?a=new&id=' . $tmp['id'] . '">' . $tmp['name'] . '</a>';
                $i .= '</span>, ';
            }
            echo substr($i, 0, -2);
            echo '
                                    </td>
                                </tr>
                            </table>
                        </div>';
        }

        private function listOnline($list_names = 1) {
            global $hesk_settings, $hesk_db_link;

            $users_online = array();

            /* Clean expired entries */
            $this->cleanOnline();

            /* Get a list of online users */
            /* --> With names */
            if ($list_names) {
                $res = $this->helpbase->database->query("SELECT `t1`.`user_id` , `t2`.`name` , `t2`.`isadmin` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online` AS `t1` INNER JOIN `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` AS `t2` ON `t1`.`user_id` = `t2`.`id`");
                while ($tmp = $this->helpbase->database->fetchAssoc($res)) {
                    $users_online[$tmp['user_id']] = array(
                        'id'        => $tmp['user_id'],
                        'name'      => $tmp['name'],
                        'isadmin'   => $tmp['isadmin']
                    );
                }
            }
            /* --> Without names */ else {
                $res = $this->helpbase->database->query("SELECT `user_id` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online`");
                while ($tmp = $this->helpbase->database->fetchAssoc($res)) {
                    $users_online[] = $tmp['user_id'];
                }
            }

            return $users_online;
        }

        private function setOnline($user_id) {
            global $hesk_settings, $hesk_db_link;

            /* If already online just update... */
            $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online` SET `tmp` = `tmp` + 1 WHERE `user_id` = '" . intval($user_id) . "' LIMIT 1");

            /* ... else insert a new entry */
            if (!$this->helpbase->database->affectedRows()) {
                $this->helpbase->database->query("INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online` (`user_id`) VALUES (" . intval($user_id) . ") ");
            }

            return true;
        }

        public function setOffline($user_id) {
            global $hesk_settings, $hesk_db_link;

            /* If already online just update... */
            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online` WHERE `user_id` = '" . intval($user_id) . "' LIMIT 1");

            return true;
        }

        private function cleanOnline() {
            global $hesk_settings, $hesk_db_link;

            /* Delete old rows from the database */
            $this->helpbase->database->query("DELETE FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "online` WHERE `dt` < ( NOW() - INTERVAL " . intval($hesk_settings['online_min']) . " MINUTE) ");

            return true;
        }

    }

}
