<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Find Tickets
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseFindTickets')){
    class HelpbaseFindTickets {
        private $helpbase           = null;
        
        public $status              = array();
        public $priority            = array();
        public $sort                = '';
        public $asc                 = 0;
        public $max_results         = 0;
        public $page                = 1;
        public $group               = '';
        public $sort_possible       = array();
        public $critical_on_top     = false;
        public $my_tickets          = array();
        public $others_tickets      = array();
        public $unassigned_tickets  = array();
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            $helpbase->admin->isLoggedIn();

            $helpbase->load_calander = true;

            $_SESSION['hide']['ticket_list'] = true;

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');

            $_SERVER['PHP_SELF'] = './admin_main.php';
            
            $this->render();
        }
        
        public function render(){
            /* Print header */
            $this->helpbase->header->render();

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                        <h3 align="center"><?php echo _('Search results'); ?></h3>
<?php
            // This SQL code will be used to retrieve results
            $sql_final = "SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE ";

            // This code will be used to count number of results
            $sql_count = "SELECT COUNT(*) FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE ";

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
                $archive[2] = 1;
                $sql .= " AND `archive`='1' ";
            }

            /*
             * Ticket owner preferences
             * 
             * my_tickets, others_tickets and unassigned_tickets returned.
             */
            $fid = 2;
            require($this->helpbase->includes . 'assignment_search.inc.php');
            $search = new HelpbaseAssignmentSearch($this->helpbase, $this, $fid);
            $search->search();
            
            $sql .= $search->sql;
            
            unset ($search);        
                        
            $hesk_error_buffer = '';
            $no_query = 0;

            // Search query
            $q = stripslashes($this->helpbase->common->_input($this->helpbase->common->_get('q', '')));

            // No query entered?
            if (!strlen($q)) {
                $hesk_error_buffer .= _('Enter your search query');
                $no_query = 1;
            }

            // What field are we searching in
            $what = $this->helpbase->common->_get('what', '') or $hesk_error_buffer .= '<br />' . _('Select the field you want to search by');

            // Sequential ID supported?
            if ($what == 'seqid' && !$hesk_settings['sequential']) {
                $what = 'trackid';
            }

            // Setup SQL based on searching preferences
            if (!$no_query) {
                $sql .= " AND ";

                $prefix     = $this->helpbase->database->escape($hesk_settings['db_pfix']);
                $query      = $this->helpbase->database->escape($q);
                $collate    = $this->helpbase->database->escape($this->helpbase->collate);
                
                switch ($what) {
                    case 'trackid':
                        $sql .= " ( `trackid` = '" . $query . "' OR `merged` LIKE '%#" . $query . "#%' ) ";
                    break;
                    case 'name':
                        $sql .= "`name` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'email':
                        $sql .= "`email` LIKE '%" . $query . "%' ";
                    break;
                    case 'company':
                        $sql .= "`company` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'homephone':
                        $sql .= "`homephone` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'mobilephone':
                        $sql .= "`mobilephone` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'workphone':
                        $sql .= "`workphone` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'subject':
                        $sql .= "`subject` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'devicetype':
                        $sql .= "`subject` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'devicebrand':
                        $sql .= "`subject` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'deviceid':
                        $sql .= "`subject` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                    break;
                    case 'message':
                        $sql .= " ( `message` LIKE '%" . $query . "%' COLLATE '" . $collate . "'
                            OR
                        `id` IN (
                            SELECT DISTINCT `replyto`
                            FROM   `" . $prefix . "replies`
                            WHERE  `message` LIKE '%" . $query . "%' COLLATE '" . $collate . "' )
                        )
                        ";
                    break;
                    case 'seqid':
                        $sql .= "`id` = '" . intval($q) . "' ";
                    break;
                    case 'notes':
                        $sql .= "`id` IN (
                            SELECT DISTINCT `ticket`
                            FROM   `" . $prefix . "notes`
                            WHERE  `message` LIKE '%" . $query . "%' COLLATE '" . $collate . "' )
                            ";
                    break;
                    default:
                        if (isset($hesk_settings['custom_fields'][$what]) && $hesk_settings['custom_fields'][$what]['use']) {
                            $sql .= "`" . $this->helpbase->database->escape($what) . "` LIKE '%" . $query . "%' COLLATE '" . $collate . "' ";
                        } else {
                            $hesk_error_buffer .= '<br />' . _('Invalid search action');
                        }
                    break;
                }
            }

            /* Date */
            /* -> Check for compatibility with old date format */
            if (preg_match("/(\d{4})-(\d{2})-(\d{2})/", $this->helpbase->common->_get('dt'), $m)) {
                $_GET['dt'] = $m[2] . $m[3] . $m[1];
            }

            /* -> Now process the date value */
            $dt = preg_replace('/[^0-9]/', '', $this->helpbase->common->_get('dt'));
            if (strlen($dt) == 8) {
                $date = substr($dt, 4, 4) . '-' . substr($dt, 0, 2) . '-' . substr($dt, 2, 2);
                $date_input = substr($dt, 0, 2) . '/' . substr($dt, 2, 2) . '/' . substr($dt, 4, 4);

                /* This search is valid even if no query is entered */
                if ($no_query) {
                    $hesk_error_buffer = str_replace(_('Enter your search query'), '', $hesk_error_buffer);
                }

                $sql .= " AND (`dt` LIKE '" . $this->helpbase->database->escape($date) . "%' OR `lastchange` LIKE '" . $this->helpbase->database->escape($date) . "%') ";
            } else {
                $date = '';
                $date_input = '';
            }

            /* Any errors? */
            if (strlen($hesk_error_buffer)) {
                $this->helpbase->common->process_messages($hesk_error_buffer, 'NOREDIRECT');
            }

            /* This will handle error, success and notice messages */
            $handle = $this->helpbase->common->handle_messages();

            # echo "$sql<br/>";
            // That's all the SQL we need for count
            $sql_count .= $sql;
            $sql = $sql_final . $sql;

            /* Prepare variables used in search and forms */
            include_once($this->helpbase->includes . 'prepare_ticket_search.inc.php');
            $prep       = new HelpbasePrepTicketSearch($this->helpbase, $this);
            $prep->sql  = $sql;

            $prep->prep();
            $sql = $prep->sql;
            
            unset ($prep);        

            /* If there has been an error message skip searching for tickets */
            if ($handle !== FALSE) {
                $href = 'find_tickets.php';
                include_once($this->helpbase->includes . 'ticket_list.inc.php');
                
                $ticket_list                        = new HelpbaseTicketList($this->helpbase);
                $ticket_list->sql                   = $sql;
                $ticket_list->sql_count             = $sql_count;
                $ticket_list->query                 = $q;
                $ticket_list->fields                = $what;
                $ticket_list->category              = $category;
                $ticket_list->date_input            = $date_input;
                $ticket_list->sort                  = $this->sort;
                $ticket_list->max_results           = $this->max_results;
                $ticket_list->archive               = $archive;
                $ticket_list->my_tickets            = $this->my_tickets;
                $ticket_list->others_tickets        = $this->others_tickets;
                $ticket_list->unassigned_tickets    = $this->unassigned_tickets;
                $ticket_list->page                  = $this->page;
                $ticket_list->group                 = $this->group;
                $ticket_list->sort_possible         = $this->sort_possible;
                $ticket_list->critical_on_top       = $this->critical_on_top;

                $ticket_list->list_tickets();

                unset ($ticket_list);
            }
?>
                        <hr />
<?php
            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars('hide');

            /* Show the search form */
            require_once($this->helpbase->includes . 'show_search_form.inc.php');
            $search             = new HelpbaseShowSearch($this->helpbase, $this);

            $search->archive            = $archive;
            $search->date_input         = $date_input;
            $search->query              = $what;
          
            $search->render();
            
            unset ($search);

            /* Print footer */
            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
    }
    
    new HelpbaseFindTickets;
}

?>
