<?php

/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Settings Save
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseSettingsSave')) {
    class HelpbaseSettingsSave {
        private $helpbase   = null;
        private $setup      = null;

        public function __construct(){
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);
            $this->helpbase = $helpbase;

            require($helpbase->includes . 'setup_functions.inc.php');
            $setup = new HelpbaseSetup($helpbase);
            $this->setup = $setup;

            $helpbase->load_email_functions();
            
            $helpbase->admin->isLoggedIn();

            // Check permissions for this feature
            $helpbase->admin->checkPermission('can_man_settings');

            // A security check
            $helpbase->common->token_check('POST');

            // Demo mode
            if (true == $helpbase->demo_mode) {
                $helpbase->common->process_messages(_('Saving changes has been disabled in DEMO mode'), 'admin_settings.php');
            }

            $this->set_variables();
        }

        private function set_variables(){
            global $set;
            
            $set = array();

            /* * * GENERAL ** */

            /* --> General settings */
            $set['site_title']      = $this->helpbase->common->_input($this->helpbase->common->_post('s_site_title'), _('Please enter your website title'));
            $set['site_title']      = str_replace('\\&quot;', '&quot;', $set['site_title']);
            $set['site_url']        = $this->helpbase->common->_input($this->helpbase->common->_post('s_site_url'), _('Please enter your website URL. Make sure it is a valid URL (start with http:// or https://)'));
            $set['webmaster_mail']  = $this->helpbase->common->validateEmail($this->helpbase->common->_post('s_webmaster_mail'), _('Please enter a valid webmaster email'));
            $set['noreply_mail']    = $this->helpbase->common->validateEmail($this->helpbase->common->_post('s_noreply_mail'), _('Please enter a valid noreply email'));
            $set['noreply_name']    = $this->helpbase->common->_input($this->helpbase->common->_post('s_noreply_name'));
            $set['noreply_name']    = str_replace(array('\\&quot;', '&lt;', '&gt;'), '', $set['noreply_name']);
            $set['noreply_name']    = trim(preg_replace('/\s{2,}/', ' ', $set['noreply_name']));

            /* --> Language settings */
            $set['can_sel_lang']    = empty($_POST['s_can_sel_lang']) ? 0 : 1;
            $set['languages']       = $this->helpbase->common->getLanguagesArray();
            $lang                   = explode('|', $this->helpbase->common->_input($this->helpbase->common->_post('s_language')));

            if (isset($lang[1]) && in_array($lang[1], $this->helpbase->common->getLanguagesArray(1))) {
                $set['language'] = $lang[1];
            } else {
                $this->helpbase->common->_error(_('Please select HelpBase language'));
            }

            /* --> Database settings */
            $this->helpbase->database->close();

            if ($this->setup->testMySQL()) {
                // Database connection OK
            } elseif ($mysql_log) {
                $this->helpbase->common->_error($mysql_error . '<br /><br /><b>' . _('MySQL said') . ':</b> ' . $mysql_log);
            } else {
                $this->helpbase->common->_error($mysql_error);
            }

            /* * * HELP DESK ** */

            /* --> Helpdesk settings */
            $set['hesk_title']  = $this->helpbase->common->_input($this->helpbase->common->_post('s_hesk_title'), _('Please enter the title of your support desk'));
            $set['hesk_title']  = str_replace('\\&quot;', '&quot;', $set['hesk_title']);
            $set['hesk_url']    = $this->helpbase->common->_input($this->helpbase->common->_post('s_hesk_url'), _('Please enter your Hesk folder url. Make sure it is a valid URL (start with http:// or https://)'));

            // ---> check admin folder
            $set['admin_dir'] = isset($_POST['s_admin_dir']) && !is_array($_POST['s_admin_dir']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['s_admin_dir']) : 'admin';

            // ---> check attachments folder
            $set['attach_dir'] = isset($_POST['s_attach_dir']) && !is_array($_POST['s_attach_dir']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['s_attach_dir']) : 'attachments';

            $set['max_listings']    = $this->checkMinMax(intval($this->helpbase->common->_post('s_max_listings')), 1, 999, 10);
            $set['print_font_size'] = $this->checkMinMax(intval($this->helpbase->common->_post('s_print_font_size')), 1, 99, 12);
            $set['autoclose']       = $this->checkMinMax(intval($this->helpbase->common->_post('s_autoclose')), 0, 999, 7);
            $set['max_open']        = $this->checkMinMax(intval($this->helpbase->common->_post('s_max_open')), 0, 999, 0);
            $set['new_top']         = empty($_POST['s_new_top']) ? 0 : 1;
            $set['reply_top']       = empty($_POST['s_reply_top']) ? 0 : 1;

            /* --> Features */
            $set['autologin']       = empty($_POST['s_autologin']) ? 0 : 1;
            $set['autoassign']      = empty($_POST['s_autoassign']) ? 0 : 1;
            $set['custopen']        = empty($_POST['s_custopen']) ? 0 : 1;
            $set['rating']          = empty($_POST['s_rating']) ? 0 : 1;
            $set['cust_urgency']    = empty($_POST['s_cust_urgency']) ? 0 : 1;
            $set['sequential']      = empty($_POST['s_sequential']) ? 0 : 1;
            $set['list_users']      = empty($_POST['s_list_users']) ? 0 : 1;
            $set['debug_mode']      = empty($_POST['s_debug_mode']) ? 0 : 1;
            $set['short_link']      = empty($_POST['s_short_link']) ? 0 : 1;

            /* --> SPAM prevention */
            $set['secimg_use']      = empty($_POST['s_secimg_use']) ? 0 : ( $this->helpbase->common->_post('s_secimg_use') == 2 ? 2 : 1);
            $set['secimg_sum']      = '';

            for ($i = 1; $i <= 10; $i++) {
                $set['secimg_sum'] .= substr('AEUYBDGHJLMNPQRSTVWXZ123456789', rand(0, 29), 1);
            }

            $set['recaptcha_use']           = empty($_POST['s_recaptcha_use']) ? 0 : 1;
            $set['recaptcha_ssl']           = empty($_POST['s_recaptcha_ssl']) ? 0 : 1;
            $set['recaptcha_public_key']    = $this->helpbase->common->_input($this->helpbase->common->_post('s_recaptcha_public_key'));
            $set['recaptcha_private_key']   = $this->helpbase->common->_input($this->helpbase->common->_post('s_recaptcha_private_key'));
            $set['question_use']            = empty($_POST['s_question_use']) ? 0 : 1;
            $set['question_ask']            = $this->helpbase->admin->getHTML($this->helpbase->common->_post('s_question_ask')) or $this->helpbase->common->_error(_('Enter an anti-SPAM question'));
            $set['question_ans']            = $this->helpbase->common->_input($this->helpbase->common->_post('s_question_ans'), _('Enter answer to the anti-SPAM question'));

            /* --> Security */
            $set['attempt_limit'] = $this->checkMinMax(intval($this->helpbase->common->_post('s_attempt_limit')), 0, 999, 5);
            if ($set['attempt_limit'] > 0) {
                $set['attempt_limit'] ++;
            }
            $set['attempt_banmin']      = $this->checkMinMax(intval($this->helpbase->common->_post('s_attempt_banmin')), 5, 99999, 60);
            $set['email_view_ticket']   = empty($_POST['s_email_view_ticket']) ? 0 : 1;

            /* --> Attachments */
            $set['attachments']['use']  = empty($_POST['s_attach_use']) ? 0 : 1;

            if ($set['attachments']['use']) {
                $set['attachments']['max_number'] = intval($this->helpbase->common->_post('s_max_number', 2));

                $size = floatval($this->helpbase->common->_post('s_max_size', '1.0'));
                $unit = $this->helpbase->common->htmlspecialchars($this->helpbase->common->_post('s_max_unit', 'MB'));

                $set['attachments']['max_size'] = $this->formatUnits($size . ' ' . $unit);

                $set['attachments']['allowed_types'] = isset($_POST['s_allowed_types']) && !is_array($_POST['s_allowed_types']) && strlen($_POST['s_allowed_types']) ? explode(',', strtolower(preg_replace('/[^a-zA-Z0-9,]/', '', $_POST['s_allowed_types']))) : array();
                $set['attachments']['allowed_types'] = array_diff($set['attachments']['allowed_types'], array('php', 'php4', 'php3', 'php5', 'phps', 'phtml', 'shtml', 'shtm', 'cgi', 'pl'));

                if (count($set['attachments']['allowed_types'])) {
                    $keep_these = array();

                    foreach ($set['attachments']['allowed_types'] as $ext) {
                        if (strlen($ext) > 1) {
                            $keep_these[] = '.' . $ext;
                        }
                    }

                    $set['attachments']['allowed_types'] = $keep_these;
                } else {
                    $set['attachments']['allowed_types'] = array('.gif', '.jpg', '.png', '.zip', '.rar', '.csv', '.doc', '.docx', '.xls', '.xlsx', '.txt', '.pdf');
                }
            } else {
                $set['attachments']['max_number'] = 2;
                $set['attachments']['max_size'] = 1048576;
                $set['attachments']['allowed_types'] = array('.gif', '.jpg', '.png', '.zip', '.rar', '.csv', '.doc', '.docx', '.xls', '.xlsx', '.txt', '.pdf');
            }

            /* * * KNOWLEDGEBASE ** */

            /* --> Knowledgebase settings */
            $set['kb_enable']           = empty($_POST['s_kb_enable']) ? 0 : 1;
            $set['kb_wysiwyg']          = empty($_POST['s_kb_wysiwyg']) ? 0 : 1;
            $set['kb_search']           = empty($_POST['s_kb_search']) ? 0 : ( $this->helpbase->common->_post('s_kb_search') == 2 ? 2 : 1);
            $set['kb_recommendanswers'] = empty($_POST['s_kb_recommendanswers']) ? 0 : 1;
            $set['kb_views']            = empty($_POST['s_kb_views']) ? 0 : 1;
            $set['kb_date']             = empty($_POST['s_kb_date']) ? 0 : 1;
            $set['kb_rating']           = empty($_POST['s_kb_rating']) ? 0 : 1;
            $set['kb_search_limit']     = $this->checkMinMax(intval($this->helpbase->common->_post('s_kb_search_limit')), 1, 99, 10);
            $set['kb_substrart']        = $this->checkMinMax(intval($this->helpbase->common->_post('s_kb_substrart')), 20, 9999, 200);
            $set['kb_cols']             = $this->checkMinMax(intval($this->helpbase->common->_post('s_kb_cols')), 1, 5, 2);
            $set['kb_numshow']          = intval($this->helpbase->common->_post('s_kb_numshow')); // Popular articles on subcat listing
            $set['kb_popart']           = intval($this->helpbase->common->_post('s_kb_popart')); // Popular articles on main category page
            $set['kb_latest']           = intval($this->helpbase->common->_post('s_kb_latest')); // Popular articles on main category page
            $set['kb_index_popart']     = intval($this->helpbase->common->_post('s_kb_index_popart'));
            $set['kb_index_latest']     = intval($this->helpbase->common->_post('s_kb_index_latest'));


            /* * * EMAIL ** */

            /* --> Email sending */
            $smtp_OK = true;
            $set['smtp'] = empty($_POST['s_smtp']) ? 0 : 1;
            if ($set['smtp']) {
                // Test SMTP connection
                $smtp_OK = $this->setup->testSMTP();

                // If SMTP not working, disable it
                if (!$smtp_OK) {
                    $set['smtp'] = 0;
                }
            } else {
                $set['smtp_host_name']  = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_smtp_host_name', 'localhost'));
                $set['smtp_host_port']  = intval($this->helpbase->common->_post('tmp_smtp_host_port', 25));
                $set['smtp_timeout']    = intval($this->helpbase->common->_post('tmp_smtp_timeout', 10));
                $set['smtp_ssl']        = empty($_POST['tmp_smtp_ssl']) ? 0 : 1;
                $set['smtp_tls']        = empty($_POST['tmp_smtp_tls']) ? 0 : 1;
                $set['smtp_user']       = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_smtp_user'));
                $set['smtp_password']   = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_smtp_password'));
            }

            /* --> Email piping */
            $set['email_piping'] = empty($_POST['s_email_piping']) ? 0 : 1;

            /* --> POP3 fetching */
            $pop3_OK = true;
            $set['pop3'] = empty($_POST['s_pop3']) ? 0 : 1;
            if ($set['pop3']) {
                // Test POP3 connection
                $pop3_OK = $this->setup->testPOP3();

                // If POP3 not working, disable it
                if (!$pop3_OK) {
                    $set['pop3'] = 0;
                }
            } else {
                $set['pop3_host_name']  = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_pop3_host_name', 'mail.domain.com'));
                $set['pop3_host_port']  = intval($this->helpbase->common->_post('tmp_pop3_host_port', 110));
                $set['pop3_tls']        = empty($_POST['tmp_pop3_tls']) ? 0 : 1;
                $set['pop3_keep']       = empty($_POST['tmp_pop3_keep']) ? 0 : 1;
                $set['pop3_user']       = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_pop3_user'));
                $set['pop3_password']   = $this->helpbase->common->_input($this->helpbase->common->_post('tmp_pop3_password'));
            }

            /* --> Email loops */
            $set['loop_hits'] = $this->checkMinMax(intval($this->helpbase->common->_post('s_loop_hits')), 0, 999, 5);
            $set['loop_time'] = $this->checkMinMax(intval($this->helpbase->common->_post('s_loop_time')), 1, 86400, 300);

            /* --> Detect email typos */
            $set['detect_typos'] = empty($_POST['s_detect_typos']) ? 0 : 1;
            $set['email_providers'] = array();

            if (!empty($_POST['s_email_providers']) && !is_array($_POST['s_email_providers'])) {
                $lines = preg_split('/$\R?^/m', $this->helpbase->common->_input($_POST['s_email_providers']));
                foreach ($lines as $domain) {
                    $domain = trim($domain);
                    $domain = str_replace('@', '', $domain);
                    $domainLen = strlen($domain);

                    /* Check domain part length */
                    if ($domainLen < 1 || $domainLen > 254) {
                        continue;
                    }

                    /* Check domain part characters */
                    if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                        continue;
                    }

                    /* Domain part mustn't have two consecutive dots */
                    if (strpos($domain, '..') !== false) {
                        continue;
                    }

                    $set['email_providers'][] = $domain;
                }
            }

            if (!$set['detect_typos'] || count($set['email_providers']) < 1) {
                $set['detect_typos'] = 0;
                $set['email_providers'] = array(
                    'gmail.com', 
                    'hotmail.com', 
                    'hotmail.co.uk', 
                    'yahoo.com', 
                    'yahoo.co.uk', 
                    'aol.com', 
                    'aol.co.uk', 
                    'msn.com', 
                    'live.com', 
                    'live.co.uk', 
                    'mail.com', 
                    'googlemail.com', 
                    'btinternet.com', 
                    'btopenworld.com'
                );
            }

            //$set['email_providers'] = count($set['email_providers']) ? "'" . implode("','", $set['email_providers']) . "'" : '';

            /* --> Other */
            $set['strip_quoted']    = empty($_POST['s_strip_quoted']) ? 0 : 1;
            $set['save_embedded']   = empty($_POST['s_save_embedded']) ? 0 : 1;
            $set['multi_eml']       = empty($_POST['s_multi_eml']) ? 0 : 1;
            $set['confirm_email']   = empty($_POST['s_confirm_email']) ? 0 : 1;
            $set['open_only']       = empty($_POST['s_open_only']) ? 0 : 1;


            /* * * MISC ** */

            /* --> Date & Time */
            $set['diff_hours']      = floatval($this->helpbase->common->_post('s_diff_hours', 0));
            $set['diff_minutes']    = floatval($this->helpbase->common->_post('s_diff_minutes', 0));
            $set['daylight']        = empty($_POST['s_daylight']) ? 0 : 1;
            $set['timeformat']      = $this->helpbase->common->_input($this->helpbase->common->_post('s_timeformat')) or $set['timeformat'] = 'Y-m-d H:i:s';

            /* --> Other */
            $set['alink']           = empty($_POST['s_alink']) ? 0 : 1;
            $set['submit_notice']   = empty($_POST['s_submit_notice']) ? 0 : 1;
            $set['online']          = empty($_POST['s_online']) ? 0 : 1;
            $set['online_min']      = $this->checkMinMax(intval($this->helpbase->common->_post('s_online_min')), 1, 999, 10);
            $set['check_updates']   = empty($_POST['s_check_updates']) ? 0 : 1;

            /* * * CUSTOM FIELDS ** */

            for ($i = 1; $i <= 20; $i++) {
                $this_field = 'custom' . $i;
                $set['custom_fields'][$this_field]['use'] = !empty($_POST['s_custom' . $i . '_use']) ? 1 : 0;

                if ($set['custom_fields'][$this_field]['use']) {
                    $set['custom_fields'][$this_field]['place']     = empty($_POST['s_custom' . $i . '_place']) ? 0 : 1;
                    $set['custom_fields'][$this_field]['type']      = $this->helpbase->common->htmlspecialchars($this->helpbase->common->_post('s_custom' . $i . '_type', 'text'));
                    $set['custom_fields'][$this_field]['req']       = !empty($_POST['s_custom' . $i . '_req']) ? 1 : 0;
                    $set['custom_fields'][$this_field]['name']      = $this->helpbase->common->_input($this->helpbase->common->_post('s_custom' . $i . '_name'), _('Please enter name(s) for selected optional field(s)'));
                    $set['custom_fields'][$this_field]['maxlen']    = intval($this->helpbase->common->_post('s_custom' . $i . '_maxlen', 255));
                    $set['custom_fields'][$this_field]['value']     = $this->helpbase->common->_input($this->helpbase->common->_post('s_custom' . $i . '_val'));

                    if (!in_array($set['custom_fields'][$this_field]['type'], array('text', 'textarea', 'select', 'radio', 'checkbox'))) {
                        $set['custom_fields'][$this_field]['type'] = 'text';
                    }
                } else {
                    $set['custom_fields'][$this_field] = array(
                        'use'       => 0,
                        'place'     => 0,
                        'type'      => 'text',
                        'req'       => 0,
                        'name'      => 'Custom field ' . $i,
                        'maxlen'    => 255,
                        'value'     => ''
                    );
                }
            }

            $set['hesk_version'] = $this->helpbase->version;
            $set['last_saved'] = time();

            $this->helpbase->save_settings($set);

            // Any settings problems?
            $tmp = array();

            if (!$smtp_OK) {
                $tmp[] = '<span style="color:red; font-weight:bold">' . _('SMTP error') . ':</span> ' . $smtp_error . '<br /><br /><a href="Javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay(\'smtplog\')">' . _('SMTP connection log') . '</a><div id="smtplog" style="display:none">&nbsp;<br /><textarea name="log" rows="10" cols="60">' . $smtp_log . '</textarea></div>';
            }

            if (!$pop3_OK) {
                $tmp[] = '<span style="color:red; font-weight:bold">' . _('POP3 error') . ':</span> ' . $pop3_error . '<br /><br /><a href="Javascript:void(0)" onclick="Javascript:hb_toggleLayerDisplay(\'pop3log\')">' . _('POP3 connection log') . '</a><div id="pop3log" style="display:none">&nbsp;<br /><textarea name="log" rows="10" cols="60">' . $pop3_log . '</textarea></div>';
            }

            // Show the settings page and display any notices or success
            if (count($tmp)) {
                $errors = implode('<br /><br />', $tmp);
                $this->helpbase->common->process_messages(_('Settings were saved, but some functions were disabled because of failed tests.') . '<br /><br />' . $errors, 'admin_settings.php', 'NOTICE');
            } else {
                $this->helpbase->common->process_messages(_('Your settings have been successfully saved'), 'admin_settings.php', 'SUCCESS');
            }

            unset($this->setup);
            unset($this->helpbase);

            exit();
        }

        private function checkMinMax($myint, $min, $max, $defval) {
            if ($myint > $max || $myint < $min) {
                return $defval;
            }
            return $myint;
        }

        private function getLanguagesArray($returnArray = 0) {
            global $hesk_settings, $hesklang;

            /* Get a list of valid emails */
            $hesk_settings['smtp'] = 0;
            $valid_emails = array_keys($this->helpbase->email->validEmails());

            $dir = $this->helpbase->dir . 'locale/';
            $path = opendir($dir);
            $code = '';
            $langArray = array();

            /* Test all folders inside the language folder */
            while (false !== ($subdir = readdir($path))) {
                if ($subdir == "." || $subdir == "..") {
                    continue;
                }

                if (filetype($dir . $subdir) == 'dir') {
                    $add        = 1;
                    $langName   = $dir . $subdir . '/lang.name';
                    $email      = $dir . $subdir . '/emails';
                    $texts      = $dir . $subdir . '/LC_MESSAGES';

                    // Check lang.name
                    if (file_exists($langName)) {
                        $tmp = file_get_contents($langName);
                        
                        // Some servers add slashes to file_get_contents output
                        if (strpos($tmp, '[\\\'LANGUAGE\\\']') !== false) {
                            $tmp = stripslashes($tmp);
                        }  
                        
                        if (!preg_match('/\[\'LANGUAGE\'\]\=\'(.*)\'/', $tmp, $l)) {
                            $add = 0;
                            $l = $subdir;
                        }
                    } else {
                        $add = 0;
                    }
                    
                    // Check for LC_MESSAGES folder
                    if (file_exists($texts) && filetype($texts) == 'dir') {
                        
                        /* Check the text.po */
                        $langu = $texts . '/text.po';
                        if (file_exists($langu)) {
                            $tmp = file_get_contents($langu);

                            if (strpos($tmp, 'msgid "Language"') === false) {
                                $add = 0;
                            }
                        } else {
                            $add = 0;
                        }
                    } else {
                        $add = 0;
                    }

                    /* Check emails folder */
                    if (file_exists($email) && filetype($email) == 'dir') {
                        foreach ($valid_emails as $eml) {
                            if (!file_exists($email . '/' . $eml . '.txt')) {
                                $add = 0;
                            }
                        }
                    } else {
                        $add = 0;
                    }

                    /* Add an option for the <select> if needed */
                    if ($add) {
                        //$code .= "'" . addslashes($l[1]) . "' => '" . $subdir . "',\n";
                        $kpArray[$l[1]] = $subdir; 
                        $langArray[]    = $l[1];
                    }
                }
            }

            closedir($path);

            if ($returnArray) {
                return $langArray;
            } else {
                return $kpArray;
            }
        }

        private function formatUnits($size) {
            $units = array(
                'GB' => 1073741824,
                'MB' => 1048576,
                'kB' => 1024,
                'B' => 1
            );

            list($size, $suffix) = explode(' ', $size);

            if (isset($units[$suffix])) {
                return round($size * $units[$suffix]);
            }

            return false;
        }
    }
    
    new HelpbaseSettingsSave;
}

?>
