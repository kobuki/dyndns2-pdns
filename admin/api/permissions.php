<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        if (!$user_id) json_err(400, 'user_id is required');
        $perms = Permission::where('user_id', $user_id)->pluck('hostname_id');
        json_ok($perms);

    case 'POST':
        $body = get_body();
        if (!isset($body['user_id']) || !isset($body['hostname_id'])) {
            json_err(400, 'user_id and hostname_id are required');
        }
        $user_id    = (int)$body['user_id'];
        $hostname_id = (int)$body['hostname_id'];
        if (!User::find($user_id)) json_err(404, 'User not found');
        if (!Hostname::find($hostname_id)) json_err(404, 'Hostname not found');
        // Ignore duplicate
        $exists = Permission::where('user_id', $user_id)
                             ->where('hostname_id', $hostname_id)
                             ->exists();
        if (!$exists) {
            Permission::create(['user_id' => $user_id, 'hostname_id' => $hostname_id]);
        }
        json_ok(['granted' => true], 201);

    case 'DELETE':
        $body = get_body();
        if (!isset($body['user_id']) || !isset($body['hostname_id'])) {
            json_err(400, 'user_id and hostname_id are required');
        }
        $user_id     = (int)$body['user_id'];
        $hostname_id = (int)$body['hostname_id'];
        Permission::where('user_id', $user_id)
                  ->where('hostname_id', $hostname_id)
                  ->delete();
        json_ok(['revoked' => true]);

    default:
        json_err(405, 'Method not allowed');
}
