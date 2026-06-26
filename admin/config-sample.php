<?php

// Admin interface configuration
// Copy this file to config.php and fill in the values.

// --- Database ---
// For local socket connection, define DB_SOCKET. For TCP, omit it or comment it out.
// const DB_SOCKET = '/run/mysqld/mysqld.sock';
const DB_HOST     = '127.0.0.1';
const DB_NAME     = 'dyndns';
const DB_USERNAME = 'dyndns';
const DB_PASSWORD = '<fill in>';

// --- Admin base URL (used for URL generator) ---
// No trailing slash
const ADMIN_BASE_URL = 'https://ddns.example.com';
