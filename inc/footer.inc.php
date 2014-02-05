<?php
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Includes 
 * @subpackage  Footer
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */
// Check if this is a valid include
if (!defined('EXECUTING')) {
    exit();
}

if (!class_exists('HelpbaseFooter')) {

    class HelpbaseFooter {
        private $helpbase = null;
        
        public function __construct($parent){
            $this->helpbase = $parent;
        }
        
        public function render() {
            
            // Auto-select first empty or error field on non-staff pages?
            if (true == $this->helpbase->autofocus) {
                ?>

                <script language="javascript">
                    (function() {
                        var forms = document.forms || [];
                        for (var i = 0; i < forms.length; i++) {
                            for (var j = 0; j < forms[i].length; j++) {
                                if (
                                        !forms[i][j].readonly != undefined &&
                                        forms[i][j].type != "hidden" &&
                                        forms[i][j].disabled != true &&
                                        forms[i][j].style.display != 'none' &&
                                        (forms[i][j].className == 'isError' || forms[i][j].className == 'isNotice' || forms[i][j].value == '')
                                        ) {
                                    forms[i][j].focus();
                                    return;
                                }
                            }
                        }
                    }
                    )();
                </script>

                <?php
            }

            // Users online
            if (true == $this->helpbase->show_online) {
                $this->helpbase->users_online->printOnline();
            }
?>
                
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html> 
<?php
        }

    }

}