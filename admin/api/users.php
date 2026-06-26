<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        $users = User::select('id', 'username', 'active')->orderBy('id')->get();
        json_ok($users);

    case 'POST':
        $body = get_body();
        if (empty($body['username']) || empty($body['password'])) {
            json_err(400, 'username and password are required');
        }
        if (User::where('username', $body['username'])->exists()) {
            json_err(409, 'Username already exists');
        }
        $user = User::create([
            'username' => $body['username'],
            'password' => password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 10]),
            'active'   => isset($body['active']) ? (int)(bool)$body['active'] : 1,
        ]);
        json_ok(User::select('id', 'username', 'active')->find($user->id), 201);

    case 'PUT':
        if (!$id) json_err(400, 'id is required');
        $user = User::find($id);
        if (!$user) json_err(404, 'User not found');
        $body = get_body();
        if (empty($body['username'])) {
            json_err(400, 'username is required');
        }
        // Check username uniqueness (excluding self)
        if (User::where('username', $body['username'])->where('id', '!=', $id)->exists()) {
            json_err(409, 'Username already exists');
        }
        $update = [
            'username' => $body['username'],
            'active'   => isset($body['active']) ? (int)(bool)$body['active'] : $user->active,
        ];
        if (!empty($body['password'])) {
            $update['password'] = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        }
        $user->update($update);
        json_ok(User::select('id', 'username', 'active')->find($id));

    case 'DELETE':
        if (!$id) json_err(400, 'id is required');
        $user = User::find($id);
        if (!$user) json_err(404, 'User not found');
        $count = Permission::where('user_id', $id)->count();
        if ($count > 0) {
            json_err(409, "Cannot delete user: has {$count} permission(s)");
        }
        $user->delete();
        json_ok(['deleted' => true]);

    default:
        json_err(405, 'Method not allowed');
}
