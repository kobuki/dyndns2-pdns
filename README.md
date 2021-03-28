# dyndns2-pdns

A thin wrapper around PowerDNS API to implement a basic DynDNS 2 protocol layer, see [[1](#references)] and [[2](#references)].\
Functionality is extended by the ability to update & delete TXT records making this usable for name validation by letsencrypt, see [[3](#references)] and [[4](#refrences)]. In order to support embedded installations of acme clients we also support acmeproxy syntax.

## Installation

* Deploy to any path on your webserver.
* Update `config.inc.php` to match your DB settings.
* Use the scripts in `sql/` to create the database tables.
* Create users in the DB like
  ```sql
  INSERT INTO `users` (`active`,`username`,`password`) VALUES (1,'username','$2y$10$cjaSgipjSg6V/XStI9lx7.LJTo2QcDvxxGhlrnu6uZe8j02xh6Rhm')
  ```
  Note that '$2y$10$cjaSgipjSg6V/XStI9lx7.LJTo2QcDvxxGhlrnu6uZe8j02xh6Rhm' is what you get from `htpasswd -bnBC 10 "" 'password' | tr -d ':'`
  
  Setup the domain names and permissions to use DynDNS update like
  ```sql
  INSERT INTO `hostnames` (`hostname`) VALUES ('web1.mycorp.com.');
  INSERT INTO `hostnames` (`hostname`) VALUES ('.sub.mycorp.com.');
  INSERT INTO `permissions` (`user_id`,`hostname_id`) VALUES (1,1);
  INSERT INTO `permissions` (`user_id`,`hostname_id`) VALUES (1,2);
  ```
  Hostnames need to end with a '.' signifying an FQDN.\
  Note: The values for user_id and hostname_id may need to be adapted, i.e. using the id's of the user and hostnames we created previously.\
  A hostname value starting with '.' (like .sub.mycorp.com) is a wildcard entry, this means the user my update any record that ends with this value in the zone provided.


## Hooks

TBD


## How to use acmeproxy

acmeproxy requires three parameters:
ENDPOINT_URL=https://www.myhost.com/dyn/update.php?acmeproxy=
USERNAME=username
PASSWORD=password


## Examples

Update IPv4:\
`https://username:password@www.myhost.com/dyn/update.php?hostname=web1.mycorp.com&myip=127.0.0.1`

Set TXT record:\
`https://username:password@www.myhost.com/dyn/update.php?hostname=_acme-challenge.db1.sub.mycorp.com&txt=12345678`

Clear (and remove) TXT record:\
`https://username:password@www.myhost.com/dyn/update.php?hostname=_acme-challenge.db1.sub.mycorp.com&txt=`

Set TXT record via acmeproxy:\
`https://username:password@www.myhost.com/dyn/update.php?acmeproxy=/present`
`{"fqdn":"_acme-challenge.db1.sub.mycorp.com","value":"12345678"}`

Clear (and remove) TXT record via acmeproxy:\
`https://username:password@www.myhost.com/dyn/update.php?acmeproxy=/cleanup`
`{"fqdn":"_acme-challenge.db1.sub.mycorp.com","value":"12345678"}`


## References

[1] https://help.dyn.com/remote-access-api/perform-update/ \
[2] https://help.dyn.com/remote-access-api/return-codes/ \
[3] https://github.com/BastiG/certbot-dns-webservice \
[4] https://github.com/BastiG/acme-sh-dyndns2
