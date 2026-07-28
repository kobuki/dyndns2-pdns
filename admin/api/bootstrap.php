<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

// Set up Eloquent capsule
$capsule = new Capsule;
$connection = [
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'database'  => DB_NAME,
    'username'  => DB_USERNAME,
    'password'  => DB_PASSWORD,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
];
if (defined('DB_SOCKET') && DB_SOCKET !== '') {
    $connection['unix_socket'] = DB_SOCKET;
}
$capsule->addConnection($connection);
$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// JSON content type
header('Content-Type: application/json; charset=utf-8');

// Helper functions
function json_ok($data = null, $code = 200) {
    http_response_code($code);
    if ($data !== null) {
        echo json_encode($data);
    } else {
        echo json_encode(['ok' => true]);
    }
    exit;
}

function json_err($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function get_body() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Silently strip leading/trailing whitespace; reject any internal whitespace.
function clean_no_space($value, $label) {
    $value = trim((string)$value);
    if (preg_match('/\s/u', $value)) {
        json_err(400, "$label must not contain spaces");
    }
    return $value;
}

// Canonical DNS names are stored with a single trailing dot.
function with_trailing_dot($value) {
    return $value === '' ? '' : rtrim($value, '.') . '.';
}

// --- Eloquent Models ---

class User extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'users';
    protected $fillable = ['username', 'password', 'active'];
    protected $hidden = ['password'];
    public $timestamps = false;

    public function permissions() {
        return $this->hasMany(Permission::class, 'user_id');
    }
}

class Hostname extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'hostnames';
    protected $fillable = ['hostname', 'domain', 'last_updated', 'last_ipv4', 'last_ipv6'];
    public $timestamps = false;

    public function permissions() {
        return $this->hasMany(Permission::class, 'hostname_id');
    }
}

class Permission extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'permissions';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['user_id', 'hostname_id'];
}

class Changelog extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'changelog';
    public $timestamps = false;
    protected $primaryKey = 'id';
}
