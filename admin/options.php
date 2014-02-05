<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Options
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

global $helpbase;

define('EXECUTING', true);

include_once('../helpbase.class.php');
$helpbase = new HelpbaseCore(true);

$id     = $helpbase->common->_input( $helpbase->common->_get('i') );
$query  = $helpbase->common->_input( $helpbase->common->utf8_urldecode( $helpbase->common->_get('q') ) );
$type   = $helpbase->common->_input( $helpbase->common->_get('t', 'text') );
$maxlen = intval( $helpbase->common->_get('m', 255) );
$query  = stripslashes($query);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title><?php echo _('Options'); ?></title>
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
        text-align:center;
}
.title
{
        color : black;
        font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
        font-weight: bold;
        font-size: 1.0em;
}
.wrong   {color : red;}
.correct {color : green;}
</style>
</head>
<body>

<h3><?php echo _('Options'); ?></h3>

<p><i><?php echo _('These are available options for this custom field. To save changes click <b>OK</b> and <b>Save changes</b> button on the admin settings page!'); ?></i></p>

<?php

switch ($type)
{
	case 'text':
    	echo '
        <script language="javascript">
        function hesk_saveOptions()
        {
        	window.opener.document.getElementById(\'s_'.$id.'_val\').value = document.getElementById(\'o2\').value;
            window.opener.document.getElementById(\'s_'.$id.'_maxlen\').value = document.getElementById(\'o1\').value;
            window.close();
        }
        </script>
		<table border="0">
        <tr>
        <td>' . _('Maximum length (chars)') . ':<td>
        <td><input type="text" name="o1" id="o1" value="'.$maxlen.'" size="30" /></td>
        </tr>
        <tr>
        <td>' . _('Default value') . ':<td>
        <td><input type="text" name="o2" id="o2" value="'.$query.'" size="30" /></td>
        </tr>
        </table>
        <p><input type="button" value="  ' . _('OK') . '  " onclick="Javascript:hesk_saveOptions()" /></p>
        ';
    	break;
    case 'textarea':
    	if (strpos($query,'#') !== false)
        {
        	list($rows,$cols)=explode('#',$query);
        }
        else
        {
        	$rows = '';
            $cols = '';
        }
    	echo '
        <script language="javascript">
        function hesk_saveOptions()
        {
        	window.opener.document.getElementById(\'s_'.$id.'_val\').value = document.getElementById(\'o1\').value + "#" + document.getElementById(\'o2\').value;
            window.close();
        }
        </script>
		<table border="0">
        <tr>
        <td>' . _('Rows (height)') . ':<td>
        <td><input type="text" name="o1" id="o1" value="'.$rows.'" size="5" /></td>
        </tr>
        <tr>
        <td>' . _('Columns (width)') . ':<td>
        <td><input type="text" name="o2" id="o2" value="'.$cols.'" size="5" /></td>
        </tr>
        </table>
        <p><input type="button" value="  ' . _('OK') . '  " onclick="Javascript:hesk_saveOptions()" /></p>
        ';
    	break;
    case 'radio':
    	$options=str_replace('#HESK#',"\n",$query);
    	echo '
        <script language="javascript">
        function hesk_saveOptions()
        {
        	text = document.getElementById(\'o1\').value;
            text = text.replace(/^\s\s*/, \'\').replace(/\s\s*$/, \'\');
			text = escape(text);
			if(text.indexOf(\'%0D%0A\') > -1)
			{
				re_nlchar = /%0D%0A/g ;
			}
		    else if(text.indexOf(\'%0A\') > -1)
			{
				re_nlchar = /%0A/g ;
            }
				else if(text.indexOf(\'%0D\') > -1)
			{
				re_nlchar = /%0D/g ;
			}
            else
            {
            	alert(\'' . addslashes(_('Enter at least two options (one per line)!')) . '\');
                return false;
            }
			text = unescape(text.replace(re_nlchar,\'#HESK#\'));

        	window.opener.document.getElementById(\'s_'.$id.'_val\').value = text;
            window.close();
        }
        </script>

        <p>' . _('Options for this radio button, enter one option per line (each line will create a new radio button value to choose from). You need to enter at least two options!') . '</p>
        <textarea name="o1" id="o1" rows="6" cols="40">'.$options.'</textarea>
        <p><input type="button" value="  ' . _('OK') . '  " onclick="Javascript:hesk_saveOptions()" /></p>
        ';
    	break;
    case 'select':
    	$options=str_replace('#HESK#',"\n",$query);
    	echo '
        <script language="javascript">
        function hesk_saveOptions()
        {
        	text = document.getElementById(\'o1\').value;
            text = text.replace(/^\s\s*/, \'\').replace(/\s\s*$/, \'\');
			text = escape(text);
			if(text.indexOf(\'%0D%0A\') > -1)
			{
				re_nlchar = /%0D%0A/g ;
			}
		    else if(text.indexOf(\'%0A\') > -1)
			{
				re_nlchar = /%0A/g ;
            }
			else if(text.indexOf(\'%0D\') > -1)
			{
				re_nlchar = /%0D/g ;
			}
            else
            {
            	alert(\'' . addslashes(_('Enter at least two options (one per line)!')) . '\');
                return false;
            }
			text = unescape(text.replace(re_nlchar,\'#HESK#\'));

        	window.opener.document.getElementById(\'s_'.$id.'_val\').value = text;
            window.close();
        }
        </script>

        <p>' . _('Options for this select box, enter one option per line (each line will be a choice your customers can choose from). You need to enter at least two options!') . '</p>
        <textarea name="o1" id="o1" rows="6" cols="40">'.$options.'</textarea>
        <p><input type="button" value="  ' . _('OK') . '  " onclick="Javascript:hesk_saveOptions()" /></p>
        ';
    	break;
    case 'checkbox':
    	$options=str_replace('#HESK#',"\n",$query);
    	echo '
        <script language="javascript">
        function hesk_saveOptions()
        {
        	text = document.getElementById(\'o1\').value;
            text = text.replace(/^\s\s*/, \'\').replace(/\s\s*$/, \'\');
			text = escape(text);
			if(text.indexOf(\'%0D%0A\') > -1)
			{
				re_nlchar = /%0D%0A/g ;
			}
		    else if(text.indexOf(\'%0A\') > -1)
			{
				re_nlchar = /%0A/g ;
            }
			else if(text.indexOf(\'%0D\') > -1)
			{
				re_nlchar = /%0D/g ;
			}
            else
            {
            	alert(\'' . addslashes(_('Enter at least two options (one per line)!')) . '\');
                return false;
            }
			text = unescape(text.replace(re_nlchar,\'#HESK#\'));

        	window.opener.document.getElementById(\'s_'.$id.'_val\').value = text;
            window.close();
        }
        </script>

        <p>' . _('Options for this checkbox, enter one option per line. Each line will be a choice your customers can choose from, multiple choices are possible. You need to enter at least two options!') . '</p>
        <textarea name="o1" id="o1" rows="6" cols="40">'.$options.'</textarea>
        <p><input type="button" value="  ' . _('OK') . '  " onclick="Javascript:hesk_saveOptions()" /></p>
        ';
    	break;
    default:
    	die('Invalid type');
}
?>

<p align="center"><a href="#" onclick="Javascript:window.close()"><?php echo _('Close window'); ?></a></p>

<p>&nbsp;</p>

</body>

</html>
<?php

unset ($helpbase);

exit();
?>
