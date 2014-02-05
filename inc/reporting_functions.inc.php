<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Reporting Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

/* * * FUNCTIONS ** */

function hesk_SecondsToHHMMSS($in) {
    // Default values for hours, minutes and seconds
    $h = 0;
    $m = 0;
    $s = intval(trim($in));

    // If time is 0 seconds just return an empty string
    if ($s == 0) {
        return '';
    }

    // Convert seconds to minutes if 60 or more seconds
    if ($s > 59) {
        $m = floor($s / 60) + $m;
        $s = intval($s % 60);
    }

    // Convert minutes to hours if 60 or more minutes
    if ($m > 59) {
        $h = floor($m / 60) + $h;
        $m = intval($m % 60);
    }

    // That's it, let's send out formatted time string
    return str_pad($h, 2, "0", STR_PAD_LEFT) . ':' . str_pad($m, 2, "0", STR_PAD_LEFT) . ':' . str_pad($s, 2, "0", STR_PAD_LEFT);
}

// END hesk_SecondsToHHMMSS()

function hesk_parseXML($msg) {
    $from = array('/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
    $to = array("$1", "$1");
    $msg = preg_replace($from, $to, $msg);
    $msg = preg_replace('/<br \/>\s*/', "\n", $msg);
    $msg = trim($msg);
    return $msg;
}

// END hesk_parseXML()

function dateweek($weeknumber, $business = 0) {
    $x = strtotime("last Monday");
    $Year = date("Y", $x);
    $Month = date("m", $x);
    $Day = date("d", $x);

    if ($Month < 2 && $Day < 8) {
        $Year = $Year--;
        $Month = $Month--;
    }

    if ($Month > 1 && $Day < 8) {
        $Month = $Month--;
    }
    //DATE BEGINN OF THE WEEK ( Monday )
    $Day = $Day + 7 * $weeknumber;
    $dt[0] = date('Y-m-d', mktime(0, 0, 0, $Month, $Day, $Year));

    if ($business) {
        //DATE END OF BUSINESS WEEK ( Friday )
        $Day = $Day + 4;
        $dt[1] = date('Y-m-d', mktime(0, 0, 0, $Month, $Day, $Year));
    } else {
        //DATE END OF THE WEEK ( Sunday )
        $Day = $Day + 6;
        $dt[1] = date('Y-m-d', mktime(0, 0, 0, $Month, $Day, $Year));
    }

    return $dt;
}

// END dateweek()

function DateArray($s, $e) {
    $start = strtotime($s);
    $end = strtotime($e);
    $da = array();
    for ($n = $start; $n <= $end; $n += 86400) {
        $da[] = date('Y-m-d', $n);
    }
    return $da;
}

// END DateArray()

function MonthsArray($s, $e) {
    $start = date('Y-m-01', strtotime($s));
    $end = date('Y-m-01', strtotime($e));
    $mt = array();
    while ($start <= $end) {
        $mt[] = $start;
        $start = date('Y-m-01', strtotime("+1 month", strtotime($start)));
    }
    return $mt;
}

// END MonthsArray()

function hesk_getOldestDate() {
    global $helpbase, $hesk_settings, $date_from, $date_to;

    $res = $helpbase->database->query("SELECT `dt` FROM `" . $helpbase->database->escape($hesk_settings['db_pfix']) . "tickets` ORDER BY `dt` ASC LIMIT 1");

    if ($helpbase->database->numRows($res) == 1) {
        $row = $helpbase->database->fetchAssoc($res);
        return date('Y-m-d', strtotime($row['dt']));
    } else {
        return date('Y-m-d');
    }
}

// END hesk_getOldestDate()
