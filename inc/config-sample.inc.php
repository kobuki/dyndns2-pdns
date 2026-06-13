<?php

// Backend: 'pdns' or 'cloudflare'
const BACKEND = 'pdns';

// --- PowerDNS backend (BACKEND = 'pdns') ---
// The 'domain' column in the hostnames table must hold the zone name (e.g. "example.com.")
const PDNS_API_KEY = '<fill in>';
const PDNS_ZONES_URL = 'http://127.0.0.1:8081/api/v1/servers/localhost/zones';

// --- Cloudflare backend (BACKEND = 'cloudflare') ---
// The 'domain' column in the hostnames table must hold the zone ID (32-char hex string from the CF dashboard)
const CF_API_TOKEN = '<fill in>';

// --- Database ---
const DB_SOCKET   = '/run/mysqld/mysqld.sock';
const DB_HOST     = '127.0.0.1';
const DB_NAME     = 'dyndns';
const DB_USERNAME = 'dyndns';
const DB_PASSWORD = '<fill in>';

const DB_URI = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4';
// const DB_URI = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

// --- General ---
const MAX_UPDATE_HOSTNAMES = 20;
const DEFAULT_TTL = 60;
