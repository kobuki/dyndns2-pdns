<?php

class Hook {
    public static function load($db, $hostname) {
        $hooks = [];
        foreach($db->query('SELECT `hooks`.`hook` AS `hook` FROM ' .
                           '`hooks` LEFT JOIN `hostnames` ON ' .
                               '`hooks`.`hostname_id`=`hostnames`.`id` ' .
                           'WHERE '.$db->quote($hostname).' LIKE CONCAT(\'%\', `hostnames`.`hostname`)') as $row) {
            $hooks[] = new Hook($hostname, json_decode($row['hook']));
        }
        return $hooks;
    }

    private $_name;
    private $_triggers;
    private $_hook_type;
    private $_hook_params;
    private $_hostname;

    private $_hook_impl;

    private function __construct($hostname, $hook) {
        $this->_name = $hook->name;
        $this->_triggers = $hook->triggers;
        $this->_hook_type = $hook->hook->type;
        $this->_hook_params = $hook->hook->parameters;
        $this->_hostname = $hostname;

        $this->_hook_impl = array(
            'curl' => '_execute_curl',
            'send-mail' => '_execute_send_mail',
            'shell' => '_execute_shell',
        );
    }

    private function _build_params($ipv4, $ipv6, $txt) {
        $params = array();
        foreach ($this->_hook_params as $_hook_param) {
            $params[$_hook_param->name] = str_replace('<domain>', $this->_hostname,
                                          str_replace('<ipv4>', $ipv4,
                                          str_replace('<ipv6>', $ipv6,
                                          str_replace('<txt>', $txt, $_hook_param->value))));
        }
        return $params;
    }

    private function _execute_curl($params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        foreach ($params as $name => $value) {
            $opt_name = 'CURLOPT_'.strtoupper($name);
            if (defined($opt_name)) {
                curl_setopt($ch, constant($opt_name), $value);
            }
        }
        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return ($response_code < 400);
    }

    private function _execute_send_mail($params) {
        // not implemented, yet
        return FALSE;
    }

    private function _execute_shell($params) {
        // not implemented, yet
        return FALSE;
    }

    public function execute($ipv4, $ipv6, $txt) {
        if (in_array('any',$this->_triggers) ||
            (($ipv4 !== FALSE) && in_array('ipv4', $this->_triggers)) ||
            (($ipv6 !== FALSE) && in_array('ipv6', $this->_triggers)) ||
            (($txt !== FALSE) && in_array('txt', $this->_triggers))) {

            if (array_key_exists($this->_hook_type, $this->_hook_impl)) {
                $hook_impl = $this->_hook_impl[$this->_hook_type];
                $params = $this->_build_params($ipv4, $ipv6, $txt);
                return $this->$hook_impl($params);
            }
        }
        return FALSE;
    }
}
