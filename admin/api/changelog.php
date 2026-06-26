<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    json_err(405, 'Method not allowed');
}

// Build filtered query
$query = Changelog::orderBy('timestamp', 'desc');

if (!empty($_GET['from'])) {
    $query->where('timestamp', '>=', $_GET['from']);
}
if (!empty($_GET['to'])) {
    $query->where('timestamp', '<=', $_GET['to'] . ' 23:59:59');
}
if (!empty($_GET['username'])) {
    $query->where('username', $_GET['username']);
}
if (!empty($_GET['hostname'])) {
    $query->where('hostname', 'like', '%' . $_GET['hostname'] . '%');
}
if (!empty($_GET['operation'])) {
    $query->where('operation', $_GET['operation']);
}
if (!empty($_GET['record_type'])) {
    $query->where('record_type', $_GET['record_type']);
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Override content-type for CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="changelog.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'timestamp', 'client_ip', 'username', 'hostname', 'operation', 'record_type', 'record_content']);
    $query->orderBy('id', 'asc')->chunk(500, function($rows) use ($out) {
        foreach ($rows as $row) {
            fputcsv($out, [
                $row->id,
                $row->timestamp,
                $row->client_ip,
                $row->username,
                $row->hostname,
                $row->operation,
                $row->record_type,
                $row->record_content,
            ]);
        }
    });
    fclose($out);
    exit;
}

// Paginated JSON response
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

$total   = $query->count();
$rows    = (clone $query)->skip(($page - 1) * $per_page)->take($per_page)->get();

json_ok([
    'data'       => $rows,
    'total'      => $total,
    'page'       => $page,
    'per_page'   => $per_page,
    'last_page'  => (int)ceil($total / $per_page),
]);
