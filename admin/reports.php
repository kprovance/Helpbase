<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Reports
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseReports')) {
    class HelpbaseReports {
        private $helpbase               = null;
        private $date_from              = '';
        private $date_to                = '';
        private $can_run_reports_full   = false;
        private $selected               = array();
        private $input_datefrom         = '';
        private $input_dateto           = '';
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase       = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            require($helpbase->includes . 'reporting_functions.inc.php');

            // Check permissions for this feature
            $helpbase->admin->checkPermission('can_run_reports');

            // Should reports be full or limited to own tickets?
            $this->can_run_reports_full = $helpbase->admin->checkPermission('can_run_reports_full', 0);

            // Set default values
            $helpbase->load_calander    = true;
            $helpbase->main_page        = true;
            $helpbase->load_tabs        = true;
            
            $this->generate();
            
            unset($helpbase);
        }
        
        private function generate(){
            $this->selected = array(
                'w' => array(
                    0 => '', 
                    1 => ''
                ),
                'time' => array(
                    1 => '', 
                    2 => '', 
                    3 => '', 
                    4 => '', 
                    5 => '', 
                    6 => '', 
                    7 => '', 
                    8 => '', 
                    9 => '', 
                    10 => '', 
                    11 => '', 
                    12 => ''
                ),
                'type' => array(
                    1 => '', 
                    2 => '', 
                    3 => '', 
                    4 => ''
                ),
            );
            $is_all_time = 0;

            /* Default this month to date */
            $this->date_from = date('Y-m-d', mktime(0, 0, 0, date("m"), 1, date("Y")));
            $this->date_to = date('Y-m-d');
            $this->input_datefrom = date('m/d/Y', strtotime('last month'));
            $this->input_dateto = date('m/d/Y');

            /* Date */
            if (!empty($_GET['w'])) {
                $df = preg_replace('/[^0-9]/', '', $this->helpbase->common->_get('datefrom'));
                if (strlen($df) == 8) {
                    $this->date_from = substr($df, 4, 4) . '-' . substr($df, 0, 2) . '-' . substr($df, 2, 2);
                    $this->input_datefrom = substr($df, 0, 2) . '/' . substr($df, 2, 2) . '/' . substr($df, 4, 4);
                } else {
                    $this->date_from = date('Y-m-d', strtotime('last month'));
                }

                $dt = preg_replace('/[^0-9]/', '', $this->helpbase->common->_get('dateto'));
                if (strlen($dt) == 8) {
                    $this->date_to = substr($dt, 4, 4) . '-' . substr($dt, 0, 2) . '-' . substr($dt, 2, 2);
                    $this->input_dateto = substr($dt, 0, 2) . '/' . substr($dt, 2, 2) . '/' . substr($dt, 4, 4);
                } else {
                    $this->date_to = date('Y-m-d');
                }

                if ($this->date_from > $this->date_to) {
                    $tmp = $this->date_from;
                    $tmp2 = $this->input_datefrom;

                    $this->date_from = $this->date_to;
                    $this->input_datefrom = $this->input_dateto;

                    $this->date_to = $tmp;
                    $this->input_dateto = $tmp2;

                    $note_buffer = _('&quot;Date From&quot; cannot be higher than &quot;Date to&quot;. The dates have been switched.');
                }

                if ($this->date_to > date('Y-m-d')) {
                    $this->date_to = date('Y-m-d');
                    $this->input_dateto = date('m/d/Y');
                }

                $query_string = 'reports.php?w=1&amp;datefrom=' . urlencode($this->input_datefrom) . '&amp;dateto=' . urlencode($this->input_dateto);
                $this->selected['w'][1] = 'checked="checked"';
                $this->selected['time'][3] = 'selected="selected"';
            } else {
                $this->selected['w'][0] = 'checked="checked"';
                $_GET['time'] = intval($this->helpbase->common->_get('time', 3));

                switch ($_GET['time']) {
                    case 1:
                        /* Today */
                        $this->date_from = date('Y-m-d');
                        $this->date_to = $this->date_from;
                        $this->selected['time'][1] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    case 2:
                        /* Yesterday */
                        $this->date_from = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
                        $this->date_to = $this->date_from;
                        $this->selected['time'][2] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    case 4:
                        /* Last month */
                        $this->date_from = date('Y-m-d', mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
                        $this->date_to = date('Y-m-d', mktime(0, 0, 0, date("m"), 0, date("Y")));
                        $this->selected['time'][4] = 'selected="selected"';
                        break;

                    case 5:
                        /* Last 30 days */
                        $this->date_from = date('Y-m-d', mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
                        $this->date_to = date('Y-m-d');
                        $this->selected['time'][5] = 'selected="selected"';
                        break;

                    case 6:
                        /* This week */
                        list($this->date_from, $this->date_to) = dateweek(0);
                        $this->date_to = date('Y-m-d');
                        $this->selected['time'][6] = 'selected="selected"';
                        break;

                    case 7:
                        /* Last week */
                        list($this->date_from, $this->date_to) = dateweek(-1);
                        $this->selected['time'][7] = 'selected="selected"';
                        break;

                    case 8:
                        /* This business week */
                        list($this->date_from, $this->date_to) = dateweek(0, 1);
                        $this->date_to = date('Y-m-d');
                        $this->selected['time'][8] = 'selected="selected"';
                        break;

                    case 9:
                        /* Last business week */
                        list($this->date_from, $this->date_to) = dateweek(-1, 1);
                        $this->selected['time'][9] = 'selected="selected"';
                        break;

                    case 10:
                        /* This year */
                        $this->date_from = date('Y') . '-01-01';
                        $this->date_to = date('Y-m-d');
                        $this->selected['time'][10] = 'selected="selected"';
                        break;

                    case 11:
                        /* Last year */
                        $this->date_from = date('Y') - 1 . '-01-01';
                        $this->date_to = date('Y') - 1 . '-12-31';
                        $this->selected['time'][11] = 'selected="selected"';
                        break;

                    case 12:
                        /* All time */
                        $this->date_from = hesk_getOldestDate();
                        $this->date_to = date('Y-m-d');
                        $this->selected['time'][12] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    default:
                        $_GET['time'] = 3;
                        $this->selected['time'][3] = 'selected="selected"';
                }

                $query_string = 'reports.php?w=0&amp;time=' . $_GET['time'];
            }

            unset($tmp);

            /* Type */
            $type = intval($this->helpbase->common->_get('type', 1));
            if (isset($this->selected['type'][$type])) {
                $this->selected['type'][$type] = 'selected="selected"';
            }
            
            $this->render($type);
        }
        
        private function render($type) {
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
            $this->helpbase->common->handle_messages();
?>
                        <!-- TABS -->
                        <div id="tab1" class="tabberlive" style="margin-top:0px">
                            <ul class="tabbernav">
                                <li class="tabberactive"><a title="<?php echo _('Run reports'); ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo _('The reports section lets you run several reports and see ticket statistics in a chosen date range.'); ?>')"><?php echo _('Run reports'); ?> [?]</a></li>
<?php
            // Show a link to export.php if user has permission to do so
            if ($this->helpbase->admin->checkPermission('can_export', 0)) {
                echo '
                                <li class=""><a title="' . _('Export tickets') . '" href="export.php">' . _('Export tickets') . ' [+]</a></li>';
            }
?>
                            </ul>
                            <div class="tabbertab">
                                &nbsp;

                                <!-- ** START REPORTS FORM ** -->
                                <form action="reports.php" method="get" name="form1">
                                    <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="20%" class="alignTop"><b><?php echo _('Date range'); ?></b>: &nbsp; </td>
                                            <td width="80%">
                                                <!-- START DATE -->
                                                <input type="radio" name="w" value="0" id="w0" <?php echo $this->selected['w'][0]; ?> />
                                                <select name="time" onclick="document.getElementById('w0').checked = true" onfocus="document.getElementById('w0').checked = true" style="margin-top:5px;margin-bottom:5px;">
                                                    <option value="1" <?php echo $this->selected['time'][1]; ?>><?php echo _('Today'); ?> (<?php echo $this->helpbase->common->getWeekday(date('w')); ?>)</option>
                                                    <option value="2" <?php echo $this->selected['time'][2]; ?>><?php echo _('Yesterday'); ?> (<?php echo $this->helpbase->common->getWeekday(date('w', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')))); ?>)</option>
                                                    <option value="3" <?php echo $this->selected['time'][3]; ?>><?php echo _('This month'); ?> (<?php echo $this->helpbase->common->getMonth(date('n')); ?>)</option>
                                                    <option value="4" <?php echo $this->selected['time'][4]; ?>><?php echo _('Last month'); ?> (<?php echo $this->helpbase->common->getMonth(date('n', mktime(0, 0, 0, date('m') - 1, date('d'), date('Y')))); ?>)</option>
                                                    <option value="5" <?php echo $this->selected['time'][5]; ?>><?php echo _('Last 30 days'); ?></option>
                                                    <option value="6" <?php echo $this->selected['time'][6]; ?>><?php echo _('This week (Mon-Sun)'); ?></option>
                                                    <option value="7" <?php echo $this->selected['time'][7]; ?>><?php echo _('Last week (Mon-Sun)'); ?></option>
                                                    <option value="8" <?php echo $this->selected['time'][8]; ?>><?php echo _('This business week (Mon-Fri)'); ?></option>
                                                    <option value="9" <?php echo $this->selected['time'][9]; ?>><?php echo _('Last business week (Mon-Fri)'); ?></option>
                                                    <option value="10" <?php echo $this->selected['time'][10]; ?>><?php echo _('This year'); ?> (<?php echo date('Y'); ?>)</option>
                                                    <option value="11" <?php echo $this->selected['time'][11]; ?>><?php echo _('Last year'); ?> (<?php echo date('Y', mktime(0, 0, 0, date('m'), date('d'), date('Y') - 1)); ?>)</option>
                                                    <option value="12" <?php echo $this->selected['time'][12]; ?>><?php echo _('All time'); ?></option>
                                                </select>
                                                <br />
                                                <input type="radio" name="w" value="1" id="w1" <?php echo $this->selected['w'][1]; ?> />
                                                <?php echo _('From'); ?> <input type="text" name="datefrom" value="<?php echo $this->input_datefrom; ?>" id="datefrom" class="tcal" size="10" onclick="document.getElementById('w1').checked = true" onfocus="document.getElementById('w1').checked = true; this.focus;" />
                                                <?php echo _('to'); ?> <input type="text" name="dateto" value="<?php echo $this->input_dateto; ?>" id="dateto" class="tcal" size="10" onclick="document.getElementById('w1').checked = true" onfocus="document.getElementById('w1').checked = true; this.focus;" />
                                                <!-- END DATE -->
                                            </td>
                                        </tr>
                                        <td width="20%" class="borderTop alignTop"><b><?php echo _('Choose report type:'); ?></b>: &nbsp; </td>
                                        <td width="80%" class="borderTop">
                                            <!-- START TYPE -->
                                            <select name="type" style="margin-top:5px;margin-bottom:5px;">
                                                <option value="1" <?php echo $this->selected['type'][1]; ?>><?php echo _('Tickets per day'); ?></option>
                                                <option value="2" <?php echo $this->selected['type'][2]; ?>><?php echo _('Tickets per month'); ?></option>
                                                <option value="3" <?php echo $this->selected['type'][3]; ?>><?php echo _('Tickets per user'); ?></option>
                                                <option value="4" <?php echo $this->selected['type'][4]; ?>><?php echo _('Tickets per category'); ?></option>
                                            </select>
                                            <!-- END TYPE -->

                                        </td>
                                        </tr>
                                    </table>
                                    <p>
                                        <input type="submit" value="<?php echo _('Display Report'); ?>" class="button blue small" />
                                        <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                    </p>
                                </form>
                                <!-- ** END REPORTS FORM ** -->
                                
                            </div>
                        </div>
                        <!-- TABS -->
                        <p>&nbsp;</p>
<?php
            if ($this->date_from == $this->date_to) {
?>
                        <p><b><?php echo $this->helpbase->admin->dateToString($this->date_from, 0); ?></b></p>
<?php
            } else {
?>
                        <p><b><?php echo $this->helpbase->admin->dateToString($this->date_from, 0); ?></b> - <b><?php echo $this->helpbase->admin->dateToString($this->date_to, 0); ?></b></p>
<?php
            }

            // Show a note if reports are limited
            if (!$this->can_run_reports_full) {
                echo '
                        <p>{' . _('<i>(only tickets assigned to you are included in the report)</i>') . '}</p>';
            }

            /* Report type */
            switch ($type) {
                case 2:
                    $this->ticketsByMonth();
                    break;
                case 3:
                    $this->ticketsByUser();
                    break;
                case 4:
                    $this->ticketsByCategory();
                    break;
                default:
                    $this->ticketsByDay();
            }

            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
        
        private function ticketsByCategory() {
            global $hesk_settings;

            /* List of categories */
            $cat        = array();
            $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $dbDateFrom = $this->helpbase->database->escape($this->date_from);
            $dbDateTo   = $this->helpbase->database->escape($this->date_to);
            
            $res = $this->helpbase->database->query("SELECT `id`,`name` FROM `" . $prefix . "categories` WHERE " . ( $this->can_run_reports_full ? '1' : $this->helpbase->admin->myCategories('id') ) . " ORDER BY `id` ASC");
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                $cat[$row['id']] = $row['name'];
            }

            $tickets = array();

            $totals = array('num_tickets' => 0, 'resolved' => 0, 'all_replies' => 0, 'staff_replies' => 0, 'worked' => 0);

            /* Populate category counts */
            foreach ($cat as $id => $name) {
                $tickets[$id] = array(
                    'num_tickets' => 0,
                    'resolved' => 0,
                    'all_replies' => 0,
                    'staff_replies' => 0,
                    'worked' => '',
                );
            }

            /* SQL query for category stats */
            $res = $this->helpbase->database->query("
                SELECT DISTINCT `t1`.`category`, `t2`.`num_tickets`, `t2`.`seconds_worked` AS `seconds_worked`, IFNULL(`t3`.`all_replies`,0) AS `all_replies`, IFNULL(`t4`.`staff_replies`,0) AS `staff_replies` FROM `" . $prefix . "tickets` AS `t1`
                LEFT JOIN (SELECT COUNT(*) AS `num_tickets`, SUM( TIME_TO_SEC(`time_worked`) ) AS `seconds_worked`, `category` FROM `" . $prefix . "tickets` AS `t1` WHERE DATE(`t1`.`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' " . ( $this->can_run_reports_full ? "" : " AND `t1`.`owner` = '" . intval($_SESSION['id']) . "'" ) . " GROUP BY `category`) AS `t2` ON `t1`.`category`=`t2`.`category`
                LEFT JOIN (SELECT COUNT(*) AS `all_replies`, `t1`.`category` FROM `" . $prefix . "tickets` AS `t1`, `" . $prefix . "replies` AS `t5` WHERE `t1`.`id`=`t5`.`replyto` AND DATE(`t5`.`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' " . ( $this->can_run_reports_full ? "" : " AND `t1`.`owner` = '" . intval($_SESSION['id']) . "'" ) . " GROUP BY `t1`.`category`) AS `t3` ON `t1`.`category`=`t3`.`category`
                LEFT JOIN (SELECT COUNT(*) AS `staff_replies`, `t1`.`category` FROM `" . $prefix . "tickets` AS `t1`, `" . $prefix . "replies` AS `t5` WHERE `t1`.`id`=`t5`.`replyto` AND " . ( $this->can_run_reports_full ? "`t5`.`staffid` > 0" : "`t5`.`staffid` = '" . intval($_SESSION['id']) . "'" ) . " AND DATE(`t5`.`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `t1`.`category`) AS `t4` ON `t1`.`category`=`t4`.`category`
                WHERE DATE(`t1`.`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "'" .
                    ( $this->can_run_reports_full ? "" : " AND `t1`.`owner` = '" . intval($_SESSION['id']) . "'" )
            );

            /* Update ticket values */
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                if (isset($cat[$row['category']])) {
                    $tickets[$row['category']]['num_tickets'] += $row['num_tickets'];
                    $tickets[$row['category']]['all_replies'] += $row['all_replies'];
                    $tickets[$row['category']]['staff_replies'] += $row['staff_replies'];
                    $tickets[$row['category']]['worked'] = hesk_SecondsToHHMMSS($row['seconds_worked']);
                } else {
                    /* Category deleted */
                    if (!isset($tickets[9999])) {
                        $cat[9999] = _('(category deleted)');
                        $tickets[9999] = array('num_tickets' => $row['num_tickets'], 'resolved' => 0, 'all_replies' => $row['all_replies'], 'staff_replies' => $row['staff_replies'], 'worked' => $row['seconds_worked']);
                    } else {
                        $tickets[9999]['num_tickets'] += $row['num_tickets'];
                        $tickets[9999]['all_replies'] += $row['all_replies'];
                        $tickets[9999]['staff_replies'] += $row['staff_replies'];
                        $tickets[9999]['worked'] += $row['seconds_worked'];
                    }
                }

                $totals['num_tickets'] += $row['num_tickets'];
                $totals['all_replies'] += $row['all_replies'];
                $totals['staff_replies'] += $row['staff_replies'];
                $totals['worked'] += $row['seconds_worked'];
            }

            // Get number of resolved tickets
            $res = $this->helpbase->database->query("SELECT COUNT(*) AS `num_tickets` , `category` FROM `" . $prefix . "tickets` WHERE `status` = '3' " . ( $this->can_run_reports_full ? "" : " AND `owner` = '" . intval($_SESSION['id']) . "'" ) . " AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `category`");

            // Update number of open and resolved tickets
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                if (isset($cat[$row['category']])) {
                    $tickets[$row['category']]['resolved'] += $row['num_tickets'];
                } else {
                    // Category deleted
                    $tickets[9999]['resolved'] += $row['num_tickets'];
                }

                $totals['resolved'] += $row['num_tickets'];
            }

            // Convert total seconds worked to HH:MM:SS
            $totals['worked'] = hesk_SecondsToHHMMSS($totals['worked']);
            if (isset($tickets[9999])) {
                $tickets[9999]['worked'] = hesk_SecondsToHHMMSS($tickets[9999]['worked']);
            }
?>
                        <table width="100%" cellpadding="5" style="text-align:justify;border-collapse:collapse;padding:10px;">
                            <tr style="border-bottom:1px solid #000000;">
                                <td><?php echo _('Category'); ?></td>
                                <td><?php echo _('Tickets'); ?></td>
                                <td><?php echo _('Open'); ?></td>
                                <td><?php echo _('Closed'); ?></td>
                                <td><?php echo _('Replies') . ' (' . _('All') . ')'; ?></td>
                                <td><?php echo _('Replies') . ' (' . _('Staff') . ')'; ?></td>
                                <td><?php echo _('Time worked'); ?></td>
                            </tr>
<?php
            $num_tickets = count($tickets);
            if ($num_tickets > 10) {
?>
                            <tr style="border-bottom:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['num_tickets']; ?></b></td>
                                <td><b><?php echo $totals['num_tickets'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['all_replies']; ?></b></td>
                                <td><b><?php echo $totals['staff_replies']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
<?php
            }
            
            $cls = '';
            foreach ($tickets as $k => $d) {
                $cls = $cls ? '' : 'style="background:#EEEEE8;"';
?>
                            <tr <?php echo $cls; ?>>
                                <td><?php echo $cat[$k]; ?></td>
                                <td><?php echo $d['num_tickets']; ?></td>
                                <td><?php echo $d['num_tickets'] - $d['resolved']; ?></td>
                                <td><?php echo $d['resolved']; ?></td>
                                <td><?php echo $d['all_replies']; ?></td>
                                <td><?php echo $d['staff_replies']; ?></td>
                                <td><?php echo $d['worked']; ?></td>
                            </tr>
<?php
            }
?>
                            <tr style="border-top:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['num_tickets']; ?></b></td>
                                <td><b><?php echo $totals['num_tickets'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['all_replies']; ?></b></td>
                                <td><b><?php echo $totals['staff_replies']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
                        </table>
                        <p>&nbsp;</p>
<?php
        }

        private function ticketsByUser() {
            global $hesk_settings;

            // Some variables we will need
            $tickets = array();
            $totals = array('asstickets' => 0, 'resolved' => 0, 'tickets' => 0, 'replies' => 0, 'worked' => 0);

            // Get list of users
            $admins = array();

            $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $dbDateFrom = $this->helpbase->database->escape($this->date_from);
            $dbDateTo   = $this->helpbase->database->escape($this->date_to);
            
            // I. ADMINISTRATORS can view all users
            if ($_SESSION['isadmin'] || $this->helpbase->admin->checkPermission('can_run_reports_full', 0)) {
                // -> get list of users
                $res = $this->helpbase->database->query("SELECT `id`,`name` FROM `" .$prefix . "users` ORDER BY `id` ASC");

                // -> populate $admins and $tickets arrays
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $admins[$row['id']] = $row['name'];

                    $tickets[$row['id']] = array(
                        'asstickets' => 0,
                        'resolved' => 0,
                        'tickets' => 0,
                        'replies' => 0,
                        'worked' => '',
                    );
                }

                // -> get list of tickets
                $res = $this->helpbase->database->query("SELECT `owner`, COUNT(*) AS `cnt`, SUM( TIME_TO_SEC(`time_worked`) ) AS `seconds_worked` FROM `" . $prefix . "tickets` WHERE `owner` IN ('" . implode("','", array_keys($admins)) . "') AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `owner`");

                // -> update ticket list values
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $tickets[$row['owner']]['asstickets'] += $row['cnt'];
                    $totals['asstickets'] += $row['cnt'];
                    $tickets[$row['owner']]['worked'] = hesk_SecondsToHHMMSS($row['seconds_worked']);
                    $totals['worked'] += $row['seconds_worked'];
                }

                // -> get list of resolved tickets
                $res = $this->helpbase->database->query("SELECT `owner`, COUNT(*) AS `cnt` FROM `" . $prefix . "tickets` WHERE `owner` IN ('" . implode("','", array_keys($admins)) . "') AND `status`='3' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `owner`");

                // -> update resolved ticket list values
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $tickets[$row['owner']]['resolved'] += $row['cnt'];
                    $totals['resolved'] += $row['cnt'];
                }

                // -> get number of replies
                $res = $this->helpbase->database->query("SELECT `staffid`, COUNT(*) AS `cnt`, COUNT(DISTINCT `replyto`) AS `tcnt` FROM `" . $prefix . "replies` WHERE `staffid` IN ('" . implode("','", array_keys($admins)) . "') AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `staffid`");

                // -> update number of replies values
                while ($row = $this->helpbase->database->fetchAssoc($res)) {
                    $tickets[$row['staffid']]['tickets'] += $row['tcnt'];
                    $tickets[$row['staffid']]['replies'] += $row['cnt'];

                    $totals['tickets'] += $row['tcnt'];
                    $totals['replies'] += $row['cnt'];
                }
            } else { // II. OTHER STAFF may only see their own stats
                $admins[$_SESSION['id']] = $_SESSION['name'];

                // -> get list of tickets
                $res = $this->helpbase->database->query("SELECT COUNT(*) AS `cnt`, SUM( TIME_TO_SEC(`time_worked`) ) AS `seconds_worked` FROM `" . $prefix . "tickets` WHERE `owner` = '" . intval($_SESSION['id']) . "' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "'");
                $row = $this->helpbase->database->fetchAssoc($res);

                // -> update ticket values
                $tickets[$_SESSION['id']]['asstickets'] = $row['cnt'];
                $totals['asstickets'] = $row['cnt'];
                $tickets[$_SESSION['id']]['worked'] = hesk_SecondsToHHMMSS($row['seconds_worked']);
                $totals['worked'] += $row['seconds_worked'];

                // -> get list of resolved tickets
                $res = $this->helpbase->database->query("SELECT COUNT(*) AS `cnt` FROM `" . $prefix . "tickets` WHERE `owner` = '" . intval($_SESSION['id']) . "' AND `status`='3' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "'");
                $row = $this->helpbase->database->fetchAssoc($res);

                // -> update resolved ticket values
                $tickets[$_SESSION['id']]['resolved'] = $row['cnt'];
                $totals['resolved'] = $row['cnt'];

                // -> get number of replies
                $res = $this->helpbase->database->query("SELECT COUNT(*) AS `cnt`, COUNT(DISTINCT `replyto`) AS `tcnt` FROM `" . $prefix . "replies` WHERE `staffid` = '" . intval($_SESSION['id']) . "' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "'");
                $row = $this->helpbase->database->fetchAssoc($res);

                $tickets[$_SESSION['id']]['tickets'] = $row['tcnt'];
                $tickets[$_SESSION['id']]['replies'] = $row['cnt'];

                $totals['tickets'] = $row['tcnt'];
                $totals['replies'] = $row['cnt'];

                // Convert total seconds worked to HH:MM:SS
                $totals['worked'] = hesk_SecondsToHHMMSS($totals['worked']);
            }
?>
                        <table width="100%" cellpadding="5" style="text-align:justify;border-collapse:collapse;padding:10px;">
                            <tr style="border-bottom:1px solid #000000;">
                                <td><?php echo _('User'); ?></td>
                                <td><?php echo _('Assigned tickets'); ?></td>
                                <td><?php echo _('Open'); ?></td>
                                <td><?php echo _('Closed'); ?></td>
                                <td><?php echo _('Replied to tickets'); ?></td>
                                <td><?php echo _('Replies'); ?></td>
                                <td><?php echo _('Time worked'); ?></td>
                            </tr>
<?php
            $num_tickets = count($tickets);
            if ($num_tickets > 10) {
?>
                            <tr style="border-bottom:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['asstickets']; ?></b></td>
                                <td><b><?php echo $totals['asstickets'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['tickets']; ?></b></td>
                                <td><b><?php echo $totals['replies']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
<?php
            }

            $cls = '';
            foreach ($tickets as $k => $d) {
                $cls = $cls ? '' : 'style="background:#EEEEE8;"';
?>
                            <tr <?php echo $cls; ?>>
                                <td><?php echo $admins[$k]; ?></td>
                                <td><?php echo $d['asstickets']; ?></td>
                                <td><?php echo $d['asstickets'] - $d['resolved']; ?></td>
                                <td><?php echo $d['resolved']; ?></td>
                                <td><?php echo $d['tickets']; ?></td>
                                <td><?php echo $d['replies']; ?></td>
                                <td><?php echo $d['worked']; ?></td>
                            </tr>
<?php
            }
?>
                            <tr style="border-top:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['asstickets']; ?></b></td>
                                <td><b><?php echo $totals['asstickets'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['tickets']; ?></b></td>
                                <td><b><?php echo $totals['replies']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
                        </table>
                        <p>&nbsp;</p>
<?php
        }

        function ticketsByMonth() {
            global $hesk_settings;

            $tickets = array();
            $totals = array('all' => 0, 'resolved' => 0, 'worked' => 0);
            $dt = MonthsArray($this->date_from, $this->date_to);

            // Pre-populate date values
            foreach ($dt as $month) {
                $tickets[$month] = array(
                    'all' => 0,
                    'resolved' => 0,
                    'worked' => '',
                );
            }

            $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $dbDateFrom = $this->helpbase->database->escape($this->date_from);
            $dbDateTo   = $this->helpbase->database->escape($this->date_to);
            
            // SQL query for all
            $res = $this->helpbase->database->query("SELECT YEAR(`dt`) AS `myyear`, MONTH(`dt`) AS `mymonth`, COUNT(*) AS `cnt`, SUM( TIME_TO_SEC(`time_worked`) ) AS `seconds_worked` FROM `" . $prefix. "tickets` WHERE " . ( $this->can_run_reports_full ? '1' : "`owner` = '" . intval($_SESSION['id']) . "'" ) . " AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `myyear`,`mymonth`");

            // Update ticket values
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                $row['mymonth'] = sprintf('%02d', $row['mymonth']);
                $tickets[$row['myyear'] . '-' . $row['mymonth'] . '-01']['all'] += $row['cnt'];
                $tickets[$row['myyear'] . '-' . $row['mymonth'] . '-01']['worked'] = hesk_SecondsToHHMMSS($row['seconds_worked']);
                $totals['all'] += $row['cnt'];
                $totals['worked'] += $row['seconds_worked'];
            }

            // SQL query for resolved
            $res = $this->helpbase->database->query("SELECT YEAR(`dt`) AS `myyear`, MONTH(`dt`) AS `mymonth`, COUNT(*) AS `cnt` FROM `" . $prefix . "tickets` WHERE " . ( $this->can_run_reports_full ? '1' : "`owner` = '" . intval($_SESSION['id']) . "'" ) . " AND `status` = '3' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `myyear`,`mymonth`");

            // Update ticket values
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                $row['mymonth'] = sprintf('%02d', $row['mymonth']);
                $tickets[$row['myyear'] . '-' . $row['mymonth'] . '-01']['resolved'] += $row['cnt'];
                $totals['resolved'] += $row['cnt'];
            }

            // Convert total seconds worked to HH:MM:SS
            $totals['worked'] = hesk_SecondsToHHMMSS($totals['worked']);
?>
                        <table width="100%" cellpadding="5" style="text-align:justify;border-collapse:collapse;padding:10px;">
                            <tr style="border-bottom:1px solid #000000;">
                                <td><?php echo _('Month'); ?></td>
                                <td><?php echo _('New tickets'); ?></td>
                                <td><?php echo _('Open'); ?></td>
                                <td><?php echo _('Closed'); ?></td>
                                <td><?php echo _('Time worked'); ?></td>
                            </tr>
<?php
            $num_tickets = count($tickets);
            if ($num_tickets > 10) {
?>
                            <tr style="border-bottom:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['all']; ?></b></td>
                                <td><b><?php echo $totals['all'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
<?php
            }

            $cls = '';
            foreach ($tickets as $k => $d) {
                $cls = $cls ? '' : 'style="background:#EEEEE8;"';
?>
                            <tr <?php echo $cls; ?>>
                                <td><?php echo $this->helpbase->admin->dateToString($k, 0, 0, 1); ?></td>
                                <td><?php echo $d['all']; ?></td>
                                <td><?php echo $d['all'] - $d['resolved']; ?></td>
                                <td><?php echo $d['resolved']; ?></td>
                                <td><?php echo $d['worked']; ?></td>
                            </tr>
<?php
            }
?>
                            <tr style="border-top:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['all']; ?></b></td>
                                <td><b><?php echo $totals['all'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
                        </table>
                        <p>&nbsp;</p>
<?php
        }

        function ticketsByDay() {
            global $hesk_settings;

            $tickets = array();
            $totals = array('all' => 0, 'resolved' => 0, 'worked' => 0);
            $dt = DateArray($this->date_from, $this->date_to);

            // Pre-populate date values
            foreach ($dt as $day) {
                $tickets[$day] = array(
                    'all' => 0,
                    'resolved' => 0,
                    'worked' => '',
                );
            }

            $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            $dbDateFrom = $this->helpbase->database->escape($this->date_from);
            $dbDateTo   = $this->helpbase->database->escape($this->date_to);
            
            // SQL query for all
            $res = $this->helpbase->database->query("SELECT DATE(`dt`) AS `mydt`, COUNT(*) AS `cnt`, SUM( TIME_TO_SEC(`time_worked`) ) AS `seconds_worked` FROM `" . $prefix . "tickets` WHERE " . ( $this->can_run_reports_full ? '1' : "`owner` = '" . intval($_SESSION['id']) . "'" ) . " AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `mydt`");

            // Update ticket values
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                $tickets[$row['mydt']]['all'] += $row['cnt'];
                $tickets[$row['mydt']]['worked'] = hesk_SecondsToHHMMSS($row['seconds_worked']);
                $totals['all'] += $row['cnt'];
                $totals['worked'] += $row['seconds_worked'];
            }

            // SQL query for resolved
            $res = $this->helpbase->database->query("SELECT DATE(`dt`) AS `mydt`, COUNT(*) AS `cnt` FROM `" . $prefix . "tickets` WHERE " . ( $this->can_run_reports_full ? '1' : "`owner` = '" . intval($_SESSION['id']) . "'" ) . " AND `status`='3' AND DATE(`dt`) BETWEEN '" . $dbDateFrom . "' AND '" . $dbDateTo . "' GROUP BY `mydt`");

            // Update ticket values
            while ($row = $this->helpbase->database->fetchAssoc($res)) {
                $tickets[$row['mydt']]['resolved'] += $row['cnt'];
                $totals['resolved'] += $row['cnt'];
            }

            // Convert total seconds worked to HH:MM:SS
            $totals['worked'] = hesk_SecondsToHHMMSS($totals['worked']);
?>
                        <table width="100%" cellpadding="5" style="text-align:justify;border-collapse:collapse;padding:10px;">
                            <tr style="border-bottom:1px solid #000000;">
                                <td><?php echo _('Date'); ?></td>
                                <td><?php echo _('New tickets'); ?></td>
                                <td><?php echo _('Open'); ?></td>
                                <td><?php echo _('Closed'); ?></td>
                                <td><?php echo _('Time worked'); ?></td>
                            </tr>
<?php
            $num_tickets = count($tickets);
            if ($num_tickets > 10) {
?>
                            <tr style="border-bottom:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['all']; ?></b></td>
                                <td><b><?php echo $totals['all'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
<?php
            }
            
            $cls = '';
            foreach ($tickets as $k => $d) {
                $cls = $cls ? '' : 'style="background:#EEEEE8;"';
?>
                            <tr <?php echo $cls; ?>>
                                <td><?php echo $this->helpbase->admin->dateToString($k); ?></td>
                                <td><?php echo $d['all']; ?></td>
                                <td><?php echo $d['all'] - $d['resolved']; ?></td>
                                <td><?php echo $d['resolved']; ?></td>
                                <td><?php echo $d['worked']; ?></td>
                            </tr>
<?php
            }
?>
                            <tr style="border-top:1px solid #000000;">
                                <td><b><?php echo _('Totals'); ?></b></td>
                                <td><b><?php echo $totals['all']; ?></b></td>
                                <td><b><?php echo $totals['all'] - $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['resolved']; ?></b></td>
                                <td><b><?php echo $totals['worked']; ?></b></td>
                            </tr>
                        </table>
                        <p>&nbsp;</p>
<?php
        }
    }
    
    new HelpbaseReports;
}

?>
