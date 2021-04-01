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
