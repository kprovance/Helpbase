<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Ticket List
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if(!class_exists('HelpbaseTicketList')) {
    class HelpbaseTicketList {
        private $helpbase           = null;
        private $mysql_time         = '';
        private $ticket_query       = '';
        
        public $sql                 = '';
        public $sql_count           = 0;
        public $href                = '';
        public $query               = '';
        public $fields              = '';
        public $category            = '';
        public $date_input          = '';
        public $sort                = '';
        public $asc                 = 0;
        public $max_results         = 0;
        public $archive             = array();
        public $my_tickets          = array();
        public $others_tickets      = array();
        public $unassigned_tickets  = array();
        public $page                = 1;
        public $group               = '';
        public $sort_possible       = array();
        public $critical_on_top     = false;
        public $status              = array();
        public $priority            = array();
        public $is_search           = false;

        public function __construct($parent){
            $this->helpbase = $parent;
        }

        public function list_tickets(){
            global $hesk_settings;

            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);

            /* List of staff */
            if (!isset($admins)) {
                $admins = array();
                $res2 = $this->helpbase->database->query("SELECT `id`,`name` FROM `" . $prefix . "users` ORDER BY `id` ASC");
                while ($row = $this->helpbase->database->fetchAssoc($res2)) {
                    $admins[$row['id']] = $row['name'];
                }
            }

            /* List of categories */
            $hesk_settings['categories'] = array();
            $catID = $this->helpbase->admin->myCategories('id');

            $res2 = $this->helpbase->database->query('SELECT `id`, `name` FROM `' . $prefix . 'categories` WHERE ' . $catID . ' ORDER BY `cat_order` ASC');
            while ($row = $this->helpbase->database->fetchAssoc($res2)) {
                $hesk_settings['categories'][$row['id']] = $row['name'];
            }

            /* Current MySQL time */
            $this->mysql_time = $this->helpbase->database->_time();

            /* Get number of tickets and page number */
            $result = $this->helpbase->database->query($this->sql_count);
            $total = $this->helpbase->database->result($result);

            if ($total > 0) {

                /* This query string will be used to browse pages */
                if ($this->href == 'show_tickets.php') {
                    $this->ticket_query = '';
                    $this->ticket_query .= 's' . implode('=1&amp;s', array_keys($this->status)) . '=1';
                    $this->ticket_query .= '&amp;p' . implode('=1&amp;p', array_keys($priority)) . '=1';

                    $this->ticket_query .= '&amp;category=' . $this->category;
                    $this->ticket_query .= '&amp;sort=' . $this->sort;
                    $this->ticket_query .= '&amp;asc=' . $this->asc;
                    $this->ticket_query .= '&amp;limit=' . $this->max_results;
                    $this->ticket_query .= '&amp;archive=' . $this->archive[1];
                    $this->ticket_query .= '&amp;my_tickets=' . $this->my_tickets[1];
                    $this->ticket_query .= '&amp;others_tickets=' . $this->others_tickets[1];
                    $this->ticket_query .= '&amp;unassigned_tickets=' . $this->unassigned_tickets[1];

                    $this->ticket_query .= '&amp;cot=' . $this->critical_on_top;
                    $this->ticket_query .= '&amp;g=' . $this->group;

                    $this->ticket_query .= '&amp;page=';
                } else {
                    $this->ticket_query = 'q=' . $this->query;
                    $this->ticket_query .= '&amp;what=' . $this->fields;
                    $this->ticket_query .= '&amp;category=' . $this->category;
                    $this->ticket_query .= '&amp;dt=' . urlencode($this->date_input);
                    $this->ticket_query .= '&amp;sort=' . $this->sort;
                    $this->ticket_query .= '&amp;asc=' . $this->asc;
                    $this->ticket_query .= '&amp;limit=' . $this->max_results;
                    $this->ticket_query .= '&amp;archive=' . $this->archive[2];
                    $this->ticket_query .= '&amp;my_tickets=' . $this->my_tickets[2];
                    $this->ticket_query .= '&amp;others_tickets=' . $this->others_tickets[2];
                    $this->ticket_query .= '&amp;unassigned_tickets=' . $this->unassigned_tickets[2];
                    $this->ticket_query .= '&amp;page=';
                }

                $pages = ceil($total / $this->max_results) or $pages = 1;
                if ($this->page > $pages) {
                    $this->page = $pages;
                }
                $limit_down = ($this->page * $this->max_results) - $this->max_results;

                $prev_page = ($this->page - 1 <= 0) ? 0 : $this->page - 1;
                $next_page = ($this->page + 1 > $pages) ? 0 : $this->page + 1;

                if ($pages > 1) {
                    echo '
                        <p align="center">' . sprintf(_('Number of tickets: %d | Number of pages: %d'), $total, $pages) . ' | Jump to page:';
                    echo '
                            <select name="myHpage" id="myHpage">';
                    for ($i = 1; $i <= $pages; $i++) {
                        $tmp = ($this->page == $i) ? ' selected="selected"' : '';
                        echo '
                                <option value="' . $i . '"' . $tmp . '>' . $i . '</option>';
                    }
                    echo '
                            </select> 
                            <input type="button" value="' . _('Go') . '" onclick="javascript:window.location=\'' . $this->href . '?' . $this->ticket_query . '\'+document.getElementById(\'myHpage\').value" class="button blue small" /><br />';

                    /* List pages */
                    if ($pages > 7) {
                        if ($this->page > 2) {
                            echo '
                            <a href="' . $this->href . '?' . $this->ticket_query . '1"><b>&laquo;</b></a> &nbsp; ';
                        }

                        if ($prev_page) {
                            echo '
                            <a href="' . $this->href . '?' . $this->ticket_query . $prev_page . '"><b>&lsaquo;</b></a> &nbsp; ';
                        }
                    }

                    for ($i = 1; $i <= $pages; $i++) {
                        if ($i <= ($this->page + 5) && $i >= ($this->page - 5)) {
                            if ($i == $this->page) {
                                echo ' 
                            <b>' . $i . '</b> ';
                            } else {
                                echo ' 
                            <a href="' . $this->href . '?' . $this->ticket_query . $i . '">' . $i . '</a> ';
                            }
                        }
                    }

                    if ($pages > 7) {
                        if ($next_page) {
                            echo ' 
                            &nbsp; 
                            <a href="' . $this->href . '?' . $this->ticket_query . $next_page . '"><b>&rsaquo;</b></a> ';
                        }

                        if ($this->page < ($pages - 1)) {
                            echo ' 
                            &nbsp; 
                            <a href="' . $this->href . '?' . $this->ticket_query . $pages . '"><b>&raquo;</b></a>';
                        }
                    }

                    echo '
                        </p>';
                }
                else {
                    echo '
                        <p align="center">' . sprintf(_('Number of tickets: %d | Number of pages: %d'), $total, $pages) . ' </p>
';
                }

                /* We have the full SQL query now, get tickets */
                $this->sql .= " LIMIT " . $this->helpbase->database->escape($limit_down) . " , " . $this->helpbase->database->escape($this->max_results) . " ";
                $result = $this->helpbase->database->query($this->sql);

                /* Uncomment for debugging */
                # echo "SQL: $this->sql\n<br>";

                /* This query string will be used to order and reverse display */
                if ($this->href == 'show_tickets.php') {
                    #$this->ticket_query  = 'status='.$this->status;

                    $this->ticket_query = '';
                    $this->ticket_query .= 's' . implode('=1&amp;s', array_keys($this->status)) . '=1';
                    $this->ticket_query .= '&amp;p' . implode('=1&amp;p', array_keys($priority)) . '=1';

                    $this->ticket_query .= '&amp;category=' . $this->category;
                    #$this->ticket_query .= '&amp;asc='.(isset($is_default) ? 1 : $asc_rev);
                    $this->ticket_query .= '&amp;limit=' . $this->max_results;
                    $this->ticket_query .= '&amp;archive=' . $this->archive[1];
                    $this->ticket_query .= '&amp;my_tickets=' . $this->my_tickets[1];
                    $this->ticket_query .= '&amp;others_tickets=' . $this->others_tickets[1];
                    $this->ticket_query .= '&amp;unassigned_tickets=' . $this->unassigned_tickets[1];
                    $this->ticket_query .= '&amp;page=1';
                    #$this->ticket_query .= '&amp;sort=';

                    $this->ticket_query .= '&amp;cot=' . $this->critical_on_top;
                    $this->ticket_query .= '&amp;g=' . $this->group;
                } else {
                    $this->ticket_query = 'q=' . $this->query;
                    $this->ticket_query .= '&amp;what=' . $this->fields;
                    $this->ticket_query .= '&amp;category=' . $this->category;
                    $this->ticket_query .= '&amp;dt=' . urlencode($this->date_input);
                    #$this->ticket_query .= '&amp;asc='.$asc;
                    $this->ticket_query .= '&amp;limit=' . $this->max_results;
                    $this->ticket_query .= '&amp;archive=' . $this->archive[2];
                    $this->ticket_query .= '&amp;my_tickets=' . $this->my_tickets[2];
                    $this->ticket_query .= '&amp;others_tickets=' . $this->others_tickets[2];
                    $this->ticket_query .= '&amp;unassigned_tickets=' . $this->unassigned_tickets[2];
                    $this->ticket_query .= '&amp;page=1';
                    #$this->ticket_query .= '&amp;sort=';
                }

                $this->ticket_query .= '&amp;asc=';

                /* Print the table with tickets */
                $random = rand(10000, 99999);
?>
                        <form name="form1" action="delete_tickets.php" method="post" onsubmit="return hb_confirmExecute('<?php echo _('Are you sure you want to continue?'); ?>')">
<?php
                if (empty($this->group)) {
                    $this->print_list_head();
                }

                $i = 0;
                $checkall = '<input type="checkbox" name="checkall" value="2" onclick="hb_changeAll()" />';

                $group_tmp = '';
                $is_table = 0;
                $space = 0;

                while ($ticket = $this->helpbase->database->fetchAssoc($result)) {

                    if ($this->group) {
                        include_once($this->helpbase->includes . 'print_group.inc.php');
                        $print_group            = new HelpbasePrintGroup;
                        $print_group->group     = $this->group;
                        $print_group->group_tmp = $group_tmp;
                        $print_group->is_table  = $is_table;
                        $print_group->space     = $space;

                        $print_group->print_group();

                        unset ($print_group);
                    }

                    if ($i) {
                        $color = "admin_gray";
                        $i = 0;
                    } else {
                        $color = "admin_white";
                        $i = 1;
                    }

                    $owner = '';
                    $first_line = '(' . _('Unassigned') . ')' . " \n\n";
                    if ($ticket['owner'] == $_SESSION['id']) {
                        $owner = '<span class="assignedyou" title="' . _('Assigned to me') . '">*</span> ';
                        $first_line = _('Assigned to me') . " \n\n";
                    } elseif ($ticket['owner']) {
                        if (!isset($admins[$ticket['owner']])) {
                            $admins[$ticket['owner']] = _('(User deleted)');
                        }
                        $owner = '<span class="assignedother" title="' . _('Assigned to:') . ' ' . $admins[$ticket['owner']] . '">*</span> ';
                        $first_line = _('Assigned to:') . ' ' . $admins[$ticket['owner']] . " \n\n";
                    }

                    $tagged = '';
                    if ($ticket['archive']) {
                        $tagged = '<img src="' . $this->helpbase->url . 'img/tag.png" width="16" height="16" alt="' . _('Tagged') . '" title="' . _('Tagged') . '"  border="0" style="vertical-align:text-bottom" /> ';
                    }

                    switch ($ticket['status']) {
                        case 0:
                            $ticket['status'] = '<span class="open">' . _('New') . '</span>';
                            break;
                        case 1:
                            $ticket['status'] = '<span class="waitingreply">' . _('Awaiting reply') . '</span>';
                            break;
                        case 2:
                            $ticket['status'] = '<span class="replied">' . _('Replied') . '</span>';
                            break;
                        case 4:
                            $ticket['status'] = '<span class="inprogress">' . _('On the bench') . '</span>';
                            break;
                        case 5:
                            $ticket['status'] = '<span class="onhold">' . _('On hold') . '</span>';
                            break;
                        case 6:
                            $ticket['status'] = '<span class="waitforpayment">' . _('Waiting for payment') . '</span>';
                            break;
                        case 7:
                            $ticket['status'] = '<span class="waitingforbench">' . _('Waiting for bench') . '</span>';
                            break;
                        case 8:
                            $ticket['status'] = '<span class="servicecall">' . _('Service call') . '</span>';
                            break;
                        case 9:
                            $ticket['status'] = '<span class="remotesupport">' . _('Remote support') . '</span>';
                            break;
                        case 10:
                            $ticket['status'] = '<span class="readyforpickup">' . _('Ready for pickup') . '</span>';
                            break;
                        default:
                            $ticket['status'] = '<span class="resolved">' . _('Closed') . '</span>';
                    }

                    switch ($ticket['priority']) {
                        case 0:
                            $ticket['priority'] = '<img src="' . $this->helpbase->url . 'img/flag_critical.png" width="16" height="16" alt="' . _('Priority') . ': ' . _(' * Critical * ') . '" title="' . _('Priority'). _(' * Critical * ') . '" border="0" />';
                            $color = 'admin_critical';
                            break;
                        case 1:
                            $ticket['priority'] = '<img src="' . $this->helpbase->url . 'img/flag_high.png" width="16" height="16" alt="' . _('Priority') . ': ' . _('High') . '" title="' . _('Priority') . ': ' . _('High') . '" border="0" />';
                            break;
                        case 2:
                            $ticket['priority'] = '<img src="' . $this->helpbase->url . 'img/flag_medium.png" width="16" height="16" alt="' . _('Priority') . ': ' . _('Medium') . '" title="' . _('Priority') . ': ' . _('Medium') . '" border="0" />';
                            break;
                        default:
                            $ticket['priority'] = '<img src="' . $this->helpbase->url . 'img/flag_low.png" width="16" height="16" alt="' . _('Priority') . ': ' . _('Low') . '" title="' . _('Priority') . ': ' . _('Low') . '" border="0" />';
                    }

                    $ticket['lastchange'] = $this->time_since(strtotime($ticket['lastchange']));

                    if ($ticket['lastreplier']) {
                        $ticket['repliername'] = isset($admins[$ticket['replierid']]) ? $admins[$ticket['replierid']] : _('Staff');
                    } else {
                        $ticket['repliername'] = $ticket['name'];
                    }

                    $ticket['archive'] = !($ticket['archive']) ? _('NO') : _('YES');

                    $ticket['message'] = $first_line . substr(strip_tags($ticket['message']), 0, 200) . '...';

                    echo <<<EOC
                                <tr title="$ticket[message]">
                                    <td class="$color" style="text-align:left; white-space:nowrap;"><input type="checkbox" name="id[]" value="$ticket[id]" />&nbsp;</td>
                                    <td class="$color" style="text-align:left; white-space:nowrap;"><a href="admin_ticket.php?track=$ticket[trackid]&amp;refresh=$random">$ticket[trackid]</a></td>
                                    <td class="$color">$ticket[lastchange]</td>
                                    <td class="$color">$ticket[name]</td>
                                    <td class="$color">$tagged$owner<a href="admin_ticket.php?track=$ticket[trackid]&amp;refresh=$random">$ticket[subject]</a></td>
                                    <td class="$color">$ticket[status]&nbsp;</td>
                                    <td class="$color">$ticket[repliername]</td>
                                    <td class="$color" style="text-align:center; white-space:nowrap;">$ticket[priority]&nbsp;</td>
                                </tr>

EOC;
                }
?>
                            </table>
                        </div>
                        &nbsp;
                        <br />

                        <table border="0" width="100%">
                            <tr>
                                <td width="50%" style="text-align:left;vertical-align:top">
<?php
                if ($this->helpbase->admin->checkPermission('can_add_archive', 0)) {
?>
                                    <img src=" <?php echo $this->helpbase->url; ?>img/tag.png" width="16" height="16" alt="<?php echo _('Tagged'); ?>" title="<?php echo _('Tagged'); ?>"  border="0"  style="vertical-align:text-bottom" /> <?php echo _('Tagged Ticket'); ?><br />
<?php
                }
?>
                                    <span class="assignedyou">*</span> <?php echo _('Assigned to me'); ?><br />
<?php
                if ($this->helpbase->admin->checkPermission('can_view_ass_others', 0)) {
?>
                                    <span class="assignedother">*</span> <?php echo _('Assigned to other staff'); ?><br />
<?php
                }
?>
                                    &nbsp;
                                </td>
                                <td width="50%" style="text-align:right;vertical-align:top">
                                    <select name="a">
                                        <option value="close" selected="selected"><?php echo _('Mark selected tickets Closed'); ?></option>
<?php
                if ($this->helpbase->admin->checkPermission('can_add_archive', 0)) {
?>
                                        <option value="tag"><?php echo _('Tag selected tickets'); ?></option>
                                        <option value="untag"><?php echo _('Untag selected tickets'); ?></option>
<?php
                }

                if (false == $this->helpbase->demo_mode) {
                    if ($this->helpbase->admin->checkPermission('can_merge_tickets', 0)) {
?>
                                        <option value="merge"><?php echo _('Merge selected tickets'); ?></option>
<?php
                    }
                    if ($this->helpbase->admin->checkPermission('can_del_tickets', 0)) {
?>
                                        <option value="delete"><?php echo _('Delete selected tickets'); ?></option>
<?php
                    }
                }
?>
                                    </select>
                                    <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
                                    <input type="submit" value="<?php echo _('Execute'); ?>" class="button blue small" />
                                </td>
                            </tr>
                        </table>
                    </form>
<?php
            } else {
                if (isset($this->is_search) || $this->href == 'find_tickets.php') {
                    $this->helpbase->common->show_notice(_('No tickets found matching your criteria'));
                } else {
                    echo '
                    <p>&nbsp;<br />&nbsp;<b><i>' . _('No closed tickets found') . '</i></b><br />&nbsp;</p>';
                }
            }
        }

        public function print_list_head() {
?>
                        <div align="center">
                            <table border="0" width="100%" cellspacing="1" cellpadding="3" class="white">
                                <tr>
                                    <th class="admin_white" style="width:1px">
                                        <input type="checkbox" name="checkall" value="2" onclick="hb_changeAll(this)" />
                                    </th>
                                    <th class="admin_white" style="text-align:left; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['trackid'] . '&amp;sort='; ?>trackid"><?php echo _('Tracking ID'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:left; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['lastchange'] . '&amp;sort='; ?>lastchange"><?php echo _('Updated'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:left; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['name'] . '&amp;sort='; ?>name"><?php echo _('Name'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:left; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['subject'] . '&amp;sort='; ?>subject"><?php echo _('Subject'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:center; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['status'] . '&amp;sort='; ?>status"><?php echo _('Status'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:center; white-space:nowrap;">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['lastreplier'] . '&amp;sort='; ?>lastreplier"><?php echo _('Last replier'); ?></a>
                                    </th>
                                    <th class="admin_white" style="text-align:center; white-space:nowrap;width:1px">
                                        <a href="<?php echo $this->href . '?' . $this->ticket_query . $this->sort_possible['priority'] . '&amp;sort='; ?>priority"><img src=" <?php echo $this->helpbase->url; ?>img/sort_priority_<?php echo (($this->sort_possible['priority']) ? 'asc' : 'desc'); ?>.png" width="16" height="16" alt="<?php echo _('Sort by') . ' ' . _('Priority'); ?>" title="<?php echo _('Sort by') . ' ' . _('Priority'); ?>" border="0" /></a>
                                    </th>
                                </tr>
<?php
        }

        function time_since($original) {
            global $hesk_settings;

            /* array of time period chunks */
            $chunks = array(
                array(60 * 60 * 24 * 365, _('y')),
                array(60 * 60 * 24 * 30, _('mo')),
                array(60 * 60 * 24 * 7, _('w')),
                array(60 * 60 * 24, _('d')),
                array(60 * 60, _('h')),
                array(60, _('m')),
                array(1, _('s')),
            );

            /* Invalid time */
            if ($this->mysql_time < $original) {
                // DEBUG return "T: $this->mysql_time (".date('Y-m-d H:i:s',$this->mysql_time).")<br>O: $original (".date('Y-m-d H:i:s',$original).")";
                return "0" . _('s');
            }

            $since = $this->mysql_time - $original;

            // $j saves performing the count function each time around the loop
            for ($i = 0, $j = count($chunks); $i < $j; $i++) {

                $seconds = $chunks[$i][0];
                $name = $chunks[$i][1];

                // finding the biggest chunk (if the chunk fits, break)
                if (($count = floor($since / $seconds)) != 0) {
                    // DEBUG print "<!-- It's $name -->\n";
                    break;
                }
            }

            $print = "$count{$name}";

            if ($i + 1 < $j) {
                // now getting the second item
                $seconds2 = $chunks[$i + 1][0];
                $name2 = $chunks[$i + 1][1];

                // add second item if it's greater than 0
                if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
                    $print .= "$count2{$name2}";
                }
            }
            return $print;
        }
    }
}