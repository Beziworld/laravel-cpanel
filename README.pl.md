# Laravel cPanel

Klient [cPanel & WHM](https://cpanel.net) dla Laravela: WHM API 1 do kont, pakietów i logowania jednorazowego (SSO),
UAPI do domen, DNS, poczty, MySQL, SSL, FTP, plików, kopii zapasowych i statystyk oraz API 2 do zadań cron.
Obsługuje dowolną liczbę serwerów i kont.

[English documentation](README.md)

## Możliwości

- Wiele nazwanych połączeń (jak połączenia bazodanowe) oraz `Cpanel::using([...])` dla serwerów zapisanych w bazie
- Dwa typy połączeń: `whm` (token root lub resellera, port 2087) i `cpanel` (token konta, port 2083)
- `forUser('jan')` na połączeniu WHM wykonuje wywołania UAPI jako wskazane konto (przez `uapi_cpanel`), więc jeden
  token resellera obsługuje wszystkie jego konta
- Zasoby: `accounts`, `packages`, `sessions`, `domains`, `dns`, `email`, `mysql`, `ssl`, `ftp`, `cron`, `files`,
  `backups`, `stats`
- Typowane, niezmienne DTO (`Account`, `HostingPackage`, `EmailAccount`, `Database`, `DnsRecord`, `UserSession`)
  oraz ogólne metody `whm()`, `uapi()` i `api2()` dla pozostałych funkcji
- Pewne wykrywanie błędów: `metadata.result = 0` z WHM i `status = 0` z UAPI kończą się wyjątkiem `ApiException`
  z komunikatami zwróconymi przez cPanel
- Operacje zapisu idą jako formularz POST (hasła nie trafiają do adresów URL) i nigdy nie są ponawiane automatycznie
- Konfigurowalna weryfikacja TLS dla paneli z certyfikatem self-signed
- `php artisan cpanel:check` do testu połączenia
- PHP 8.3+, Laravel 12 i 13, PHPStan level 8

## Instalacja

```bash
composer require beziworld/laravel-cpanel
php artisan vendor:publish --tag=cpanel-config   # opcjonalnie
```

```dotenv
CPANEL_TYPE=whm                       # whm (root/reseller) albo cpanel (pojedyncze konto)
CPANEL_HOST=serwer.example.com        # port 2087 lub 2083 zostanie dodany automatycznie
CPANEL_USERNAME=root
CPANEL_TOKEN=U7HMR63FGY292DQZ4H5BFH16JLYMO01M
CPANEL_VERIFY=true                    # false albo ścieżka do CA przy certyfikacie self-signed
```

Token utworzysz w WHM (*Development > Manage API Tokens*) albo w cPanelu (*Security > Manage API Tokens*). Ogranicz
jego uprawnienia (ACL) do potrzebnych funkcji i przypisz go do adresu IP aplikacji.

## Przykłady użycia

### Zakładanie hostingu po opłaceniu zamówienia

```php
use Beziworld\Cpanel\Data\AccountRequest;
use Beziworld\Cpanel\Facades\Cpanel;

Cpanel::accounts()->create(new AccountRequest(
    username: $order->username,
    domain: $order->domain,
    password: $haslo,
    package: $order->plan->package,
    contactEmail: $order->email,
));

$konto = Cpanel::forUser($order->username);
$konto->email()->create('biuro@' . $order->domain, Str::password(16), quotaMb: 2048);
$konto->ssl()->startAutoSsl();
```

### Blokowanie, zmiana pakietu, usuwanie

```php
Cpanel::accounts()->suspend('jan', 'Nieopłacona faktura FV/2026/11/01');
Cpanel::accounts()->unsuspend('jan');
Cpanel::accounts()->changePackage('jan', 'biznes');
Cpanel::accounts()->modify('jan', ['QUOTA' => 20480, 'MAXPOP' => 100]);
Cpanel::accounts()->remove('jan');            // nieodwracalne

Cpanel::accounts()->find('jan')?->suspended;
Cpanel::packages()->list();
```

### Logowanie jednorazowe do cPanelu lub Webmaila

```php
return redirect()->away(Cpanel::sessions()->cpanelUrl($user->cpanel_username, app: 'Email_Accounts'));
```

### Domeny i DNS

```php
$klient = Cpanel::forUser('jan');

$klient->domains()->addSubdomain('blog', 'example.com', 'public_html/blog');
$klient->domains()->addAddon('sklep.pl', 'sklep');
$klient->domains()->park('example.net');

$klient->dns()->add('example.com', '_acme-challenge', 'TXT', 'wartosc-tokenu', ttl: 300);
$klient->dns()->add('example.com', '@', 'MX', ['10', 'mx1.example.com.']);
$klient->dns()->remove('example.com', 24);

foreach ($klient->dns()->records('example.com') as $rekord) {
    echo "{$rekord->lineIndex} {$rekord->name} {$rekord->type} {$rekord->content()}";
}
```

`DNS::mass_edit_zone` wymaga aktualnego numeru seryjnego SOA; `add()`, `edit()` i `remove()` odczytują go przed
zapisem. Jeśli strefa zmieniła się w międzyczasie, cPanel odrzuci zmianę, a pakiet nie ponowi jej samodzielnie.

### Poczta, MySQL, SSL, FTP, cron, pliki, kopie

```php
$klient = Cpanel::forUser('jan');

$klient->email()->create('biuro@example.com', $haslo, quotaMb: 1024);
$klient->email()->addForwarder('sprzedaz@example.com', 'anna@example.org');
$klient->mysql()->createWithUser('jan_sklep', 'jan_app', $hasloDb);
$klient->ssl()->install('example.com', $certyfikat, $kluczPrywatny, $paczkaCa);
$klient->ftp()->create('deploy', $haslo, 'example.com', 'public_html/app');
$klient->cron()->add('* * * * *', '/usr/local/bin/php /home/jan/app/artisan schedule:run');
$klient->files()->put('public_html', 'przerwa.html', '<h1>Wracamy wkrótce</h1>');
$klient->backups()->createInHomeDirectory(notify: 'admin@example.com');
$klient->stats()->account();
```

### Wiele serwerów i kont

```php
Cpanel::connection('node2')->accounts()->list();

Cpanel::using([
    'type' => 'whm',
    'host' => $serwer->hostname,
    'username' => $serwer->username,
    'token' => decrypt($serwer->api_token),
    'verify' => ! $serwer->self_signed,
])->forUser($usluga->username)->domains()->list();
```

### Pozostałe funkcje

```php
Cpanel::whm('listzones');
Cpanel::forUser('jan')->uapi('Email', 'list_mxs', ['domain' => 'example.com']);
Cpanel::forUser('jan')->api2('CronJob', 'listcron');
```

## Wyjątki

Wszystkie dziedziczą po `CpanelException`:

| Wyjątek | Kiedy |
|---|---|
| `AuthenticationException` | Odrzucony token (HTTP 401 lub 403), bez ponowień |
| `ApiException` | `metadata.result = 0` z WHM, `status = 0` z UAPI, błąd API 2, nieoczekiwana odpowiedź lub błąd połączenia (`function()`, `errors()`, `payload()`) |
| `UnsupportedOperation` | Funkcja WHM na połączeniu `cpanel` albo wywołanie UAPI przez WHM bez `forUser()` |
| `InvalidConfiguration` | Nieznane połączenie, brak hosta, użytkownika lub tokenu, błędny typ |

## Uwagi

- Nazwy baz i użytkowników MySQL zawierają prefiks konta (`jan_sklep`), jeśli serwer go wymaga.
- Zakładanie konta, AutoSSL i kopie trwają od kilku sekund do kilku minut, więc uruchamiaj je w jobach kolejki.
- Zadania cron korzystają ze starszego API 2 (`CronJob`), bo ani UAPI, ani WHM API 1 nie mają funkcji cron.

## Rozwój

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Autor

Pakiet tworzy i rozwija **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia błędów: [GitHub issues](https://github.com/beziworld/laravel-cpanel/issues)
- Podatności bezpieczeństwa: [SECURITY.md](SECURITY.md)

Potrzebujesz automatyzacji hostingu, panelu klienta albo automatycznego zakładania usług? Zapraszam do kontaktu przez
[beziworld.eu](https://beziworld.eu).

## Licencja

MIT. Pakiet nie jest powiązany z WebPros International (cPanel, L.L.C.) ani przez nią wspierany. cPanel i WHM są
znakami towarowymi ich właścicieli.
