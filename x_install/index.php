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

define('EXECUTING', true);
define('HESK_PATH','../');

require(HESK_PATH . 'install/install_functions.inc.php');

// Reset installation steps
hesk_session_stop();

hesk_iHeader();
?>

<h3>Thank you for downloading HESK!</h3>

<p>This script will help you install and configure HESK <?php echo HESK_NEW_VERSION; ?></p>

<hr />

<form method="get" action="install.php">
<p align="center"><input type="submit" value="New install &raquo;" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
<p align="center">Install a new copy of HESK on your server</p>
</form>

<hr />

<form method="get" action="update.php">
<p align="center"><input type="submit" value="Update existing install &raquo;" class="orangebutton" onmouseover="hb_btn(this,'orangebuttonover');" onmouseout="hb_btn(this,'orangebutton');" /></p>
<p align="center">Update existing HESK to version <?php echo HESK_NEW_VERSION; ?></p>
</form>

<?php
hesk_iFooter();
exit();
?>
