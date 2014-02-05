<?php
/**
 * PHP Helpdesk and knowledge base.
 *
 * @package     HelpBase
 * @subpackage  Admin
 * @subpackage  Settings
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
define('EXECUTING', true);

if (!class_exists('HelpbaseAdminSettings')) {

    class HelpbaseAdminSettings {

        private $helpbase = null;
        private $help_folder = '';
        private $enable_save_settings = false;
        private $enable_use_attachments = false;
        private $server_time = '';

        public function __construct() {
            global $hesk_settings;

            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(true);

            $this->helpbase = $helpbase;

            // Make sure the install folder is deleted
            if (is_dir($helpbase->dir . 'install')) {
                unset($helpbase);
                die('Please delete the <b>install</b> folder from your server for security reasons then refresh this page!');
            }

            // Save the default language for the settings page before choosing user's preferred one
            $hesk_settings['language_default'] = $hesk_settings['language'];
            $hesk_settings['language'] = $hesk_settings['language_default'];

            $helpbase->admin->isLoggedIn();
            $helpbase->load_tabs = true;

            // Check permissions for this feature
            $helpbase->admin->checkPermission('can_man_settings');

            // Test languages function
            if (isset($_GET['test_languages'])) {
                $this->testLanguage(0);
            }

            //$this->help_folder = '' . $this->helpbase->url . 'language/' . $hesk_settings['languages'][$hesk_settings['language']]['folder'] . '/help_files/';

            $this->server_time = date('H:i', strtotime($helpbase->common->_date()));

            $this->render();
        }

        private function render() {
            global $hesk_settings;

            // Print header
            $this->helpbase->header->render();

            // Print main manage users page
            $this->helpbase->admin_nav->render();

            // Demo mode? Hide values of sensitive settings
            if (true == $this->helpbase->demo_mode) {
                $msg = _('(HIDDEN IN DEMO)');
                $hesk_settings['db_host'] = $msg;
                $hesk_settings['db_name'] = $msg;
                $hesk_settings['db_user'] = $msg;
                $hesk_settings['db_pass'] = $msg;
                $hesk_settings['db_pfix'] = $msg;
                $hesk_settings['smtp_host_name'] = $msg;
                $hesk_settings['smtp_user'] = $msg;
                $hesk_settings['smtp_password'] = $msg;
                $hesk_settings['pop3_host_name'] = $msg;
                $hesk_settings['pop3_user'] = $msg;
                $hesk_settings['pop3_password'] = $msg;
                $hesk_settings['recaptcha_public_key'] = $msg;
                $hesk_settings['recaptcha_private_key'] = $msg;
            }

            $s_on = _('ON');
            $s_off = _('OFF');
?>
                    </td>
                </tr>
                <tr>
                    <td>
<?php
            /* This will handle error, success and notice messages */
            $this->helpbase->common->handle_messages();
?>
                        <h3><?php echo _('Settings'); ?> [<a href="javascript:void(0)" onclick="javascript:alert('<?php echo $this->helpbase->admin->makeJsString(_('Use this tool to configure your help desk. For more information about all settings and options click the help sign or refer to the readme.html file.')) . '\n\n' . $this->helpbase->admin->makeJsString(_('All fields (except disabled ones) are required!')); ?>')">?</a>]</h3>
                        &nbsp;
<?php
            $custname = _('Please enter name(s) for selected optional field(s)');
            $custname = addslashes($custname);
?>

                        <script language="javascript" type="text/javascript">
                            <!--
                            function hb_checkFields() {
                                d = document.form1;

                                // GENERAL
                                if (d.s_site_title.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your website title')); ?>');
                                    return false;
                                }
                                if (d.s_site_url.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your website URL. Make sure it is a valid URL (start with http:// or https://)')); ?>');
                                    return false;
                                }
                                if (d.s_webmaster_mail.value == '' || d.s_webmaster_mail.value.indexOf(".") == -1 || d.s_webmaster_mail.value.indexOf("@") == -1) {
                                    alert('<?php echo addslashes(_('Please enter a valid webmaster email')); ?>');
                                    return false;
                                }
                                if (d.s_noreply_mail.value == '' || d.s_noreply_mail.value.indexOf(".") == -1 || d.s_noreply_mail.value.indexOf("@") == -1) {
                                    alert('<?php echo addslashes(_('Please enter a valid noreply email')); ?>');
                                    return false;
                                }

                                if (d.s_db_host.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your MySQL database host')); ?>');
                                    return false;
                                }
                                if (d.s_db_name.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your MySQL database name')); ?>');
                                    return false;
                                }
                                if (d.s_db_user.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your MySQL database username')); ?>');
                                    return false;
                                }
                                if (d.s_db_pass.value == '') {
                                    if (!confirm('<?php echo addslashes(_('Your MySQL password is empty, are you sure you want to login with root user? This is a significant security risk!')); ?>')) {
                                        return false;
                                    }
                                }

                                // HELPDESK
                                if (d.s_hesk_title.value == '') {
                                    alert('<?php echo addslashes(_('Please enter the title of your support desk')); ?>');
                                    return false;
                                }
                                if (d.s_hesk_url.value == '') {
                                    alert('<?php echo addslashes(_('Please enter your Hesk folder url. Make sure it is a valid URL (start with http:// or https://)')); ?>');
                                    return false;
                                }
                                if (d.s_max_listings.value == '') {
                                    alert('<?php echo addslashes(_('Please enter maximum listings displayed per page')); ?>');
                                    return false;
                                }
                                if (d.s_print_font_size.value == '') {
                                    alert('<?php echo addslashes(_('Please enter the print font size')); ?>');
                                    return false;
                                }

                                // KNOWLEDGEBASE

                                // MISC

                                // CUSTOM FIELDS
                                if (d.s_custom1_use.checked && d.s_custom1_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom2_use.checked && d.s_custom2_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom3_use.checked && d.s_custom3_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom4_use.checked && d.s_custom4_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom5_use.checked && d.s_custom5_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom6_use.checked && d.s_custom6_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom7_use.checked && d.s_custom7_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom8_use.checked && d.s_custom8_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom9_use.checked && d.s_custom9_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom10_use.checked && d.s_custom10_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom11_use.checked && d.s_custom11_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom12_use.checked && d.s_custom12_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom13_use.checked && d.s_custom13_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom14_use.checked && d.s_custom14_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom15_use.checked && d.s_custom15_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom16_use.checked && d.s_custom16_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom17_use.checked && d.s_custom17_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom18_use.checked && d.s_custom18_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom19_use.checked && d.s_custom19_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }
                                if (d.s_custom20_use.checked && d.s_custom20_name.value == '') {
                                    alert('<?php echo addslashes($custname); ?>');
                                    return false;
                                }

                                // DISABLE SUBMIT BUTTON
                                d.submitbutton.disabled = true;
                                d.submitbutton.value = '<?php echo addslashes(_('Saving settings, please wait...')); ?>';

                                return true;
                            }

                            function hb_customOptions(cID, fID, fTYPE, maxlenID, oldTYPE) {
                                var t = document.getElementById(fTYPE).value;
                                if (t == oldTYPE) {
                                    var d = document.getElementById(fID).value;
                                    var m = document.getElementById(maxlenID).value;
                                } else {
                                    var d = '';
                                    var m = 255;
                                }
                                var myURL = "options.php?i=" + cID + "&q=" + encodeURIComponent(d) + "&t=" + t + "&m=" + m;
                                window.open(myURL, "hb_window", "height=400,width=500,menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
                                return false;
                            }

                            function hb_toggleLayer(nr, setto) {
                                if (document.all) {
                                    document.all[nr].style.display = setto;
                                } else if (document.getElementById) {
                                    document.getElementById(nr).style.display = setto;
                                }
                            }

                            function hb_testLanguage() {
                                window.open('admin_settings.php?test_languages=1', "hb_window", "height=400,width=500,menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
                                return false;
                            }

                            var tabberOptions = {
                                'cookie': "tabber",
                                'onLoad': function(argsObj) {
                                    var t = argsObj.tabber;
                                    var i;
                                    if (t.id) {
                                        t.cookie = t.id + t.cookie;
                                    }

                                    i = parseInt(getCookie(t.cookie));
                                    if (isNaN(i)) {
                                        return;
                                    }
                                    t.tabShow(i);
                                },
                                'onClick': function(argsObj) {
                                    var c = argsObj.tabber.cookie;
                                    var i = argsObj.index;
                                    setCookie(c, i);
                                }
                            };

                            function setCookie(name, value, expires, path, domain, secure) {
                                document.cookie = name + "=" + escape(value) +
                                    ((expires) ? "; expires=" + expires.toGMTString() : "") +
                                    ((path) ? "; path=" + path : "") +
                                    ((domain) ? "; domain=" + domain : "") +
                                    ((secure) ? "; secure" : "");
                            }

                            function getCookie(name) {
                                var dc = document.cookie;
                                var prefix = name + "=";
                                var begin = dc.indexOf("; " + prefix);
                                if (begin == -1) {
                                    begin = dc.indexOf(prefix);
                                    if (begin != 0)
                                        return null;
                                } else {
                                    begin += 2;
                                }
                                var end = document.cookie.indexOf(";", begin);
                                if (end == -1) {
                                    end = dc.length;
                                }
                                return unescape(dc.substring(begin + prefix.length, end));
                            }

                            function deleteCookie(name, path, domain) {
                                if (getCookie(name)) {
                                    document.cookie = name + "=" +
                                        ((path) ? "; path=" + path : "") +
                                        ((domain) ? "; domain=" + domain : "") +
                                        "; expires=Thu, 01-Jan-70 00:00:01 GMT";
                                }
                            }

                            var server_time = "<?php echo $this->server_time; ?>";
                            var today = new Date();
                            today.setHours(server_time.substr(0, server_time.indexOf(":")));
                            today.setMinutes(server_time.substr(server_time.indexOf(":") + 1));

                            function startTime() {
                                var h = today.getHours();
                                var m = today.getMinutes();
                                var s = today.getSeconds();

                                h = checkTime(h);
                                m = checkTime(m);

                                document.getElementById('servertime').innerHTML = h + ":" + m;
                                s = s + 1;
                                today.setSeconds(s);
                                t = setTimeout('startTime()', 1000);
                            }

                            function checkTime(i) {
                                if (i < 10) {
                                    i = "0" + i;
                                }
                                return i;
                            }
                            //-->
                        </script>
                        <form method="post" action="admin_settings_save.php" name="form1" onsubmit="return hb_checkFields()">
                            <!-- Checkign status of files and folders -->
                            <span class="section">&raquo; <?php echo _('Checking status'); ?></span>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="7" height="7"><img src="<?php echo $this->helpbase->url;  ?>img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornerstop"></td>
                                    <td><img src="<?php echo $this->helpbase->url;  ?>img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                                <tr>
                                    <td class="roundcornersleft">&nbsp;</td>
                                    <td>
                                        <table border="0">
                                            <tr>
                                                <td width="200" valign="top"><?php echo _('HelpBase version'); ?>:</td>
                                                <td><b><?php echo $hesk_settings['hesk_version']; ?></b>
<?php
            if ($hesk_settings['check_updates']) {
                $latest = $this->checkVersion();

                if ($latest === true) {
                    echo '
                                                    - <span style="color:green">' . _('HelpBase is up to date') . '</span> ';
                } elseif ($latest != -1) {
                    // Is this a beta/dev version?
                    if (strpos($hesk_settings['hesk_version'], 'beta') || strpos($hesk_settings['hesk_version'], 'dev')) {
                        echo '
                                                    <span style="color:darkorange">' . _('(TEST VERSION)') . '</span> ';
?>
                                                    <a href="http://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo _('Check for updates'); ?></a>
<?php
                    } else {
                        echo '
                                                    - <span style="color:darkorange;font-weight:bold">' . _('Update available') . '</span> ';
?>
                                                    <a href="http://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo _('Update HelpBase'); ?></a>
<?php
                    }
                } else {
?>
                                                    - <a href="http://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo _('Check for updates'); ?></a>
<?php
                }
            } else {
?>
                                                    - <a href="http://www.hesk.com/update.php?v=<?php echo $hesk_settings['hesk_version']; ?>" target="_blank"><?php echo _('Check for updates'); ?></a>
<?php
            }
?>

                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="200" valign="top"><?php echo _('PHP version'); ?>:</td>
                                                <td><?php echo $this->helpbase->demo_mode ? _('(HIDDEN IN DEMO)') : PHP_VERSION . ' ' . (function_exists('mysqli_connect') ? '(MySQLi)' : '(MySQL)'); ?></td>
                                            </tr>
                                            <tr>
                                                <td width="200" valign="top"><?php echo _('MySQL version'); ?>:</td>
                                                <td><?php echo $this->helpbase->demo_mode ? _('(HIDDEN IN DEMO)') : $this->helpbase->database->result($this->helpbase->database->query('SELECT VERSION() AS version')); ?></td>
                                            </tr>
                                            <tr>
                                                <td width="200" valign="top">/hesk_settings.inc.php</td>
                                                <td>
<?php
            $not_writable = _('Not writable');
            if (is_writable($this->helpbase->dir . 'hesk_settings.inc.php')) {
                $this->enable_save_settings = true;
                echo '
                                                    <font class="success">' . _('Exists') . '</font>, <font class="success">' . _('Writable') . '</font>';
            } else {
                echo '
                                                    <font class="success">' . _('Exists') . '</font>, <font class="error">' . $not_writable . '</font><br />' . _('You will not be able to save your settings unless this file is writable by the script. Please refer to the readme file for further instructions!');
            }
?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="200">/<?php echo $hesk_settings['attach_dir']; ?></td>
                                                <td>
<?php
            if (is_dir($this->helpbase->dir . $hesk_settings['attach_dir'])) {
                echo '
                                                    <font class="success">' . _('Exists') . '</font>, ';
                if (is_writable($this->helpbase->dir . $hesk_settings['attach_dir'])) {
                    $this->enable_use_attachments = true;
                    echo '
                                                    <font class="success">' . _('Writable') . '</font>';
                } else {
                    echo '
                                                    <font class="error">' . $not_writable . '</font><br />' . _('You will not be able to use file attachments unless the attachments folder exists and is writable by the script.');
                }
            } else {
                echo '
                                                    <font class="error">' . _('Doesn\'t exist') . '</font>, <font class="error">' . $not_writable . '</font><br />' . _('You will not be able to use file attachments unless the attachments folder exists and is writable by the script.');
            }
?>
                                                </td>
                                            </tr>
                                        </table>
<?php
            // Check file attachment limits
            if ($hesk_settings['attachments']['use'] && !$this->helpbase->demo_mode) {
                // Check number of attachments per post
                if (version_compare(phpversion(), '5.2.12', '>=') && @ini_get('max_file_uploads') && @ini_get('max_file_uploads') < $hesk_settings['attachments']['max_number']) {
                    $this->helpbase->common->show_notice(_('Your attachments setting &quot;Number per post&quot; is larger than what your server allows!'));
                }

                // Check max attachment size
                $tmp = @ini_get('upload_max_filesize');
                if ($tmp) {
                    $last = strtoupper(substr($tmp, -1));

                    switch ($last) {
                        case 'K':
                            $tmp = $tmp * 1024;
                            break;
                        case 'M':
                            $tmp = $tmp * 1048576;
                            break;
                        case 'G':
                            $tmp = $tmp * 1073741824;
                            break;
                        default:
                            $tmp = $tmp;
                    }

                    if ($tmp < $hesk_settings['attachments']['max_size']) {
                        $this->helpbase->common->show_notice(_('Your maximum attachment file size is larger than what your server allows!'));
                    }
                }

                // Check max post size
                $tmp = @ini_get('post_max_size');
                if ($tmp) {
                    $last = strtoupper(substr($tmp, -1));

                    switch ($last) {
                        case 'K':
                            $tmp = $tmp * 1024;
                            break;
                        case 'M':
                            $tmp = $tmp * 1048576;
                            break;
                        case 'G':
                            $tmp = $tmp * 1073741824;
                            break;
                        default:
                            $tmp = $tmp;
                    }

                    if ($tmp < ( $hesk_settings['attachments']['max_size'] * $hesk_settings['attachments']['max_number'] + 524288 )) {
                        $this->helpbase->common->show_notice(_('Your server does not allow large enough posts, try reducing number of attachments or allowed file size!'));
                    }
                }
            }
?>

                                    </td>
                                    <td class="roundcornersright">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td><img src="<?php echo $this->helpbase->url;  ?>img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
                                    <td class="roundcornersbottom"></td>
                                    <td width="7" height="7"><img src="<?php echo $this->helpbase->url;  ?>img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
                                </tr>
                            </table>
                            <br />
                            <script language="Javascript" type="text/javascript" src="<?php echo $this->helpbase->url; ?>inc/tabs/tabber-minimized.js"></script>
                            <!-- TABS -->
                            <div class="tabber" id="tab1">
                                <!-- GENERAL -->
                                <div class="tabbertab">
                                    <h2><?php echo _('General'); ?></h2>
                                    &nbsp;
                                    <br />

                                    <!-- Website info -->
                                    <span class="section">&raquo; <?php echo _('General settings'); ?></span>
                                    <table border="0"  width="100%">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Website title'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#1', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_site_title" size="40" maxlength="255" value="<?php echo $hesk_settings['site_title']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Website URL'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#2', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_site_url" size="40" maxlength="255" value="<?php echo $hesk_settings['site_url']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Webmaster email'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#4', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_webmaster_mail" size="40" maxlength="255" value="<?php echo $hesk_settings['webmaster_mail']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('&quot;From:&quot; email'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#5', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_noreply_mail" size="40" maxlength="255" value="<?php echo $hesk_settings['noreply_mail']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('&quot;From:&quot; name'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#6', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_noreply_name" size="40" maxlength="255" value="<?php echo $hesk_settings['noreply_name']; ?>" /></td>
                                        </tr>
                                    </table>
                                    <br />
                                    <span class="section">&raquo; <?php echo _('Language'); ?></span>

                                    <!-- Language -->
                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right;vertical-align:top" width="200"><?php echo _('Default Language'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#9', '400', '500')"><b>?</b></a>]</td>
                                            <td>
                                                <select name="s_language">
                                                    <?php echo $this->testLanguage(1); ?>
                                                </select>
                                                &nbsp;
                                                <a href="Javascript:void(0)" onclick="Javascript:return hb_testLanguage()"><?php echo _('Test language folder'); ?></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right;vertical-align:top;" width="200"><?php echo _('Multiple languages'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#43', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['can_sel_lang'] ? 'checked="checked"' : '';
            $off = $hesk_settings['can_sel_lang'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_can_sel_lang" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_can_sel_lang" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                    </table>
                                    <br />

                                    <!-- Database -->
                                    <span class="section">&raquo; <?php echo _('Database'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Database host'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#32', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_db_host" id="m1" size="40" maxlength="255" value="<?php echo $hesk_settings['db_host']; ?>" autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Database name'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#33', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_db_name" id="m2" size="40" maxlength="255" value="<?php echo $hesk_settings['db_name']; ?>" autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Database username'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#34', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_db_user" id="m3" size="40" maxlength="255" value="<?php echo $hesk_settings['db_user']; ?>" autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Database password'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#35', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="password" name="s_db_pass" id="m4" size="40" maxlength="255" value="<?php echo $hesk_settings['db_pass']; ?>" autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Table prefix'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>general.html#36', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_db_pfix" id="m5" size="40" maxlength="255" value="<?php echo $hesk_settings['db_pfix']; ?>" autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200">&nbsp;</td>
                                            <td><input type="button" class="button blue small" onclick="hb_testMySQL()" value="<?php echo _('Test MySQL connection'); ?>" style="margin-top:4px" /></td>
                                        </tr>
                                    </table>

                                    <!-- START MYSQL TEST -->
                                    <div id="mysql_test" style="display:none">
                                    </div>
                                    <script language="Javascript" type="text/javascript">
                                        <!--
                                        function hb_testMySQL() {
                                            var element = document.getElementById('mysql_test');
                                            element.innerHTML = '<img src="<?php echo $this->helpbase->url; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes(_('Testing connection, this can take a while...')); ?></i>';
                                            element.style.display = 'block';

                                            var s_db_host = document.getElementById('m1').value;
                                            var s_db_name = document.getElementById('m2').value;
                                            var s_db_user = document.getElementById('m3').value;
                                            var s_db_pass = document.getElementById('m4').value;
                                            var s_db_pfix = document.getElementById('m5').value;

                                            var params = "test=mysql" +
                                                "&s_db_host=" + encodeURIComponent(s_db_host) +
                                                "&s_db_name=" + encodeURIComponent(s_db_name) +
                                                "&s_db_user=" + encodeURIComponent(s_db_user) +
                                                "&s_db_pass=" + encodeURIComponent(s_db_pass) +
                                                "&s_db_pfix=" + encodeURIComponent(s_db_pfix);

                                            xmlHttp = GetXmlHttpObject();
                                            if (xmlHttp == null) {
                                                return;
                                            }

                                            xmlHttp.open('POST', 'test_connection.php', true);
                                            xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                                            xmlHttp.setRequestHeader("Content-length", params.length);
                                            xmlHttp.setRequestHeader("Connection", "close");

                                            xmlHttp.onreadystatechange = function() {
                                                if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                                                    element.innerHTML = xmlHttp.responseText;
                                                }
                                            }

                                            xmlHttp.send(params);
                                        }
                                        //-->
                                    </script>
                                    <!-- END MYSQL TEST -->
                                </div>
                                <!-- GENERAL -->

                                <!-- HELP DESK -->
                                <div class="tabbertab">
                                    <h2><?php echo _('Help Desk'); ?></h2>
                                    &nbsp;
                                    <br />

                                    <!-- Help Desk -->
                                    <span class="section">&raquo; <?php echo _('Help desk settings'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Help desk title'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#6', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_hesk_title" size="40" maxlength="255" value="<?php echo $hesk_settings['hesk_title']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Help desk URL'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#7', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_hesk_url" size="40" maxlength="255" value="<?php echo $hesk_settings['hesk_url']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Admin folder'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#61', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_admin_dir" size="40" maxlength="255" value="<?php echo $hesk_settings['admin_dir']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Attachments folder'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#62', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_attach_dir" size="40" maxlength="255" value="<?php echo $hesk_settings['attach_dir']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Listings per page'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#10', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_max_listings" size="5" maxlength="30" value="<?php echo $hesk_settings['max_listings']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Print font size'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#11', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_print_font_size" size="5" maxlength="3" value="<?php echo $hesk_settings['print_font_size']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Autoclose tickets'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#15', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_autoclose" size="5" maxlength="3" value="<?php echo $hesk_settings['autoclose']; ?>" />
                                                <?php echo _('days after last staff reply'); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Max open tickets'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#58', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_max_open" size="5" maxlength="3" value="<?php echo $hesk_settings['max_open']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right;vertical-align:text-top" width="200"><?php echo _('Reply order'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['new_top'] ? 'checked="checked"' : '';
            $off = $hesk_settings['new_top'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_new_top" value="1" ' . $on . ' /> ' . _('Newest reply at top') . '</label><br />
                                                <label><input type="radio" name="s_new_top" value="0" ' . $off . ' /> ' . _('Newest reply at bottom') . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right;vertical-align:text-top" width="200"><?php echo _('Reply form'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#60', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['reply_top'] ? 'checked="checked"' : '';
            $off = $hesk_settings['reply_top'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_reply_top" value="1" ' . $on . ' /> ' . _('Show form at top') . '</label><br />
                                                <label><input type="radio" name="s_reply_top" value="0" ' . $off . ' /> ' . _('Show form at bottom') . '</label>';
?>
                                            </td>
                                        </tr>
                                    </table>
                                    <br />

                                    <!-- Features -->
                                    <span class="section">&raquo; <?php echo _('Features'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Allow automatic login'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#44', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['autologin'] ? 'checked="checked"' : '';
            $off = $hesk_settings['autologin'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_autologin" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_autologin" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Auto-assign tickets'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#51', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['autoassign'] ? 'checked="checked"' : '';
            $off = $hesk_settings['autoassign'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_autoassign" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_autoassign" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Reopen tickets'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#16', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['custopen'] ? 'checked="checked"' : '';
            $off = $hesk_settings['custopen'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_custopen" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_custopen" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Reply ratings'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#17', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['rating'] ? 'checked="checked"' : '';
            $off = $hesk_settings['rating'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_rating" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_rating" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Customer priority'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#45', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['cust_urgency'] ? 'checked="checked"' : '';
            $off = $hesk_settings['cust_urgency'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_cust_urgency" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_cust_urgency" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Sequential IDs'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#49', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['sequential'] ? 'checked="checked"' : '';
            $off = $hesk_settings['sequential'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_sequential" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_sequential" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('List usernames'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#14', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['list_users'] ? 'checked="checked"' : '';
            $off = $hesk_settings['list_users'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_list_users" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_list_users" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Debug mode'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#12', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['debug_mode'] ? 'checked="checked"' : '';
            $off = $hesk_settings['debug_mode'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_debug_mode" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_debug_mode" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Short links'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#63', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['short_link'] ? 'checked="checked"' : '';
            $off = $hesk_settings['short_link'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_short_link" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_short_link" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                    </table>
                                    <br />

                                    <!-- SPAM prevention -->
                                    <span class="section">&raquo; <?php echo _('SPAM Prevention'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200" valign="top"><?php echo _('Use anti-SPAM image'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#13', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $onc = $hesk_settings['secimg_use'] == 1 ? 'checked="checked"' : '';
            $ons = $hesk_settings['secimg_use'] == 2 ? 'checked="checked"' : '';
            $off = $hesk_settings['secimg_use'] ? '' : 'checked="checked"';
            $div = $hesk_settings['secimg_use'] ? 'block' : 'none';

            echo '
                                                <label><input type="radio" name="s_secimg_use" value="0" ' . $off . ' onclick="javascript:hb_toggleLayer(\'captcha\',\'none\')" /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_secimg_use" value="1" ' . $onc . ' onclick="javascript:hb_toggleLayer(\'captcha\',\'block\')" /> ' . _('ON - Customers') . '</label> |
                                                <label><input type="radio" name="s_secimg_use" value="2" ' . $ons . ' onclick="javascript:hb_toggleLayer(\'captcha\',\'block\')" /> ' . _('ON - All') . '</label>';
?>
                                                <div id="captcha" style="display: <?php echo $div; ?>;">
                                                    &nbsp;
                                                    <br />
                                                    <b><?php echo _('-&gt; Image Type'); ?>:</b>
                                                    <br />
<?php
            $on = '';
            $off = '';
            $div = 'block';

            if ($hesk_settings['recaptcha_use']) {
                $on = 'checked="checked"';
            } else {
                $off = 'checked="checked"';
                $div = 'none';
            }
?>
                                                    <label><input type="radio" name="s_recaptcha_use" value="0" onclick="javascript:hb_toggleLayer('recaptcha', 'none')" <?php echo $off; ?> /> <?php echo _('Simple image'); ?></label> <br />
                                                    <label><input type="radio" name="s_recaptcha_use" value="1" onclick="javascript:hb_toggleLayer('recaptcha', 'block')" <?php echo $on; ?> /> <?php echo _('ReCaptcha'); ?></label> [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#64', '400', '500')"><b>?</b></a>] <br />
                                                    <div id="recaptcha" style="display: <?php echo $div; ?>;">
                                                        &nbsp;
                                                        <br />
                                                        <i><?php echo _('Public key'); ?></i> [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#64', '400', '500')"><b>?</b></a>]<br />
                                                        <input type="text" name="s_recaptcha_public_key" size="40" maxlength="255" value="<?php echo $hesk_settings['recaptcha_public_key']; ?>" />
                                                        <br />
                                                        &nbsp;
                                                        <br />
                                                        <i><?php echo _('Private key'); ?></i> [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#64', '400', '500')"><b>?</b></a>]<br />
                                                        <input type="text" name="s_recaptcha_private_key" size="40" maxlength="255" value="<?php echo $hesk_settings['recaptcha_private_key']; ?>" />
                                                        <br />
                                                        &nbsp;
                                                        <br />
                                                        <i><?php echo _('Use SSL'); ?>:</i> [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#64', '400', '500')"><b>?</b></a>]
<?php
            $on = $hesk_settings['recaptcha_ssl'] ? 'checked="checked"' : '';
            $off = $hesk_settings['recaptcha_ssl'] ? '' : 'checked="checked"';
            echo '
                                                        <label><input type="radio" name="s_recaptcha_ssl" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                                        <label><input type="radio" name="s_recaptcha_ssl" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                                    </div>
                                                    &nbsp;
                                                    <br />
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200" valign="top"><?php echo _('Use anti-SPAM question'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#42', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = '';
            $off = '';
            $div = 'block';

            if ($hesk_settings['question_use']) {
                $on = 'checked="checked"';
            } else {
                $off = 'checked="checked"';
                $div = 'none';
            }
            echo '
                                                <label><input type="radio" name="s_question_use" value="0" ' . $off . ' onclick="javascript:hb_toggleLayer(\'question\',\'none\')" /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_question_use" value="1" ' . $on . ' onclick="javascript:hb_toggleLayer(\'question\',\'block\')" /> ' . $s_on . '</label>';
?>
                                                <div id="question" style="display: <?php echo $div; ?>;">
                                                    &nbsp;
                                                    <br />
                                                    <a href="Javascript:void(0)" onclick="Javascript:hb_rate('generate_spam_question.php', 'question')"><?php echo _('Generate a random question'); ?></a>
                                                    <br />
                                                    &nbsp;
                                                    <br />
                                                    <?php echo _('-&gt; Question (HTML code is <font class="success">allowed</font>)'); ?>:
                                                    <br />
                                                    <textarea name="s_question_ask" rows="3" cols="40"><?php echo $this->helpbase->common->htmlentities($hesk_settings['question_ask']); ?></textarea><br />
                                                    &nbsp;
                                                    <br />
                                                    <?php echo _('-&gt; Answer'); ?>:<br />
                                                    <input type="text" name="s_question_ans" value="<?php echo $hesk_settings['question_ans']; ?>" size="10" />
                                                    <br />
                                                    &nbsp;
                                                    <br />
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    <br />

                                    <!-- Security -->
                                    <span class="section">&raquo; <?php echo _('Security'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Login attempts limit'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#47', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_attempt_limit" size="5" maxlength="30" value="<?php echo ($hesk_settings['attempt_limit'] ? ($hesk_settings['attempt_limit'] - 1) : 0); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Ban time (minutes)'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#47', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" name="s_attempt_banmin" size="5" maxlength="3" value="<?php echo $hesk_settings['attempt_banmin']; ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('View tickets'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#46', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $checked = '';
            if ($hesk_settings['email_view_ticket']) {
                $checked = 'checked="checked"';
            }
?>
                                                <label>
                                                    <input type="checkbox" name="s_email_view_ticket" value="1" <?php echo $checked; ?>/> <?php echo _('Require email to view a ticket'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    </table>
                                    <br />

                                    <!-- Attachments -->
                                    <span class="section">&raquo; <?php echo _('Attachments'); ?></span>
                                    <table width="100%" border="0">
                                        <tr>
                                            <td style="text-align:right" width="200" valign="top">
                                                <?php echo _('Use attachments'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#37', '400', '500')"><b>?</b></a>]
                                            </td>
                                        <td>
<?php
            if (true == $this->enable_use_attachments) {
                $onload_status = '';
                $checked = '';
                if (!$hesk_settings['attachments']['use']) {
                    $checked = ' checked="checked" ';
                    $onload_status = ' disabled="disabled" ';
                }
?>
                                            <label>
                                                <input type="radio" name="s_attach_use" value="0" onclick="hb_attach_disable(new Array('a1', 'a2', 'a3', 'a4'))" <?php echo $checked; ?>/>
                                                <?php echo _('NO'); ?>
                                            </label> |
                                            <label>
<?php
                $checked = '';
                if ($hesk_settings['attachments']['use']) {
                    $checked = ' checked="checked" ';
                }
?>
                                                <input type="radio" name="s_attach_use" value="1" onclick="hb_attach_enable(new Array('a1', 'a2', 'a3', 'a4'))" <?php echo $checked; ?>/>
                                                <?php echo _('YES'); ?>
<?php
                echo '
                                            </label>';
                if (!$this->helpbase->demo_mode) {
?>
                                            &nbsp; (<a href="javascript:void(0);" onclick="hb_toggleLayerDisplay('attachments_limits');"><?php echo _('Server configuration limits'); ?></a>)
                                            <div id="attachments_limits" style="display:none">
                                                <i>upload_max_filesize</i>: <?php echo @ini_get('upload_max_filesize'); ?>
                                                <br />
<?php
                    if (version_compare(phpversion(), '5.2.12', '>=')) {
                        echo '
                                                <i>max_file_uploads</i>: ' . @ini_get('max_file_uploads') . '<br />';
                    }
?>
                                                <i>post_max_size</i>: <?php echo @ini_get('post_max_size'); ?><br />
                                            </div>
<?php
                }
            } else {
                $onload_status = ' disabled="disabled" ';
                echo '
                                            <input type="hidden" name="s_attach_use" value="0" /><font class="notice">' . _('Disabled because your <b>attachments</b> directory is not writable by the script.') . '</font>';
            }
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Number per post'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#38', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_max_number" size="5" maxlength="2" id="a1" value="<?php echo $hesk_settings['attachments']['max_number']; ?>" <?php echo $onload_status; ?> /></td>
                                    </tr>
<?php
            $suffixes = array(
                'B' => _('B') . ' (' . _('bytes') . ')',
                'kB' => _('KB') . ' (' . _('kilobytes') . ')',
                'MB' => _('MB') . ' (' . _('megabytes') . ')',
                'GB' => _('GB') . ' (' . _('gigabytes') . ')',
            );
            $tmp = $this->helpbase->common->formatBytes($hesk_settings['attachments']['max_size'], 0);
            list($size, $unit) = explode(' ', $tmp);
?>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Maxmimum file size'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#39', '400', '500')"><b>?</b></a>]</td>
                                        <td>
                                            <input type="text" name="s_max_size" size="5" maxlength="6" id="a2" value="<?php echo $size; ?>" <?php echo $onload_status; ?> />
                                            <select name="s_max_unit" id="a4" <?php echo $onload_status; ?> >
<?php
            foreach ($suffixes as $k => $v) {
                if ($k == $unit) {
                    echo '
                                                <option value="' . $k . '" selected="selected">' . $v . '</option>';
                } else {
                    echo '
                                                <option value="' . $k . '">' . $v . '</option>';
                }
            }
?>
                                            </select>
                                         </td>
                                     </tr>
                                     <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Allowed file types'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>helpdesk.html#40', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_allowed_types" size="40" maxlength="255" id="a3" value="<?php echo implode(',', $hesk_settings['attachments']['allowed_types']); ?>" <?php echo $onload_status; ?> /></td>
                                    </tr>
                                </table>
                             </div>
                            <!-- HELP DESK -->

                            <!-- KNOWLEDGEBASE -->
                            <div class="tabbertab">
                                <h2><?php echo _('Knowledgebase'); ?></h2>
                                &nbsp;
                                <br />
                                <span class="section">&raquo; <?php echo _('Knowledgebase'); ?></span>
                                <table width="100%" border="0">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Knowledgebase (KB)'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#22', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_enable'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_enable'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_enable" value="0" ' . $off . ' /> ' . _('Disable') . '</label> |
                                            <label><input type="radio" name="s_kb_enable" value="1" ' . $on . ' /> ' . _('Enable') . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('WYSIWYG Editor'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#52', '400', '500')"><b>?</b></a>]<br />&nbsp;</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_wysiwyg'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_wysiwyg'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_wysiwyg" value="0" ' . $off . ' /> ' . _('Disable') . '</label> |
                                            <label><input type="radio" name="s_kb_wysiwyg" value="1" ' . $on . ' /> ' . _('Enable') . '</label>';
?>
                                            <br />&nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Suggest KB articles'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#23', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_recommendanswers'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_recommendanswers'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_recommendanswers" value="0" ' . $off . ' /> ' . _('NO') . '</label> |
                                            <label><input type="radio" name="s_kb_recommendanswers" value="1" ' . $on . ' /> ' . _('YES') . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Enable KB rating'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#24', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_rating'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_rating'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_rating" value="0" ' . $off . ' /> ' . _('NO') . '</label> |
                                            <label><input type="radio" name="s_kb_rating" value="1" ' . $on . ' /> ' . _('YES') . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Show article views'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#58', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_views'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_views'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_views" value="0" ' . $off . ' /> ' . _('NO') . '</label> |
                                            <label><input type="radio" name="s_kb_views" value="1" ' . $on . ' /> ' . _('YES') . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Show article date'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#59', '400', '500')"><b>?</b></a>]<br />&nbsp;</td>
                                        <td>
<?php
            $on = $hesk_settings['kb_date'] ? 'checked="checked"' : '';
            $off = $hesk_settings['kb_date'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_kb_date" value="0" ' . $off . ' /> ' . _('NO') . '</label> |
                                            <label><input type="radio" name="s_kb_date" value="1" ' . $on . ' /> ' . _('YES') . '</label>';
?>
                                            <br />&nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Enable KB search'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#25', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $off = $hesk_settings['kb_search'] ? '' : 'checked="checked"';
            $small = $hesk_settings['kb_search'] == 1 ? 'checked="checked"' : '';
            $large = $hesk_settings['kb_search'] == 2 ? 'checked="checked"' : '';

            echo '
                                            <label><input type="radio" name="s_kb_search" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                            <label><input type="radio" name="s_kb_search" value="1" ' . $small . ' /> ' . _('Small Box') . '</label> |
                                            <label><input type="radio" name="s_kb_search" value="2" ' . $large . ' /> ' . _('Large Box') . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Max search results'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#26', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_kb_search_limit" size="5" maxlength="3" value="<?php echo $hesk_settings['kb_search_limit']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Article preview length'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#27', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_kb_substrart" size="5" maxlength="5" value="<?php echo $hesk_settings['kb_substrart']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Categories in row'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#28', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_kb_cols" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_cols']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Subcategory articles'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#29', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_kb_numshow" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_numshow']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td valign="top" style="text-align:right" width="200"><?php echo _('Show popular articles'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#30', '400', '500')"><b>?</b></a>]</td>
                                        <td>
                                            <input type="text" name="s_kb_index_popart" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_index_popart']; ?>" /> <?php echo _('on <a href="' . $this->helpbase->url . '" target="_blank">help desk index</a> page'); ?><br />
                                            <input type="text" name="s_kb_popart" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_popart']; ?>" /> <?php echo _('on <a href="' . $this->helpbase->url . 'knowledgebase.php" target="_blank">Knowledgebase index</a> page'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td valign="top" style="text-align:right" width="200"><?php echo _('Show latest articles'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>knowledgebase.html#31', '400', '500')"><b>?</b></a>]</td>
                                        <td>
                                            <input type="text" name="s_kb_index_latest" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_index_latest']; ?>" /> <?php echo _('on <a href="' . $this->helpbase->url . '" target="_blank">help desk index</a> page'); ?><br />
                                            <input type="text" name="s_kb_latest" size="5" maxlength="2" value="<?php echo $hesk_settings['kb_latest']; ?>" /> <?php echo _('on <a href="' . $this->helpbase->url . 'knowledgebase.php" target="_blank">Knowledgebase index</a> page'); ?>
                                        </td>
                                    </tr>
                                </table>

                            </div>
                            <!-- KNOWLEDGEBASE -->

                            <!-- CUSTOM -->
                            <div class="tabbertab">
                                <h2><?php echo _('Custom Fields'); ?></h2>
                                &nbsp;
                                <br />

                                <!-- Custom fields -->
                                <span class="section">&raquo; <?php echo _('Custom fields'); ?></span> [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>custom.html#41', '400', '500')"><b>?</b></a>]
                                <br />&nbsp;
                                <table border="0" cellspacing="1" cellpadding="3" width="100%" class="white">
                                    <tr>
                                        <th><b><i><?php echo _('Enable'); ?></i></b></th>
                                        <th><b><i><?php echo _('Type'); ?></i></b></th>
                                        <th><b><i><?php echo _('Required'); ?></i></b></th>
                                        <th><b><i><?php echo _('Field name'); ?></i></b></th>
                                        <th><b><i><?php echo _('Location'); ?></i></b></th>
                                        <th><b><i><?php echo _('Options'); ?></i></b></th>
                                    </tr>
<?php
            for ($i = 1; $i <= 20; $i++) {
                //$this_field='custom' . $i;
                $this_field = $hesk_settings['custom_fields']['custom' . $i];

                $onload_locally = $this_field['use'] ? '' : ' disabled="disabled" ';
                $color = ($i % 2) ? ' class="admin_white" ' : ' class="admin_gray"';
                echo '
                                    <tr>';
                $checked = '';
                if ($this_field['use']) {
                    $checked = 'checked="checked"';
                }
                echo '
                                        <td' . $color . '><label><input type="checkbox" name="s_custom' . $i . '_use" value="1" id="c' . $i . '1" ' . $checked . ' onclick="hb_attach_toggle(\'c' . $i . '1\',new Array(\'s_custom' . $i . '_type\',\'s_custom' . $i . '_req\',\'s_custom' . $i . '_name\',\'c' . $i . '5\',\'c' . $i . '6\'))" /> ' . _('YES') . '</label></td>
                                        <td' . $color . '>
                                            <select name="s_custom' . $i . '_type" id="s_custom' . $i . '_type" ' . $onload_locally . '>
                                                <option value="text"     ' . ($this_field['type'] == 'text' ? 'selected="selected"' : '') . '>' . _('Text field') . '</option>
                                                <option value="textarea" ' . ($this_field['type'] == 'textarea' ? 'selected="selected"' : '') . '>' . _('Large text box') . '</option>
                                                <option value="radio"    ' . ($this_field['type'] == 'radio' ? 'selected="selected"' : '') . '>' . _('Radio button') . '</option>
                                                <option value="select"   ' . ($this_field['type'] == 'select' ? 'selected="selected"' : '') . '>' . _('Select box') . '</option>
                                                <option value="checkbox" ' . ($this_field['type'] == 'checkbox' ? 'selected="selected"' : '') . '>' . _('Checkbox') . '</option>
                                            </select>
                                        </td>';
                $checked = '';
                if ($this_field['req']) {
                    $checked = 'checked="checked"';
                }
                echo '
                                        <td' . $color . '><label><input type="checkbox" name="s_custom' . $i . '_req" value="1" id="s_custom' . $i . '_req" ' . $checked . $onload_locally . ' /> ' . _('YES') . '</label></td>
                                        <td' . $color . '><input type="text" name="s_custom' . $i . '_name" size="20" maxlength="255" id="s_custom' . $i . '_name" value="' . $this_field['name'] . '"' . $onload_locally . ' /></td>
                                        <td' . $color . '>
                                            <label><input type="radio" name="s_custom' . $i . '_place" value="0" id="c' . $i . '5" ' . ($this_field['place'] ? '' : 'checked="checked"') . '  ' . $onload_locally . ' /> ' . _('Before Message') . '</label><br />
                                            <label><input type="radio" name="s_custom' . $i . '_place" value="1" id="c' . $i . '6" ' . ($this_field['place'] ? 'checked="checked"' : '') . '  ' . $onload_locally . ' /> ' . _('After Message') . '</label>
                                        </td>
                                        <td' . $color . '>
                                            <input type="hidden" name="s_custom' . $i . '_val" id="s_custom' . $i . '_val" value="' . $this_field['value'] . '" />
                                            <input type="hidden" name="s_custom' . $i . '_maxlen" id="s_custom' . $i . '_maxlen" value="' . $this_field['maxlen'] . '" />
                                            <a href="Javascript:void(0)" onclick="Javascript:return hb_customOptions(\'custom' . $i . '\',\'s_custom' . $i . '_val\',\'s_custom' . $i . '_type\',\'s_custom' . $i . '_maxlen\',\'' . $this_field['type'] . '\')">' . _('Options') . '</a>
                                        </td>
                                    </tr>';
            } // End FOR
?>
                                </table>
                            </div>
                            <!-- CUSTOM -->

                            <!-- EMAIL -->
                            <div class="tabbertab">
                                <h2><?php echo _('Email'); ?></h2>
                                &nbsp;
                                <br />

                                <!-- Email sending -->
                                <span class="section">&raquo; <?php echo _('Email Sending'); ?></span>
                                <table border="0"  width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Send emails using'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = '';
            $off = '';
            $onload_div = 'none';
            $onload_status = '';

            if ($hesk_settings['smtp']) {
                $on = 'checked="checked"';
                $onload_div = 'block';
            } else {
                $off = 'checked="checked"';
                $onload_status = ' disabled="disabled" ';
            }
            echo '
                                            <label><input type="radio" name="s_smtp" value="0" onclick="hb_attach_disable(new Array(\'s1\',\'s2\',\'s3\',\'s4\',\'s5\',\'s6\',\'s7\',\'s8\',\'s9\'))" onchange="hb_toggleLayerDisplay(\'smtp_settings\');" ' . $off . ' /> ' . _('PHP mail()') . '</label> |
                                            <label><input type="radio" name="s_smtp" value="1" onclick="hb_attach_enable(new Array(\'s1\',\'s2\',\'s3\',\'s4\',\'s5\',\'s6\',\'s7\',\'s8\',\'s9\'))"  onchange="hb_toggleLayerDisplay(\'smtp_settings\');" ' . $on . ' /> ' . _('SMTP Server') . '</label>';
?>
                                            <input type="hidden" name="tmp_smtp_host_name" value="<?php echo $hesk_settings['smtp_host_name']; ?>" />
                                            <input type="hidden" name="tmp_smtp_host_port" value="<?php echo $hesk_settings['smtp_host_port']; ?>" />
                                            <input type="hidden" name="tmp_smtp_timeout" value="<?php echo $hesk_settings['smtp_timeout']; ?>" />
                                            <input type="hidden" name="tmp_smtp_user" value="<?php echo $hesk_settings['smtp_user']; ?>" />
                                            <input type="hidden" name="tmp_smtp_password" value="<?php echo $hesk_settings['smtp_password']; ?>" />
                                            <input type="hidden" name="tmp_smtp_ssl" value="<?php echo $hesk_settings['smtp_ssl']; ?>" />
                                            <input type="hidden" name="tmp_smtp_tls" value="<?php echo $hesk_settings['smtp_tls']; ?>" />
                                        </td>
                                    </tr>
                                </table>
                                <div id="smtp_settings" style="display:<?php echo $onload_div; ?>">
                                    <table border="0"  width="100%">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SMTP Host'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="s1" name="s_smtp_host_name" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_host_name']; ?>" <?php echo $onload_status; ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SMTP Port'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="s2" name="s_smtp_host_port" size="5" maxlength="255" value="<?php echo $hesk_settings['smtp_host_port']; ?>" <?php echo $onload_status; ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SMTP Timeout'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="s3" name="s_smtp_timeout" size="5" maxlength="255" value="<?php echo $hesk_settings['smtp_timeout']; ?>" <?php echo $onload_status; ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SSL Protocol'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['smtp_ssl'] ? 'checked="checked"' : '';
            $off = $hesk_settings['smtp_ssl'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_smtp_ssl" value="0" id="s6" ' . $off . ' ' . $onload_status . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_smtp_ssl" value="1" id="s7" ' . $on . ' ' . $onload_status . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('TLS Protocol'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['smtp_tls'] ? 'checked="checked"' : '';
            $off = $hesk_settings['smtp_tls'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_smtp_tls" value="0" id="s8" ' . $off . ' ' . $onload_status . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_smtp_tls" value="1" id="s9" ' . $on . ' ' . $onload_status . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SMTP Username'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="s4" name="s_smtp_user" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_user']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('SMTP Password'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#55', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="password" id="s5" name="s_smtp_password" size="40" maxlength="255" value="<?php echo $hesk_settings['smtp_password']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200">&nbsp;</td>
                                            <td><input type="button" class="button blue small" onclick="hb_testSMTP()" value="<?php echo _('Test SMTP connection'); ?>" style="margin-top:4px" /></td>
                                        </tr>
                                    </table>

                                    <!-- START SMTP TEST -->
                                    <div id="smtp_test" style="display:none">
                                    </div>

                                    <script language="Javascript" type="text/javascript">
                                        <!--
                                        function hb_testSMTP() {
                                            var element = document.getElementById('smtp_test');
                                            element.innerHTML = '<img src="<?php echo $this->helpbase->url; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes(_('Testing connection, this can take a while...')); ?></i>';
                                            element.style.display = 'block';

                                            var s_smtp_host_name = document.getElementById('s1').value;
                                            var s_smtp_host_port = document.getElementById('s2').value;
                                            var s_smtp_timeout = document.getElementById('s3').value;
                                            var s_smtp_user = document.getElementById('s4').value;
                                            var s_smtp_password = document.getElementById('s5').value;
                                            var s_smtp_ssl = document.getElementById('s7').checked ? 1 : 0;
                                            var s_smtp_tls = document.getElementById('s9').checked ? 1 : 0;

                                            var params = "test=smtp" +
                                                "&s_smtp_host_name=" + encodeURIComponent(s_smtp_host_name) +
                                                "&s_smtp_host_port=" + encodeURIComponent(s_smtp_host_port) +
                                                "&s_smtp_timeout=" + encodeURIComponent(s_smtp_timeout) +
                                                "&s_smtp_user=" + encodeURIComponent(s_smtp_user) +
                                                "&s_smtp_password=" + encodeURIComponent(s_smtp_password) +
                                                "&s_smtp_ssl=" + encodeURIComponent(s_smtp_ssl) +
                                                "&s_smtp_tls=" + encodeURIComponent(s_smtp_tls);

                                            xmlHttp = GetXmlHttpObject();
                                            if (xmlHttp == null) {
                                                return;
                                            }

                                            xmlHttp.open('POST', 'test_connection.php', true);
                                            xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                                            xmlHttp.setRequestHeader("Content-length", params.length);
                                            xmlHttp.setRequestHeader("Connection", "close");

                                            xmlHttp.onreadystatechange = function() {
                                                if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                                                    element.innerHTML = xmlHttp.responseText;
                                                }
                                            }

                                            xmlHttp.send(params);
                                        }
                                        //-->
                                    </script>
                                    <!-- END SMTP TEST -->
                                </div> <!-- END SMTP SETTINGS DIV -->
                                <br />

                                <!-- Email piping -->
                                <span class="section">&raquo; <?php echo _('Email Piping'); ?></span>

                                <table border="0"  width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Email Piping'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#54', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['email_piping'] ? 'checked="checked"' : '';
            $off = $hesk_settings['email_piping'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_email_piping" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                            <label><input type="radio" name="s_email_piping" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                        </td>
                                    </tr>
                                </table>
                                <br />

                                <!-- POP3 Fetching -->
                                <span class="section">&raquo; <?php echo _('POP3 Fetching'); ?></span>

                                <table border="0"  width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('POP3 Fetching'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = '';
            $off = '';
            $onload_div = 'none';
            $onload_status = '';

            if ($hesk_settings['pop3']) {
                $on = 'checked="checked"';
                $onload_div = 'block';
            } else {
                $off = 'checked="checked"';
                $onload_status = ' disabled="disabled" ';
            }

            echo '
                                            <label><input type="radio" name="s_pop3" value="0" onclick="hb_attach_disable(new Array(\'p1\',\'p2\',\'p3\',\'p4\',\'p5\',\'p6\',\'p7\',\'p8\'))" onchange="hb_toggleLayerDisplay(\'pop3_settings\');" ' . $off . ' /> ' . $s_off . '</label> |
                                            <label><input type="radio" name="s_pop3" value="1" onclick="hb_attach_enable(new Array(\'p1\',\'p2\',\'p3\',\'p4\',\'p5\',\'p6\',\'p7\',\'p8\'))" onchange="hb_toggleLayerDisplay(\'pop3_settings\');"  ' . $on . ' /> ' . $s_on . '</label>';
?>
                                            <input type="hidden" name="tmp_pop3_host_name" value="<?php echo $hesk_settings['pop3_host_name']; ?>" />
                                            <input type="hidden" name="tmp_pop3_host_port" value="<?php echo $hesk_settings['pop3_host_port']; ?>" />
                                            <input type="hidden" name="tmp_pop3_user" value="<?php echo $hesk_settings['pop3_user']; ?>" />
                                            <input type="hidden" name="tmp_pop3_password" value="<?php echo $hesk_settings['pop3_password']; ?>" />
                                            <input type="hidden" name="tmp_pop3_tls" value="<?php echo $hesk_settings['pop3_tls']; ?>" />
                                            <input type="hidden" name="tmp_pop3_keep" value="<?php echo $hesk_settings['pop3_keep']; ?>" />
                                        </td>
                                    </tr>
                                </table>
                                <div id="pop3_settings" style="display:<?php echo $onload_div; ?>">
                                    <table border="0"  width="100%">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('POP3 Host'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="p1" name="s_pop3_host_name" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_host_name']; ?>" <?php echo $onload_status; ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('POP3 Port'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="p2" name="s_pop3_host_port" size="5" maxlength="255" value="<?php echo $hesk_settings['pop3_host_port']; ?>" <?php echo $onload_status; ?> /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('TLS Protocol'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['pop3_tls'] ? 'checked="checked"' : '';
            $off = $hesk_settings['pop3_tls'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_pop3_tls" value="0" id="p3" ' . $off . ' ' . $onload_status . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_pop3_tls" value="1" id="p4" ' . $on . ' ' . $onload_status . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Keep a copy'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td>
<?php
            $on = $hesk_settings['pop3_keep'] ? 'checked="checked"' : '';
            $off = $hesk_settings['pop3_keep'] ? '' : 'checked="checked"';
            echo '
                                                <label><input type="radio" name="s_pop3_keep" value="0" id="p7" ' . $off . ' ' . $onload_status . ' /> ' . $s_off . '</label> |
                                                <label><input type="radio" name="s_pop3_keep" value="1" id="p8" ' . $on . ' ' . $onload_status . ' /> ' . $s_on . '</label>';
?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('POP3 Username'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="text" id="p5" name="s_pop3_user" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_user']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('POP3 Password'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td><input type="password" id="p6" name="s_pop3_password" size="40" maxlength="255" value="<?php echo $hesk_settings['pop3_password']; ?>" <?php echo $onload_status; ?> autocomplete="off" /></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200">&nbsp;</td>
                                            <td><input type="button" class="button blue small" onclick="hb_testPOP3()" value="<?php echo _('Test POP3 connection'); ?>" style="margin-top:4px" /></td>
                                        </tr>
                                    </table>

                                    <!-- START POP3 TEST -->
                                    <div id="pop3_test" style="display:none">
                                    </div>

                                    <script language="Javascript" type="text/javascript">
                                        <!--
                                        function hb_testPOP3() {
                                            var element = document.getElementById('pop3_test');
                                            element.innerHTML = '<img src="<?php echo $this->helpbase->url; ?>img/loading.gif" width="24" height="24" alt="" border="0" style="vertical-align:text-bottom" /> <i><?php echo addslashes(_('Testing connection, this can take a while...')); ?></i>';
                                            element.style.display = 'block';

                                            var s_pop3_host_name = document.getElementById('p1').value;
                                            var s_pop3_host_port = document.getElementById('p2').value;
                                            var s_pop3_user = document.getElementById('p5').value;
                                            var s_pop3_password = document.getElementById('p6').value;
                                            var s_pop3_tls = document.getElementById('p4').checked ? 1 : 0;

                                            var params = "test=pop3" +
                                                "&s_pop3_host_name=" + encodeURIComponent(s_pop3_host_name) +
                                                "&s_pop3_host_port=" + encodeURIComponent(s_pop3_host_port) +
                                                "&s_pop3_user=" + encodeURIComponent(s_pop3_user) +
                                                "&s_pop3_password=" + encodeURIComponent(s_pop3_password) +
                                                "&s_pop3_tls=" + encodeURIComponent(s_pop3_tls);

                                            xmlHttp = GetXmlHttpObject();
                                            if (xmlHttp == null) {
                                                return;
                                            }

                                            xmlHttp.open('POST', 'test_connection.php', true);
                                            xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                                            xmlHttp.setRequestHeader("Content-length", params.length);
                                            xmlHttp.setRequestHeader("Connection", "close");

                                            xmlHttp.onreadystatechange = function() {
                                                if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                                                    element.innerHTML = xmlHttp.responseText;
                                                }
                                            }

                                            xmlHttp.send(params);
                                        }
                                        //-->
                                    </script>
                                    <!-- END POP3 TEST -->
                                </div>
                                <!-- END POP3 SETTINGS DIV -->
                                <br />

                                <!-- Email loops -->
                                <span class="section">&raquo; <?php echo _('Email Loops'); ?></span>

                                <table border="0"  width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Max Hits'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#60', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_loop_hits" size="5" maxlength="5" value="<?php echo $hesk_settings['loop_hits']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Timeframe'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#60', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_loop_time" size="5" maxlength="5" value="<?php echo $hesk_settings['loop_time']; ?>" /> <?php echo _('Seconds'); ?></td>
                                    </tr>
                                </table>
                                <br />

                                <!-- Detect email typos -->
                                <span class="section">&raquo; <?php echo _('Detect email typos'); ?></span>

                                <table border="0"  width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Detect email typos'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#62', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = '';
            $off = '';
            $onload_div = 'none';
            $onload_status = '';

            if ($hesk_settings['detect_typos']) {
                $on = 'checked="checked"';
                $onload_div = 'block';
            } else {
                $off = 'checked="checked"';
                $onload_status = ' disabled="disabled" ';
            }

            echo '
                                            <label><input type="radio" name="s_detect_typos" value="0" onclick="hb_attach_disable(new Array(\'d1\'))" onchange="hb_toggleLayerDisplay(\'detect_typos\');" ' . $off . ' /> ' . $s_off . '</label> |
                                            <label><input type="radio" name="s_detect_typos" value="1" onclick="hb_attach_enable(new Array(\'d1\'))" onchange="hb_toggleLayerDisplay(\'detect_typos\');"  ' . $on . ' /> ' . $s_on . '</label>
';
?>
                                        </td>
                                    </tr>
                                </table>
                                <div id="detect_typos" style="display:<?php echo $onload_div; ?>">
                                    <table border="0"  width="100%">
                                        <tr>
                                            <td style="text-align:right;vertical-align:top" width="200"><?php echo _('Email providers'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#63', '400', '500')"><b>?</b></a>]</td>
                                            <td><textarea name="s_email_providers" id="d1" rows="5" cols="40"/><?php echo implode("\n", $hesk_settings['email_providers']); ?></textarea></td>
                                        </tr>
                                    </table>
                                </div>
                                <br />

                                <!-- Other -->
                                <span class="section">&raquo; <?php echo _('Other'); ?></span>
                                <table border="0" width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Strip quoted reply'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#61', '400', '500')"><b>?</b></a>]</td>
                                        <td><label><input type="checkbox" name="s_strip_quoted" value="1" <?php
                                                if ($hesk_settings['strip_quoted']) {
                                                    echo 'checked="checked"';
                                                }
                                                ?>/> <?php echo _('Delete quoted reply from customer emails'); ?></label></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Embedded files'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#64', '400', '500')"><b>?</b></a>]</td>
                                        <td><label><input type="checkbox" name="s_save_embedded" value="1" <?php
                                                if ($hesk_settings['save_embedded']) {
                                                    echo 'checked="checked"';
                                                }
                                                ?>/> <?php echo _('Save embedded files as attachments'); ?></label></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Multiple emails'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#57', '400', '500')"><b>?</b></a>]</td>
                                        <td><label><input type="checkbox" name="s_multi_eml" value="1" <?php
                                                if ($hesk_settings['multi_eml']) {
                                                    echo 'checked="checked"';
                                                }
                                                ?>/> <?php echo _('Allow customers to enter multiple contact emails'); ?></label></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Confirm email'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#50', '400', '500')"><b>?</b></a>]</td>
                                        <td><label><input type="checkbox" name="s_confirm_email" value="1" <?php
                                                          if ($hesk_settings['confirm_email']) {
                                                              echo 'checked="checked"';
                                                          }
                                                ?>/> <?php echo _('Show a &quot;Confirm email&quot; field on the submit a ticket form'); ?></label></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Open only'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>email.html#58', '400', '500')"><b>?</b></a>]</td>
                                        <td><label><input type="checkbox" name="s_open_only" value="1" <?php
                                                          if ($hesk_settings['open_only']) {
                                                              echo 'checked="checked"';
                                                          }
                                                ?>/> <?php echo _('List only open tickets in &quot;Forgot tracking ID&quot; email'); ?></label></td>
                                    </tr>
                                </table>
                                <br />
                            </div>
                            <!-- EMAIL -->

                            <!-- MISC -->
                            <div class="tabbertab">
                                <h2><?php echo _('Misc'); ?></h2>

                                &nbsp;<br />

                                <span class="section">&raquo; <?php echo _('Date &amp; Time'); ?></span>

                                <!-- Date & Time -->
                                <table border="0" width="100%">
                                    <tr>
                                        <td style="text-align:right" width="200" valign="top"><?php echo _('Server time offset'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#18', '400', '500')"><b>?</b></a>]</td>
                                        <td><?php echo _('Current HelpBase time:') . ' <span id="servertime">' . $this->server_time . '</span>'; ?>
                                            <script language="javascript" type="text/javascript">
                                                <!-- startTime(); //-->
                                            </script>
                                            <br />
                                            <input type="text" name="s_diff_hours" size="5" maxlength="3" value="<?php echo $hesk_settings['diff_hours']; ?>" />
                                            <?php echo _('hours'); ?>
                                            <br />
                                            <input type="text" name="s_diff_minutes" size="5" maxlength="3" value="<?php echo $hesk_settings['diff_minutes']; ?>" />
                                            <?php echo _('minutes'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Daylight saving'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#19', '400', '500')"><b>?</b></a>]</td>
                                        <td>
<?php
            $on = $hesk_settings['daylight'] ? 'checked="checked"' : '';
            $off = $hesk_settings['daylight'] ? '' : 'checked="checked"';
            echo '
                                            <label><input type="radio" name="s_daylight" value="0" ' . $off . ' /> ' . $s_off . '</label> |
                                            <label><input type="radio" name="s_daylight" value="1" ' . $on . ' /> ' . $s_on . '</label>';
?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:right" width="200"><?php echo _('Time format'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#20', '400', '500')"><b>?</b></a>]</td>
                                        <td><input type="text" name="s_timeformat" size="40" maxlength="255" value="<?php echo $hesk_settings['timeformat']; ?>" /></td>
                                    </tr>
                                </table>
                                    <br />

                                    <!-- Other -->
                                    <span class="section">&raquo; <?php echo _('Other'); ?></span>

                                    <table border="0" width="100%">
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Admin link'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#21', '400', '500')"><b>?</b></a>]</td>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="s_alink" value="1" <?php
                                                        if ($hesk_settings['alink']) {
                                                            echo 'checked="checked"';
                                                        }
                                                        ?>/> <?php echo _('Display a link to admin panel from <a href="' . $this->helpbase->url . '" target="_blank">help desk index</a>'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Submit notice'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#48', '400', '500')"><b>?</b></a>]</td>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="s_submit_notice" value="1" <?php
                                                        if ($hesk_settings['submit_notice']) {
                                                            echo 'checked="checked"';
                                                        }
                                                        ?>/> <?php echo _('Show notice to clients submitting tickets'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Users Online'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#56', '400', '500')"><b>?</b></a>]</td>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="s_online" value="1" <?php
                                                        if ($hesk_settings['online']) {
                                                            echo 'checked="checked"';
                                                        }
                                                        ?>/> <?php echo _('Show online users. Limit (minutes):'); ?> <input type="text" name="s_online_min" size="5" maxlength="4" value="<?php echo $hesk_settings['online_min']; ?>" />
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:right" width="200"><?php echo _('Updates'); ?>: [<a href="Javascript:void(0)" onclick="Javascript:hb_window('<?php echo $this->help_folder; ?>misc.html#59', '400', '500')"><b>?</b></a>]</td>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="s_check_updates" value="1" <?php
                                                        if ($hesk_settings['check_updates']) {
                                                            echo 'checked="checked"';
                                                        }
                                                        ?>/> <?php echo _('Automatically check for HelpBase updates.'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <!-- MISC -->
                            </div>

                            <!-- TABS -->
                            <p>&nbsp;</p>
                            <p align="center">
                                <input type="hidden" name="token" value="<?php $this->helpbase->common->token_echo(); ?>" />
<?php
            if (true == $this->enable_save_settings) {
                echo '
                                <input type="submit" id="submitbutton" value="' . _('Save changes') . '" class="button blue small" />';
            } else {
                echo '
                                <input type="button" value="' . _('Save changes') . ' (' . _('disabled') . ')" class="button blue small" disabled="disabled" /><br /><font class="error">' . _('Unable to save your settings because <b>hesk_settings.inc.php</b> file is not writable by the script.') . '</font>';
            }
?>
                            </p>
                        </form>
                        <p>&nbsp;</p>
<?php
            $this->helpbase->footer->render();

            unset($helpbase);

            exit();
        }

        private function checkVersion() {
            global $hesk_settings;

            if ($latest = $this->getLatestVersion()) {
                if (strlen($latest) > 12) {
                    return -1;
                } elseif ($latest == $hesk_settings['hesk_version']) {
                    return true;
                } else {
                    return $latest;
                }
            } else {
                return -1;
            }
        }

        private function getLatestVersion() {
            global $hesk_settings;

            // Do we have a cached version file?
            if (file_exists($this->helpbase->dir . $hesk_settings['attach_dir'] . '/__latest.txt')) {
                if (preg_match('/^(\d+)\|([\d.]+)+$/', @file_get_contents($this->helpbase->dir . $hesk_settings['attach_dir'] . '/__latest.txt'), $matches) && (time() - intval($matches[1])) < 3600) {
                    return $matches[2];
                }
            }

            // No cached file or older than 3600 seconds, try to get an update
            $hesk_version_url = 'http://heskcom.s3.amazonaws.com/hesk_version.txt';

            // Try using cURL
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $hesk_version_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
                $latest = curl_exec($ch);
                curl_close($ch);
                return $this->cacheLatestVersion($latest);
            }

            // Try using a simple PHP function instead
            if ($latest = file_get_contents($hesk_version_url)) {
                return $this->cacheLatestVersion($latest);
            }

            // Can't check automatically, will need a manual check
            return false;
        }

        function cacheLatestVersion($latest) {
            global $hesk_settings;

            @file_put_contents($this->helpbase->dir . $hesk_settings['attach_dir'] . '/__latest.txt', time() . '|' . $latest);

            return $latest;
        }

        private function testLanguage($return_options = 0) {
            global $hesk_settings, $hesklang;

            /* Get a list of valid emails */
            $this->helpbase->load_email_functions();

            $valid_emails = array_keys($this->helpbase->email->validEmails());

            $dir = $this->helpbase->dir . 'locale/';
            $path = opendir($dir);

            $text = '';
            $html = '';

            $text .= "/locale\n";

            /* Test all folders inside the language folder */
            while (false !== ($subdir = readdir($path))) {
                if ($subdir == "." || $subdir == "..") {
                    continue;
                }

                if (filetype($dir . $subdir) == 'dir') {
                    $add = 1;

                    $email = $dir . $subdir . '/emails';
                    $texts = $dir . $subdir . '/LC_MESSAGES';
                    $langName = $dir . $subdir . '/lang.name';

                    /* Check for lang.name */
                    $text .= "   |-> /$subdir\n";
                    $text .= "        |-> lang.name: ";

                    if (file_exists($langName)) {
                        $err = '';
                        $tmp = file_get_contents($langName);

                        // Some servers add slashes to file_get_contents output
                        if (strpos($tmp, '[\\\'LANGUAGE\\\']') !== false) {
                            $tmp = stripslashes($tmp);
                        }

                        // Verify contents
                        if (!preg_match('/\[\'LANGUAGE\'\]\=\'(.*)\'/', $tmp, $l)) {
                            $err .= "              |---->  MISSING: ['LANGUAGE']\n";
                            $l = $subdir;
                        }

                        if ($err) {
                            $text .= "ERROR\n" . $err;
                            $add = 0;
                        } else {
                            $l[1] = $this->helpbase->common->_input($l[1]);
                            $l[1] = str_replace('|', ' ', $l[1]);
                            $text .= "OK ($l[1])\n";
                        }
                    } else {
                        $text .= "ERROR\n";
                        $text .= "              |---->  MISSING: lang.name\n";
                        $add = 0;
                    }

                    // Check for LC_MESSAGES folder
                    $text .= "        |-> /LC_MESSAGES:  ";
                    if (file_exists($texts) && filetype($texts) == 'dir') {

                        // check for text.po
                        $langu = $texts . '/text.po';
                        if (file_exists($langu)) {
                            $err = '';
                            $tmp = file_get_contents($langu);

                            if (strpos($tmp, 'msgid "Language"') === false) {
                                $err .= "              |---->  MISSING: \msgid \"Language\"\n";
                            }

                           if ($err) {
                                $text .= "ERROR\n" . $err;
                                $add = 0;
                            } else {
                                $text .= "OK\n";
                            }
                        } else {
                            $text .= "ERROR\n";
                            $text .= "              |---->  MISSING: text.po\n";
                            $add = 0;
                        }
                    } else {
                        $text .= "ERROR\n";
                        $text .= "              |---->  MISSING: /LC_MESSAGES folder\n";
                        $add = 0;
                    }

                    /* Check emails folder */
                    $text .= "        |-> /emails:  ";
                    if (file_exists($email) && filetype($email) == 'dir') {
                        $err = '';
                        foreach ($valid_emails as $eml) {
                            if (!file_exists($email . '/' . $eml . '.txt')) {
                                $err .= "              |---->  MISSING: $eml.txt\n";
                            }
                        }

                        if ($err) {
                            $text .= "ERROR\n" . $err;
                            $add = 0;
                        } else {
                            $text .= "OK\n";
                        }
                    } else {
                        $text .= "ERROR\n";
                        $text .= "              |---->  MISSING: /emails folder\n";
                        $add = 0;
                    }

                    $text .= "\n";

                    /* Add an option for the <select> if needed */
                    if ($add) {
                        if ($l[1] == $hesk_settings['language']) {
                            $html .= '<option value="' . $subdir . '|' . $l[1] . '" selected="selected">' . $l[1] . '</option>';
                        } else {
                            $html .= '<option value="' . $subdir . '|' . $l[1] . '">' . $l[1] . '</option>';
                        }
                    }
                }
            }

            closedir($path);

            /* Output select options or the test log for debugging */
            if ($return_options) {
                return $html;
            } else {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML; 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
    <head>
        <title><?php echo _('Test language folder'); ?></title>
        <meta http-equiv="Content-Type" content="text/html;charset=<?php echo $hesklang['ENCODING']; ?>" />
        <style type="text/css">
            body {
                margin:5px 5px;
                padding:0;
                background:#fff;
                color: black;
                font : 68.8%/1.5 Verdana, Geneva, Arial, Helvetica, sans-serif;
                text-align:left;
            }

            p {
                color : black;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-size: 1.0em;
            }

            h3 {
                color : #AF0000;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-weight: bold;
                font-size: 1.0em;
                text-align:center;
            }

            .title {
                color : black;
                font-family : Verdana, Geneva, Arial, Helvetica, sans-serif;
                font-weight: bold;
                font-size: 1.0em;
            }

            .wrong   {color : red;}
            .correct {color : green;}
            pre {font-size:1.2em;}
        </style>
    </head>
    <body>
        <h3><?php echo _('Test language folder'); ?></h3>
        <p><i><?php echo _('Testing the language folder for valid languages. Only languages that pass all tests are properly installed.'); ?></i></p>
        <pre><?php echo $text; ?></pre>
        <p>&nbsp;</p>
        <p align="center"><a href="admin_settings.php?test_languages=1&amp;<?php echo rand(10000, 99999); ?>"><?php echo _('Test again'); ?></a> | <a href="#" onclick="Javascript:window.close()"><?php echo _('Close window'); ?></a></p>
        <p>&nbsp;</p>
    </body>
</html>
<?php
            unset($this->helpbase);
            exit();
            }
        }
    }

    new HelpbaseAdminSettings;
}

