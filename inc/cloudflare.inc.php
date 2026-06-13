<?php

include_once('config.inc.php');
include_once('common.inc.php');

const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

function cf_request($ch, $method, $path, $body = null)
{
    curl_setopt($ch, CURLOPT_URL, CF_API_BASE . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body !== null ? json_encode($body) : null);
    $response = curl_exec($ch);
    return [
        'code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'body' => json_decode($response, true)
    ];
}

function cf_get_zone_id($ch, $domain)
{
    static $cache = [];
    if (isset($cache[$domain])) return $cache[$domain];

    $result = cf_request($ch, 'GET', '/zones?name=' . urlencode(rtrim($domain, '.')));
    if ($result['code'] >= 400 || empty($result['body']['result'])) {
        curl_close($ch);
        fail($result['code'] ?: 404, 'dnserr', "Cloudflare: zone not found for domain {$domain}");
    }
    $cache[$domain] = $result['body']['result'][0]['id'];
    return $cache[$domain];
}

function cf_get_records($ch, $zone_id, $name, $type)
{
    $result = cf_request($ch, 'GET', "/zones/{$zone_id}/dns_records?name=" . urlencode($name) . "&type={$type}");
    if ($result['code'] >= 400) {
        curl_close($ch);
        fail($result['code'], 'dnserr', "Cloudflare API error getting {$type} records for {$name}");
    }
    return $result['body']['result'] ?? [];
}

function cf_upsert_address($ch, $zone_id, $name, $type, $ip)
{
    $records = cf_get_records($ch, $zone_id, $name, $type);
    $payload = ['type' => $type, 'name' => $name, 'content' => $ip, 'ttl' => DEFAULT_TTL];

    if (empty($records)) {
        $result = cf_request($ch, 'POST', "/zones/{$zone_id}/dns_records", $payload);
    } else {
        $result = cf_request($ch, 'PUT', "/zones/{$zone_id}/dns_records/{$records[0]['id']}", $payload);
        for ($i = 1; $i < count($records); $i++) {
            cf_request($ch, 'DELETE', "/zones/{$zone_id}/dns_records/{$records[$i]['id']}");
        }
    }

    if ($result['code'] >= 400) {
        curl_close($ch);
        fail($result['code'], 'dnserr', "Cloudflare API error updating {$type} for {$name}: " . json_encode($result['body']));
    }
}

function cf_delete_records($ch, $zone_id, $name, $type)
{
    foreach (cf_get_records($ch, $zone_id, $name, $type) as $record) {
        $result = cf_request($ch, 'DELETE', "/zones/{$zone_id}/dns_records/{$record['id']}");
        if ($result['code'] >= 400) {
            curl_close($ch);
            fail($result['code'], 'dnserr', "Cloudflare API error deleting {$type} for {$name}");
        }
    }
}

function update_dns($hostnames, $ipv4, $ipv6, $txt)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . CF_API_TOKEN
    ]);

    foreach ($hostnames as $hostname => $info) {
        $zone_id = cf_get_zone_id($ch, $info['zone']);
        $name = rtrim($hostname, '.');

        if ($ipv4 !== false) {
            if ($ipv4 === '') {
                cf_delete_records($ch, $zone_id, $name, 'A');
            } else {
                cf_upsert_address($ch, $zone_id, $name, 'A', $ipv4);
            }
        }

        if ($ipv6 !== false) {
            if ($ipv6 === '') {
                cf_delete_records($ch, $zone_id, $name, 'AAAA');
            } else {
                cf_upsert_address($ch, $zone_id, $name, 'AAAA', $ipv6);
            }
        }

        if ($txt !== false) {
            if ($txt === '') {
                cf_delete_records($ch, $zone_id, $name, 'TXT');
            } else {
                $result = cf_request($ch, 'POST', "/zones/{$zone_id}/dns_records", [
                    'type' => 'TXT', 'name' => $name, 'content' => $txt, 'ttl' => DEFAULT_TTL
                ]);
                if ($result['code'] >= 400) {
                    curl_close($ch);
                    fail($result['code'], 'dnserr', "Cloudflare API error adding TXT for {$name}: " . json_encode($result['body']));
                }
            }
        }
    }

    curl_close($ch);
}
