<?php

/*
  Use the static method getInstance to get the object.
 */

class Session {

    const SESSION_STARTED = TRUE;
    const SESSION_NOT_STARTED = FALSE;

    // The state of the session
    private $sessionState = self::SESSION_NOT_STARTED;
    // THE only instance of the class
    private static $instance;

    private static $db = null;
    private function __construct() {
        global $helpbase;

    }

    /**
     *    Returns THE instance of 'Session'.
     *    The session is automatically initialized if it wasn't.
     *   
     *    @return    object
     * */
    public static function getInstance($db) {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        self::$db = $db;
        self::$instance->startSession();

        return self::$instance;
    }

    /**
     *    (Re)starts the session.
     *   
     *    @return    bool    TRUE if the session has been initialized, else FALSE.
     * */
    public function startSession() {
        global $helpbase, $hesk_settings, $db;

        if ($this->sessionState == self::SESSION_NOT_STARTED) {
            //$db = self::$db->load_database_function();
            //$data = serialize($hesk_settings);
            
            //$res = self::$db->query('UPDATE `' . self::$db->escape($hesk_settings['db_pfix']) . "options` SET `settings`='" . self::$db->escape($data) . "' WHERE `key`='hb_data' LIMIT 1");
          
            $this->sessionState = true; //session_start();
            $res = self::$db->query('SELECT * FROM `' . self::$db->escape($hesk_settings['db_pfix']) . "options` WHERE `key`='hb_data'");
            $row = self::$db->fetchAssoc($res);
            
            $row = unserialize($row['settings']);
            //print_r ($row['site_title']);        
        }

        return $row; //$this->sessionState;
    }

    /**
     *    Stores datas in the session.
     *    Example: $instance->foo = 'bar';
     *   
     *    @param    name    Name of the datas.
     *    @param    value    Your datas.
     *    @return    void
     * */
    public function __set($name, $value) {
        $_SESSION[$name] = $value;
    }

    /**
     *    Gets datas from the session.
     *    Example: echo $instance->foo;
     *   
     *    @param    name    Name of the datas to get.
     *    @return    mixed    Datas stored in session.
     * */
    public function __get($name) {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        }
    }

    public function __isset($name) {
        return isset($_SESSION[$name]);
    }

    public function __unset($name) {
        unset($_SESSION[$name]);
    }

    /**
     *    Destroys the current session.
     *   
     *    @return    bool    TRUE is session has been deleted, else FALSE.
     * */
    public function destroy() {
        if ($this->sessionState == self::SESSION_STARTED) {
            $this->sessionState = !session_destroy();
            unset($_SESSION);

            return !$this->sessionState;
        }

        return FALSE;
    }

}
