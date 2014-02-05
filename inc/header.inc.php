<?php
/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Includes
 * @subpackage  Header
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseHeader')){
    class HelpbaseHeader{
        private $helpbase = null;
        
        public function __construct($parent) {
            $this->helpbase = $parent;
        }
        
        public function render(){
            global $hesk_settings;
            
            $url = $this->helpbase->url;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title><?php echo (isset($hesk_settings['tmp_title']) ? $hesk_settings['tmp_title'] : $hesk_settings['hesk_title']); ?></title>
        <meta http-equiv="Content-Type" content="text/html;charset=<?php echo $this->helpbase->encoding; ?>" />
        <link href="<?php echo $url; ?>css/helpbase.css" type="text/css" rel="stylesheet" />
        <script language="Javascript" type="text/javascript" src="<?php echo $url; ?>js/helpbase.js"></script>
<?php

            /* Prepare Javascript that browser should load on page load */
            $onload = "javascript:var i=new Image();i.src='" . $url . "img/orangebtnover.gif';var i2=new Image();i2.src='" . $url . "img/greenbtnover.gif';";

            /* Tickets shouldn't be indexed by search engines */
            if (true == $this->helpbase->no_robots) {
?>
        <meta name="robots" content="noindex, nofollow" />
<?php
            }

            /* If page requires calendar include calendar Javascript and CSS */
            if (true == $this->helpbase->load_calander) {
?>
        <script language="Javascript" type="text/javascript" src="<?php echo $url; ?>inc/calendar/tcal.php"></script>
        <script language="Javascript" type="text/javascript" src="<?php echo $url; ?>inc/calendar/tcal.js"></script>
        <link href="<?php echo $url; ?>inc/calendar/tcal.css" type="text/css" rel="stylesheet" />
<?php
            }

            /* If page requires WYSIWYG editor include TinyMCE Javascript */
            if (true == $this->helpbase->wysiwyg && $hesk_settings['kb_wysiwyg']) {
?>
        <script type="text/javascript" src="<?php echo $url; ?>inc/tiny_mce/3.5.9/tiny_mce.js"></script>
<?php
            }

            /* If page requires tabs load tabs Javascript and CSS */
            if (true == $this->helpbase->load_tabs) {
?>
        <link href="<?php echo $url; ?>inc/tabs/tabber.css" type="text/css" rel="stylesheet" />
<?php
            }

            /* If page requires timer load Javascript */
            if (true == $this->helpbase->timer) {
?>
        <script language="Javascript" type="text/javascript" src="<?php echo $url; ?>inc/timer/hesk_timer.js"></script>
<?php

                /* Need to load default time or a custom one? */
                if (isset($_SESSION['time_worked'])) {
                    $t = $this->helpbase->admin->getHHMMSS($_SESSION['time_worked']);
                    $onload .= "load_timer('time_worked', " . $t[0] . ", " . $t[1] . ", " . $t[2] . ");";
                    unset($t);
                } else {
                    $onload .= "load_timer('time_worked', 0, 0, 0);";
                }

                /* Autostart timer? */
                if (!empty($_SESSION['autostart'])) {
                    $onload .= "ss();";
                }
            }
            ?>

    </head>
    <body onload="<?php echo $onload;
            unset($onload); ?>">
        <div style="center">
<?php
            include($url . 'header.txt');
?>
            <table class="enclosing">
                <tr>
                    <td>
<?php
        }
    }
}