<?php

/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Admin 
 * @subpackage  Generate Anti-spam Question
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */

define('EXECUTING', true);

if (!class_exists('HelpbaseGenSpamQ')) {
    class HelpbaseGenSpamQ {
        private $helpbase       = null;
        private $spam_question  = array();
        
        public function __construct(){
            include_once('../helpbase.class.php');
            $helpbase = new HelpbaseCore(false);
            $this->helpbase = $helpbase;
            
            include_once($helpbase->includes . 'setup_functions.inc.php');
            $setup = new HelpbaseSetup($helpbase);

            $this->spam_question = $setup->generate_SPAM_question();  

            $this->render();
            
            unset($setup);
            unset($helpbase);            
        }
        
        private function render(){
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header('Content-type: text/plain');
?>
            <a href="Javascript:void(0)" onclick="Javascript:hb_rate('generate_spam_question.php', 'question')"><?php echo _('Generate a random question'); ?></a><br />

<?php 
            echo _('-&gt; Question (HTML code is <font class="success">allowed</font>)'); ?>:
            <br />
            <textarea name="s_question_ask" rows="3" cols="40"><?php echo addslashes($this->helpbase->common->htmlspecialchars($this->spam_question[0])); ?></textarea><br />

<?php 
            echo _('-&gt; Answer'); ?>:
            <br />
            <input type="text" name="s_question_ans" value="<?php echo addslashes($this->helpbase->common->htmlspecialchars($this->spam_question[1])); ?>" size="10" />
<?php
            exit();
        }
    }
    
    new HelpbaseGenSpamQ;
}

?>
