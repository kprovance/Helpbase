<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Ticket Export Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseTicketExport')) {
    class HelpbaseTicketExport {
        private $helpbase   = null;
        private $parent     = null;
        
        public $sql         = '';
        public $date_from   = '';
        public $date_to     = '';
        
        public function __construct($core, $parent){
            $this->helpbase = $core;
            $this->parent   = $parent;
        }
        
        public function prep(){
            /* Acceptable $sort values and default asc(1)/desc(0) setting */
           $sort_possible = array(
               'trackid'       => 1,
               'lastchange'    => 0,
               'name'          => 1,
               'subject'       => 1,
               'status'        => 1,
               'lastreplier'   => 1,
               'priority'      => 1,
               'category'      => 1,
               'dt'            => 0,
               'id'            => 1,
           );

           // These values should have collate appended in SQL
           $sort_collation = array(
               'name',
               'subject',
           );

           // DATE
           $sql = $this->sql;
           $sql .= " AND DATE(`dt`) BETWEEN '" . $this->helpbase->database->escape($this->date_from) . "' AND '" . $this->helpbase->database->escape($this->date_to) . "' ";


           // Start the order by part of the SQL query
           $sql .= " ORDER BY ";

           /* Sort by which field? */
           if (isset($_GET['sort']) && !is_array($_GET['sort']) && isset($sort_possible[$_GET['sort']])) {
               $sort = $this->helpbase->common->_input($_GET['sort']);

               $sql .= ' `' . $this->helpbase->database->escape($sort) . '` ';

               // Need to set MySQL collation?
               if (in_array($_GET['sort'], $sort_collation)) {
                   $sql .= " COLLATE '" . $this->helpbase->database->escape($this->helpbase->collate) . "' ";
               }
           } else {
               /* Default sorting by ticket status */
               $sql .= ' `id` ';
               $sort = 'id';
           }

           /* Ascending or Descending? */
           if (isset($_GET['asc']) && intval($_GET['asc']) == 0) {
               $sql .= ' DESC ';
               $asc = 0;
               $asc_rev = 1;

               $sort_possible[$sort] = 1;
           } else {
               $sql .= ' ASC ';
               $asc = 1;
               $asc_rev = 0;
               if (!isset($_GET['asc'])) {
                   $is_default = 1;
               }

               $sort_possible[$sort] = 0;
           }

           $this->sql = $sql;

            $this->parent->sort             = $sort;
            $this->parent->asc              = $asc;
           
           # Uncomment for debugging purposes
           # echo "SQL: $sql<br>";
           
        }
    }
}