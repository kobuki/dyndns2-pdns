<?php

include_once('../inc/config.inc.php');
include_once('../inc/common.inc.php');
include_once('../inc/' . BACKEND . '.inc.php');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    if (!isset($_GET['user']) && !isset($_GET['username']) || !isset($_GET['password'])) {
        $code = basename($_SERVER['SCRIPT_URL']);
        $auth_data = base64_decode($code, true);
        if ($auth_data !== false) {
            $auth_data = explode(':', $auth_data, 3);
            // user_id:domain_id:password
            if (count($auth_data) == 3) {
                $user_id = $auth_data[0];
                $hostname_id = $auth_data[1];
                $pass = $auth_data[2];
                $myip_input = 'auto';
            } else {
                auth_fail();
            }
        } else {
            auth_fail();
        }
    } else {
        $user = isset($_GET['user']) ? $_GET['user'] : $_GET['username'];
        $pass = $_GET['password'];
    }
} else {
    $user = $_SERVER['PHP_AUTH_USER'];
    $pass = $_SERVER['PHP_AUTH_PW'];
}

try {
    $db = new PDO(DB_URI, DB_USERNAME, DB_PASSWORD, array(
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
    ));
} catch (PDOException $e) {
    fail(500, 'dberror', $e->getMessage());
}

if (isset($user_id)) {
    $user = verify_credentials($db, null, $pass, $user_id);
} else {
    $user_id = verify_credentials($db, $user, $pass, null);
}

if (!$user_id) {
    $db = null;
    auth_fail();
}

$client_ip = get_client_ip();

if (isset($_GET['acmeproxy'])) {
    $acmeproxy_action = ltrim($_GET['acmeproxy'], '/');
    $acmeproxy_input = json_decode(file_get_contents('php://input'), true);
    $acmeproxy_hostname = rtrim($acmeproxy_input['fqdn'], '.');
    $acmeproxy_txt = $acmeproxy_input['value'];
}

if (isset($_GET['hostname'])) {
    $hostname_input = $_GET['hostname'];
} elseif (isset($acmeproxy_hostname)) {
    $hostname_input = $acmeproxy_hostname;
} elseif (isset($hostname_id)) {
    $hostname_input = get_hostname($db, $hostname_id);
} else {
    $hostname_input = false;
}
$hostnames = array();
if ($hostname_input) {
    $hostname_input = explode(',', $hostname_input);
    foreach ($hostname_input as $hostname) {
        $extra = '';
        if ($hostname[0] === '_') {
            $extra = '_';
            $hostname = substr($hostname, 1);
        }
        $hostname = filter_var(strtolower($hostname), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        if ($hostname) {
            $hostname = $extra . $hostname . '.';
            $zone = verify_hostname($db, $user_id, $hostname);
            if ($zone) {
                $hostnames[$hostname] = array(
                    'zone' => $zone
                );
            } else {
                $db = null;
                fail(400, 'nohost', 'Hostname = ' . $hostname . ' is invalid for user ' . $user . ' (' . $user_id . ')');
            }
        }
    }
}
if (empty($hostnames)) {
    fail(400, 'notfqdn', 'Invalid field hostname = ' . implode(',', $hostname_input));
}
if (count($hostnames) > MAX_UPDATE_HOSTNAMES) {
    fail(400, 'numhosts', 'Too many hostnames in request (' . count($hostnames) . ' > maximum ' . MAX_UPDATE_HOSTNAMES . ')');
}

if (isset($_GET['myip']) || isset($myip_input)) {
    if (!isset($myip_input)) $myip_input = $_GET['myip'];
    if ($myip_input === '') {
        $ipv4 = '';
        $ipv6 = '';
    } else {
        $myip_input = array_filter(explode(',', $myip_input));
        foreach ($myip_input as $myip) {
            $tryip = filter_var($myip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($tryip !== false) {
                $ipv4 = $tryip;
            }
            $tryip = filter_var($myip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($tryip !== false) {
                $ipv6 = $tryip;
            }
        }
        if (!isset($ipv4) && !isset($ipv6)) {
            $tryip = filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($tryip !== false) {
                $ipv4 = $tryip;
            }
            $tryip = filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($tryip !== false) {
                $ipv6 = $tryip;
            }
        }
    }
    if (!isset($ipv4) && !isset($ipv6)) {
        fail(500, 'iperr', 'Cannot identify any client IP');
    }
}
if (isset($_GET['txt'])) {
    $txt = $_GET['txt'];
} elseif (isset($acmeproxy_action) && isset($acmeproxy_txt)) {
    $txt = $acmeproxy_action == 'present' ? $acmeproxy_txt : '';
}

if (!isset($ipv4) && !isset($ipv6) && !isset($txt)) {
    fail(200, 'nochg', 'No change requested: ' . implode(',', $hostnames));
}

$ipv4 = isset($ipv4) ? $ipv4 : false;
$ipv6 = isset($ipv6) ? $ipv6 : false;
$txt = isset($txt) ? $txt : false;
// For acmeproxy, $acmeproxy_txt holds the real value on both present and cleanup,
// so the changelog delete row records the value instead of the '' delete signal.
$txt_content = isset($acmeproxy_txt) ? $acmeproxy_txt : $txt;
// On acmeproxy cleanup, remove only the matching TXT value rather than the whole
// rrset; null keeps the plain dyndns2 txt='' delete-all behaviour untouched.
$txt_remove = (isset($acmeproxy_action) && $acmeproxy_action === 'cleanup') ? $acmeproxy_txt : null;

if ($txt === false && ($ipv4 !== false || $ipv6 !== false)) {
    $last_ips = get_last_ips($db, $hostnames);
    $changed = array_filter($hostnames, function($info, $hostname) use ($last_ips, $ipv4, $ipv6) {
        $last = $last_ips[$hostname] ?? ['ipv4' => null, 'ipv6' => null];
        if ($ipv4 !== false && $ipv4 !== $last['ipv4']) return true;
        if ($ipv6 !== false && $ipv6 !== $last['ipv6']) return true;
        return false;
    }, ARRAY_FILTER_USE_BOTH);
    if (empty($changed)) {
        $db = null;
        echo 'nochg';
        exit;
    }
    $hostnames = $changed;
}

update_dns($hostnames, $ipv4, $ipv6, $txt, $txt_remove);
update_last_updated($db, $hostnames, $ipv4, $ipv6);
log_changelog($db, $user, $hostnames, $ipv4, $ipv6, $txt, $client_ip, $txt_content);
$db = null;

echo 'good';
if (isset($acmeproxy_txt)) {
    echo " (TXT=\"{$acmeproxy_txt}\")";
}
