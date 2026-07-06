<?php

include_once('config.inc.php');
include_once('common.inc.php');

function get_records($hostname, $type, $ch, $info)
{
    curl_setopt($ch, CURLOPT_URL, PDNS_ZONES_URL . '/' . $info['zone']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    $response = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response_code >= 400) {
        curl_close($ch);
        fail($response_code, 'dnserr', 'PowerDNS API failed getting TXT records for ' . $hostname);
    } else {
        $zone = json_decode($response, true);
        foreach ($zone['rrsets'] as $rr) {
            if ($rr['type'] == $type && $rr['name'] == $hostname) return $rr['records'];
        }
        return [];
    }
}

function build_rrset($hostname, $type, $content, $old_records=[])
{
    $rrset = array(
        'name' => $hostname,
        'type' => $type,
        'ttl' => DEFAULT_TTL
    );
    if ($content == '') {
        $rrset['changetype'] = 'DELETE';
        $rrset['records'] = array();
    } else {
        if ($type === 'TXT') {
            $content = '"' . addslashes($content) . '"';
        }
        $rrset['changetype'] = 'REPLACE';
        $rrset['records'] = array(
            array(
                'content' => $content,
                'disabled' => FALSE
            )
        );
        $rrset['records'] = array_merge($rrset['records'], $old_records);
    }
    return $rrset;
}

function build_txt_removal($hostname, $value, $old_records)
{
    // Remove only the record matching $value, keeping any other TXT records at
    // this name (e.g. the second challenge token in a wildcard + apex issuance).
    $quoted = '"' . addslashes($value) . '"';
    $remaining = array_values(array_filter($old_records, function($rec) use ($quoted) {
        return $rec['content'] !== $quoted;
    }));
    $rrset = array(
        'name' => $hostname,
        'type' => 'TXT',
        'ttl' => DEFAULT_TTL
    );
    if (empty($remaining)) {
        $rrset['changetype'] = 'DELETE';
        $rrset['records'] = array();
    } else {
        $rrset['changetype'] = 'REPLACE';
        $rrset['records'] = $remaining;
    }
    return $rrset;
}

function update_dns($hostnames, $ipv4, $ipv6, $txt, $txt_remove = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-Key: ' . PDNS_API_KEY
    ));

    foreach ($hostnames as $hostname => $info) {
        $rrsets = [];
        if ($ipv4 !== false) {
            $rrsets[] = build_rrset($hostname, 'A', $ipv4);
        }
        if ($ipv6 !== false) {
            $rrsets[] = build_rrset($hostname, 'AAAA', $ipv6);
        }
        if ($txt !== false) {
            $old_records = get_records($hostname, 'TXT', $ch, $info);
            if ($txt_remove !== null) {
                $rrsets[] = build_txt_removal($hostname, $txt_remove, $old_records);
            } else {
                $rrsets[] = build_rrset($hostname, 'TXT', $txt, $old_records);
            }
        }

        $payload = json_encode(array('rrsets' => $rrsets));

        curl_setopt($ch, CURLOPT_URL, PDNS_ZONES_URL . '/' . $info['zone']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($response_code >= 400) {
            curl_close($ch);
            fail($response_code, 'dnserr', "PowerDNS API failed: {$hostname}/{$info['zone']} = IPv4: {$ipv4}, IPv6: {$ipv6}, TXT: {$txt} => {$response}");
        }
    }

    curl_close($ch);
}
