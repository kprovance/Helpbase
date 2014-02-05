<?php
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Show Tickets
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
define('EXECUTING', true);

if (!class_exists('HelpbaseShowTickets')) {
    class HelpbaseShowTickets {
        private $helpbase           = null;
        
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
            
            $helpbase->admin->isLoggedIn();

            $helpbase->load_calander = true;

            /* Check permissions for this feature */
            $helpbase->admin->checkPermission('can_view_tickets');
            
            $this->render;
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
?>
                            <table style="width:100%;border:none;border-collapse:collapse;"><tr>
                                    <td style="width:25%">&nbsp;</td>
                                    <td style="width:50%;text-align:center"><h3><?php echo _('Tickets'); ?></h3></td>
                                    <td style="width:25%;text-align:right"><a href="new_ticket.php"><?php echo _('+ New ticket'); ?></a></td>
                                </tr>
                            </table>
<?php
            /*
             * Print the list of tickets
             * 
             * Returns status, priority, sort, asc, group, critical_on_top
             * category, my_tickets, others_tickets, unassigned_tickets
             * archive, max_results
             */
            include_once($this->helpbase->includes . 'print_tickets.inc.php');

            $print_tickets              = new HelpbasePrintTickets($this->helpbase);
            $print_tickets->is_search   = true;
            
            $print_tickets->print_tickets();

            unset ($print_tickets);

            /* Update staff default settings? */
            if (!empty($_GET['def'])) {
                $this->helpbase->admin->updateStaffDefaults();
            }
?>
                            &nbsp;
                            <br />
<?php
            /* Print forms for listing and searching tickets */
            require_once($this->helpbase->includes . 'show_search_form.inc.php');
            
            $search                     = new HelpbaseShowSearch($this->helpbase, $this);
            $search->archive            = $this->archive;
            
            $search->render();            
?>
                            <p>&nbsp;</p>
<?php

            /* Print footer */
            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
    }
    
    new HelpbaseShowTickets;
}

?>
