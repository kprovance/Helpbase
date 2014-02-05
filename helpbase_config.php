
<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Database config 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */                    

//1391392949

if (!defined('EXECUTING')) {
    exit();
}

$database['db_host']    = 'localhost';
$database['db_name']    = 'tpasoftc_hesk';
$database['db_user']    = 'root';
$database['db_pass']    = '';
$database['db_pre']     = 'hesk_';
$database['db_ver']     = 1;

$locale['language']     = 'English';
$locale['languages']    = array(
    'Deutsch' => 'de',
    'English' => 'en',
);

?>