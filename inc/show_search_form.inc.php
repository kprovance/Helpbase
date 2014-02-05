<?php
/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Search
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseShowSearch')) {
    class HelpbaseShowSearch {
        private $helpbase           = null;
        private $status             = array();
        private $priority           = array();
        private $sort               = '';
        private $group              = '';
        private $asc                = false;
        private $critical_on_top    = false;
        private $category           = '';
        private $my_tickets         = array();
        private $others_tickets     = array();
        private $unassigned_tickets = array();
        private $max_results        = 0;
        
        public $query               = ''; //what
        public $date_input          = '';
        public $archive             = array();
        
        public function __construct($core, $parent){
            $this->helpbase             = $core;
            $this->status               = $parent->status;
            $this->priority             = $parent->priority;
            $this->sort                 = $parent->sort;
            $this->asc                  = $parent->asc;
            $this->group                = $parent->group;
            $this->critical_on_top      = $parent->critical_on_top;
            $this->category             = $parent->category;
            $this->my_tickets           = $parent->my_tickets;
            $this->others_tickets       = $parent->others_tickets;
            $this->unassigned_tickets   = $parent->unassigned_tickets;
            $this->max_results          = $parent->max_results;
        }

        public function render(){
            global $hesk_settings;

            if (!isset($this->status)) {
               $this->status = array(
                   0 => 'NEW',
                   1 => 'WAITING REPLY',
                   2 => 'REPLIED',
                   #3 => 'RESOLVED (CLOSED)',
                   4 => 'IN PROGRESS',
                   5 => 'ON HOLD',
                   6 => 'WAITING FOR PAYMENT',
                   7 => 'WAITING FOR BENCH',
                   8 => 'SERVICE CALL',
                   9 => 'REMOTE SUPPORT',
                   10 => 'READY FOR PICKUP',
               );
           }

           if (!isset($this->priority)) {
               $this->priority = array(
                   0 => 'CRITICAL',
                   1 => 'HIGH',
                   2 => 'MEDIUM',
                   3 => 'LOW',
               );
           }

           if (!isset($this->query)) {
               $this->query = 'trackid';
           }

           if (!isset($this->date_input)) {
               $this->date_input = '';
           }

           /* Can view tickets that are unassigned or assigned to others? */
           $can_view_ass_others = $this->helpbase->admin->checkPermission('can_view_ass_others', 0);
           $can_view_unassigned = $this->helpbase->admin->checkPermission('can_view_unassigned', 0);

           /* Category options */
           $category_options = '';
           if (isset($hesk_settings['categories']) && count($hesk_settings['categories'])) {
               foreach ($hesk_settings['categories'] as $row['id'] => $row['name']) {
                   $row['name'] = (strlen($row['name']) > 30) ? substr($row['name'], 0, 30) . '...' : $row['name'];
                   $selected = ($row['id'] == $this->category) ? 'selected="selected"' : '';
                   $category_options .= '
                                                <option value="' . $row['id'] . '" ' . $selected . '>' . $row['name'] . '</option>';
               }
           } else {
               $res2 = $this->helpbase->database->query('SELECT `id`, `name` FROM `' . $this->helpbase->database->escape($hesk_settings['db_pfix']) . 'categories` WHERE ' . $this->helpbase->admin->myCategories('id') . ' ORDER BY `cat_order` ASC');
               while ($row = $this->helpbase->database->fetchAssoc($res2)) {
                   $row['name'] = (strlen($row['name']) > 30) ? substr($row['name'], 0, 30) . '...' : $row['name'];
                   $selected = ($row['id'] == $this->category) ? 'selected="selected"' : '';
                   $category_options .= '
                                                <option value="' . $row['id'] . '" ' . $selected . '>' . $row['name'] . '</option>';
               }
           }

           $more = empty($_GET['more']) ? 0 : 1;
           $more2 = empty($_GET['more2']) ? 0 : 1;

           #echo "SQL: $sql";
?>
                        <!-- ** START SHOW TICKET FORM ** -->
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="<?php echo $this->helpbase->url; ?>img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="<?php echo $this->helpbase->url; ?>img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td valign="top">
                                    <form name="showt" action="show_tickets.php" method="get">
                                        <h3 style="margin-bottom:5px">&raquo; <?php echo _('Show tickets'); ?></h3>
                                        <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="20%" class="alignTop"><b><?php echo _('Status'); ?></b>: &nbsp; </td>
                                                <td width="80%">
                                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                        <tr>
                                                            <td width="34%">
<?php
            $checked = '';
            if (isset($this->status[0])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label><input type="checkbox" name="s0" value="1" <?php echo $checked; ?>/>
                                                                    <span class="open"><?php echo _('New'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[2])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s2" value="1" <?php echo $checked; ?>/>
                                                                    <span class="replied"><?php echo _('Replied'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="34%">
<?php
            $checked = '';
            if (isset($this->status[1])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s1" value="1" <?php echo $checked; ?>/>
                                                                    <span class="waitingreply"><?php echo _('Awaiting reply'); ?></span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[7])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s7" value="1" <?php echo $checked; ?>/>
                                                                    <span class="waitingforbench"><?php echo _('Waiting for bench'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[4])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s4" value="1" <?php echo $checked; ?>/>
                                                                    <span class="inprogress"><?php echo _('On the bench'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[9])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s9" value="1" <?php echo $checked; ?>/>
                                                                    <span class="remotesupport"><?php echo _('Remote support'); ?></span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[8])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s8" value="1" <?php echo $checked; ?>/>
                                                                    <span class="servicecall"><?php echo _('Service call'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[6])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s6" value="1" <?php echo $checked; ?>/>
                                                                    <span class="waitforpayment"><?php echo _('Waiting for payment'); ?></span>
                                                                </label>
                                                            </td>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[10])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s10" value="1" <?php echo $checked; ?>/>
                                                                    <span class="readyforpickup"><?php echo _('Ready for pickup'); ?></span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="33%">
<?php
            $checked = '';
            if (isset($this->status[5])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <label>
                                                                    <input type="checkbox" name="s5" value="1" <?php echo $checked; ?>/>
                                                                    <span class="onhold"><?php echo _('On hold'); ?></span>
                                                                </label>
                                                            </td>
<?php
            $checked = '';
            if (isset($this->status[3])) {
                $checked = 'checked="checked"';
            }
?>
                                                            <td width="33%">
                                                                <label><input type="checkbox" name="s3" value="1" <?php echo $checked; ?>/>
                                                                    <span class="resolved"><?php echo _('Closed'); ?></span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <div id="topSubmit" style="display:<?php echo $more ? 'none' : 'block'; ?>">
                                            &nbsp;
                                            <br />
                                            <input type="submit" value="<?php echo _('Show tickets'); ?>" class="button blue small" />
                                            |
                                            <a href="javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('divShow');
                                                Javascript:hb_toggleLayerDisplay('topSubmit');
                                                document.showt.more.value = '1';"><?php echo _('More options'); ?></a>
                                            <br />
                                            &nbsp;
                                            <br />
                                        </div>
                                        <div id="divShow" style="display:<?php echo $more ? 'block' : 'none'; ?>">
                                            <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                                <tr>
                                                    <td width="20%" class="borderTop alignTop"><b><?php echo _('Priority'); ?></b>: &nbsp; </td>
                                                    <td width="80%" class="borderTop alignTop">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
<?php
            $checked = '';
            if (isset($this->priority[0])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <td width="34%">
                                                                    <label>
                                                                        <input type="checkbox" name="p0" value="1" <?php echo $checked; ?>/>
                                                                        <span class="critical"><?php echo _(' * Critical * '); ?></span>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if (isset($this->priority[2])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="checkbox" name="p2" value="1" <?php echo $checked; ?>/> <span class="medium"><?php echo _('Medium'); ?></span>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                            <tr>
                                                                <td width="34%">
<?php
            $checked = '';
            if (isset($this->priority[1])) {
                $checked = 'checked="checked"';
            }
    ?>
                                                                    <label>
                                                                        <input type="checkbox" name="p1" value="1" <?php echo $checked; ?>/>
                                                                        <span class="important"><?php echo _('High'); ?></span>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if (isset($this->priority[3])) {
                $checked = 'checked="checked"';
            }
?>
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="checkbox" name="p3" value="1" <?php echo $checked; ?>/>
                                                                        <span class="normal"><?php echo _('Low'); ?></span>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignTop"><b><?php echo _('Show'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
                                                                <td width="34%" class="alignTop">
<?php
            $checked = '';
            if ($this->my_tickets[1]) {
                $checked = 'checked="checked"';
            }
?>
                                                                    <label>
                                                                        <input type="checkbox" name="my_tickets" value="1" <?php echo $checked; ?>/> <?php echo _('Assigned to me'); ?>
                                                                    </label>
<?php
           if ($can_view_unassigned) {
                $checked = '';
                if ($this->unassigned_tickets[1]) {
                    $checked = 'checked="checked"';
                }               
?>
                                                                    <br />
                                                                    <label>
                                                                        <input type="checkbox" name="unassigned_tickets" value="1" <?php echo $checked; ?>/> <?php echo _('Unassigned tickets'); ?>
                                                                    </label>
<?php
            }
?>
                                                                </td>
                                                                <td width="33%" class="alignTop">
<?php
           if ($can_view_ass_others) {
                $checked = '';
                if ($this->others_tickets[1]) {
                    $checked = 'checked="checked"';
                }                              
?>
                                                                    <label>
                                                                        <input type="checkbox" name="others_tickets" value="1" <?php echo $checked; ?>/> <?php echo _('Assigned to others'); ?>
                                                                    </label>
                                                                    <br />
<?php
            }
            $checked = '';
            if ($this->archive[1]) {
                $checked = 'checked="checked"';
            }             
?>
                                                                    <label>
                                                                        <input type="checkbox" name="archive" value="1" <?php echo $checked; ?>/> <?php echo _('Only tagged tickets'); ?>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignTop"><b><?php echo _('Sort by'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
<?php
            $checked = '';
            if ($this->sort == 'priority') {
                $checked = 'checked="checked"';
            }
?>
                                                                <td width="34%">
                                                                    <label>
                                                                        <input type="radio" name="sort" value="priority" <?php echo $checked; ?>/> <?php echo _('Priority'); ?>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if ($this->sort == 'lastchange') {
                $checked = 'checked="checked"';
            }
?>                                                            
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="radio" name="sort" value="lastchange" <?php echo $checked; ?>/> <?php echo _('Updated'); ?>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if ($this->sort == 'name') {
                $checked = 'checked="checked"';
            }
?>                                                              
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="radio" name="sort" value="name" <?php echo $checked; ?>/> <?php echo _('Name'); ?>
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                            <tr>
<?php
            $checked = '';
            if ($this->sort == 'subject') {
                $checked = 'checked="checked"';
            }
    ?>                                                     
                                                                <td width="34%">
                                                                    <label>
                                                                        <input type="radio" name="sort" value="subject" <?php echo $checked; ?>/> <?php echo _('Subject'); ?>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if ($this->sort == 'status') {
                $checked = 'checked="checked"';
            }
?>                                                     
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="radio" name="sort" value="status" <?php echo $checked; ?>/> <?php echo _('Status'); ?>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignTop"><b><?php echo _('Group by'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
<?php
            $checked = '';
            if (!$this->group) {
                $checked = 'checked="checked"';
            }
?>                                                     
                                                                <td width="34%">
                                                                    <label>
                                                                        <input type="radio" name="g" value="" <?php echo $checked; ?>/> <?php echo _('Don\'t group'); ?>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">
<?php
            if ($can_view_unassigned || $can_view_ass_others) {
                $checked = '';
                if ($this->group == 'owner') {
                    $checked = 'checked="checked"';
                }
?>
                                                                    <label>
                                                                        <input type="radio" name="g" value="owner" <?php echo $checked; ?>/> <?php echo _('Owner'); ?>
                                                                    </label>
    <?php
           } else {
               echo '
                                                                    &nbsp;';
           }
    ?>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                            <tr>
<?php
            $checked = '';
            if ($this->group == 'category') {
                $checked = 'checked="checked"';
            }
?>
                                                                <td width="34%">
                                                                    <label>
                                                                        <input type="radio" name="g" value="category" <?php echo $checked; ?>/> <?php echo _('Category'); ?>
                                                                    </label>
                                                                </td>
<?php
            $checked = '';
            if ($this->group == 'priority') {
                $checked = 'checked="checked"';
            }
?>                                                    
                                                                <td width="33%">
                                                                    <label>
                                                                        <input type="radio" name="g" value="priority" <?php echo $checked; ?>/> <?php echo _('Priority'); ?>
                                                                    </label>
                                                                </td>
                                                                <td width="33%">&nbsp;</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignMiddle"><b><?php echo _('Category'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop alignMiddle">
                                                        <select name="category">
                                                            <option value="0" ><?php echo _('Any category'); ?></option>
                                                            <?php echo $category_options; ?>
                                                        </select>
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <td class="borderTop"><b><?php echo _('Display'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop"><input type="text" name="limit" value="<?php echo $this->max_results; ?>" size="4" /> <?php echo _('tickets per page'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignMiddle"><b><?php echo _('Order'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop alignMiddle">
<?php
            $checked = '';
            if ($this->asc) {
                $checked = 'checked="checked"';
            }
?>                                            
                                                        <label>
                                                            <input type="radio" name="asc" value="1" <?php echo $checked; ?>/> <?php echo _('ascending'); ?>
                                                        </label>
                                                       |
                                                       <label>
<?php
            $checked = '';
            if (!$this->asc) {
                $checked = 'checked="checked"';
            }
?>                                               
                                                            <input type="radio" name="asc" value="0" <?php echo $checked; ?>/> <?php echo _('descending'); ?>
                                                        </label>
                                                     </td>
                                                 </tr>
                                                <tr>
                                                    <td class="borderTop alignTop"><b><?php echo _('Options'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop">
<?php
            $checked = '';
            if ($this->critical_on_top) {
                $checked = 'checked="checked"';
            }
?>                                             
                                                        <label>
                                                            <input type="checkbox" name="cot" value="1" <?php echo $checked; ?>/> <?php echo _('Don\'t force Critical tickets on top'); ?>
                                                        </label>
                                                        <br />
                                                        <label>
                                                            <input type="checkbox" name="def" value="1" /> <?php echo _('Make this my default view'); ?>
                                                        </label> 
                                                        (<a href="admin_main.php?reset=1&amp;token=<?php echo $this->helpbase->common->token_echo(0); ?>"><?php echo _('reset default view'); ?></a>)
                                                    </td>
                                                </tr>
                                            </table>
                                            <p>
                                                <input type="submit" value="<?php echo _('Show tickets'); ?>" class="button blue small" />
                                       |
                                                <input type="hidden" name="more" value="<?php echo $more ? 1 : 0; ?>" />
                                                <a href="javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('divShow');
                                                    Javascript:hb_toggleLayerDisplay('topSubmit');
                                                    document.showt.more.value = '0';"><?php echo _('Less options'); ?>
                                                </a>
                                            </p>
                                        </div>
                                    </form>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td><img src="<?php echo $this->helpbase->url; ?>img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="<?php echo $this->helpbase->url; ?>img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <!-- ** END SHOW TICKET FORM ** -->
                        <br />&nbsp;<br />

                        <!-- ** START SEARCH TICKETS FORM ** -->
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="7" height="7"><img src="<?php echo $this->helpbase->url; ?>img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornerstop"></td>
                                <td><img src="<?php echo $this->helpbase->url; ?>img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                            <tr>
                                <td class="roundcornersleft">&nbsp;</td>
                                <td valign="top">
                                    <form action="find_tickets.php" method="get" name="findby" id="findby">
                                        <h3 style="margin-bottom:5px">&raquo; <?php echo _('Find a ticket'); ?></h3>
                                        <table border="0" cellpadding="3" cellspacing="0">
                                            <tr>
                                                <td stlye="text-align:left">
                                                    <b><?php echo _('Search for'); ?></b>:<br />
                                                    <input type="text" name="q" size="30" <?php if (isset($q)) {
                                                            echo 'value="' . $q . '"';
                                                        } ?> />
                                                </td>
                                                <td stlye="text-align:left">
                                                    <b><?php echo _('Search in'); ?></b>:<br />
                                                    <select name="what">
<?php
            $checked = '';
            if ($this->query == 'trackid') {
                $checked = 'selected="selected"';
            }
?>                                                        
                                                        <option value="trackid" <?php echo $checked; ?>>
                                                            <?php echo _('Tracking ID'); ?>
                                                        </option>
<?php
            if ($hesk_settings['sequential']) {
                $checked = '';
                if ($this->query == 'seqid') {
                    $checked = 'selected="selected"';
                }               
?>
                                                        <option value="seqid" <?php echo $checked; ?>>
                                                            <?php echo _('Ticket number'); ?>
                                                        </option>
<?php
            }
            $checked = '';
            if ($this->query == 'name') {
                $checked = 'selected="selected"';
            }                           
?>
                                                        <option value="name" <?php echo $checked; ?>>
                                                            <?php echo _('Name'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'company') {
                $checked = 'selected="selected"';
            }                           
?>                                                      
                                                        <option value="company" <?php echo $checked; ?>>
                                                            <?php echo _('Company'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'email') {
                $checked = 'selected="selected"';
            }                           
?>                                                      
                                                        <option value="email" <?php echo $checked; ?>>
                                                            <?php echo _('Email'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'homephone') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="homephone" <?php echo $checked; ?>>
                                                                <?php echo _('Home phone'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'mobilephone') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="mobilephone" <?php echo $checked; ?>>
                                                            <?php echo _('Mobile phone'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'workphone') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="workphone" <?php echo $checked; ?>>
                                                            <?php echo _('Work phone'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'devicetype') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="devicetype" <?php echo $checked; ?>>
                                                            <?php echo _('Device type'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'devicebrand') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="devicebrand" <?php echo $checked; ?>>
                                                            <?php echo _('Device brand'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'deviceid') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="deviceid"	<?php echo $checked; ?>>
                                                            <?php echo _('Device ID'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'subject') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="subject" <?php echo $checked; ?>>
                                                            <?php echo _('Subject'); ?>
                                                        </option>
<?php
            $checked = '';
            if ($this->query == 'message') {
                $checked = 'selected="selected"';
            }                           
?>                                                                                                 
                                                        <option value="message" <?php echo $checked; ?>>
                                                            <?php echo _('Message'); ?>
                                                        </option>
<?php
           foreach ($hesk_settings['custom_fields'] as $k => $v) {
               $selected = ($this->query == $k) ? 'selected="selected"' : '';
               if ($v['use']) {
                   $v['name'] = (strlen($v['name']) > 30) ? substr($v['name'], 0, 30) . '...' : $v['name'];
                   echo '
                                                        <option value="' . $k . '" ' . $selected . '>' . $v['name'] . '</option>';
               }
           }
           
            $checked = '';
            if ($this->query == 'notes') {
                $checked = 'selected="selected"';
            }                                      
?>
                                                        <option value="notes" <?php echo $checked; ?>>
                                                            <?php echo _('Notes'); ?>
                                                        </option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>

                                        <div id="topSubmit2" style="display:<?php echo $more2 ? 'none' : 'block'; ?>">
                                            &nbsp;<br />
                                            <input type="submit" value="<?php echo _('Find ticket'); ?>" class="button blue small" />
                                            |
                                            <a href="javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('divShow2');
                                                    Javascript:hb_toggleLayerDisplay('topSubmit2');
                                                    document.findby.more2.value = '1';"><?php echo _('More options'); ?></a>
                                            <br />&nbsp;<br />
                                        </div>
                                        <div id="divShow2" style="display:<?php echo $more2 ? 'block' : 'none'; ?>">
                                            &nbsp;
                                            <br />
                                            <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                                <tr>
                                                    <td class="borderTop alignMiddle" width="20%"><b><?php echo _('Category'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop alignMiddle" width="80%">
                                                        <select name="category">
                                                            <option value="0" ><?php echo _('Any category'); ?></option>
                                                            <?php echo $category_options; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignMiddle"><b><?php echo _('Date'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop alignMiddle">
<?php
            $value = '';
            if ($this->date_input) {
                $value = 'value="' . $this->date_input . '"';
            }                           
?>                                                       
                                                        <input type="text" name="dt" id="dt" size="10" class="tcal" <?php echo $value; ?>/>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop alignTop"><b><?php echo _('Search within'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop">
<?php
            $checked = '';
            if ($this->my_tickets[2]) {
                $checked = 'checked="checked"';
            }
?>                                                    
                                                        <label>
                                                            <input type="checkbox" name="my_tickets" value="1" <?php echo $checked; ?>/> <?php echo _('Assigned to me'); ?>
                                                        </label>
<?php
           if ($can_view_ass_others) {
?>
                                                        <br />
                                                        <label>
                                                            <input type="checkbox" name="others_tickets" value="1" <?php if ($this->others_tickets[2]) echo 'checked="checked"'; ?> /> <?php echo _('Assigned to others'); ?>
                                                        </label>
<?php
           }

           if ($can_view_unassigned) {
?>
                                                        <br />
                                                        <label><input type="checkbox" name="unassigned_tickets" value="1" <?php if ($this->unassigned_tickets[2]) echo 'checked="checked"'; ?> /> <?php echo _('Unassigned tickets'); ?></label>
<?php
           }
?>
                                                        <br />
                                                        <label><input type="checkbox" name="archive" value="1" <?php if ($this->archive[2]) echo 'checked="checked"'; ?> /> <?php echo _('Only tagged tickets'); ?></label>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="borderTop"><b><?php echo _('Display'); ?></b>: &nbsp; </td>
                                                    <td class="borderTop"><input type="text" name="limit" value="<?php echo $this->max_results; ?>" size="4" /> <?php echo _('results per page'); ?></td>
                                                </tr>
                                            </table>
                                            <p>
                                                <input type="submit" value="<?php echo _('Find ticket'); ?>" class="button blue small" />
                                                |
                                                <input type="hidden" name="more2" value="<?php echo $more2 ? 1 : 0; ?>" />
                                                <a href="javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay('divShow2');
                                                    Javascript:hb_toggleLayerDisplay('topSubmit2');
                                                    document.findby.more2.value = '0';"><?php echo _('Less options'); ?>
                                                </a>
                                            </p>
                                        </div>
                                    </form>
                                </td>
                                <td class="roundcornersright">&nbsp;</td>
                            </tr>
                            <tr>
                                <td width="6"><img src="<?php echo $this->helpbase->url; ?>img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                <td class="roundcornersbottom"></td>
                                <td width="7" height="7"><img src="<?php echo $this->helpbase->url; ?>img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                            </tr>
                        </table>
                        <!-- ** END SEARCH TICKETS FORM ** -->
<?php
        }
    }
}