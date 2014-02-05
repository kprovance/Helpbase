<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Print Ticket Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbassePrintTickets')) {
    class HelpbasePrintTickets {
        private $helpbase           = null;
        private $parent             = null;
        
        public $sort                = '';
        public $asc                 = 0;
        public $max_results         = 0;
        public $page                = 1;
        public $group               = '';
        public $sort_possible       = array();
        public $critical_on_top     = false;
        public $is_search           = false;
        public $my_tickets          = array();
        public $others_tickets      = array();
        public $unassigned_tickets  = array();
        
        public function __construct($core, $parent) {
            $this->helpbase = $core;
            $this->parent   = $parent;
        }
        
        public function __destruct() {
            
        }
        
        public function print_tickets(){
            global $hesk_settings;
            
            $prefix = $this->helpbase->database->escape($hesk_settings['db_pfix']);
            
            // This SQL code will be used to retrieve results
            $sql_final = "SELECT * FROM `" . $prefix . "tickets` WHERE ";

            // This code will be used to count number of results
            $sql_count = "SELECT COUNT(*) FROM `" . $prefix . "tickets` WHERE ";

            // This is common SQL for both queries
            $sql = "";

            // Some default settings
            $archive                    = array(1 => 0, 2 => 0);
            $this->my_tickets           = array(1 => 1, 2 => 1);
            $this->others_tickets       = array(1 => 1, 2 => 1);
            $this->unassigned_tickets   = array(1 => 1, 2 => 1);

            // --> TICKET CATEGORY
            $category = intval($this->helpbase->common->_get('category', 0));

            // Make sure user has access to this category
            if ($category && $this->helpbase->admin->okCategory($category, 0)) {
               $sql .= " `category`='{$category}' ";
            } else {
                // No category selected, show only allowed categories
               $sql .= $this->helpbase->admin->myCategories();
            }

            // Show only tagged tickets?
            if (!empty($_GET['archive'])) {
               $archive[1] = 1;
               $sql .= " AND `archive`='1' ";
            }

            // Ticket owner preferences
            $fid = 1;
            include_once($this->helpbase->includes . 'assignment_search.inc.php');
            $search = new HelpbaseAssignmentSearch($this->helpbase, $this, $fid);
            $search->search();
            
            $sql .= $search->sql;
            
            unset ($search);

            // --> TICKET STATUS
            $possible_status = array(
               0    => 'NEW',
               1    => 'WAITING REPLY',
               2    => 'REPLIED',
               3    => 'RESOLVED (CLOSED)',
               4    => 'IN PROGRESS',
               5    => 'ON HOLD',
               6    => 'WAITING FOR PAYMENT',
               7    => 'WAITING FOR BENCH',
               8    => 'SERVICE CALL',
               9    => 'REMOTE SUPPORT',
               10   => 'READY FOR PICKUP',
            );

            $status = $possible_status;

            foreach ($status as $k => $v) {
               if (empty($_GET['s' . $k])) {
                   unset($status[$k]);
               }
            }

            // How many statuses are we pulling out of the database?
            $tmp = count($status);

            // Do we need to search by status?
            if ($tmp < 6) {
               // If no statuses selected, show default (all except RESOLVED)
               if ($tmp == 0) {
                   $status = $possible_status;
                   unset($status[3]);
               }

               // Add to the SQL
               $sql .= " AND `status` IN ('" . implode("','", array_keys($status)) . "') ";
            }

            // --> TICKET PRIORITY
            $possible_priority = array(
               0 => 'CRITICAL',
               1 => 'HIGH',
               2 => 'MEDIUM',
               3 => 'LOW',
            );

            $priority = $possible_priority;

            foreach ($priority as $k => $v) {
               if (empty($_GET['p' . $k])) {
                   unset($priority[$k]);
               }
            }

            // How many priorities are we pulling out of the database?
            $tmp = count($priority);

            // Create the SQL based on the number of priorities we need
            if ($tmp == 0 || $tmp == 4) {
               // Nothing or all selected, no need to modify the SQL code
               $priority = $possible_priority;
            } else {
               // A custom selection of priorities
               $sql .= " AND `priority` IN ('" . implode("','", array_keys($priority)) . "') ";
            }

            // That's all the SQL we need for count
            $sql_count .= $sql;
            $sql        = $sql_final . $sql;

            $this->parent->status   = $status;
            $this->parent->priority = $priority;
            
            // Prepare variables used in search and forms
            include_once($this->helpbase->includes . 'prepare_ticket_search.inc.php');
            $prep = new HelpbasePrepTicketSearch($this->helpbase, $this);
            $prep->sql = $sql;
            $prep->prep();
            unset ($prep);

            $this->parent->sort                 = $this->sort;
            $this->parent->asc                  = $this->asc;
            $this->parent->group                = $this->group;
            $this->parent->critical_on_top      = $this->critical_on_top;
            $this->parent->category             = $category;
            $this->parent->my_tickets           = $this->my_tickets;
            $this->parent->others_tickets       = $this->others_tickets;
            $this->parent->unassigned_tickets   = $this->unassigned_tickets;
            $this->parent->archive              = $archive;
            $this->parent->max_results          = $this->max_results;
            
            // List tickets?
            if (!isset($_SESSION['hide']['ticket_list'])) {
               $href = 'show_tickets.php';
               include_once($this->helpbase->includes . 'ticket_list.inc.php');
               
               $ticket_list                     = new HelpbaseTicketList($this->helpbase);
               $ticket_list->sql                = $sql;
               $ticket_list->sql_count          = $sql_count;
               $ticket_list->category           = $category;
               $ticket_list->sort               = $this->sort;
               $ticket_list->asc                = $this->asc;
               $ticket_list->max_results        = $this->max_results;
               $ticket_list->archive            = $archive;
               $ticket_list->my_tickets         = $this->my_tickets;
               $ticket_list->others_tickets     = $this->others_tickets;
               $ticket_list->unassigned_tickets = $this->unassigned_tickets;
               $ticket_list->page               = $this->page;
               $ticket_list->group              = $this->group;
               $ticket_list->sort_possible      = $this->sort_possible;
               $ticket_list->critical_on_top    = $this->critical_on_top;
               $ticket_list->is_search          = $this->is_search;
               
               $ticket_list->list_tickets();
               
               unset ($ticket_list);
            }
        }
    }
}