<?php

function fail($code, $message, $details = NULL) {
    error_log('dyn-update / ' . $message . ': ' . $details);

    http_response_code($code);
    exit($message);
}

function verify_credentials($db, $user, $pass) {
    // generate a password using command
    // htpasswd -bnBC 10 "" 'mypassword' | tr -d ':'
    foreach ($db->query('SELECT `id`,`password` ' .
                        'FROM `users` ' .
                        'WHERE `active`=1 AND `username`=' . $db->quote($user)) as $row) {
        if (password_verify($pass, $row['password'])) {
            return $row['id'];
        }
    }
    return false;
}

function match_domain($domain, $pattern) {
    // treat as wildcard pattern if it starts with '.'
    if ($pattern[0] !== '.') {
        return ($domain === $pattern);
    }
    $length = strlen($pattern);
    if ($length == 0) {
        return true;
    }
    return (substr($domain, -$length) === $pattern);
}

function verify_hostname($db, $user_id, $hostname, $zones) {
    foreach($db->query('SELECT `hostnames`.`hostname` AS `hostname` ' .
                       'FROM `permissions` LEFT JOIN `hostnames` ON ' .
                           '`permissions`.`hostname_id`=`hostnames`.`id` ' .
                       'WHERE `permissions`.`user_id`='.$user_id.' AND ' .
                           $db->quote($hostname).' LIKE CONCAT(\'%\', `hostnames`.`hostname`)') as $row) {
        if (match_domain($hostname, $row['hostname'])) {
            $hostname_zone = false;
            foreach ($zones as $zone) {
                if (strlen($zone) > strlen($hostname_zone) && substr($hostname, -strlen($zone)) === $zone) {
                    $hostname_zone = $zone;
                }
            }

            return $hostname_zone;
        }
    }
    return false;
}
