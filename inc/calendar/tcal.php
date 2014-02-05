<?php
/*******************************************************************************
*  Title: Help Desk Software HESK
*  Version: 2.5.1 from 8th August 2013
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

define('EXECUTING', true);

include_once('../../helpbase.class.php');

// Tigra Calendar v5.2 (11/20/2011)
// http://www.softcomplex.com/products/tigra_calendar/
// License: Public Domain... You're welcome.

// Modified for HelpBase by C. Kevin Provance
// http://www.tpasoft.com
// 01/22/2014

// default settins - this structure can be moved in separate file in multilangual applications
?>

var A_TCALCONF = {
    'cssprefix'  : 'tcal',
    'months'     : ['<?php echo _('January'); ?>', '<?php echo _('February'); ?>', '<?php echo _('March'); ?>', '<?php echo _('April'); ?>', '<?php echo _('May'); ?>', '<?php echo _('June'); ?>', '<?php echo _('July'); ?>', '<?php echo _('August'); ?>', '<?php echo _('September'); ?>', '<?php echo _('October'); ?>', '<?php echo _('November'); ?>', '<?php echo _('December'); ?>'],
    'weekdays'   : ['<?php echo _('Sun'); ?>', '<?php echo _('Mon'); ?>', '<?php echo _('Tues'); ?>', '<?php echo _('Wed'); ?>', '<?php echo _('Thur'); ?>', '<?php echo _('Fri'); ?>', '<?php echo _('Sat'); ?>'],
    'longwdays'  : ['<?php echo _('Sunday'); ?>', '<?php echo _('Monday'); ?>', '<?php echo _('Tuesday'); ?>', '<?php echo _('Wednesday'); ?>', '<?php echo _('Thursday'); ?>', '<?php echo _('Friday'); ?>', '<?php echo _('Saturday'); ?>'],
    'yearscroll' : true, // show year scroller
    'weekstart'  : 0, // first day of week: 0-Su or 1-Mo
    'prevyear'   : '<?php echo _('Previous Year'); ?>',
    'nextyear'   : '<?php echo _('Next Year'); ?>',
    'prevmonth'  : '<?php echo _('Previous Month'); ?>',
    'nextmonth'  : '<?php echo _('Next Month'); ?>',
    'format'     : 'm/d/Y' // 'd-m-Y', Y-m-d', 'l, F jS Y'
};
