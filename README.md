# Laravel cPanel

[![tests](https://github.com/beziworld/laravel-cpanel/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-cpanel/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-cpanel.svg)](https://packagist.org/packages/beziworld/laravel-cpanel)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-cpanel.svg)](LICENSE.md)

Laravel client for [cPanel & WHM](https://cpanel.net): WHM API 1 for accounts, packages and single sign-on, UAPI
for domains, DNS, e-mail, MySQL, SSL, FTP, files, backups and statistics, and API 2 for cron jobs. Works with
any number of servers and accounts.

[Polska wersja dokumentacji](README.pl.md)

## Features

- Multiple named connections (like database connections) plus `Cpanel::using([...])` for servers stored in your DB
- Two connection types: `whm` (root or reseller token, port 2087) and `cpanel` (account token, port 2083)
- `forUser('john')` on a WHM connection runs UAPI calls as that account through `uapi_cpanel`, so one reseller
  token manages every account
- Resources: `accounts`, `packages`, `sessions`, `domains`, `dns`, `email`, `mysql`, `ssl`, `ftp`, `cron`, `files`,
  `backups`, `stats`
- Typed, immutable DTOs (`Account`, `HostingPackage`, `EmailAccount`, `Database`, `DnsRecord`, `UserSession`) and
  generic `whm()`, `uapi()` and `api2()` methods for every other function
- Reliable error detection: WHM `metadata.result = 0` and UAPI `status = 0` become an `ApiException` with the
  messages returned by cPanel
- Writes are sent as POST forms (passwords never appear in URLs) and are never retried automatically
- Configurable TLS verification for self-signed panel certificates
- `php artisan cpanel:check` to test a connection
- PHP 8.3+, Laravel 12 and 13, PHPStan level 8

## Installation

```bash
composer require beziworld/laravel-cpanel
php artisan vendor:publish --tag=cpanel-config   # optional
```

```dotenv
CPANEL_TYPE=whm                       # whm (root/reseller) or cpanel (single account)
CPANEL_HOST=server.example.com        # port 2087 or 2083 is added automatically
CPANEL_USERNAME=root
CPANEL_TOKEN=U7HMR63FGY292DQZ4H5BFH16JLYMO01M
CPANEL_VERIFY=true                    # false or a CA bundle path for self-signed certificates
```

```bash
php artisan cpanel:check
```

Create the token in WHM (*Development > Manage API Tokens*) or in cPanel (*Security > Manage API Tokens*), limit
its ACLs to what the application needs and restrict it to the application's IP address. The package sends
`Authorization: whm user:token` or `Authorization: cpanel user:token`.

## Usage examples

### Provision a hosting account after an order is paid

```php
use Beziworld\Cpanel\Data\AccountRequest;
use Beziworld\Cpanel\Exceptions\ApiException;
use Beziworld\Cpanel\Facades\Cpanel;

class ProvisionHosting implements ShouldQueue
{
    public function handle(): void
    {
        try {
            Cpanel::accounts()->create(new AccountRequest(
                username: $this->order->username,          // up to 16 lowercase letters and digits
                domain: $this->order->domain,
                password: $password = Str::password(16),
                package: $this->order->plan->package,       // WHM package name
                contactEmail: $this->order->email,
            ));
        } catch (ApiException $e) {
            // e.g. "cPanel createacct failed: Sorry, a group for that username already exists."
            $this->fail($e);

            return;
        }

        $account = Cpanel::forUser($this->order->username);     // UAPI as the new account
        $account->email()->create('office@' . $this->order->domain, Str::password(16), quotaMb: 2048);
        $account->ssl()->startAutoSsl();

        Mail::to($this->order->email)->send(new HostingReady($this->order, $password));
    }
}
```

### Suspend, upgrade, terminate

```php
Cpanel::accounts()->suspend('john', 'Unpaid invoice FV/2026/11/01');
Cpanel::accounts()->unsuspend('john');
Cpanel::accounts()->changePackage('john', 'business');
Cpanel::accounts()->modify('john', ['QUOTA' => 20480, 'MAXPOP' => 100]);
Cpanel::accounts()->changePassword('john', $newPassword, updateDatabasePassword: true);
Cpanel::accounts()->remove('john');                    // cannot be undone

$account = Cpanel::accounts()->find('john');           // ?Account
$account?->suspended; $account?->package; $account?->diskUsed;

foreach (Cpanel::accounts()->list('reseller1', 'owner') as $account) { /* ... */ }
foreach (Cpanel::packages()->list() as $package) { $package->name; $package->diskQuota; }
```

### Single sign-on into cPanel or Webmail

```php
return redirect()->away(Cpanel::sessions()->cpanelUrl($user->cpanel_username, app: 'Email_Accounts'));

$session = Cpanel::sessions()->create('john', service: 'cpaneld');   // UserSession: url, expiresAt
$webmail = Cpanel::sessions()->webmailUrl('office@example.com');
```

### Domains

```php
$client = Cpanel::forUser('john');

$client->domains()->list();       // ['main' => 'example.com', 'addon' => [...], 'sub' => [...], 'parked' => [...]]
$client->domains()->addSubdomain('blog', 'example.com', 'public_html/blog');
$client->domains()->addAddon('shop.com', 'shop', 'public_html/shop.com');
$client->domains()->park('example.net');
$client->domains()->removeSubdomain('blog.example.com');
```

### DNS records

```php
$dns = Cpanel::forUser('john')->dns();

foreach ($dns->records('example.com') as $record) {
    echo "{$record->lineIndex} {$record->name} {$record->type} {$record->content()}";
}

$dns->add('example.com', '_acme-challenge', 'TXT', 'token-value', ttl: 300);
$dns->add('example.com', '@', 'MX', ['10', 'mx1.example.com.']);
$dns->edit('example.com', lineIndex: 24, name: 'www', type: 'A', data: '192.0.2.10');
$dns->remove('example.com', 24, 25);

// Root or reseller: WHM zone functions
Cpanel::dns()->addZoneRecord('example.com', 'www', 'A', ['address' => '192.0.2.10']);
Cpanel::dns()->dumpZone('example.com');
```

`DNS::mass_edit_zone` requires the current SOA serial; `add()`, `edit()` and `remove()` read it first. When the zone
changed in the meantime cPanel rejects the call and the package does not retry it.

### E-mail

```php
$email = Cpanel::forUser('john')->email();

$email->create('office@example.com', $password, quotaMb: 1024);
$email->changePassword('office@example.com', $newPassword);
$email->setQuota('office@example.com', 4096);
$email->addForwarder('sales@example.com', 'anna@example.org');
$email->accounts();          // list<EmailAccount> with diskUsedMb and quotaMb
$email->delete('office@example.com');
```

### MySQL

```php
$mysql = Cpanel::forUser('john')->mysql();

$mysql->createWithUser('john_shop', 'john_app', $password);   // database, user, ALL PRIVILEGES
$mysql->grant('john_report', 'john_shop', 'SELECT');
$mysql->databases();                                          // list<Database>
$mysql->deleteDatabase('john_old');
```

### SSL, FTP, cron, files, backups, statistics

```php
$client = Cpanel::forUser('john');

$client->ssl()->install('example.com', $certificatePem, $privateKeyPem, $caBundlePem);
$client->ssl()->startAutoSsl();
$client->ftp()->create('deploy', $password, 'example.com', 'public_html/app');
$client->cron()->add('* * * * *', '/usr/local/bin/php /home/john/app/artisan schedule:run');
$client->files()->put('public_html', 'maintenance.html', view('maintenance')->render());
$client->backups()->createInHomeDirectory(notify: 'admin@example.com');
$client->stats()->account();        // ['diskusage' => [...], 'bandwidthusage' => [...]]

Cpanel::stats()->bandwidth(month: 10, year: 2026);   // WHM showbw
Cpanel::stats()->diskUsage();                        // WHM get_disk_usage
```

### Many servers and accounts

```php
// config/cpanel.php
'connections' => [
    'default' => ['type' => 'whm', 'host' => env('WHM1_HOST'), 'username' => 'root', 'token' => env('WHM1_TOKEN')],
    'node2' => ['type' => 'whm', 'host' => env('WHM2_HOST'), 'username' => 'reseller', 'token' => env('WHM2_TOKEN')],
    'client' => ['type' => 'cpanel', 'host' => 'shared.example.com', 'username' => 'john', 'token' => env('CP_TOKEN')],
],

Cpanel::connection('node2')->accounts()->list();
Cpanel::connection('client')->email()->accounts();

// Servers stored in the database
Cpanel::using([
    'type' => 'whm',
    'host' => $server->hostname,
    'username' => $server->username,
    'token' => decrypt($server->api_token),
    'verify' => $server->self_signed ? false : true,
])->forUser($service->username)->domains()->list();
```

### Anything else

```php
Cpanel::whm('listzones');                                                   // any WHM API 1 function
Cpanel::whm('setsiteip', ['user' => 'john', 'ip' => '192.0.2.20'], write: true);
Cpanel::forUser('john')->uapi('Email', 'list_mxs', ['domain' => 'example.com']);  // any UAPI function
Cpanel::forUser('john')->api2('CronJob', 'listcron');                       // any API 2 function
```

## Exceptions

All exceptions extend `CpanelException`:

| Exception | When |
|---|---|
| `AuthenticationException` | Token rejected (HTTP 401 or 403). Never retried. |
| `ApiException` | WHM `metadata.result = 0`, UAPI `status = 0`, API 2 error, unexpected response or transport error (`function()`, `errors()`, `httpStatus()`, `payload()`) |
| `UnsupportedOperation` | WHM function on a `cpanel` connection, or a UAPI call on a WHM connection without `forUser()` |
| `InvalidConfiguration` | Unknown connection, missing host, username or token, invalid type |

## Notes

- Database and database user names include the account prefix (`john_shop`) when prefixing is enabled.
- Account creation, AutoSSL and backups take seconds to minutes: run them in queued jobs.
- Cron jobs use the legacy API 2 module `CronJob`, because neither UAPI nor WHM API 1 provide cron functions.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues and feature requests: [GitHub issues](https://github.com/beziworld/laravel-cpanel/issues)
- Security vulnerabilities: see [SECURITY.md](SECURITY.md)

Need hosting automation, a client panel or automated provisioning? Get in touch through
[beziworld.eu](https://beziworld.eu).

### Other packages by the author

- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): DirectAdmin hosting panel
- [beziworld/laravel-plesk](https://github.com/beziworld/laravel-plesk): Plesk REST and XML API
- [beziworld/laravel-cloudflare](https://github.com/beziworld/laravel-cloudflare): Cloudflare DNS, cache and Turnstile

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with or endorsed by WebPros International
(cPanel, L.L.C.). cPanel and WHM are trademarks of their respective owners.
