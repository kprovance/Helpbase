<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Posting Functions
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

/* Check if this is a valid include */
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbasePosting')) {
    class HelpbasePosting {
        private $helpbase = null;
        
        public function __construct($core){
            $this->helpbase = $core;
        }
        
        public function newTicket($ticket) {
            global $hesk_settings, $hesk_db_link;

            // If language is not set or default, set it to NULL
            $language = (!$hesk_settings['can_sel_lang'] || $this->helpbase->common->language == 'English' ) ? "NULL" : "'" . $this->helpbase->database->escape($this->helpbase->common->language) . "'";

            // Insert ticket into database
            $this->helpbase->database->query("
                INSERT INTO `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "tickets`
                (
                        `trackid`,
                        `name`,
                        `email`,
                        `category`,
                        `priority`,
                        `subject`,
                        `message`,
                        `dt`,
                        `lastchange`,
                        `ip`,
                        `language`,
                        `owner`,
                        `attachments`,
                        `merged`,
                        `history`,
                        `custom1`,
                        `custom2`,
                        `custom3`,
                        `custom4`,
                        `custom5`,
                        `custom6`,
                        `custom7`,
                        `custom8`,
                        `custom9`,
                        `custom10`,
                        `custom11`,
                        `custom12`,
                        `custom13`,
                        `custom14`,
                        `custom15`,
                        `custom16`,
                        `custom17`,
                        `custom18`,
                        `custom19`,
                        `custom20`,
                        `company`,
                        `homephone`,
                        `mobilephone`,
                        `workphone`,
                        `devicetype`,
                        `devicebrand`,
                        `deviceid`
                )
                VALUES
                (
                        '" . $this->helpbase->database->escape($ticket['trackid']) . "',
                        '" . $this->helpbase->database->escape($ticket['name']) . "',
                        '" . $this->helpbase->database->escape($ticket['email']) . "',
                        '" . intval($ticket['category']) . "',
                        '" . intval($ticket['priority']) . "',
                        '" . $this->helpbase->database->escape($ticket['subject']) . "',
                        '" . $this->helpbase->database->escape($ticket['message']) . "',
                        NOW(),
                        NOW(),
                        '" . $this->helpbase->database->escape($_SERVER['REMOTE_ADDR']) . "',
                        $language,
                        '" . intval($ticket['owner']) . "',
                        '" . $this->helpbase->database->escape($ticket['attachments']) . "',
                        '',
                        '" . $this->helpbase->database->escape($ticket['history']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom1']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom2']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom3']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom4']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom5']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom6']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom7']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom8']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom9']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom10']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom11']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom12']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom13']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom14']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom15']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom16']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom17']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom18']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom19']) . "',
                        '" . $this->helpbase->database->escape($ticket['custom20']) . "',
                        '" . $this->helpbase->database->escape($ticket['company']) . "',
                        '" . $this->helpbase->database->escape($ticket['homephone']) . "',
                        '" . $this->helpbase->database->escape($ticket['mobilephone']) . "',
                        '" . $this->helpbase->database->escape($ticket['workphone']) . "',
                        '" . $this->helpbase->database->escape($ticket['devicetype']) . "',
                        '" . $this->helpbase->database->escape($ticket['devicebrand']) . "',
                        '" . $this->helpbase->database->escape($ticket['deviceid']) . "'                		
                )
                ");

            // Generate the array with ticket info that can be used in emails
            $info = array(
                'email'         => $ticket['email'],
                'devicetype'    => $ticket['devicetype'],
                'devicebrand'   => $ticket['devicebrand'],
                'deviceid'      => $ticket['deviceid'],
                'homephone'     => $ticket['homephone'],
                'mobilephone'   => $ticket['mobilephone'],
                'workphone'     => $ticket['workphone'],
                'category'      => $ticket['category'],
                'priority'      => $ticket['priority'],
                'owner'         => $ticket['owner'],
                'trackid'       => $ticket['trackid'],
                'status'        => 0,
                'name'          => $ticket['name'],
                'company'       => $ticket['company'],
                'lastreplier'   => $ticket['name'],
                'subject'       => $ticket['subject'],
                'message'       => $ticket['message'],
                'attachments'   => $ticket['attachments'],
                'dt'            => $this->helpbase->common->_date(),
                'lastchange'    => $this->helpbase->common->_date(),
            );

            // Add custom fields to the array
            foreach ($hesk_settings['custom_fields'] as $k => $v) {
                $info[$k] = $v['use'] ? $ticket[$k] : '';
            }

            return $this->helpbase->common->ticketToPlain($info, 1);
        }

        public function cleanFileName($filename) {
            $special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", chr(0)); /* ' */
            $filename = str_replace($special_chars, '', $filename);
            $filename = preg_replace('/[\s-]+/', '-', $filename);
            $filename = trim($filename, '.-_');
            $filename = $this->kb_remove_accents($filename);
            return $filename;
        }

        public function verifyCategory($any_type = 0) {
            global $hesk_settings, $hesk_db_link, $tmpvar;

            // Verify just by public or any category type?
            $type = $any_type ? " 1 " : " `type`='0' ";

            // Does the category exist?
            $res = $this->helpbase->database->query("SELECT `name`, `autoassign` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE `id`='" . $tmpvar['category'] . "' AND {$type} LIMIT 1");
            if ($this->helpbase->database->numRows($res) == 1) {
                $hesk_settings['category_data'][$tmpvar['category']] = $this->helpbase->database->fetchAssoc($res);
                return true;
            }

            // OK, something wrong with the category. Get a list of categories to check few things
            $res = $this->helpbase->database->query("SELECT `id`, `name`, `autoassign` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` WHERE {$type} ORDER BY `id` ASC");
            $num = $this->helpbase->database->numRows($res);

            // If more than 1 choice is available, let the user choose
            if ($num > 1) {
                $hesk_error_buffer['category'] = _('Please select the appropriate category');
                return false;
            }

            // Exactly one category is available, use it
            elseif ($num == 1) {
                $tmp = $this->helpbase->database->fetchAssoc($res);
                $tmpvar['category'] = $tmp['id'];
                $hesk_settings['category_data'][$tmpvar['category']] = $tmp;
                return true;
            }

            // No category is available, use the first one we find (should be ID 1)
            else {
                $res = $this->helpbase->database->query("SELECT `id`, `name`, `autoassign` FROM `" . $this->helpbase->database->escape($hesk_settings['db_pfix']) . "categories` ORDER BY `id` ASC LIMIT 1");

                if ($this->helpbase->database->numRows($res) == 1) {
                    $tmp = $this->helpbase->database->fetchAssoc($res);
                    $tmpvar['category'] = $tmp['id'];
                    $hesk_settings['category_data'][$tmpvar['category']] = $tmp;
                } else {
                    // What the ...? No categories exist??? You know what, just error out...
                    $this->helpbase->common->_error(_('Internal script error') . ': ' . _('Category not found'));
                }
            }
        }

        // The following code has been borrowed from Wordpress
        // Credits: http://wordpress.org
        private function kb_remove_accents($string) {
            if (!preg_match('/[\x80-\xff]/', $string))
                return $string;

            if ($this->kb_seems_utf8($string)) {
                $chars = array(
                    // Decompositions for Latin-1 Supplement
                    chr(194) . chr(170) => 'a', chr(194) . chr(186) => 'o',
                    chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
                    chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
                    chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
                    chr(195) . chr(134) => 'AE', chr(195) . chr(135) => 'C',
                    chr(195) . chr(136) => 'E', chr(195) . chr(137) => 'E',
                    chr(195) . chr(138) => 'E', chr(195) . chr(139) => 'E',
                    chr(195) . chr(140) => 'I', chr(195) . chr(141) => 'I',
                    chr(195) . chr(142) => 'I', chr(195) . chr(143) => 'I',
                    chr(195) . chr(144) => 'D', chr(195) . chr(145) => 'N',
                    chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
                    chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
                    chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
                    chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
                    chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
                    chr(195) . chr(158) => 'TH', chr(195) . chr(159) => 's',
                    chr(195) . chr(160) => 'a', chr(195) . chr(161) => 'a',
                    chr(195) . chr(162) => 'a', chr(195) . chr(163) => 'a',
                    chr(195) . chr(164) => 'a', chr(195) . chr(165) => 'a',
                    chr(195) . chr(166) => 'ae', chr(195) . chr(167) => 'c',
                    chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
                    chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
                    chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
                    chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
                    chr(195) . chr(176) => 'd', chr(195) . chr(177) => 'n',
                    chr(195) . chr(178) => 'o', chr(195) . chr(179) => 'o',
                    chr(195) . chr(180) => 'o', chr(195) . chr(181) => 'o',
                    chr(195) . chr(182) => 'o', chr(195) . chr(184) => 'o',
                    chr(195) . chr(185) => 'u', chr(195) . chr(186) => 'u',
                    chr(195) . chr(187) => 'u', chr(195) . chr(188) => 'u',
                    chr(195) . chr(189) => 'y', chr(195) . chr(190) => 'th',
                    chr(195) . chr(191) => 'y', chr(195) . chr(152) => 'O',
                    // Decompositions for Latin Extended-A
                    chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a',
                    chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a',
                    chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a',
                    chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c',
                    chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c',
                    chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c',
                    chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c',
                    chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd',
                    chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd',
                    chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e',
                    chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e',
                    chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e',
                    chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e',
                    chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e',
                    chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g',
                    chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g',
                    chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g',
                    chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g',
                    chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h',
                    chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h',
                    chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i',
                    chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i',
                    chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i',
                    chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i',
                    chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i',
                    chr(196) . chr(178) => 'IJ', chr(196) . chr(179) => 'ij',
                    chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j',
                    chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k',
                    chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L',
                    chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L',
                    chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L',
                    chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L',
                    chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L',
                    chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N',
                    chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N',
                    chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N',
                    chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N',
                    chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N',
                    chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o',
                    chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o',
                    chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o',
                    chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe',
                    chr(197) . chr(148) => 'R', chr(197) . chr(149) => 'r',
                    chr(197) . chr(150) => 'R', chr(197) . chr(151) => 'r',
                    chr(197) . chr(152) => 'R', chr(197) . chr(153) => 'r',
                    chr(197) . chr(154) => 'S', chr(197) . chr(155) => 's',
                    chr(197) . chr(156) => 'S', chr(197) . chr(157) => 's',
                    chr(197) . chr(158) => 'S', chr(197) . chr(159) => 's',
                    chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
                    chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't',
                    chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't',
                    chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't',
                    chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u',
                    chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u',
                    chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u',
                    chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u',
                    chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u',
                    chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u',
                    chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w',
                    chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y',
                    chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z',
                    chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z',
                    chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z',
                    chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's',
                    // Decompositions for Latin Extended-B
                    chr(200) . chr(152) => 'S', chr(200) . chr(153) => 's',
                    chr(200) . chr(154) => 'T', chr(200) . chr(155) => 't',
                    // Euro Sign
                    chr(226) . chr(130) . chr(172) => 'E',
                    // GBP (Pound) Sign
                    chr(194) . chr(163) => '',
                    // Vowels with diacritic (Vietnamese)
                    // unmarked
                    chr(198) . chr(160) => 'O', chr(198) . chr(161) => 'o',
                    chr(198) . chr(175) => 'U', chr(198) . chr(176) => 'u',
                    // grave accent
                    chr(225) . chr(186) . chr(166) => 'A', chr(225) . chr(186) . chr(167) => 'a',
                    chr(225) . chr(186) . chr(176) => 'A', chr(225) . chr(186) . chr(177) => 'a',
                    chr(225) . chr(187) . chr(128) => 'E', chr(225) . chr(187) . chr(129) => 'e',
                    chr(225) . chr(187) . chr(146) => 'O', chr(225) . chr(187) . chr(147) => 'o',
                    chr(225) . chr(187) . chr(156) => 'O', chr(225) . chr(187) . chr(157) => 'o',
                    chr(225) . chr(187) . chr(170) => 'U', chr(225) . chr(187) . chr(171) => 'u',
                    chr(225) . chr(187) . chr(178) => 'Y', chr(225) . chr(187) . chr(179) => 'y',
                    // hook
                    chr(225) . chr(186) . chr(162) => 'A', chr(225) . chr(186) . chr(163) => 'a',
                    chr(225) . chr(186) . chr(168) => 'A', chr(225) . chr(186) . chr(169) => 'a',
                    chr(225) . chr(186) . chr(178) => 'A', chr(225) . chr(186) . chr(179) => 'a',
                    chr(225) . chr(186) . chr(186) => 'E', chr(225) . chr(186) . chr(187) => 'e',
                    chr(225) . chr(187) . chr(130) => 'E', chr(225) . chr(187) . chr(131) => 'e',
                    chr(225) . chr(187) . chr(136) => 'I', chr(225) . chr(187) . chr(137) => 'i',
                    chr(225) . chr(187) . chr(142) => 'O', chr(225) . chr(187) . chr(143) => 'o',
                    chr(225) . chr(187) . chr(148) => 'O', chr(225) . chr(187) . chr(149) => 'o',
                    chr(225) . chr(187) . chr(158) => 'O', chr(225) . chr(187) . chr(159) => 'o',
                    chr(225) . chr(187) . chr(166) => 'U', chr(225) . chr(187) . chr(167) => 'u',
                    chr(225) . chr(187) . chr(172) => 'U', chr(225) . chr(187) . chr(173) => 'u',
                    chr(225) . chr(187) . chr(182) => 'Y', chr(225) . chr(187) . chr(183) => 'y',
                    // tilde
                    chr(225) . chr(186) . chr(170) => 'A', chr(225) . chr(186) . chr(171) => 'a',
                    chr(225) . chr(186) . chr(180) => 'A', chr(225) . chr(186) . chr(181) => 'a',
                    chr(225) . chr(186) . chr(188) => 'E', chr(225) . chr(186) . chr(189) => 'e',
                    chr(225) . chr(187) . chr(132) => 'E', chr(225) . chr(187) . chr(133) => 'e',
                    chr(225) . chr(187) . chr(150) => 'O', chr(225) . chr(187) . chr(151) => 'o',
                    chr(225) . chr(187) . chr(160) => 'O', chr(225) . chr(187) . chr(161) => 'o',
                    chr(225) . chr(187) . chr(174) => 'U', chr(225) . chr(187) . chr(175) => 'u',
                    chr(225) . chr(187) . chr(184) => 'Y', chr(225) . chr(187) . chr(185) => 'y',
                    // acute accent
                    chr(225) . chr(186) . chr(164) => 'A', chr(225) . chr(186) . chr(165) => 'a',
                    chr(225) . chr(186) . chr(174) => 'A', chr(225) . chr(186) . chr(175) => 'a',
                    chr(225) . chr(186) . chr(190) => 'E', chr(225) . chr(186) . chr(191) => 'e',
                    chr(225) . chr(187) . chr(144) => 'O', chr(225) . chr(187) . chr(145) => 'o',
                    chr(225) . chr(187) . chr(154) => 'O', chr(225) . chr(187) . chr(155) => 'o',
                    chr(225) . chr(187) . chr(168) => 'U', chr(225) . chr(187) . chr(169) => 'u',
                    // dot below
                    chr(225) . chr(186) . chr(160) => 'A', chr(225) . chr(186) . chr(161) => 'a',
                    chr(225) . chr(186) . chr(172) => 'A', chr(225) . chr(186) . chr(173) => 'a',
                    chr(225) . chr(186) . chr(182) => 'A', chr(225) . chr(186) . chr(183) => 'a',
                    chr(225) . chr(186) . chr(184) => 'E', chr(225) . chr(186) . chr(185) => 'e',
                    chr(225) . chr(187) . chr(134) => 'E', chr(225) . chr(187) . chr(135) => 'e',
                    chr(225) . chr(187) . chr(138) => 'I', chr(225) . chr(187) . chr(139) => 'i',
                    chr(225) . chr(187) . chr(140) => 'O', chr(225) . chr(187) . chr(141) => 'o',
                    chr(225) . chr(187) . chr(152) => 'O', chr(225) . chr(187) . chr(153) => 'o',
                    chr(225) . chr(187) . chr(162) => 'O', chr(225) . chr(187) . chr(163) => 'o',
                    chr(225) . chr(187) . chr(164) => 'U', chr(225) . chr(187) . chr(165) => 'u',
                    chr(225) . chr(187) . chr(176) => 'U', chr(225) . chr(187) . chr(177) => 'u',
                    chr(225) . chr(187) . chr(180) => 'Y', chr(225) . chr(187) . chr(181) => 'y',
                );

                $string = strtr($string, $chars);
            } else {
                // Assume ISO-8859-1 if not UTF-8
                $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                        . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                        . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                        . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                        . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                        . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                        . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                        . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                        . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                        . chr(252) . chr(253) . chr(255);

                $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

                $string = strtr($string, $chars['in'], $chars['out']);
                $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
                $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
                $string = str_replace($double_chars['in'], $double_chars['out'], $string);
            }

            return $string;
        }

        private function kb_seems_utf8($str) {
            $length = strlen($str);
            for ($i = 0; $i < $length; $i++) {
                $c = ord($str[$i]);
                if ($c < 0x80)
                    $n = 0;# 0bbbbbbb
                elseif (($c & 0xE0) == 0xC0)
                    $n = 1;# 110bbbbb
                elseif (($c & 0xF0) == 0xE0)
                    $n = 2;# 1110bbbb
                elseif (($c & 0xF8) == 0xF0)
                    $n = 3;# 11110bbb
                elseif (($c & 0xFC) == 0xF8)
                    $n = 4;# 111110bb
                elseif (($c & 0xFE) == 0xFC)
                    $n = 5;# 1111110b
                else
                    return false;# Does not match any model
                for ($j = 0; $j < $n; $j++) { # n bytes matching 10bbbbbb follow ?
                    if (( ++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                        return false;
                }
            }
            return true;
        }

        private function kb_utf8_uri_encode($utf8_string, $length = 0) {
            $unicode = '';
            $values = array();
            $num_octets = 1;
            $unicode_length = 0;

            $string_length = strlen($utf8_string);
            for ($i = 0; $i < $string_length; $i++) {

                $value = ord($utf8_string[$i]);

                if ($value < 128) {
                    if ($length && ( $unicode_length >= $length ))
                        break;
                    $unicode .= chr($value);
                    $unicode_length++;
                } else {
                    if (count($values) == 0)
                        $num_octets = ( $value < 224 ) ? 2 : 3;

                    $values[] = $value;

                    if ($length && ( $unicode_length + ($num_octets * 3) ) > $length)
                        break;
                    if (count($values) == $num_octets) {
                        if ($num_octets == 3) {
                            $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
                            $unicode_length += 9;
                        } else {
                            $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
                            $unicode_length += 6;
                        }

                        $values = array();
                        $num_octets = 1;
                    }
                }
            }

            return $unicode;
        }
    }
}