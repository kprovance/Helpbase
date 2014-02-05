<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Print Group Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbasePrintGroup')) {
    class HelpbasePrintGroup {
        private $parent     = null;
        
        public $group       = '';
        public $group_tmp   = '';
        public $is_table    = false;
        public $space       = 0;
        
        public function __construct($parent){
            $this->parent = $parent;
        }
        
        public function print_group(){
            /* Group tickets into tables */
           if ($this->group == 'owner') {
               if ($ticket['owner'] != $this->group_tmp) {
                   $this->group_tmp = $ticket['owner'];

                   if (true == $this->is_table) {
                       echo '</table></div>';
                   }

                   if ($this->space) {
                       echo '&nbsp;<br />';
                   }

                   if (empty($this->group_tmp) || !isset($admins[$this->group_tmp])) {
                       echo '<p>' . _('These tickets are <b>Unassigned</b>:') . '</p>';
                       $this->space++;
                   } else {
                       if ($this->group_tmp == $_SESSION['id']) {
                           echo '<p>' . _('Tickets assigned to <b>me</b>:') . '</p>';
                           $this->space++;
                       } else {
                           echo '<p>' . sprintf(_('Tickets assigned to <b>%s</b>:'), $admins[$this->group_tmp]) . '</p>';
                           $this->space++;
                       }
                   }

                   $parent->print_list_head();
                   $this->is_table = true;
               }
           } elseif ($this->group == 'priority') {
               switch ($ticket['priority']) {
                   case 0:
                       $tmp = '<font class="critical">' . _(' * Critical * ') . '</font>';
                   break;
               
                   case 1:
                       $tmp = '<font class="important">' . _('High') . '</font>';
                   break;
               
                   case 2:
                       $tmp = '<font class="medium">' . _('Medium') . '</font>';
                   break;
               
                   default:
                       $tmp = _('Low');
               }

               if ($ticket['priority'] != $this->group_tmp) {
                   $this->group_tmp = $ticket['priority'];

                   if (true == $this->is_table) {
                       echo '</table></div>';
                   }

                   if ($this->space) {
                       echo '&nbsp;<br />';
                   }

                   echo '<p>' . _('Priority') . ': <b>' . $tmp . '</b></p>';
                   
                   $this->space++;

                   $parent->print_list_head();
                   
                   $this->is_table = true;
               }
           } else {
               if ($ticket['category'] != $this->group_tmp) {
                   $this->group_tmp = $ticket['category'];

                   if (true == $this->is_table) {
                       echo '</table></div>';
                   }

                   if ($this->space) {
                       echo '&nbsp;<br />';
                   }

                   $tmp = isset($hesk_settings['categories'][$this->group_tmp]) ? $hesk_settings['categories'][$this->group_tmp] : '(' . _('Unknown') . ')';

                   echo '<p>' . _('Category') . ': <b>' . $tmp . '</b></p>';
                   
                   $this->space++;

                   $parent->print_list_head();
                   $this->is_table = true;
               }
           }
        }
    }
}