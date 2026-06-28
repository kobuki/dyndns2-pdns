<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// SOA-walk guess endpoint
if ($method === 'GET' && isset($_GET['guess'])) {
    $fqdn = trim($_GET['guess'], '.');
    if (empty($fqdn)) {
        json_ok(null);
    }
    // Step up labels until we find a SOA record
    $parts = explode('.', $fqdn);
    $found = null;
    // We need at least 2 labels for a valid zone
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $candidate = implode('.', array_slice($parts, $i));
        $records = @dns_get_record($candidate, DNS_SOA);
        if (!empty($records)) {
            $found = $candidate . '.';
            break;
        }
    }
    if ($found) {
        json_ok(['domain' => $found]);
    } else {
        // Silent empty response
        echo '{}';
        exit;
    }
}

switch ($method) {
    case 'GET':
        $hostnames = Hostname::orderBy('id')->get();
        json_ok($hostnames);

    case 'POST':
        $body = get_body();
        if (empty($body['hostname']) || empty($body['domain'])) {
            json_err(400, 'hostname and domain are required');
        }
        if (Hostname::where('hostname', $body['hostname'])->exists()) {
            json_err(409, 'Hostname already exists');
        }
        $hostname = Hostname::create([
            'hostname'     => $body['hostname'],
            'domain'       => $body['domain'],
            'last_updated' => null,
        ]);
        json_ok(Hostname::find($hostname->id), 201);

    case 'PUT':
        if (!$id) json_err(400, 'id is required');
        $hostname = Hostname::find($id);
        if (!$hostname) json_err(404, 'Hostname not found');
        $body = get_body();
        if (empty($body['hostname']) || empty($body['domain'])) {
            json_err(400, 'hostname and domain are required');
        }
        // Check uniqueness (excluding self)
        if (Hostname::where('hostname', $body['hostname'])->where('id', '!=', $id)->exists()) {
            json_err(409, 'Hostname already exists');
        }
        $hostname->update([
            'hostname' => $body['hostname'],
            'domain'   => $body['domain'],
        ]);
        json_ok(Hostname::find($id));

    case 'DELETE':
        if (!$id) json_err(400, 'id is required');
        $hostname = Hostname::find($id);
        if (!$hostname) json_err(404, 'Hostname not found');
        $count = Permission::where('hostname_id', $id)->count();
        if ($count > 0) {
            json_err(409, "Cannot delete hostname: has {$count} permission(s)");
        }
        $hostname->delete();
        json_ok(['deleted' => true]);

    default:
        json_err(405, 'Method not allowed');
}
