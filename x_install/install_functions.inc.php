<?php
/*******************************************************************************
*  Title: Help Desk Software HESK
*  Version: 2.5.2 from 13th October 2013
*  Author: Klemen Stirn
*  Website: http://www.hesk.com
********************************************************************************
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2005-2013 Klemen Stirn. All Rights Reserved.
*  HESK is a registered trademark of Klemen Stirn.

*  The HESK may be used and modified free of charge by anyone
*  AS LONG AS COPYRIGHT NOTICES AND ALL THE COMMENTS REMAIN INTACT.
*  By using this code you agree to indemnify Klemen Stirn from any
*  liability that might arise from it's use.

*  Selling the code for this program, in part or full, without prior
*  written consent is expressly forbidden.

*  Using this code, in part or full, to create derivate work,
*  new scripts or products is expressly forbidden. Obtain permission
*  before redistributing this software over the Internet or in
*  any other medium. In all cases copyright and header must remain intact.
*  This Copyright is in full effect in any country that has International
*  Trade Agreements with the United States of America or
*  with the European Union.

*  Removing any of the copyright notices without purchasing a license
*  is expressly forbidden. To remove HESK copyright notice you must purchase
*  a license for this script. For more information on how to obtain
*  a license please visit the page below:
*  https://www.hesk.com/buy.php
*******************************************************************************/

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

// We will be installing this HESK version:
define('HESK_NEW_VERSION','2.5.2');

// Other required files and settings
define('INSTALL',1);
define('HIDE_ONLINE',1);

require(HESK_PATH . 'hesk_settings.inc.php');

$hesk_settings['debug_mode'] = 1;
$hesk_settings['language']='English';
$hesk_settings['languages']=array('English' => array('folder'=>'en','hr'=>'------ Reply above this line ------'));

error_reporting(E_ALL);

require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');
hesk_load_database_functions();

// Start the session
hesk_session_start();


// ******* FUNCTIONS ******* //


function hesk_iTestDatabaseConnection()
{
	global $hesk_settings, $hesklang;

    $db_success = 1;

    $hesk_settings['db_host'] = hesk_input( hesk_POST('host') );
    $hesk_settings['db_name'] = hesk_input( hesk_POST('name') );
    $hesk_settings['db_user'] = hesk_input( hesk_POST('user') );
    $hesk_settings['db_pass'] = hesk_input( hesk_POST('pass') );

	// Allow & in password
    $hesk_settings['db_pass'] = str_replace('&amp;', '&', $hesk_settings['db_pass']);

	// Use MySQLi extension to connect?
	$use_mysqli = function_exists('mysqli_connect') ? true : false;

    // Start output buffering
    ob_start();

    // Connect to database
    if ($use_mysqli)
    {
		// Do we need a special port? Check and connect to the database
		if ( strpos($hesk_settings['db_host'], ':') )
		{
			list($hesk_settings['db_host'], $hesk_settings['db_port']) = explode(':', $hesk_settings['db_host']);
			$hesk_db_link = mysqli_connect($hesk_settings['db_host'], $hesk_settings['db_user'], $hesk_settings['db_pass'], $hesk_settings['db_name'], intval($hesk_settings['db_port']) ) or $db_success=0;
		}
		else
		{
			$hesk_db_link = mysqli_connect($hesk_settings['db_host'], $hesk_settings['db_user'], $hesk_settings['db_pass'], $hesk_settings['db_name']) or $db_success=0;
		}
    }
    else
    {
    	$hesk_db_link = mysql_connect($hesk_settings['db_host'],$hesk_settings['db_user'], $hesk_settings['db_pass']) or $db_success=0;

        // Select database works OK?
        if ($db_success == 1 && ! mysql_select_db($hesk_settings['db_name'], $hesk_db_link) )
        {
	    	// No, try to create the database
			if (function_exists('mysql_create_db') && mysql_create_db($hesk_settings['db_name'], $hesk_db_link))
	        {
	        	if (mysql_select_db($hesk_settings['db_name'], $hesk_db_link))
	            {
					$db_success = 1;
	            }
	            else
	            {
					$db_success = 0;
	            }
	        }
	        else
	        {
	        	$db_success = 0;
	        }
        }
    }

	ob_end_clean();

    // Any errors?
	if ( ! $db_success)
    {
    	global $mysql_log;
    	$mysql_log = $use_mysqli ? mysqli_connect_error() : mysql_error();

		hesk_iDatabase(1);
    }

    return $hesk_db_link;
} // END hesk_iTestDatabaseConnection()


function hesk_iSaveSettingsFile($set)
{
	global $hesk_settings, $hesklang;

	$settings_file_content='<?php
// Settings file for HESK ' . $set['hesk_version'] . '

// ==> GENERAL

// --> General settings
$hesk_settings[\'site_title\']=\'' . $set['site_title'] . '\';
$hesk_settings[\'site_url\']=\'' . $set['site_url'] . '\';
$hesk_settings[\'webmaster_mail\']=\'' . $set['webmaster_mail'] . '\';
$hesk_settings[\'noreply_mail\']=\'' . $set['noreply_mail'] . '\';
$hesk_settings[\'noreply_name\']=\'' . $set['noreply_name'] . '\';

// --> Language settings
$hesk_settings[\'can_sel_lang\']=' . $set['can_sel_lang'] . ';
$hesk_settings[\'language\']=\'' . $set['language'] . '\';
$hesk_settings[\'languages\']=array(
\'English\' => array(\'folder\'=>\'en\',\'hr\'=>\'------ Reply above this line ------\'),
);

// --> Database settings
$hesk_settings[\'db_host\']=\'' . $set['db_host'] . '\';
$hesk_settings[\'db_name\']=\'' . $set['db_name'] . '\';
$hesk_settings[\'db_user\']=\'' . $set['db_user'] . '\';
$hesk_settings[\'db_pass\']=\'' . $set['db_pass'] . '\';
$hesk_settings[\'db_pfix\']=\'' . $set['db_pfix'] . '\';
$hesk_settings[\'db_vrsn\']=' . $set['db_vrsn'] . ';


// ==> HELP DESK

// --> Help desk settings
$hesk_settings[\'hesk_title\']=\'' . $set['hesk_title'] . '\';
$hesk_settings[\'hesk_url\']=\'' . $set['hesk_url'] . '\';
$hesk_settings[\'admin_dir\']=\'' . $set['admin_dir'] . '\';
$hesk_settings[\'attach_dir\']=\'' . $set['attach_dir'] . '\';
$hesk_settings[\'max_listings\']=' . $set['max_listings'] . ';
$hesk_settings[\'print_font_size\']=' . $set['print_font_size'] . ';
$hesk_settings[\'autoclose\']=' . $set['autoclose'] . ';
$hesk_settings[\'max_open\']=' . $set['max_open'] . ';
$hesk_settings[\'new_top\']=' . $set['new_top'] . ';
$hesk_settings[\'reply_top\']=' . $set['reply_top'] . ';

// --> Features
$hesk_settings[\'autologin\']=' . $set['autologin'] . ';
$hesk_settings[\'autoassign\']=' . $set['autoassign'] . ';
$hesk_settings[\'custopen\']=' . $set['custopen'] . ';
$hesk_settings[\'rating\']=' . $set['rating'] . ';
$hesk_settings[\'cust_urgency\']=' . $set['cust_urgency'] . ';
$hesk_settings[\'sequential\']=' . $set['sequential'] . ';
$hesk_settings[\'list_users\']=' . $set['list_users'] . ';
$hesk_settings[\'debug_mode\']=' . $set['debug_mode'] . ';
$hesk_settings[\'short_link\']=' . $set['short_link'] . ';

// --> SPAM Prevention
$hesk_settings[\'secimg_use\']=' . $set['secimg_use'] . ';
$hesk_settings[\'secimg_sum\']=\'' . $set['secimg_sum'] . '\';
$hesk_settings[\'recaptcha_use\']=' . $set['recaptcha_use'] . ';
$hesk_settings[\'recaptcha_ssl\']=' . $set['recaptcha_ssl'] . ';
$hesk_settings[\'recaptcha_public_key\']=\'' . $set['recaptcha_public_key'] . '\';
$hesk_settings[\'recaptcha_private_key\']=\'' . $set['recaptcha_private_key'] . '\';
$hesk_settings[\'question_use\']=' . $set['question_use'] . ';
$hesk_settings[\'question_ask\']=\'' . $set['question_ask'] . '\';
$hesk_settings[\'question_ans\']=\'' . $set['question_ans'] . '\';

// --> Security
$hesk_settings[\'attempt_limit\']=' . $set['attempt_limit'] . ';
$hesk_settings[\'attempt_banmin\']=' . $set['attempt_banmin'] . ';
$hesk_settings[\'email_view_ticket\']=' . $set['email_view_ticket'] . ';

// --> Attachments
$hesk_settings[\'attachments\']=array (
\'use\' => ' . $set['attachments']['use'] . ',
\'max_number\' => ' . $set['attachments']['max_number'] . ',
\'max_size\' => ' . $set['attachments']['max_size'] . ',
\'allowed_types\' => array(\'' . implode('\',\'',$set['attachments']['allowed_types']) . '\')
);


// ==> KNOWLEDGEBASE

// --> Knowledgebase settings
$hesk_settings[\'kb_enable\']=' . $set['kb_enable'] . ';
$hesk_settings[\'kb_wysiwyg\']=' . $set['kb_wysiwyg'] . ';
$hesk_settings[\'kb_search\']=' . $set['kb_search'] . ';
$hesk_settings[\'kb_search_limit\']=' . $set['kb_search_limit'] . ';
$hesk_settings[\'kb_views\']=' . $set['kb_views'] . ';
$hesk_settings[\'kb_date\']=' . $set['kb_date'] . ';
$hesk_settings[\'kb_recommendanswers\']=' . $set['kb_recommendanswers'] . ';
$hesk_settings[\'kb_rating\']=' . $set['kb_rating'] . ';
$hesk_settings[\'kb_substrart\']=' . $set['kb_substrart'] . ';
$hesk_settings[\'kb_cols\']=' . $set['kb_cols'] . ';
$hesk_settings[\'kb_numshow\']=' . $set['kb_numshow'] . ';
$hesk_settings[\'kb_popart\']=' . $set['kb_popart'] . ';
$hesk_settings[\'kb_latest\']=' . $set['kb_latest'] . ';
$hesk_settings[\'kb_index_popart\']=' . $set['kb_index_popart'] . ';
$hesk_settings[\'kb_index_latest\']=' . $set['kb_index_latest'] . ';


// ==> EMAIL

// --> Email sending
$hesk_settings[\'smtp\']=' . $set['smtp'] . ';
$hesk_settings[\'smtp_host_name\']=\'' . $set['smtp_host_name'] . '\';
$hesk_settings[\'smtp_host_port\']=' . $set['smtp_host_port'] . ';
$hesk_settings[\'smtp_timeout\']=' . $set['smtp_timeout'] . ';
$hesk_settings[\'smtp_ssl\']=' . $set['smtp_ssl'] . ';
$hesk_settings[\'smtp_tls\']=' . $set['smtp_tls'] . ';
$hesk_settings[\'smtp_user\']=\'' . $set['smtp_user'] . '\';
$hesk_settings[\'smtp_password\']=\'' . $set['smtp_password'] . '\';

// --> Email piping
$hesk_settings[\'email_piping\']=' . $set['email_piping'] . ';

// --> POP3 Fetching
$hesk_settings[\'pop3\']=' . $set['pop3'] . ';
$hesk_settings[\'pop3_host_name\']=\'' . $set['pop3_host_name'] . '\';
$hesk_settings[\'pop3_host_port\']=' . $set['pop3_host_port'] . ';
$hesk_settings[\'pop3_tls\']=' . $set['pop3_tls'] . ';
$hesk_settings[\'pop3_keep\']=' . $set['pop3_keep'] . ';
$hesk_settings[\'pop3_user\']=\'' . $set['pop3_user'] . '\';
$hesk_settings[\'pop3_password\']=\'' . $set['pop3_password'] . '\';

// --> Email loops
$hesk_settings[\'loop_hits\']=' . $set['loop_hits'] . ';
$hesk_settings[\'loop_time\']=' . $set['loop_time'] . ';

// --> Detect email typos
$hesk_settings[\'detect_typos\']=' . $set['detect_typos'] . ';
$hesk_settings[\'email_providers\']=array(' . $set['email_providers'] . ');

// --> Other
$hesk_settings[\'strip_quoted\']=' . $set['strip_quoted'] . ';
$hesk_settings[\'save_embedded\']=' . $set['save_embedded'] . ';
$hesk_settings[\'multi_eml\']=' . $set['multi_eml'] . ';
$hesk_settings[\'confirm_email\']=' . $set['confirm_email'] . ';
$hesk_settings[\'open_only\']=' . $set['open_only'] . ';


// ==> MISC

// --> Date & Time
$hesk_settings[\'diff_hours\']=' . $set['diff_hours'] . ';
$hesk_settings[\'diff_minutes\']=' . $set['diff_minutes'] . ';
$hesk_settings[\'daylight\']=' . $set['daylight'] . ';
$hesk_settings[\'timeformat\']=\'' . $set['timeformat'] . '\';

// --> Other
$hesk_settings[\'alink\']=' . $set['alink'] . ';
$hesk_settings[\'submit_notice\']=' . $set['submit_notice'] . ';
$hesk_settings[\'online\']=' . $set['online'] . ';
$hesk_settings[\'online_min\']=' . $set['online_min'] . ';
$hesk_settings[\'check_updates\']=' . $set['check_updates'] . ';


// ==> CUSTOM FIELDS

$hesk_settings[\'custom_fields\']=array (
';

for ($i = 1; $i <= 20; $i++) {
    $settings_file_content.='\'custom'.$i.'\'=>array(\'use\'=>'.$set['custom_fields']['custom'.$i]['use'].',\'place\'=>'.$set['custom_fields']['custom'.$i]['place'].',\'type\'=>\''.$set['custom_fields']['custom'.$i]['type'].'\',\'req\'=>'.$set['custom_fields']['custom'.$i]['req'].',\'name\'=>\''.$set['custom_fields']['custom'.$i]['name'].'\',\'maxlen\'=>'.$set['custom_fields']['custom'.$i]['maxlen'].',\'value\'=>\''.$set['custom_fields']['custom'.$i]['value'].'\')';
    if ($i != 20) {$settings_file_content.=',
';}
}

$settings_file_content.='
);

#############################
#     DO NOT EDIT BELOW     #
#############################
$hesk_settings[\'hesk_version\']=\'' . $set['hesk_version'] . '\';
if ($hesk_settings[\'debug_mode\'])
{
    error_reporting(E_ALL);
}
else
{
    error_reporting(0);
}
if (!defined(\'IN_SCRIPT\')) {die(\'Invalid attempt!\');}';

	// Write to the settings file
	if ( ! file_put_contents(HESK_PATH . 'hesk_settings.inc.php', $settings_file_content) )
	{
		hesk_error($hesklang['err_openset']);
	}

	return true;
} // END hesk_iSaveSettingsFile()


function hesk_iDatabase($problem=0)
{
    global $hesk_settings, $hesk_db_link, $mysql_log;
    hesk_iHeader();
	?>

	<h3>Database settings</h3>

	<br />

	<?php
	if ($problem == 1)
	{
	    hesk_show_error('<br /><br />Double-check all the information below. Contact your hosting company for the correct information to use!<br /><br /><b>MySQL said:</b> '.$mysql_log.'</p>', 'Database connection failed');
	}
    elseif ($problem == 2)
    {
	    hesk_show_error('<b>Database tables already exist!</b><br /><br />
        HESK database tables with <b>'.$hesk_settings['db_pfix'].'</b> prefix already exist in this database!<br /><br />
	    To upgrade an existing HESK installation select <a href="index.php">Update existing install</a> instead.<br /><br />
	    To install a new copy of HESK in use a unique table prefix.');
    }
    elseif ($problem == 3)
    {
	    hesk_show_error('<b>Old database tables not found!</b><br /><br />
        HESK database tables have not been found in this database!<br /><br />
	    To install HESK use the <a href="index.php">New install</a> option instead.');
    }
    elseif ($problem == 4)
    {
	    hesk_show_error('<b>Version '.HESK_NEW_VERSION.' tables already exist!</b><br /><br />
        Your database seems to be compatible with HESK version '.HESK_NEW_VERSION.'<br /><br />
	    To install a new copy of HESK use the <a href="index.php">New install</a> option instead.');
    }
    else
    {
    	hesk_show_notice('To complete setup HESK needs to connect to your database.<br /><br />You must get these settings from your hosting company (where you setup MySQL databases)!');
    }
	?>


	<div align="center">
	<table border="0" width="750" cellspacing="1" cellpadding="5" class="white">
	<tr>
	<td>

	<form action="<?php echo INSTALL_PAGE; ?>" method="post">
	<table>
	<tr>
	<td>Database Host:</td>
	<td><input type="text" name="host" value="<?php echo $hesk_settings['db_host']; ?>" size="40" autocomplete="off" /></td>
	</tr>
	<tr>
	<td>Database Name:</td>
	<td><input type="text" name="name" value="<?php echo $hesk_settings['db_name']; ?>" size="40" autocomplete="off" /></td>
	</tr>
	<tr>
	<td>Database User (login):</td>
	<td><input type="text" name="user" value="<?php echo $hesk_settings['db_user']; ?>" size="40" autocomplete="off" /></td>
	</tr>
	<tr>
	<td>User Password:</td>
	<td><input type="text" name="pass" value="<?php echo $hesk_settings['db_pass']; ?>" size="40" autocomplete="off" /></td>
	</tr>
    <?php
    if (INSTALL_PAGE == 'install.php')
    {
	    ?>
		<tr>
		<td>Table prefix:</td>
		<td><input type="text" name="pfix" value="<?php echo $hesk_settings['db_pfix']; ?>" size="40" autocomplete="off" /></td>
		</tr>
	    <?php
    }
    ?>
	</table>

	<p align="center"><input type="hidden" name="dbtest" value="1" /><input type="submit" value="Continue to Step 4" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
	</form>

	</td>
	</tr>
	</table>
	</div>

	<?php
    hesk_iFooter();
} // End hesk_iDatabase()


function hesk_iCheckSetup()
{
    global $hesk_settings;

    hesk_iHeader();
    $_SESSION['all_passed']=1;
    $correct_this=array();
	?>

	<h3>Check setup</h3>

	<p>Checking whether your server meets all requirements and that files are setup correctly</p>

	<div align="center">
	<table border="0" width="750" cellspacing="1" cellpadding="3" class="white">
	<tr>
	<th class="admin_white"><b>Required</b></th>
	<th class="admin_white"><b>Your setting</b></th>
	<th class="admin_white"><b>Status</b></th>
	</tr>

	<tr>
	<td class="admin_gray"><b>PHP version</b><br />HESK requires PHP version 5.x</td>
	<td class="admin_gray" valign="middle" nowrap="nowrap"><b><?php echo PHP_VERSION; ?></b></td>
	<td class="admin_gray" valign="middle">
	<?php
	if (function_exists('version_compare') && version_compare(PHP_VERSION,'5.0.0','>='))
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	else
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='You are using an old and non-secure version of PHP, ask your host to update your PHP version!';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_white"><b>hesk_settings.inc.php file</b><br />Must be uploaded and writable by the script</td>
	<td class="admin_white" valign="middle" nowrap="nowrap">
	<?php
	$mypassed=1;
	if (file_exists('../hesk_settings.inc.php'))
	{
	    echo '<b><font color="#008000">Exists</font>, ';
	    if (is__writable('../hesk_settings.inc.php'))
	    {
	        echo '<font color="#008000">Writable</font></b>';
	    }
	    else
	    {
	        echo '<font color="#FF0000">Not writable</font></b>';
	        $mypassed=2;
	    }
	}
	else
	{
	    $mypassed=0;
	    echo '<b><font color="#FF0000">Not uploaded</font>, <font color="#FF0000">Not writable</font></b>';
	}
	?>
	</td>
	<td class="admin_white" valign="middle">
	<?php
	if ($mypassed==1)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	elseif ($mypassed==2)
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='Make sure the <b>hesk_settings.inc.php</b> file is writable: on Linux chmod it to 666 or rw-rw-rw-, on Windows (IIS) make sure IUSR account has modify/read/write permissions';
	}
	else
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='Upload the <b>hesk_settings.inc.php</b> file to the server and make sure it\'s writable!';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_gray"><b>attachments directory</b><br />Must exist and be writable by the script</td>
	<td class="admin_gray" valign="middle" nowrap="nowrap">
	<?php
	$mypassed=1;

    $hesk_settings['attach_dir'] = isset($hesk_settings['attach_dir']) ? $hesk_settings['attach_dir'] : 'attachments';
    $hesk_settings['attach_dir'] = '../' . $hesk_settings['attach_dir'];

	if ( ! file_exists($hesk_settings['attach_dir']))
	{
	    @mkdir($hesk_settings['attach_dir'], 0777);
	}

	if (is_dir($hesk_settings['attach_dir'])) {
	    echo '<b><font color="#008000">Exists</font>, ';
	    if (is__writable($hesk_settings['attach_dir']))
	    {
	        echo '<font color="#008000">Writable</font></b>';
	    }
	    else
	    {
	        echo '<font color="#FF0000">Not writable</font></b>';
	        $mypassed=2;
	    }
	}
	else
	{
	    $mypassed=0;
	    echo '<b><font color="#FF0000">Not uploaded</font>, <font color="#FF0000">Not writable</font></b>';
	}
	?>
	</td>
	<td class="admin_gray" valign="middle">
	<?php
	if ($mypassed==1)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	} elseif ($mypassed==2)
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='Make sure the <b>attachments</b> directory is writable: on Linux chmod it to 777 or rwxrwxrwx, on Windows (IIS) make sure IUSR account has modify/read/write permissions';
	}
	else
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='Within hesk folder create a new one called <b>attachments</b> and make sure it is writable: on Linux chmod it to 777 or rwxrwxrwx, on Windows (IIS) make sure IUSR account has modify/read/write permissions';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_white"><b>MySQL Enabled</b><br />MySQL must be enabled in PHP</td>
	<td class="admin_white" valign="middle" nowrap="nowrap">
	<?php
	$mypassed=1;
	if (function_exists('mysql_connect'))
	{
	    echo '<b><font color="#008000">Enabled</font></b>';
	}
	else
	{
	    $mypassed=0;
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Disabled</b></font>';
	    $correct_this[]='MySQL extension is not compiled/enabled in your PHP installation.';
	}
	?>
	</td>
	<td class="admin_white" valign="middle">
	<?php
	if ($mypassed==1)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	else
	{
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_gray"><b>File uploads</b><br />To use file attachments <i>file_uploads</i> must be enabled in PHP</td>
	<td class="admin_gray" valign="middle">
	<?php
	$mypassed=1;
	$can_use_attachments=1;
	if (ini_get('file_uploads'))
	{
	    echo '<b><font color="#008000">Enabled</font></b>';
	}
	else
	{
	    $mypassed=0;
	    $can_use_attachments=0;
	    echo '<b><font color="#FFA500">Disabled</font></b>';
	}
	?>
	</td>
	<td class="admin_gray" valign="middle">
	<?php
	if ($mypassed==1)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	else
	{
	    echo '<font color="#FFA500"><b>Unavailable*</b></font>';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_white"><b>GD Library</b><br />Check if GD library is enabled</td>
	<td class="admin_white" valign="middle" nowrap="nowrap">
	<?php
	$mypassed=1;
	if (extension_loaded('gd') && function_exists('gd_info'))
	{
	    echo '<b><font color="#008000">Enabled</font></b>';
	    $can_use_gd=1;
	}
	else
	{
	    $mypassed=0;
	    $can_use_gd=0;
	    echo '<font color="#FF0000"><b>Disabled</b></font>';
	}
	?>
	</td>
	<td class="admin_white" valign="middle">
	<?php
	if ($mypassed==1)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	else
	{
	    echo '<font color="#ff9900"><b>Not available</b></font>';
	}
	?>
	</td>
	</tr>

	<tr>
	<td class="admin_gray"><b>Check files</b><br />Making sure any outdated files aren't present</td>
	<td class="admin_gray" valign="middle" nowrap="nowrap"><b>Checking...</b></td>
	<td class="admin_gray" valign="middle">
	<?php
	$old_files = array(

	    // pre-0.93 *.inc files
	    'hesk_settings.inc','hesk.sql','inc/common.inc','inc/database.inc','inc/footer.inc','inc/header.inc',
	    'inc/print_tickets.inc','inc/show_admin_nav.inc','inc/show_search_form.inc','install.php','update.php',

		// pre-2.0 files
		'admin.php','admin_change_status.php','admin_main.php','admin_move_category','admin_reply_ticket.php',
	    'admin_settings.php','admin_settings_save.php','admin_ticket.php','archive.php',
	    'delete_tickets.php','find_tickets.php','manage_canned.php','manage_categories.php',
	    'manage_users.php','profile.php','show_tickets.php',

		// pre-2.1 files
		'emails/','language/english.php',

	    // pre-2.3 files
	    'secimg.inc.php','hesk_style.css',

	    // pre-2.4 files
	    'hesk_style_v23.css','hesk_javascript.js','help_files/','TreeMenu.js',

        // malicious files that were found on some websites illegally redistributing HESK
        'inc/tiny_mce/utils/r00t10.php', 'language/en/help_files/r00t10.php',

        // pre-2.5 files
        'hesk_style_v24.css', 'hesk_javascript_v24.js',
	    );
	sort($old_files);
	$still_exist = array();

	foreach ($old_files as $f)
	{
		if (file_exists('../'.$f))
	    {
	    	$still_exist[] = $f;
	    }
	}

	if (count($still_exist) == 0)
	{
	    echo '<font color="#008000"><b>Passed</b></font>';
	}
	else
	{
	    $_SESSION['all_passed']=0;
	    echo '<font color="#FF0000"><b>Failed</b></font>';
	    $correct_this[]='For security reasons delete these old files and folders from your
	    main HESK folder:<br />&nbsp;<br /><ul><li><b>'.implode('</b></li><li><b>',$still_exist).'</b></li></ul>';
	}
	?>
	</td>
	</tr>

	</table>
	</div>

	<?php
	if ($can_use_attachments==0)
	{
	    echo '<p><font color="#FFA500"><b>*</b></font> Hesk will still work if all other tests are successful, but File attachments won\'t work.</p>';
	    $_SESSION['set_attachments']=0;
	}
	else
	{
		$_SESSION['set_attachments']=1;
	}

	if ($can_use_gd==0)
	{
	    echo '<p><font color="#FFA500"><b>*</b></font> Hesk will work without GD library but you will not be able to use the anti-SPAM image (captcha)</p>';
	    $_SESSION['set_captcha']=0;
	    $_SESSION['use_spamq']=1;
	}
	else
	{
		$_SESSION['set_captcha']=1;
	    $_SESSION['use_spamq']=0;
	}
	?>

	<p>&nbsp;</p>

	<?php
	if (!empty($correct_this))
	{
		?>
		<div align="center">
		<table border="0" width="750" cellspacing="1" cellpadding="3">
		<tr>
		<td>
		<p><font color="#FF0000"><b>You will not be able to continue installation until the required tests are passed. Things you need to correct before continuing installation:</b></font></p>
		<ol>
		<?php
		foreach($correct_this as $mythis)
		{
		    echo "<li>$mythis<br />&nbsp;</li>";
		}
		?>
		</ol>
		<form method="post" action="<?php echo INSTALL_PAGE; ?>">
		<p align="center">&nbsp;<br /><input type="submit" value="Test again" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
		</form>
		</td>
		</tr>
		</table>
		</div>
		<?php
	}
	else
	{
		$_SESSION['step']=3;
		?>
		<form method="POST" action="<?php echo INSTALL_PAGE; ?>">
		<div align="center">
		<table border="0">
		<tr>
		<td>
		<p align="center"><font color="#008000"><b>All required tests passed, you may now continue to database setup</b></font></p>
		<p align="center"><input type="submit" value="Continue to Step 3" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
		</td>
		</tr>
		</table>
		</div>
		</form>

		<?php
	}

    hesk_iFooter();
} // End hesk_iCheckSetup()


function hesk_iStart()
{
	global $hesk_settings;

	// Set this session variable to check later if sessions are working
	$_SESSION['works'] = true;

	hesk_iHeader();
	?>

<h3>License agreement</h3>

<p><b>Summary:</b></p>

<ul>
<li>The script is provided &quot;as is&quot;, without any warranty. Use at your own risk.<br />&nbsp;</li>
<li>HESK is a registered trademark, using the term HESK requires permission.<br />&nbsp;</li>
<li>Do not redistribute this script without express written permission<br />&nbsp;</li>
<li>To remove the &quot;Powered by&quot; links you must purchase a <a href="https://www.hesk.com/buy.php" target="_blank">license</a></li>
</ul>

<br />

<p><b>The entire agreement:</b></p>

<p align="center"><textarea rows="15" cols="80">
LICENSE AGREEMENT

The &quot;script&quot; is all files included with the HESK distribution archive as well as all files produced as a result of the installation scripts. Klemen Stirn (&quot;Author&quot;,&quot;HESK&quot;) is the author and copyrights owner of the script. The &quot;Licensee&quot; (&quot;you&quot;) is the person downloading or using the Licensed version of script. &quot;User&quot; is any person using or viewing the script with their HTML browser.

&quot;Powered by&quot; link is herein defined as an anchor link pointing to HESK website and/or script webpage, usually located at the bottom of the script and visible to users of the script without looking into source code.

&quot;Copyright headers&quot; is a written copyright notice located in script source code and normally not visible to users.

This License may be modified by the Author at any time. The new version of the License becomes valid when published on HESK website. You are encouraged to regularly check back for License updates.

THIS SCRIPT IS PROVIDED &quot;AS IS&quot; AND ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL KLEMEN STIRN BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SCRIPT, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

Using this code, in part or full, to create derivative work, new scripts or products is expressly forbidden. Obtain permission before redistributing this software over the Internet or in any other medium.

REMOVING POWERED BY LINKS
You are not allowed to remove or in any way edit the &quot;Powered by&quot; links in this script without purchasing a License. You can purchase a License at
https://www.hesk.com/buy.php

If you remove the Powered by links without purchasing a License and paying the licensee fee, you are in a direct violation of European Union and International copyright laws. Your License to use the scripts is immediately terminated and you must delete all copies of the entire program from your web server. Klemen Stirn may, at any time, terminate this License agreement if Klemen Stirn determines, that this License agreement has been breached.

Under no circumstance is the removal of copyright headers from the script source code permitted.

TRADEMARK POLICY

HESK is a US registered trademark of Klemen Stirn. Certain usages of the Trademark are fine and no specific permission from the author is needed:

- there is no commercial intent behind the use

- what you are referring to is in fact HESK. If someone is confused into thinking that what isn't HESK is in fact HESK, you are probably doing something wrong

- there is no suggestion (through words or appearance) that your project is approved, sponsored, or affiliated with HESK or its related projects unless it actually has been approved by and is accountable to the author

Permission from the author is necessary to use the HESK trademark under any circumstances other than those specifically permitted above. These include:

- any commercial use

- use on or in relation to a software product that includes or is built on top of a product supplied by author, if there is any commercial intent associated with that product

- use in a domain name or URL

- use for merchandising purposes, e.g. on t-shirts and the like

- use of a name which includes the letters HESK in relation to computer hardware or software.

- services relating to any of the above

If you wish to have permission for any of the uses above or for any other use which is not specifically referred to in this policy, please contact me and I'll let you know as soon as possible if your proposed use is permissible. Note that due to the volume of mail I receive, it may take some time to process your request. Permission may only be granted subject to certain conditions and these may include the requirement that you enter into an agreement with me to maintain the quality of the product and/or service which you intend to supply at a prescribed level.

While there may be exceptions, it is very unlikely that I will approve Trademark use in the following cases:

- use of a Trademark in a company name

- use of a Trademark in a domain name which has a commercial intent. The commercial intent can range from promotion of a company or product, to collecting revenue generated by advertising

- the calling of any software or product by the name HESK (or another related Trademark), unless that software or product is a substantially unmodified HESK product

- use in combination with any other marks or logos. This include use of a Trademark in a manner that creates a "combined mark," or use that integrates other wording with the Trademark in a way that the public may think of the use as a new mark (for example Club HESK or HESKBooks, or in a way that by use of special fonts or presentation with nearby words or images conveys an impression that the two are tied in some way)

- use in combination with any product or service which is presented as being Certified or Official or formally associated with me or my products or services

- use in a way which implies an endorsement where that doesn't exist, or which attempts to unfairly or confusingly capitalise on the goodwill or brand of the project

- use of a Trademark in a manner that disparages HESK and is not clearly third-party parody

- on or in relation to a software product which constitutes a substantially modified version of a product supplied by HESK.com, that is to say with material changes to the code, or services relating to such a product

- in a title or metatag of a web page whose sole intention or result is to influence search engine rankings or result listings, rather than for discussion, development or advocacy of the Trademarks

OTHER

This License Agreement is governed by the laws of Slovenia, European Union. Both the Licensee and Klemen Stirn submit to the jurisdiction of the courts of Slovenia, European Union. Both the Licensee and Klemen Stirn agree to commence any litigation that may arise hereunder in the courts located in Slovenia.

If any provision hereof shall be held illegal, invalid or unenforceable, in whole or in part, such provision shall be modified to the minimum extent necessary to make it legal, valid and enforceable, and the legality, validity and enforceability of all other provisions of this Agreement shall not be affected thereby. No delay or failure by either party to exercise or enforce at any time any right or provision hereof shall be considered a waiver thereof or of such party's right thereafter to exercise or enforce each and every right and provision of this Agreement.
</textarea></p>

<br />

<form method="post" action="<?php echo INSTALL_PAGE; ?>" name="license" onsubmit="return hesk_checkAgree()">
<div align="center">
<table border="0">
<tr>
<td>

	<p><b>Do you agree to the License agreement and all the terms incorporated therein?</b> <font color="#FF0000"><i>(required)</i></font></b></p>

	<p align="center">
	<input type="hidden" name="agree" value="YES" />
	<input type="button" onclick="javascript:parent.location='index.php'" value="NO, I DO NOT AGREE (Cancel setup)" class="orangebuttonsec" onmouseover="hb_btn(this,'orangebuttonsecover');" onmouseout="hb_btn(this,'orangebuttonsec');" />
	&nbsp;
	<input type="submit" value="YES, I AGREE (Click to continue) &raquo;" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" />
	</p>

    <p>&nbsp;</p>

</td>
</tr>
</table>
</div>
</form>

	<?php
    hesk_iFooter();
} // End hesk_iStart()


function hesk_iHeader()
{
    global $hesk_settings;

	$steps = array(
    	1 => '1. License agreement',
        2 => '2. Check setup',
        3 => '3. Database settings',
        4 => '4. Setup database tables'
        );

	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
	<title>HESK setup script: <?php echo HESK_NEW_VERSION; ?></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<link href="../hesk_style_v25.css" type="text/css" rel="stylesheet" />
	<script language="Javascript" type="text/javascript" src="../hesk_javascript_v24.js"></script>
	</head>
	<body>

	<div align="center">
	<table border="0" cellspacing="0" cellpadding="5" class="enclosing">
	<tr>
	<td>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr>
		<td width="3"><img src="../img/headerleftsm.jpg" width="3" height="25" alt="" /></td>
		<td class="headersm">HESK setup script: <?php echo HESK_NEW_VERSION; ?></td>
		<td width="3"><img src="../img/headerrightsm.jpg" width="3" height="25" alt="" /></td>
		</tr>
		</table>
	</td>
	</tr>
	<tr>
	<td>

    <?php
    if ( isset($_SESSION['step']) )
    {
    	$_SESSION['step'] = intval($_SESSION['step']);
    	?>

		<table border="0" width="100%">
		<tr>
		<td>
        <?php
        foreach ($steps as $number => $description)
        {
        	if ($number == $_SESSION['step'])
            {
            	$steps[$number] = '<b>' . $steps[$number] . '</b>';
            }
            elseif ($number < $_SESSION['step'])
            {
            	$steps[$number] = '<span style="color:#008000">' . $steps[$number] . '</span>';
            }
        }

        echo implode(' &raquo; ', $steps);
        ?>
        </td>
		</tr>
		</table>

		<br />
	<?php
    }
    else
    {
		hesk_show_notice('<a href="../docs/index.html">Read installation guide</a> before using this setup script!', 'Important');
    }
    ?>

    <div align="center">
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>
	<?php
} // End hesk_iHeader()


function hesk_iFooter()
{
	global $hesk_settings;
	?>
		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>
    </div>

	<p style="text-align:center"><span class="smaller">&nbsp;<br />Powered by <a href="http://www.hesk.com" class="smaller" title="Free PHP Help Desk Software">Help Desk Software</a> <b>HESK</b> - brought to you by <a href="http://www.ilient.com">Help Desk Software</a> SysAid</span></p>
	</td>
	</tr>
	</table>
	</div>
	</body>
	</html>
	<?php
    exit();
} // End hesk_iFooter()


function hesk_iSessionError()
{
	hesk_session_stop();
	hesk_iHeader();
	?>

	<br />
	<div class="error">
		<img src="<?php echo HESK_PATH; ?>img/error.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" />
		<b>Error:</b> PHP sessions not working!<br /><br />Note that this is a server configuration issue, not a HESK issue.<br /><br />Please contact your hosting company and ask them to verify why PHP sessions aren't working on your server!
	</div>
	<br />

	<form method="get" action="<?php echo INSTALL_PAGE; ?>">
	<p align="center"><input type="submit" value="&laquo; Start over" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
	</form>

	<?php
	hesk_iFooter();
} // END hesk_iSessionError()


function hesk_compareVariable($k,$v)
{
	global $hesk_settings;

    if (is_array($v))
    {
    	foreach ($v as $sub_k => $sub_v)
        {
			$v[$k] = hesk_compareVariable($sub_k,$sub_v);
        }
    }

	if (isset($hesk_settings[$k]))
    {
    	return $hesk_settings[$k];
    }
    else
    {
    	return $v;
    }
} // END hesk_compareVariable()


function is__writable($path)
{
//will work in despite of Windows ACLs bug
//NOTE: use a trailing slash for folders!!!
//see http://bugs.php.net/bug.php?id=27609
//see http://bugs.php.net/bug.php?id=30931

    if ($path{strlen($path)-1}=='/') // recursively return a temporary file path
        return is__writable($path.uniqid(mt_rand()).'.tmp');
    else if (is_dir($path))
        return is__writable($path.'/'.uniqid(mt_rand()).'.tmp');
    // check tmp file for read/write capabilities
    $rm = file_exists($path);
    $f = @fopen($path, 'a');
    if ($f===false)
        return false;
    fclose($f);
    if (!$rm)
        unlink($path);
    return true;
} // END is__writable()
