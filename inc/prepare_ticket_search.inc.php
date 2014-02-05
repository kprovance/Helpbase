<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Ticket Search Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */

if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbasePrepTicketSearch')) {

    class HelpbasePrepTicketSearch {
        private $helpbase   = null;
        private $parent     = null;
        
        public $sql         = '';

        public function __construct($core, $parent) {
            $this->helpbase = $core;
            $this->parent   = $parent;
        }

        public function prep() {
            global $hesk_settings;

            $tmp = intval($this->helpbase->common->_get('limit'));
            $maxresults = ($tmp > 0) ? $tmp : $hesk_settings['max_listings'];

            $tmp = intval($this->helpbase->common->_get('page', 1));
            $page = ($tmp > 1) ? $tmp : 1;

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

            /* These values should have collate appended in SQL */
            $sort_collation = array(
                'name',
                'subject',
            );

            /* Acceptable $group values and default asc(1)/desc(0) setting */
            $group_possible = array(
                'owner'     => 1,
                'priority'  => 1,
                'category'  => 1,
            );

            /* Start the order by part of the SQL query */
            $sql = $this->sql;
            $sql .= " ORDER BY ";

            /* Group tickets? Default: no */
            if (isset($_GET['g']) && !is_array($_GET['g']) && isset($group_possible[$_GET['g']])) {
                $group = $this->helpbase->common->_input($_GET['g']);

                if ($group == 'priority' && isset($_GET['sort']) && !is_array($_GET['sort']) && $_GET['sort'] == 'priority') {
                    // No need to group by priority if we are already sorting by priority
                } elseif ($group == 'owner') {
                    // If group by owner place own tickets on top
                    $sql .= " CASE WHEN `owner` = '" . intval($_SESSION['id']) . "' THEN 1 ELSE 0 END DESC, `owner` ASC, ";
                } else {
                    $sql .= ' `' . $this->helpbase->database->escape($group) . '` ';
                    $sql .= $group_possible[$group] ? 'ASC, ' : 'DESC, ';
                }
            } else {
                $group = '';
            }


            /* Show critical tickets always on top? Default: yes */
            $cot = (isset($_GET['cot']) && intval($_GET['cot']) == 1) ? 1 : 0;
            if (!$cot) {
                $sql .= " CASE WHEN `priority` = '0' THEN 1 ELSE 0 END DESC , ";
            }

            /* Sort by which field? */
            if (isset($_GET['sort']) && !is_array($_GET['sort']) && isset($sort_possible[$_GET['sort']])) {
                $sort = $this->helpbase->common->_input($_GET['sort']);

                //$sql .= ' `'.$this->helpbase->database->escape($sort).'` ';
                $sql .= $sort == 'lastreplier' ? " CASE WHEN `lastreplier` = '0' THEN 0 ELSE 1 END DESC, COALESCE(`replierid`, NULLIF(`lastreplier`, '0'), `name`) " : ' `' . $this->helpbase->database->escape($sort) . '` ';


                // Need to set MySQL collation?
                if (in_array($_GET['sort'], $sort_collation)) {
                    $sql .= " COLLATE '" . $this->helpbase->database->escape($this->helpbase->collate) . "' ";
                }
            } else {
                /* Default sorting by ticket status */
                $sql .= ' `status` ';
                $sort = 'status';
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

            /* In the end same results should always be sorted by priority */
            if ($sort != 'priority') {
                $sql .= ' , `priority` ASC ';
            }

            $this->sql = $sql;
            
            $this->parent->sort             = $sort;
            $this->parent->asc              = $asc;
            $this->parent->max_results      = $maxresults;
            $this->parent->page             = $page;
            $this->parent->group            = $group;
            $this->parent->sort_possible    = $sort_possible;
            $this->parent->critical_on_top  = $cot;
            
            # Uncomment for debugging purposes
            # echo "SQL: $sql<br>";
        }

    }

}