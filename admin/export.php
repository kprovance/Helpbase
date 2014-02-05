<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Export Data
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

if (!class_exists('HelpbaseExport')) {
    class HelpbaseExport {
        private $helpbase               = null;
        private $selected               = array();
        private $input_datefrom         = '';
        private $input_dateto           = '';
        private $can_view_unassigned    = false;
        private $can_view_ass_others    = false;
        private $archive                = array();
        private $category_options       = '';
        public $my_tickets              = array();
        public $others_tickets          = array();
        public $unassigned_tickets      = array();
        public $sort                    = '';
        public $asc                     = 0;
        
        public function __construct() {
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;
            
            require($helpbase->includes . 'reporting_functions.inc.php');

            $helpbase->admin->isLoggedIn();
            
            // Check permissions for this feature
            $helpbase->admin->checkPermission('can_export');

            // Set default values
            $helpbase->load_calander = true;
            $helpbase->main_page = true;
            $helpbase->load_tabs = true;            
            
            $this->build_export();
            $this->render();
            
            unset ($helpbase);
        }
        
        private function build_export(){
            global $hesk_settings;
            
            $this->selected = array(
                'w' => array(
                    0 => '', 
                    1 => ''
                ),
                'time' => array(
                    1 => '', 
                    2 => '', 
                    3 => '', 
                    4 => '', 
                    5 => '', 
                    6 => '', 
                    7 => '', 
                    8 => '', 
                    9 => '', 
                    10 => '', 
                    11 => '', 
                    12 => ''
                ),
            );

            $is_all_time = 0;

            // Default this month to date
            $date_from = date('Y-m-d', mktime(0, 0, 0, date("m"), 1, date("Y")));
            $date_to = date('Y-m-d');
            $this->input_datefrom = date('m/d/Y', strtotime('last month'));
            $this->input_dateto = date('m/d/Y');

            /* Date */
            if (!empty($_GET['w'])) {
                $df = preg_replace('/[^0-9]/', '', $this->helpbase->common->_get('datefrom'));
                if (strlen($df) == 8) {
                    $date_from = substr($df, 4, 4) . '-' . substr($df, 0, 2) . '-' . substr($df, 2, 2);
                    $this->input_datefrom = substr($df, 0, 2) . '/' . substr($df, 2, 2) . '/' . substr($df, 4, 4);
                } else {
                    $date_from = date('Y-m-d', strtotime('last month'));
                }

                $dt = preg_replace('/[^0-9]/', '', $this->helpbase->common->_get('dateto'));
                if (strlen($dt) == 8) {
                    $date_to = substr($dt, 4, 4) . '-' . substr($dt, 0, 2) . '-' . substr($dt, 2, 2);
                    $this->input_dateto = substr($dt, 0, 2) . '/' . substr($dt, 2, 2) . '/' . substr($dt, 4, 4);
                } else {
                    $date_to = date('Y-m-d');
                }

                if ($date_from > $date_to) {
                    $tmp = $date_from;
                    $tmp2 = $this->input_datefrom;

                    $date_from = $date_to;
                    $this->input_datefrom = $this->input_dateto;

                    $date_to = $tmp;
                    $this->input_dateto = $tmp2;

                    $note_buffer = _('&quot;Date From&quot; cannot be higher than &quot;Date to&quot;. The dates have been switched.');
                }

                if ($date_to > date('Y-m-d')) {
                    $date_to = date('Y-m-d');
                    $this->input_dateto = date('m/d/Y');
                }

                $this->selected['w'][1] = 'checked="checked"';
                $this->selected['time'][3] = 'selected="selected"';
            } else {
                $this->selected['w'][0] = 'checked="checked"';
                $_GET['time'] = intval($this->helpbase->common->_get('time', 3));

                switch ($_GET['time']) {
                    case 1:
                        /* Today */
                        $date_from = date('Y-m-d');
                        $date_to = $date_from;
                        $this->selected['time'][1] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    case 2:
                        /* Yesterday */
                        $date_from = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));
                        $date_to = $date_from;
                        $this->selected['time'][2] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    case 4:
                        /* Last month */
                        $date_from = date('Y-m-d', mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
                        $date_to = date('Y-m-d', mktime(0, 0, 0, date("m"), 0, date("Y")));
                        $this->selected['time'][4] = 'selected="selected"';
                        break;

                    case 5:
                        /* Last 30 days */
                        $date_from = date('Y-m-d', mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
                        $date_to = date('Y-m-d');
                        $this->selected['time'][5] = 'selected="selected"';
                        break;

                    case 6:
                        /* This week */
                        list($date_from, $date_to) = dateweek(0);
                        $date_to = date('Y-m-d');
                        $this->selected['time'][6] = 'selected="selected"';
                        break;

                    case 7:
                        /* Last week */
                        list($date_from, $date_to) = dateweek(-1);
                        $this->selected['time'][7] = 'selected="selected"';
                        break;

                    case 8:
                        /* This business week */
                        list($date_from, $date_to) = dateweek(0, 1);
                        $date_to = date('Y-m-d');
                        $this->selected['time'][8] = 'selected="selected"';
                        break;

                    case 9:
                        /* Last business week */
                        list($date_from, $date_to) = dateweek(-1, 1);
                        $this->selected['time'][9] = 'selected="selected"';
                        break;

                    case 10:
                        /* This year */
                        $date_from = date('Y') . '-01-01';
                        $date_to = date('Y-m-d');
                        $this->selected['time'][10] = 'selected="selected"';
                        break;

                    case 11:
                        /* Last year */
                        $date_from = date('Y') - 1 . '-01-01';
                        $date_to = date('Y') - 1 . '-12-31';
                        $this->selected['time'][11] = 'selected="selected"';
                        break;

                    case 12:
                        /* All time */
                        $date_from = hesk_getOldestDate();
                        $date_to = date('Y-m-d');
                        $this->selected['time'][12] = 'selected="selected"';
                        $is_all_time = 1;
                        break;

                    default:
                        $_GET['time'] = 3;
                        $this->selected['time'][3] = 'selected="selected"';
                }
            }

            unset($tmp);

            // Start SQL statement for selecting tickets
            $sql = "SELECT * FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` WHERE ";

            // Some default settings
            $this->archive = array(
                1 => 0, 
                2 => 0
            );

            $this->my_tickets = array(
                1 => 1, 
                2 => 1
            );

            $this->others_tickets = array(
                1 => 1, 
                2 => 1
            );

            $this->unassigned_tickets = array(
                1 => 1, 
                2 => 1
            );

            // --> TICKET CATEGORY
            $category = intval($this->helpbase->common->_get('category', 0));

            // Make sure user has access to this category
            if ($category && $this->helpbase->admin->okCategory($category, 0)) {
                $sql .= " `category`='{$category}' ";
            }
            // No category selected, show only allowed categories
            else {
                $sql .= $this->helpbase->admin->myCategories();
            }

            // Show only tagged tickets?
            if (!empty($_GET['archive'])) {
                $this->archive[1] = 1;
                $sql .= " AND `archive`='1' ";
            }

            // Ticket owner preferences
            $fid = 1;
            require($this->helpbase->includes . 'assignment_search.inc.php');
            $search         = new HelpbaseAssignmentSearch($this->helpbase, $this, $fid);
            $search->search();

            $sql .= $search->sql;

            unset ($search);

            // --> TICKET STATUS
            $possible_status = array(
                0   => 'NEW',
                1   => 'WAITING REPLY',
                2   => 'REPLIED',
                3   => 'RESOLVED (CLOSED)',
                4   => 'IN PROGRESS',
                5   => 'ON HOLD',
                6   => 'WAITING FOR PAYMENT',
                7   => 'WAITING FOR BENCH',
                8   => 'SERVICE CALL',
                9   => 'REMOTE SUPPORT',
                10  => 'READY FOR PICKUP',
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
                // If no statuses selected, show all
                if ($tmp == 0) {
                    $status = $possible_status;
                } else {
                    // Add to the SQL
                    $sql .= " AND `status` IN ('" . implode("','", array_keys($status)) . "') ";
                }
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

            // Prepare variables used in search and forms
            require_once($this->helpbase->includes . 'prepare_ticket_export.inc.php');
            
            $prep               = new HelpbaseTicketExport($this->helpbase, $this);
            $prep->date_from    = $date_from;
            $prep->date_to      = $date_to;
            
            $prep->prep();

            ////////////////////////////////////////////////////////////////////////////////
            // Can view tickets that are unassigned or assigned to others?
            $this->can_view_ass_others = $this->helpbase->admin->checkPermission('can_view_ass_others', 0);
            $this->can_view_unassigned = $this->helpbase->admin->checkPermission('can_view_unassigned', 0);

            // Category options
            $this->category_options = '';
            $my_cat = array();
            $res2 = $this->helpbase->database->query("SELECT `id`, `name` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE " . $this->helpbase->admin->myCategories('id') . " ORDER BY `cat_order` ASC");
            while ($row = $this->helpbase->database->fetchAssoc($res2)) {
                $my_cat[$row['id']] = $this->helpbase->common->msgToPlain($row['name'], 1);
                $row['name']        = (strlen($row['name']) > 50) ? substr($row['name'], 0, 50) . '...' : $row['name'];
                $cat_selected       = ($row['id'] == $category) ? 'selected="selected"' : '';
                $this->category_options   .= '<option value="' . $row['id'] . '" ' . $cat_selected . '>' . $row['name'] . '</option>';
            }

            // Generate export file
            if (isset($_GET['w'])) {
                // We'll need HH:MM:SS format for $this->helpbase->common->_date() here
                $hesk_settings['timeformat'] = 'H:i:s';

                // Get staff names
                $admins = array();
                $result = $this->helpbase->database->query("SELECT `id`,`name` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "users` ORDER BY `id` ASC");
                while ($row = $this->helpbase->database->fetchAssoc($result)) {
                    $admins[$row['id']] = $row['name'];
                }

                // This will be the export directory
                $export_dir = $this->helpbase->dir . $hesk_settings['attach_dir'] . '/export/';

                // This will be the name of the export and the XML file
                $export_name = 'hesk_export_' . date('Y-m-d_H-i-s') . '_' . mt_rand(10000, 99999);
                $save_to = $export_dir . $export_name . '.xml';

                // Do we have the export directory?
                if (is_dir($export_dir) || ( @mkdir($export_dir, 0777) && is_writable($export_dir) )) {
                    // Cleanup old files
                    $files = glob($export_dir . '*', GLOB_NOSORT);
                    if (is_array($files) && count($files)) {
                        foreach ($files as $file) {
                            $this->helpbase->common->unlink($file, 86400);
                        }
                    }
                } else {
                    $this->helpbase->common->_error(_('Cannot create export directory, please manually create a folder called <b>export</b> inside your attachments folder and make sure it is writable by PHP (on Linux CHMOD it to 777 - rwxrwxrwx).'));
                }

                // Make sure the file can be saved and written to
                @file_put_contents($save_to, '');
                if (!file_exists($save_to)) {
                    $this->helpbase->common->_error(_('Cannot create export file, no permission to write inside the export directory.'));
                }

                // Start generating the report message and generating the export
                $flush_me = '<br /><br />';
                $flush_me .= $this->helpbase->common->_date() . " | " . _('Initializing export');

                if ($date_from == $date_to) {
                    $flush_me .= "(" . $this->helpbase->admin->dateToString($date_from, 0) . ")<br />\n";
                } else {
                    $flush_me .= "(" . $this->helpbase->admin->dateToString($date_from, 0) . " - " . $this->helpbase->admin->dateToString($date_to, 0) . ")<br />\n";
                }

                // Start generating file contents
                $tmp = '
<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">
    <OfficeDocumentSettings xmlns="urn:schemas-microsoft-com:office:office">
        <AllowPNG/>
    </OfficeDocumentSettings>
    <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
        <WindowHeight>8250</WindowHeight>
        <WindowWidth>16275</WindowWidth>
        <WindowTopX>360</WindowTopX>
        <WindowTopY>90</WindowTopY>
        <ProtectStructure>False</ProtectStructure>
        <ProtectWindows>False</ProtectWindows>
    </ExcelWorkbook>
    <Styles>
        <Style ss:ID="Default" ss:Name="Normal">
            <Alignment ss:Vertical="Bottom"/>
            <Borders/>
            <Font ss:FontName="Calibri" x:CharSet="238" x:Family="Swiss" ss:Size="11"
             ss:Color="#000000"/>
            <Interior/>
            <NumberFormat/>
            <Protection/>
        </Style>
        <Style ss:ID="s62">
            <NumberFormat ss:Format="General Date"/>
        </Style>
        <Style ss:ID="s65">
            <NumberFormat ss:Format="[h]:mm:ss"/>
        </Style>
    </Styles>
    <Worksheet ss:Name="Sheet1">
        <Table>';

                // Define column width
                $tmp .= '
            <Column ss:AutoFitWidth="0" ss:Width="50"/>
            <Column ss:AutoFitWidth="0" ss:Width="84" ss:Span="1"/>
            <Column ss:AutoFitWidth="0" ss:Width="110"/>
            <Column ss:AutoFitWidth="0" ss:Width="110"/>
            <Column ss:AutoFitWidth="0" ss:Width="90"/>
            <Column ss:AutoFitWidth="0" ss:Width="90"/>
            <Column ss:AutoFitWidth="0" ss:Width="87"/>
            <Column ss:AutoFitWidth="0" ss:Width="57.75"/>
            <Column ss:AutoFitWidth="0" ss:Width="57.75"/>
            <Column ss:AutoFitWidth="0" ss:Width="100"/>
            <Column ss:AutoFitWidth="0" ss:Width="100"/>
            <Column ss:AutoFitWidth="0" ss:Width="80"/>
            <Column ss:AutoFitWidth="0" ss:Width="80"/>';

                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    if ($v['use']) {
                        $tmp .= '
            <Column ss:AutoFitWidth="0" ss:Width="80"/>' . "\n";
                    }
                }

                // Define first row (header)
                $tmp .= '
            <Row>
                <Cell><Data ss:Type="String">#</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Tracking ID') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Date') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Updated') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Name') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Company') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Email') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Home phone') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Mobile phone') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Work phone') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Device type') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Device brand') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Device ID') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Category') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Priority') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Status') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Subject') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Message') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Owner') . '</Data></Cell>
                <Cell><Data ss:Type="String">' . _('Time worked') . '</Data></Cell>';

                foreach ($hesk_settings['custom_fields'] as $k => $v) {
                    if ($v['use']) {
                        $tmp .= '
                <Cell><Data ss:Type="String">' . $v['name'] . '</Data></Cell>' . "\n";
                    }
                }

                $tmp .= "
            </Row>\n";

                // Write what we have by now into the XML file
                file_put_contents($save_to, $tmp, FILE_APPEND);
                $flush_me .= $this->helpbase->common->_date() . " | " . _('Generating XML file') . "<br />\n";

                // OK, now start dumping data and writing it into the file
                $tickets_exported = 0;
                $save_after = 100;
                $this_round = 0;
                $tmp = '';

                $result = $this->helpbase->database->query($sql);
                while ($ticket = $this->helpbase->database->fetchAssoc($result)) {

                    switch ($ticket['status']) {
                        case 0:
                            $ticket['status'] = _('New');
                            break;
                        case 1:
                            $ticket['status'] = _('Awaiting reply');
                            break;
                        case 2:
                            $ticket['status'] = _('Replied');
                            break;
                        case 4:
                            $ticket['status'] = _('On the bench');
                            break;
                        case 5:
                            $ticket['status'] = _('On hold');
                            break;
                        case 6:
                            $ticket['status'] = _('Waiting for payment');
                            break;
                        case 7:
                            $ticket['status'] = _('Waiting for bench');
                            break;
                        case 8:
                            $ticket['status'] = _('Service call');
                            break;
                        case 9:
                            $ticket['status'] = _('Remote support');
                            break;
                        case 10:
                            $ticket['status'] = _('Ready for pickup');
                            break;
                        default:
                            $ticket['status'] = _('Closed');
                    }

                    switch ($ticket['priority']) {
                        case 0:
                            $ticket['priority'] = _(' * Critical * ');
                            break;
                        case 1:
                            $ticket['priority'] = _('High');
                            break;
                        case 2:
                            $ticket['priority'] = _('Medium');
                            break;
                        default:
                            $ticket['priority'] = _('Low');
                    }

                    $ticket['archive'] = !($ticket['archive']) ? _('NO') : _('YES');
                    $ticket['message'] = $this->helpbase->common->msgToPlain($ticket['message'], 1);
                    $ticket['subject'] = $this->helpbase->common->msgToPlain($ticket['subject'], 1);
                    $ticket['owner'] = isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : '';
                    $ticket['dt'] = date("Y-m-d\TH:i:s\.000", strtotime($ticket['dt']));
                    $ticket['lastchange'] = date("Y-m-d\TH:i:s\.000", strtotime($ticket['lastchange']));

                    // Create row for the XML file
                    $tmp .= '
            <Row>
                <Cell><Data ss:Type="Number">' . $ticket['id'] . '</Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['trackid'] . ']]></Data></Cell>
                <Cell ss:StyleID="s62"><Data ss:Type="DateTime">' . $ticket['dt'] . '</Data></Cell>
                <Cell ss:StyleID="s62"><Data ss:Type="DateTime">' . $ticket['lastchange'] . '</Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $this->helpbase->common->msgToPlain($ticket['name'], 1) . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $this->helpbase->common->msgToPlain($ticket['company'], 1) . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['email'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['homephone'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['mobilephone'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['workphone'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['devicetype'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['devicebrand'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['deviceid'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $my_cat[$ticket['category']] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['priority'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['status'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['subject'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['message'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['owner'] . ']]></Data></Cell>
                <Cell><Data ss:Type="String"><![CDATA[' . $ticket['time_worked'] . ']]></Data></Cell>';

                    // Add custom fields
                    foreach ($hesk_settings['custom_fields'] as $k => $v) {
                        if ($v['use']) {
                            $tmp .= '
                <Cell><Data ss:Type="String"><![CDATA[' . $this->helpbase->common->msgToPlain($ticket[$k], 1) . ']]></Data></Cell>  ' . "\n";
                        }
                    }

                    $tmp .= "
            </Row>\n";

                    // Write every 100 rows into the file
                    if ($this_round >= $save_after) {
                        file_put_contents($save_to, $tmp, FILE_APPEND);
                        $this_round = 0;
                        $tmp = '';
                        usleep(1);
                    }

                    $tickets_exported++;
                    $this_round++;
                } // End of while loop
                // Append any remaining rows into the file
                if ($this_round > 0) {
                    file_put_contents($save_to, $tmp, FILE_APPEND);
                }

                // If any tickets were exported, continue, otherwise cleanup
                if ($tickets_exported > 0) {
                    // Finish the XML file
                    $tmp = '
        </Table>
        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
            <PageSetup>
                <Header x:Margin="0.3"/>
                <Footer x:Margin="0.3"/>
                <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
            </PageSetup>
            <Selected/>
            <Panes>
                <Pane>
                    <Number>3</Number>
                    <ActiveRow>4</ActiveRow>
                </Pane>
            </Panes>
            <ProtectObjects>False</ProtectObjects>
            <ProtectScenarios>False</ProtectScenarios>
        </WorksheetOptions>
    </Worksheet>
    <Worksheet ss:Name="Sheet2">
        <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="1" x:FullColumns="1" x:FullRows="1" ss:DefaultRowHeight="15"></Table>
        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
            <PageSetup>
                <Header x:Margin="0.3"/>
                <Footer x:Margin="0.3"/>
                <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
            </PageSetup>
            <ProtectObjects>False</ProtectObjects>
            <ProtectScenarios>False</ProtectScenarios>
        </WorksheetOptions>
    </Worksheet>
    <Worksheet ss:Name="Sheet3">
        <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="1" x:FullColumns="1" x:FullRows="1" ss:DefaultRowHeight="15"></Table>
        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
            <PageSetup>
                <Header x:Margin="0.3"/>
                <Footer x:Margin="0.3"/>
                <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
            </PageSetup>
            <ProtectObjects>False</ProtectObjects>
            <ProtectScenarios>False</ProtectScenarios>
        </WorksheetOptions>
    </Worksheet>
</Workbook>';
                    file_put_contents($save_to, $tmp, FILE_APPEND);

                    // Log how many rows we exported
                    $flush_me .= $this->helpbase->common->_date() . " | " . sprintf(_('Number of rows exported: %d'), $tickets_exported) . "<br />\n";

                    // We will convert XML to Zip to save a lot of space
                    $save_to_zip = $export_dir . $export_name . '.zip';

                    // Log start of Zip creation
                    $flush_me .= $this->helpbase->common->_date() . ' | ' . _('Compressing file into a Zip archive') . '<br />';

                    // Preferrably use the zip extension
                    if (extension_loaded('zip')) {
                        $save_to_zip = $export_dir . $export_name . '.zip';

                        $zip = new ZipArchive;
                        $res = $zip->open($save_to_zip, ZipArchive::CREATE);
                        if ($res === TRUE) {
                            $zip->addFile($save_to, "{$export_name}.xml");
                            $zip->close();
                        } else {
                            die(_('Error creating Zip archive') . '<' . $save_to_zip . '>\n');
                        }
                    }
                    // Some servers have ZipArchive class enabled anyway - can we use it?
                    elseif (class_exists('ZipArchive')) {
                        require($this->helpbase->includes . 'zip/Zip.php');
                        $zip = new Zip();
                        $zip->addLargeFile($save_to, "{$export_name}.xml");
                        $zip->finalize();
                        $zip->setZipFile($save_to_zip);
                    }
                    // If not available, use a 3rd party Zip class included with HESK
                    else {
                        require($this->helpbase->includes . 'zip/pclzip.lib.php');
                        $zip = new PclZip($save_to_zip);
                        $zip->add($save_to, PCLZIP_OPT_REMOVE_ALL_PATH);
                    }

                    // Delete XML, just leave the Zip archive
                    $this->helpbase->common->unlink($save_to);

                    // Echo memory peak usage
                    $flush_me .= $this->helpbase->common->_date() . " | " . sprintf(_('Peak memory usage: %.2f Mb'), (@memory_get_peak_usage(true) / 1048576)) . "<br />\r\n";

                    // We're done!
                    $flush_me .= $this->helpbase->common->_date() . " | " . _('Finished compressing the file') . "<br /><br />";
                    $flush_me .= '<a href="' . $save_to_zip . '">' . _('&raquo; CLICK HERE TO DOWNLOAD THE EXPORT FILE &laquo;') . "</a>\n";
                }
                // No tickets exported, cleanup
                else {
                    $this->helpbase->common->unlink($save_to);
                }
            }            
        }
        
        private function render() {
            /* Print header */
            $this->helpbase->header->render();

            /* Print main manage users page */
            $this->helpbase->admin_nav->render();
?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();

            // If an export was generated, show the link to download
            if (isset($flush_me)) {
                if ($tickets_exported > 0) {
                    $this->helpbase->common->show_success($flush_me);
                } else {
                    $this->helpbase->common->show_success(_('No tickets found matching your criteria, nothing to export!'));
                }
            }
?>
                        <!-- TABS -->
                        <div id="tab1" class="tabberlive" style="margin-top:0px">
                            <ul class="tabbernav">
<?php
            // Show a link to reports.php if user has permission to do so
            if ($this->helpbase->admin->checkPermission('can_run_reports', 0)) {
                echo '
                                <li class=""><a title="' . _('Run reports') . '" href="reports.php">' . _('Run reports') . ' [+]</a></li>';
            }
?>
                                <li class="tabberactive"><a title="<?php echo _('Export tickets'); ?>" href="javascript:void(null);" onclick="javascript:alert('<?php echo _('This tool allows you to export tickets into an XML spreadsheet that can be opened in Excel.'); ?>')"><?php echo _('Export tickets'); ?> [?]</a></li>
                            </ul>
                            <div class="tabbertab">
                                &nbsp;

                                <!-- ** START EXPORT FORM ** -->
                                <form name="showt" action="export.php" method="get">
                                    <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="20%" class="alignTop"><b><?php echo _('Date range'); ?></b>: &nbsp; </td>
                                            <td width="80%">
                                                
                                                <!-- START DATE -->
                                                <input type="radio" name="w" value="0" id="w0" <?php echo $this->selected['w'][0]; ?> />
                                                <select name="time" onclick="document.getElementById('w0').checked = true" onfocus="document.getElementById('w0').checked = true" style="margin-top:5px;margin-bottom:5px;">
                                                    <option value="1" <?php echo $this->selected['time'][1]; ?>><?php echo _('Today'); ?> (<?php echo $this->helpbase->common->getWeekday(date('w')); ?>)</option>
                                                    <option value="2" <?php echo $this->selected['time'][2]; ?>><?php echo _('Yesterday'); ?> (<?php echo $this->helpbase->common->getWeekday(date('w', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')))); ?>)</option>
                                                    <option value="3" <?php echo $this->selected['time'][3]; ?>><?php echo _('This month'); ?> (<?php echo $this->helpbase->common->getMonth(date('n')); ?>)</option>
                                                    <option value="4" <?php echo $this->selected['time'][4]; ?>><?php echo _('Last month'); ?> (<?php echo $this->helpbase->common->getMonth(date('n', mktime(0, 0, 0, date('m') - 1, date('d'), date('Y')))); ?>)</option>
                                                    <option value="5" <?php echo $this->selected['time'][5]; ?>><?php echo _('Last 30 days'); ?></option>
                                                    <option value="6" <?php echo $this->selected['time'][6]; ?>><?php echo _('This week (Mon-Sun)'); ?></option>
                                                    <option value="7" <?php echo $this->selected['time'][7]; ?>><?php echo _('Last week (Mon-Sun)'); ?></option>
                                                    <option value="8" <?php echo $this->selected['time'][8]; ?>><?php echo _('This business week (Mon-Fri)'); ?></option>
                                                    <option value="9" <?php echo $this->selected['time'][9]; ?>><?php echo _('Last business week (Mon-Fri)'); ?></option>
                                                    <option value="10" <?php echo $this->selected['time'][10]; ?>><?php echo _('This year'); ?> (<?php echo date('Y'); ?>)</option>
                                                    <option value="11" <?php echo $this->selected['time'][11]; ?>><?php echo _('Last year'); ?> (<?php echo date('Y', mktime(0, 0, 0, date('m'), date('d'), date('Y') - 1)); ?>)</option>
                                                    <option value="12" <?php echo $this->selected['time'][12]; ?>><?php echo _('All time'); ?></option>
                                                </select>
                                                <br />

                                                <input type="radio" name="w" value="1" id="w1" <?php echo $this->selected['w'][1]; ?> />
                                                <?php echo _('From'); ?> <input type="text" name="datefrom" value="<?php echo $this->input_datefrom; ?>" id="datefrom" class="tcal" size="10" onclick="document.getElementById('w1').checked = true" onfocus="document.getElementById('w1').checked = true; this.focus;" />
                                                <?php echo _('to'); ?> <input type="text" name="dateto" value="<?php echo $this->input_dateto; ?>" id="dateto" class="tcal" size="10" onclick="document.getElementById('w1').checked = true" onfocus="document.getElementById('w1').checked = true; this.focus;" />
                                                <!-- END DATE -->
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="20%" class="borderTop alignTop"><b><?php echo _('Status'); ?></b>: &nbsp; </td>
                                            <td width="80%" class="borderTop">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td width="34%">
                                                            <label><input type="checkbox" name="s0" value="1" <?php if (isset($status[0])) { echo 'checked="checked"'; } ?> />
                                                                <span class="open"><?php echo _('New'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s2" value="1" <?php if (isset($status[2])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="replied"><?php echo _('Replied'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="34%">
                                                            <label>
                                                                <input type="checkbox" name="s1" value="1" <?php if (isset($status[1])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="waitingreply"><?php echo _('Awaiting reply'); ?></span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s7" value="1" <?php if (isset($status[7])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="waitingforbench"><?php echo _('Waiting for bench'); ?></span>
                                                            </label>
                                                        </td>        
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s4" value="1" <?php if (isset($status[4])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="inprogress"><?php echo _('On the bench'); ?></span>
                                                            </label>
                                                        </td>        
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s9" value="1" <?php if (isset($status[9])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="remotesupport"><?php echo _('Remote support'); ?></span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s8" value="1" <?php if (isset($status[8])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="servicecall"><?php echo _('Service call'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s6" value="1" <?php if (isset($status[6])) { echo 'checked="checked"'; } ?>  /> 
                                                                <span class="waitforpayment"><?php echo _('Waiting for payment'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s10" value="1" <?php if (isset($status[10])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="readyforpickup"><?php echo _('Ready for pickup'); ?></span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s5" value="1" <?php if (isset($status[5])) { echo 'checked="checked"'; } ?>  /> 
                                                                <span class="onhold"><?php echo _('On hold'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="s3" value="1" <?php if (isset($status[3])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="resolved"><?php echo _('Closed'); ?></span>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <table border="0" cellpadding="3" cellspacing="0" width="100%">
                                        <tr>
                                            <td width="20%" class="borderTop alignTop"><b><?php echo _('Priority'); ?></b>: &nbsp; </td>
                                            <td width="80%" class="borderTop alignTop">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td width="34%">
                                                            <label>
                                                                <input type="checkbox" name="p0" value="1" <?php if (isset($priority[0])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="critical"><?php echo _(' * Critical * '); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="p2" value="1" <?php if (isset($priority[2])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="medium"><?php echo _('Medium'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">&nbsp;</td>
                                                    </tr>
                                                    <tr>
                                                        <td width="34%">
                                                            <label>
                                                                <input type="checkbox" name="p1" value="1" <?php if (isset($priority[1])) { echo 'checked="checked"'; } ?> /> 
                                                                <span class="important"><?php echo _('High'); ?></span>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="checkbox" name="p3" value="1" <?php if (isset($priority[3])) { echo 'checked="checked"'; } ?> /> 
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
                                                            <label>
                                                                <input type="checkbox" name="my_tickets" value="1" <?php if ($this->my_tickets[1]) echo 'checked="checked"'; ?> /> <?php echo _('Assigned to me'); ?>
                                                            </label>
<?php
            if ($this->can_view_unassigned) {
?>
                                                            <br />
                                                            <label>
                                                                <input type="checkbox" name="unassigned_tickets" value="1" <?php if ($this->unassigned_tickets[1]) echo 'checked="checked"'; ?> /> <?php echo _('Unassigned tickets'); ?>
                                                            </label>
<?php
            }
?>
                                                        </td>
                                                        <td width="33%" class="alignTop">
<?php
            if ($this->can_view_ass_others) {
?>
                                                            <label>
                                                                <input type="checkbox" name="others_tickets" value="1" <?php if ($this->others_tickets[1]) echo 'checked="checked"'; ?> /> <?php echo _('Assigned to others'); ?>
                                                            </label>
                                                            <br />
<?php
            }
?>
                                                            <label>
                                                                <input type="checkbox" name="archive" value="1" <?php if ($this->archive[1]) echo 'checked="checked"'; ?> /> <?php echo _('Only tagged tickets'); ?>
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
                                                        <td width="34%">
                                                            <label>
                                                                <input type="radio" name="sort" value="priority"  <?php if ($this->sort == 'priority') { echo 'checked="checked"'; } ?> /> <?php echo _('Priority'); ?>
                                                            </label>
                                                        </td>
                                                        <td width="33%"><label><input type="radio" name="sort" value="lastchange" <?php if ($this->sort == 'lastchange') { echo 'checked="checked"'; } ?> /> <?php echo _('Updated'); ?>
                                                            </label>
                                                        </td>
                                                        <td width="33%"><label><input type="radio" name="sort" value="name" <?php if ($this->sort == 'name') { echo 'checked="checked"'; } ?> /> <?php echo _('Name'); ?>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="34%">
                                                            <label>
                                                                <input type="radio" name="sort" value="subject" <?php if ($this->sort == 'subject') { echo 'checked="checked"'; } ?> /> <?php echo _('Subject'); ?>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="radio" name="sort" value="status" <?php if ($this->sort == 'status') { echo 'checked="checked"'; } ?> /> <?php echo _('Status'); ?>
                                                            </label>
                                                        </td>
                                                        <td width="33%">
                                                            <label>
                                                                <input type="radio" name="sort" value="id" <?php if ($this->sort == 'id') { echo 'checked="checked"'; } ?> /> <?php echo _('Sequentially'); ?>
                                                            </label>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="borderTop alignMiddle"><b><?php echo _('Category'); ?></b>: &nbsp; </td>
                                            <td class="borderTop alignMiddle">
                                                <select name="category">
                                                    <option value="0" ><?php echo _('Any category'); ?></option>
                                                    <?php echo $this->category_options; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="borderTop alignMiddle"><b><?php echo _('Order'); ?></b>: &nbsp; </td>
                                            <td class="borderTop alignMiddle">
                                                <label>
                                                    <input type="radio" name="asc" value="1" <?php if ($this->asc) { echo 'checked="checked"'; } ?> /> <?php echo _('ascending'); ?>
                                                </label>
                                                |
                                                <label>
                                                    <input type="radio" name="asc" value="0" <?php if (!$this->asc) { echo 'checked="checked"'; } ?> /> <?php echo _('descending'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    </table>
                                    <p>
                                        <input type="submit" value="<?php echo _('Export tickets'); ?>" class="button blue small" />
                                        <input type="hidden" name="cot" value="1" />
                                    </p>
                                </form>
                                <!-- ** END EXPORT FORM ** -->
                            </div>
                        </div>
                        <!-- TABS -->
<?php
            $this->helpbase->footer->render();

            unset($this->helpbase);

            exit();
        }
    }
    
    new HelpbaseExport;
}

?>    