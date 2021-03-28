<?php

include_once('config.inc.php');

function build_rrset($hostname, $type, $content) {
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
                'disabled' => FALSE,
                'priority' => 0
            )
        );
    }
    return $rrset;
}
