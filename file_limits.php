<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  File Limits 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('./helpbase.class.php');
$helpbase = new HelpbaseCore(false);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title><?php echo _('File upload limits'); ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=<?php echo $helpbase->encoding; ?>" />
<style type="text/css">
body
{
	margin:5px 5px;
	padding:0;
	background:#fff;
	color: black;
	font : 68.8%/1.5 Verdana, Geneva, Arial, Helvetica, sans-serif;
	text-align:left;
}

p
{
	color : black;
	font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
	font-size: 1.0em;
}
h3
{
	color : #AF0000;
	font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 1.0em;
	text-align:left;
}
</style>
</head>
<body>

<h3><?php echo _('File upload limits'); ?></h3>

<table border="0" cellspacing="1" cellpadding="3">
<tr>
<td valign="top">&raquo;</td>
<td valign="top"><?php echo _('Maximum number of attachments:'); ?> <b><?php echo $hesk_settings['attachments']['max_number']; ?></b></td>
</tr>
<tr>
<td valign="top">&raquo;</td>
<td valign="top"><?php echo _('Maximum size per attachment:'); ?> <b><?php echo $helpbase->common->formatBytes($hesk_settings['attachments']['max_size']); ?></b></td>
</tr>
<tr>
<td valign="top">&raquo;</td>
<td valign="top"><?php echo _('You may upload files ending with:'); ?>
<p><?php echo implode(', ', $hesk_settings['attachments']['allowed_types']); ?></p>
</td>
</tr>
</table>

<p align="center"><a href="#" onclick="Javascript:window.close()"><?php echo _('Close window'); ?></a></p>

<p>&nbsp;</p>

</body>

</html>
<?php

unset($helpbase);

?>