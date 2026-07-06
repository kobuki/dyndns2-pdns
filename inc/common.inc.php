<?php

function fail($code, $message, $details = NULL)
{
    error_log('dyn-update / ' . $message . ': ' . $details);

    http_response_code($code);
    exit($message);
}

function auth_fail() {
    header('WWW-Authenticate: Basic realm="DynDNS"');
    fail(401, 'Authentication required');
}

function get_hostname($db, $hostname_id) {
    if (!ctype_digit((string)$hostname_id)) return false;
    foreach ($db->query('SELECT hostname from hostnames where id = ' . $hostname_id) as $row) {
        return substr($row['hostname'], 0, -1);
    }
    return false;
}

function verify_credentials($db, $user, $pass, $user_id=null)
{
    if (isset($user_id)) {
        if (!ctype_digit((string)$user_id)) return false;
        foreach ($db->query('SELECT `username`, `password` ' .
            'FROM `users` ' .
            'WHERE `active` = 1 AND `id` = ' . $user_id) as $row) {
            if (password_verify($pass, $row['password'])) {
                return $row['username'];
            }
        }
    } else {
        foreach ($db->query('SELECT `id`, `password` ' .
            'FROM `users` ' .
            'WHERE `active` = 1 AND `username` = ' . $db->quote($user)) as $row) {
            if (password_verify($pass, $row['password'])) {
                return $row['id'];
            }
        }
    }
    return false;
}

function match_domain($domain, $pattern)
{
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

function get_client_ip() {
    $headers = [
        'HTTP_X_REAL_IP',        // nginx
        'HTTP_X_FORWARDED_FOR',  // standard proxy header; may be a comma-separated list
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'];
}

function log_changelog($db, $username, $hostnames, $ipv4, $ipv6, $txt, $client_ip, $txt_content = null) {
    $stmt = $db->prepare('INSERT INTO `changelog` (`username`, `hostname`, `operation`, `record_type`, `record_content`, `client_ip`) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($hostnames as $hostname => $info) {
        if ($ipv4 !== false) {
            $op = ($ipv4 === '') ? 'delete' : 'set';
            $stmt->execute([$username, $hostname, $op, 'A', $ipv4, $client_ip]);
        }
        if ($ipv6 !== false) {
            $op = ($ipv6 === '') ? 'delete' : 'set';
            $stmt->execute([$username, $hostname, $op, 'AAAA', $ipv6, $client_ip]);
        }
        if ($txt !== false) {
            // $txt is the DNS operation signal ('' means delete); $txt_content, when
            // provided (acmeproxy), is the actual TXT value so the delete row records
            // which value was removed instead of an empty string.
            $op = ($txt === '') ? 'delete' : 'add';
            $content = ($txt_content !== null) ? $txt_content : $txt;
            $stmt->execute([$username, $hostname, $op, 'TXT', $content, $client_ip]);
        }
    }
}

function get_last_ips($db, $hostnames) {
    $quoted = array_map(function($h) use ($db) { return $db->quote($h); }, array_keys($hostnames));
    $result = [];
    foreach ($db->query('SELECT `hostname`, `last_ipv4`, `last_ipv6` FROM `hostnames` WHERE `hostname` IN (' . implode(',', $quoted) . ')') as $row) {
        $result[$row['hostname']] = ['ipv4' => $row['last_ipv4'], 'ipv6' => $row['last_ipv6']];
    }
    return $result;
}

function update_last_updated($db, $hostnames, $ipv4, $ipv6) {
    $quoted = array_map(function($h) use ($db) { return $db->quote($h); }, array_keys($hostnames));
    $sets = ['`last_updated` = NOW()'];
    if ($ipv4 !== false) $sets[] = '`last_ipv4` = ' . $db->quote($ipv4);
    if ($ipv6 !== false) $sets[] = '`last_ipv6` = ' . $db->quote($ipv6);
    $db->exec('UPDATE `hostnames` SET ' . implode(', ', $sets) .
        ' WHERE `hostname` IN (' . implode(',', $quoted) . ')');
}

function verify_hostname($db, $user_id, $hostname)
{
    foreach ($db->query('SELECT `hostnames`.`hostname` AS `hostname`, `hostnames`.`domain` AS `domain` ' .
        'FROM `permissions` LEFT JOIN `hostnames` ON ' .
        '`permissions`.`hostname_id`=`hostnames`.`id` ' .
        'WHERE `permissions`.`user_id`=' . $user_id . ' AND ' .
        $db->quote($hostname) . ' LIKE CONCAT(\'%\', `hostnames`.`hostname`)') as $row) {
        if (match_domain($hostname, $row['hostname'])) {
            return $row['domain'];
        }
    }
    return false;
}
