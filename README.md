# dyndns2-pdns

A thin PHP wrapper implementing the DynDNS 2 protocol [[1](#references), [2](#references)] on top of a DNS backend. Supports updating A, AAAA, and TXT records, with ACME proxy compatibility for automated Let's Encrypt DNS-01 challenges [[3](#references), [4](#references)].

Based on the original work by [BastiG](https://github.com/BastiG).

## Backends

Select the active backend via the `BACKEND` constant in `config.inc.php`:

| Backend | Value | Requires |
|---|---|---|
| PowerDNS | `'pdns'` | PDNS API key and URL |
| Cloudflare | `'cloudflare'` | CF API token (Zone/Read + Zone/DNS/Edit, scoped to the zone) |

Only one backend is active at a time. Both backends use the same update endpoint and database schema. The `domain` column in the `hostnames` table holds the zone name with a trailing dot (e.g. `example.com.`) for both backends.

**Cloudflare note:** the zone ID is resolved automatically from the zone name via the API and cached for the duration of the request — no manual zone ID configuration needed.


## Installation

1. Deploy the project to any path on your server (only the `public/` directory needs to be web-accessible).
2. Copy `inc/config-sample.inc.php` to `inc/config.inc.php` and fill in the values for your chosen backend and database.
3. Create the database schema using `sql/dyndns.sql`.
4. Configure your web server (see below).


## Web server configuration

Only the essentials are shown. DynDNS2 clients typically call `/nic/update` — the rewrite rules below map that to the actual endpoint.

### Apache

```apache
<VirtualHost *:80>
    ServerName ddns.example.com
    DocumentRoot /path/to/dyndns2-pdns/public

    <FilesMatch ".+\.php$">
        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"
    </FilesMatch>

    RewriteEngine On
    RewriteRule ^(/nic)?/update(\.php)?$ /update.php [L]

</VirtualHost>
```

### Admin UI — separate vhost (Apache)

The admin is served from its own vhost with `DocumentRoot` pointing to `admin/dist/`.
The API lives at `/api/` within the same vhost.

```apache
<VirtualHost *:80>
    ServerName admin.ddns.example.com
    DocumentRoot /path/to/dyndns2-pdns/admin/dist

    <FilesMatch ".+\.php$">
        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"
    </FilesMatch>

    <Directory "/path/to/dyndns2-pdns/admin/dist">
        AuthType Basic
        AuthName "Admin"
        AuthUserFile /path/to/.htpasswd
        Require valid-user

        FallbackResource /index.html
    </Directory>

    Alias /api/ /path/to/dyndns2-pdns/admin/api/

    <Directory "/path/to/dyndns2-pdns/admin/api">
        AuthType Basic
        AuthName "Admin"
        AuthUserFile /path/to/.htpasswd
        Require valid-user
    </Directory>
</VirtualHost>
```

### Admin UI — separate vhost (nginx)

```nginx
server {
    server_name admin.ddns.example.com;
    root /path/to/dyndns2-pdns/admin/dist;

    auth_basic "Admin";
    auth_basic_user_file /path/to/.htpasswd;

    location /api/ {
        alias /path/to/dyndns2-pdns/admin/api/;

        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /path/to/dyndns2-pdns/admin/api/$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    location / {
        try_files $uri /index.html;
    }
}
```

If the service sits behind a reverse proxy, ensure it passes `X-Real-IP` or `X-Forwarded-For` so client IPs are logged correctly.


## Configuration

```php
// Backend: 'pdns' or 'cloudflare'
const BACKEND = 'pdns';

const PDNS_API_KEY   = '<fill in>';
const PDNS_ZONES_URL = 'http://127.0.0.1:8081/api/v1/servers/localhost/zones';

const CF_API_TOKEN = '<fill in>';

const DB_URI      = 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=dyndns;charset=utf8mb4';
const DB_USERNAME = 'dyndns';
const DB_PASSWORD = '<fill in>';

const MAX_UPDATE_HOSTNAMES = 20;
const DEFAULT_TTL          = 60;
```


## Database setup

### Users

Passwords are stored as bcrypt hashes. Generate one with:

```sh
htpasswd -bnBC 10 "" 'yourpassword' | tr -d ':'
```

```sql
INSERT INTO `users` (`active`, `username`, `password`)
VALUES (1, 'username', '$2y$10$...');
```

### Hostnames and permissions

Hostname values must end with `.` (FQDN). A hostname starting with `.` is a wildcard — the user may update any record ending with that suffix within the configured zone.

```sql
INSERT INTO `hostnames` (`hostname`, `domain`) VALUES ('web1.example.com.', 'example.com.');
INSERT INTO `hostnames` (`hostname`, `domain`) VALUES ('.dyn.example.com.', 'example.com.');

INSERT INTO `permissions` (`user_id`, `hostname_id`) VALUES (1, 1);
INSERT INTO `permissions` (`user_id`, `hostname_id`) VALUES (1, 2);
```


## Authentication

Three methods are supported. See `examples.txt` for working curl examples of each.

**HTTP Basic Auth** (standard DynDNS2 client behaviour):
```
https://username:password@ddns.example.com/update?...
```

**Query string credentials:**
```
https://ddns.example.com/update?username=user&password=pass&...
```
(`user` is accepted as an alias for `username`)

**Base64 URL token** — encodes `user_id:hostname_id:password` as base64 and uses it as the URL path. IP defaults to auto-detect when using this method:
```sh
TOKEN=$(printf '1:1:yourpassword' | base64 -w0)
curl "https://ddns.example.com/$TOKEN"
```


## Update endpoint

`GET /update`

### Parameters

| Parameter | Description |
|---|---|
| `hostname` | FQDN to update. Comma-separate for multiple (up to `MAX_UPDATE_HOSTNAMES`). Prefix with `_` for underscore labels (e.g. `_acme-challenge.example.com`). |
| `myip` | IP address(es) to set. Comma-separate to set both A and AAAA in one request. Pass `auto` or omit the value to detect the caller's IP. Pass empty (`myip=`) to delete both A and AAAA records. |
| `txt` | TXT record value to append. Pass empty (`txt=`) to delete all TXT records for the hostname. |

`myip` and `txt` can be combined in a single request.

### IP records (A / AAAA)

```sh
# Set A record
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&myip=1.2.3.4"

# Set AAAA record
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&myip=2001:db8::1"

# Set both A and AAAA
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&myip=1.2.3.4,2001:db8::1"

# Auto-detect caller IP
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&myip=auto"

# Short URL — credentials and hostname embedded as a base64 token, IP auto-detected
# Token encodes user_id:hostname_id:password  (e.g. "1:1:yourpassword")
TOKEN=$(printf '1:1:yourpassword' | base64 -w0)
curl "https://ddns.example.com/$TOKEN"

# Delete A and AAAA records
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&myip="
```

### TXT records

TXT records are always appended — existing records are preserved. This allows multiple simultaneous ACME challenge tokens for the same hostname. Passing an empty value deletes all TXT records for the hostname.

```sh
# Add TXT record
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&txt=hello"

# Delete all TXT records
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com&txt="
```

### Multiple hostnames

```sh
curl -u user:pass "https://ddns.example.com/update?hostname=home.example.com,office.example.com&myip=1.2.3.4"
```


## ACME proxy

The endpoint is compatible with the acmeproxy protocol [[3](#references), [4](#references)] for DNS-01 challenge automation.

```
?acmeproxy=present   — add the challenge TXT record
?acmeproxy=cleanup   — remove the challenge TXT record
```

The JSON body must contain `fqdn` and `value`. Authentication uses the same methods as the standard endpoint.

```sh
# Present challenge
curl -u user:pass -X POST \
  -H 'Content-Type: application/json' \
  -d '{"fqdn":"_acme-challenge.home.example.com.","value":"TOKEN"}' \
  "https://ddns.example.com/update?acmeproxy=present"

# Clean up
curl -u user:pass -X POST \
  -H 'Content-Type: application/json' \
  -d '{"fqdn":"_acme-challenge.home.example.com.","value":"TOKEN"}' \
  "https://ddns.example.com/update?acmeproxy=cleanup"
```

More curl examples, including error cases, are in `examples.txt`.


## Response codes

| Response | Meaning |
|---|---|
| `good` | Update successful |
| `nochg` | No change requested (no `myip` or `txt` parameter supplied) |
| `nohost` | Hostname not found or not permitted for this user |
| `notfqdn` | Invalid or missing hostname |
| `numhosts` | Too many hostnames in one request |
| `dnserr` | Backend DNS update failed |
| `dberror` | Database error |
| HTTP 401 | Authentication failed |


## Logging

Every successful update writes a row to the `changelog` table:

| Column | Description |
|---|---|
| `timestamp` | When the update occurred |
| `client_ip` | IP address of the connecting client |
| `username` | Authenticated user |
| `hostname` | Updated hostname |
| `operation` | `set` (A/AAAA upsert), `add` (TXT append), or `delete` |
| `record_type` | `A`, `AAAA`, or `TXT` |
| `record_content` | The value written (empty string for deletions) |

The `hostnames` table also tracks `last_updated` (timestamp of the last successful update) and `last_client_ip` (the client IP that performed it).

Client IP is detected from `X-Real-IP` or `X-Forwarded-For` (first token) when behind a reverse proxy, falling back to `REMOTE_ADDR`.


## References

[1] https://help.dyn.com/remote-access-api/perform-update/ \
[2] https://help.dyn.com/remote-access-api/return-codes/ \
[3] https://github.com/BastiG/certbot-dns-webservice \
[4] https://github.com/BastiG/acme-sh-dyndns2
