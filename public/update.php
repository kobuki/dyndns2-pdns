<?php

include_once('../inc/config.inc.php');
include_once('../inc/common.inc.php');
include_once('../inc/pdns.inc.php');
include_once('../inc/hooks.inc.php');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    if (!isset($_GET['user']) || !isset($_GET['password'])) {
        $code = basename($_SERVER['SCRIPT_URL']);
        $auth_data = base64_decode($code, true);
        if ($auth_data !== false) {
            $auth_data = explode(':', $auth_data, 3);
            // user_id:domain_id:password
            if (sizeof($auth_data) == 3) {
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
        $user = $_GET['user'];
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

$ch = curl_init(PDNS_ZONES_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'X-API-Key: ' . PDNS_API_KEY
));
$response = curl_exec($ch);
$response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($response_code != 200) {
    $db = null;
    curl_close($ch);
    print_r([$response, $response_code]);
    fail(500, 'zoneerr', 'Could not retrieve zones: ' . $response);
}
$zones = array();
foreach (json_decode($response, true) as $zone) {
    $zones[] = $zone['id'];
}

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
            $zone = verify_hostname($db, $user_id, $hostname, $zones);
            if ($zone) {
                $hostnames[$hostname] = array(
                    'zone' => $zone,
                    'hooks' => Hook::load($db, $hostname),
                );
            } else {
                $db = null;
                curl_close($ch);
                fail(400, 'nohost', 'Hostname = ' . $hostname . ' is invalid for user ' . $user . ' (' . $user_id . ')');
            }
        }
    }
}
$db = null;
if (empty($hostnames)) {
    curl_close($ch);
    fail(400, 'notfqdn', 'Invalid field hostname = ' . implode(',', $hostname_input));
}
if (count($hostnames) > MAX_UPDATE_HOSTNAMES) {
    curl_close($ch);
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
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $myip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $myip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $myip = $_SERVER['REMOTE_ADDR'];
            }
            $tryip = filter_var($myip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($tryip !== false) {
                $ipv4 = $tryip;
            }
            $tryip = filter_var($myip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($tryip !== false) {
                $ipv6 = $tryip;
            }
        }
    }
    if (!isset($ipv4) && !isset($ipv6)) {
        curl_close($ch);
        fail(500, 'iperr', 'Cannot identify any client IP');
    }
}
if (isset($_GET['txt'])) {
    $txt = $_GET['txt'];
} elseif (isset($acmeproxy_action) && isset($acmeproxy_txt)) {
    $txt = $acmeproxy_action == 'present' ? $acmeproxy_txt : '';
}

if (!isset($ipv4) && !isset($ipv6) && !isset($txt)) {
    curl_close($ch);
    fail(200, 'nochg', 'No change requested: ' . implode(',', $hostnames));
}

$ipv4 = isset($ipv4) ? $ipv4 : false;
$ipv6 = isset($ipv6) ? $ipv6 : false;
$txt = isset($txt) ? $txt : false;

foreach ($hostnames as $hostname => $info) {
    $rrsets = [];
    if ($ipv4 !== false) {
        $rrsets[] = build_rrset($hostname, 'A', $ipv4);
    }
    if ($ipv6 !== false) {
        $rrsets[] = build_rrset($hostname, 'AAAA', $ipv6);
    }
    if ($txt !== false) {
        $rrsets[] = build_rrset($hostname, 'TXT', $txt);
    }

    $payload = json_encode(array('rrsets' => $rrsets));

    curl_setopt($ch, CURLOPT_URL, PDNS_ZONES_URL . '/' . $info['zone']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response_code >= 400) {
        curl_close($ch);
        fail($response_code, 'dnserr', 'PowerDNS API failed: ' . $hostname . '/' . $info['zone'] . ' = IPv4 ' . $ipv4 . ', IPv6 ' . $ipv6 . ', TXT ' . $txt . ' => ' . $response);
    }

    foreach ($info['hooks'] as $hook) {
        $hook->execute($ipv4, $ipv6, $txt);
    }
}

curl_close($ch);

echo 'good';
if (isset($acmeproxy_txt)) {
    echo ' (TXT="' . $acmeproxy_txt . '")';
}
