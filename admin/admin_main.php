<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Main
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseAdminMain')) {
    class HelpbaseAdminMain {
        private $helpbase           = null;
        
        public $status              = array();
        public $priority            = array();
        public $sort                = '';
        public $asc                 = false;
        public $group               = '';
        public $critical_on_top     = false;
        public $category            = '';
        public $my_tickets          = array();
        public $others_tickets      = array();
        public $unassigned_tickets  = array();
        public $archive             = array();
        public $max_results         = 0;
        
        public function __construct(){
            
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            /* Make sure the install folder is deleted */
            if (is_dir($helpbase->dir . 'install')) {
                die('Please delete the <b>install</b> folder from your server for security reasons then refresh this page!');
            }

            $helpbase->admin->isLoggedIn();

            // Load defaults
            $helpbase->load_calander = true;
            $helpbase->main_page = true;
            
            $this->render();
        }
        
        private function render(){
            /* Print header */
            $this->helpbase->header->render();

            /* Print admin navigation */
            $this->helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();

            /* Print tickets? */
            if ($this->helpbase->admin->checkPermission('can_view_tickets', 0)) {
                if (!isset($_SESSION['hide']['ticket_list'])) {
                    echo '
                        <table style="width:100%;border:none;border-collapse:collapse;">
                            <tr>
                                <td style="width:25%">&nbsp;</td>
                                <td style="width:50%;text-align:center"><h3>' . _('Open tickets') . '</h3></td>
                                <td style="width:25%;text-align:right"><a href="new_ticket.php">' . _('+ New ticket') . '</a></td>
                            </tr>
                        </table>';
                }

                /* Reset default settings? */
                if (isset($_GET['reset']) && $this->helpbase->common->token_check()) {
                    $res = $this->helpbase->database->query("UPDATE `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` SET `default_list`='' WHERE `id` = '" . intval($_SESSION['id']) . "' LIMIT 1");
                    $_SESSION['default_list'] = '';
                }
                
                /* Get default settings */ else {
                    parse_str($_SESSION['default_list'], $defaults);
                    $_GET = isset($_GET) && is_array($_GET) ? array_merge($_GET, $defaults) : $defaults;
                }

                /*
                 * Print the list of tickets
                 * 
                 * Returns status, priority, sort, asc, group, critical_on_top
                 * category, my_tickets, others_tickets, unassigned_tickets
                 * archive, max_results
                 */
                include_once($this->helpbase->includes . 'print_tickets.inc.php');
                $print_tickets = new HelpbasePrintTickets($this->helpbase, $this);
                $print_tickets->print_tickets();
                unset ($print_tickets);

                echo "
                    &nbsp;<br />";

                /* Print forms for listing and searching tickets */
                include_once($this->helpbase->includes . 'show_search_form.inc.php');
                $search                     = new HelpbaseShowSearch($this->helpbase, $this);
                $search->archive            = $this->archive;
                
                $search->render();
                
                unset ($search);
            } else {
                echo '
                    <p><i>' . _('You are not authorized to view tickets') . '</i></p>';
            }

            /* Clean unneeded session variables */
            $this->helpbase->common->cleanSessionVars('hide');

            $this->helpbase->footer->render();

            unset ($this->helpbase);

            exit();
        }
    }
    
    new HelpbaseAdminMain;
}

?>

        