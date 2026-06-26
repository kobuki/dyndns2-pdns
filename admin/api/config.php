<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err(405, 'Method not allowed');
}

json_ok(['base_url' => ADMIN_BASE_URL]);
