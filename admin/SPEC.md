# Admin UI — Specification

## Overview

A single-page application (SPA) admin interface for managing users, hostnames, permissions, and viewing the changelog. Entirely self-contained under `admin/`, independent from the DDNS service. Authentication is handled externally by the web server (HTTP Basic Auth) — no login screen, no session management.

## Technology stack

**Frontend**
- Vue 3 + Vue Router
- Vuestic Admin — component library and layout
- Axios for API calls
- Vite for build

**Backend**
- PHP, JSON API only (no HTML rendering)
- Eloquent ORM standalone (`illuminate/database` capsule)
- Composer for dependency management

**Directory layout**
```
admin/
  api/              PHP API endpoints
    users.php
    hostnames.php
    permissions.php
    changelog.php
    config.php
  src/              Vue SPA source
    views/
    components/
    router/
  dist/             Vite build output (web-accessible)
  vendor/           Composer packages
  composer.json
  package.json
  vite.config.js
  config.php        DB connection settings
```

## Layout

Persistent left sidebar with navigation icons + labels:
- Dashboard
- Users
- Hostnames
- Permissions
- Changelog
- URL Generator

Top bar:
- App name / logo left
- Informational line right: total users, total hostnames, last changelog entry timestamp

Content area: right of sidebar, full height, scrollable.

## Views

### Dashboard

Summary cards: total active users, total hostnames, total permissions, changelog entries (last 24h).

Recent activity table: last 20 changelog rows (timestamp, username, hostname, operation, record_type, record_content, client_ip).

---

### Users

Sortable, filterable, searchable table.

| Column | Notes |
|---|---|
| ID | |
| Username | |
| Active | Inline toggle |
| Actions | Edit, delete |

**Add / Edit modal**
- Username field
- Password field with a "Generate" button — creates a random 20-char password from all printable ASCII except `'  " $ ` \ & # + = %` — safe for use in shell scripts without escaping inside single or double quotes, and safe to paste raw into a URL query string without percent-encoding, displays it in plaintext for copy-paste, eye toggle to show/hide
- Active toggle
- On save, plaintext password is sent to the backend and hashed with `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10])`
- On edit, leaving password blank keeps the existing hash

**Delete**: confirmation dialog; blocked if the user has permissions (count shown).

---

### Hostnames

Sortable, filterable, searchable table.

| Column | Notes |
|---|---|
| ID | |
| Hostname | |
| Domain | |
| Last updated | |
| Last client IP | |
| Actions | Edit, delete |

**Add / Edit modal**
- Hostname field — must end with `.` (enforced client-side)
- Domain field with auto-guess: when the hostname field loses focus, the frontend calls the guess endpoint, which SOA-walks the hostname from the backend using `dns_get_record()`, stepping up labels until a SOA record is found. This correctly handles 2-level and 3-level TLDs without heuristics. The result pre-populates the domain field with a "guessed" badge; user can override. Silent fallback if DNS times out.
- `last_updated` and `last_client_ip` shown read-only on edit

**Delete**: confirmation dialog; blocked if hostname has permissions (count shown).

---

### Permissions

**Two-panel layout** (default):
- Left panel: user list (click to select)
- Right panel: all hostnames as a checklist, checked = permission granted

Checking or unchecking a hostname immediately POSTs or DELETEs the permission (optimistic UI with error rollback on failure).

**Matrix view** (toggle): users as rows, hostnames as columns, checkboxes at intersections. Useful when both counts are small.

Wildcard hostnames (starting with `.`) shown with a visual indicator.

---

### Changelog

Read-only sortable, paginated table (50 rows per page default).

| Column |
|---|
| Timestamp |
| Client IP |
| Username |
| Hostname |
| Operation |
| Record type |
| Record content |

**Filter bar**: date range picker, username dropdown, hostname text search, operation (all / set / add / delete), record type (all / A / AAAA / TXT).

**Export**: "Export CSV" button streams the filtered result set from the backend.

---

### URL Generator

Generates the base64 short-URL token for the single-URL update method.

**Form**
- User dropdown (active users only)
- Hostname dropdown (filtered to hostnames the selected user has permission for, populated from the permissions API)
- Password field — same UI as the user form: manual entry or "Generate" button producing a random 20-char `[A-Za-z0-9]` password, eye toggle to show/hide. Password is never sent to the backend unless "Force update" is checked.
- "Force update" checkbox — when checked, saves the entered password as the user's new hash in the DB (via `PUT /admin/api/users.php?id=N`) before generating the token. Useful when issuing a fresh token with a new password.
- "Generate URL" button

**Output** (computed entirely client-side)
- Token: `base64(user_id + ':' + hostname_id + ':' + password)`
- Full URL: `{base_url}/{token}`
- Copy button next to each field
- Note: *"This URL auto-detects the caller's IP. Guard it like a password."*

The base URL is hardcoded in `admin/config.php`.

## API endpoints

All endpoints return JSON. HTTP status codes used semantically (200, 201, 400, 404, 409, 500).

```
GET    /admin/api/users.php                  list users
POST   /admin/api/users.php                  create user
PUT    /admin/api/users.php?id=N             update user
DELETE /admin/api/users.php?id=N             delete user

GET    /admin/api/hostnames.php              list hostnames
POST   /admin/api/hostnames.php              create hostname
PUT    /admin/api/hostnames.php?id=N         update hostname
DELETE /admin/api/hostnames.php?id=N         delete hostname
GET    /admin/api/hostnames.php?guess=fqdn   SOA-walk to guess zone for a hostname

GET    /admin/api/permissions.php?user_id=N  list hostname IDs permitted for user
POST   /admin/api/permissions.php            grant permission  { user_id, hostname_id }
DELETE /admin/api/permissions.php            revoke permission { user_id, hostname_id }

GET    /admin/api/changelog.php              list changelog (filter params: from, to,
                                             username, hostname, operation, record_type,
                                             page, per_page)
GET    /admin/api/changelog.php?export=csv   stream CSV of filtered results

GET    /admin/api/config.php                 return { base_url }
```

## Web server configuration

The admin lives outside the DDNS `public/` document root, so both backends need
explicit path mappings rather than relying on the document root.

### nginx

```nginx
location /admin/api/ {
    auth_basic "Admin";
    auth_basic_user_file /path/to/.htpasswd;

    alias /path/to/dyndns2-pdns/admin/api/;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /path/to/dyndns2-pdns/admin/api/$fastcgi_script_name;
        include fastcgi_params;
    }
}

location /admin/dist/ {
    auth_basic "Admin";
    auth_basic_user_file /path/to/.htpasswd;

    alias /path/to/dyndns2-pdns/admin/dist/;
    try_files $uri /admin/dist/index.html;
}
```

### Apache

```apache
Alias /admin/dist/ /path/to/dyndns2-pdns/admin/dist/
Alias /admin/api/  /path/to/dyndns2-pdns/admin/api/

<Directory "/path/to/dyndns2-pdns/admin/dist">
    AuthType Basic
    AuthName "Admin"
    AuthUserFile /path/to/.htpasswd
    Require valid-user

    FallbackResource /admin/dist/index.html
</Directory>

<Directory "/path/to/dyndns2-pdns/admin/api">
    AuthType Basic
    AuthName "Admin"
    AuthUserFile /path/to/.htpasswd
    Require valid-user

    <FilesMatch ".+\.php$">
        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"
    </FilesMatch>
</Directory>
```

## Decisions

1. **Build output**: `dist/` is committed to the repo — no Node required on the target server.
2. **Base URL**: hardcoded in `admin/config.php`.
3. **Changelog CSV export**: unlimited rows streamed.
4. **Config**: `admin/config.php` duplicates the DB constants independently from `inc/config.inc.php`.
