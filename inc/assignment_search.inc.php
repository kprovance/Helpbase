<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Assignment Search
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseAssignmentSearch')){
    class HelpbaseAssignmentSearch {
        private $helpbase           = null;
        private $parent             = null;
        private $fid                = '';
        private $my_tickets         = array();
        private $others_tickets     = array();
        private $unassigned_tickets = array();
        
        public $sql         = '';
        
        public function __construct($core, $parent, $fid) {
            $this->helpbase             = $core;
            $this->parent               = $parent;
            $this->fid                  = $fid;
            $this->my_tickets           = $parent->my_tickets;
            $this->others_tickets       = $parent->others_tickets;
            $this->unassigned_tickets   = $parent->unassigned_tickets;
        }
        
        public function search (){
            /* Assignment */
           // -> SELF
           $this->my_tickets[$this->fid] = empty($_GET['my_tickets']) ? 0 : 1;
           // -> OTHERS
           $this->others_tickets[$this->fid] = empty($_GET['others_tickets']) ? 0 : 1;
           // -> UNASSIGNED
           $this->unassigned_tickets[$this->fid] = empty($_GET['unassigned_tickets']) ? 0 : 1;

           // -> Setup SQL based on selected ticket assignments

           /* Make sure at least one is chosen */
           if (!$this->my_tickets[$this->fid] && !$this->others_tickets[$this->fid] && !$this->unassigned_tickets[$this->fid]) {
               $this->my_tickets[$this->fid] = 1;
               $this->others_tickets[$this->fid] = 1;
               $this->unassigned_tickets[$this->fid] = 1;
               if (false == $this->helpbase->main_page) {
                   $this->helpbase->common->show_notice(_('No assignment status selected, showing all tickets.'));
               }
           }

           /* If the user doesn't have permission to view assigned to others block those */
           if (!$this->helpbase->admin->checkPermission('can_view_ass_others', 0)) {
               $this->others_tickets[$this->fid] = 0;
           }

           /* If the user doesn't have permission to view unassigned tickets block those */
           if (!$this->helpbase->admin->checkPermission('can_view_unassigned', 0)) {
               $this->unassigned_tickets[$this->fid] = 0;
           }

           /* Process assignments */
           if (!$this->my_tickets[$this->fid] || !$this->others_tickets[$this->fid] || !$this->unassigned_tickets[$this->fid]) {
               if ($this->my_tickets[$this->fid] && $this->others_tickets[$this->fid]) {
                   // All but unassigned
                   $this->sql .= " AND `owner` > 0 ";
               } elseif ($this->my_tickets[$this->fid] && $this->unassigned_tickets[$this->fid]) {
                   // My tickets + unassigned
                   $this->sql .= " AND `owner` IN ('0', '" . intval($_SESSION['id']) . "') ";
               } elseif ($this->others_tickets[$this->fid] && $this->unassigned_tickets[$this->fid]) {
                   // Assigned to others + unassigned
                   $this->sql .= " AND `owner` != '" . intval($_SESSION['id']) . "' ";
               } elseif ($this->my_tickets[$this->fid]) {
                   // Assigned to me only
                   $this->sql .= " AND `owner` = '" . intval($_SESSION['id']) . "' ";
               } elseif ($this->others_tickets[$this->fid]) {
                   // Assigned to others
                   $this->sql .= " AND `owner` NOT IN ('0', '" . intval($_SESSION['id']) . "') ";
               } elseif ($this->unassigned_tickets[$this->fid]) {
                   // Only unassigned
                   $this->sql .= " AND `owner` = 0 ";
               }
           }
           
           $this->parent->my_tickets            = $this->my_tickets;
           $this->parent->others_tickets        = $this->others_tickets;
           $this->parent->unassigned_tickets    = $this->unassigned_tickets;
        }
    }
}