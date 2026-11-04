<?php

declare(strict_types=1);

use Beziworld\Cpanel\Data\AccountRequest;
use Beziworld\Cpanel\Facades\Cpanel;
use Beziworld\Cpanel\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Builds a parse_zone row.
 *
 * @param list<string> $data
 * @return array<string, mixed>
 */
function zoneRow(int $line, string $name, string $type, array $data): array
{
    return [
        'line_index' => $line, 'type' => 'record', 'record_type' => $type, 'ttl' => 14400,
        'dname_b64' => base64_encode($name), 'data_b64' => array_map(base64_encode(...), $data),
    ];
}

it('lists accounts with a search filter', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('listaccts', ['acct' => [
        ['user' => 'john', 'domain' => 'example.com', 'plan' => 'basic', 'suspended' => 0],
    ]]))]);

    $accounts = Cpanel::accounts()->list('example.com', 'domain', exact: true);

    expect($accounts[0]->username)->toBe('john')->and($accounts[0]->package)->toBe('basic');

    Http::assertSent(fn(Request $request): bool => ($request->data()['search'] ?? null) === 'example.com'
        && ($request->data()['searchtype'] ?? null) === 'domain'
        && ($request->data()['searchmethod'] ?? null) === 'exact');
});

it('finds a single account or returns null', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('listaccts', ['acct' => []]))]);

    expect(Cpanel::accounts()->find('ghost'))->toBeNull();
});

it('creates accounts', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('createacct', ['ip' => '192.0.2.10', 'package' => 'basic']))]);

    $result = Cpanel::accounts()->create(new AccountRequest('john', 'example.com', 'secret', package: 'basic', contactEmail: 'john@example.com'));

    expect($result['ip'] ?? null)->toBe('192.0.2.10');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && str_starts_with($request->url(), TestCase::WHM . '/json-api/createacct')
        && ($request->data()['username'] ?? null) === 'john'
        && ($request->data()['plan'] ?? null) === 'basic'
        && ($request->data()['contactemail'] ?? null) === 'john@example.com');
});

it('suspends, unsuspends, modifies and removes accounts', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('x'))]);

    Cpanel::accounts()->suspend('john', 'Unpaid invoice', disallowUnsuspend: true);
    Cpanel::accounts()->unsuspend('john');
    Cpanel::accounts()->modify('john', ['QUOTA' => 2048, 'MAXPOP' => 50]);
    Cpanel::accounts()->changePackage('john', 'business');
    Cpanel::accounts()->remove('john');

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/suspendacct') && ($request->data()['reason'] ?? null) === 'Unpaid invoice' && ($request->data()['disallowun'] ?? null) === '1');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/unsuspendacct') && ($request->data()['user'] ?? null) === 'john');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/modifyacct') && ($request->data()['QUOTA'] ?? null) === '2048');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/changepackage') && ($request->data()['pkg'] ?? null) === 'business');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/removeacct') && ($request->data()['username'] ?? null) === 'john' && ($request->data()['keepdns'] ?? null) === '0');
});

it('returns the account summary', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('accountsummary', ['acct' => [['user' => 'john', 'domain' => 'example.com', 'diskused' => '14M']]]))]);

    expect(Cpanel::accounts()->summary('john')->diskUsed)->toBe('14M');
});

it('lists packages', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('listpkgs', ['pkg' => [['name' => 'basic', 'QUOTA' => '1024'], ['name' => 'pro', 'QUOTA' => 'unlimited']]]))]);

    expect(Cpanel::packages()->find('pro')?->diskQuota)->toBe('unlimited')
        ->and(Cpanel::packages()->find('none'))->toBeNull();
});

it('creates one-time login urls', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('create_user_session', [
        'url' => 'https://whm.example.com:2083/cpsess123/login/?session=john:abc', 'service' => 'cpaneld', 'expires' => 1700000000, 'cp_security_token' => '/cpsess123',
    ]))]);

    $session = Cpanel::sessions()->create('john', app: 'Email_Accounts');

    expect($session->url)->toContain('/login/?session=john:abc')->and($session->securityToken)->toBe('/cpsess123');

    Http::assertSent(fn(Request $request): bool => ($request->data()['user'] ?? null) === 'john'
        && ($request->data()['service'] ?? null) === 'cpaneld'
        && ($request->data()['app'] ?? null) === 'Email_Accounts');
});

it('manages subdomains, addon and parked domains', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);
    $domains = Cpanel::connection('shared')->domains();

    $domains->addSubdomain('blog', 'example.com', 'public_html/blog');
    $domains->addAddon('shop.com', 'shop');
    $domains->park('example.net');
    $domains->removeAddon('shop.com', 'shop_example.com');

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::CPANEL . '/execute/SubDomain/addsubdomain'
        && ($request->data()['rootdomain'] ?? null) === 'example.com' && ($request->data()['dir'] ?? null) === 'public_html/blog');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/AddonDomain/addaddondomain') && ($request->data()['newdomain'] ?? null) === 'shop.com');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Park/park') && ($request->data()['domain'] ?? null) === 'example.net');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/AddonDomain/deladdondomain') && ($request->data()['subdomain'] ?? null) === 'shop_example.com');
});

it('reads dns records from parse_zone', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk([
        ['line_index' => 0, 'type' => 'control', 'text_b64' => base64_encode('$TTL 14400')],
        zoneRow(2, 'example.com.', 'SOA', ['ns1.example.com.', 'admin.example.com.', '2026110401', '3600', '1800', '1209600', '86400']),
        zoneRow(9, 'www', 'A', ['192.0.2.1']),
    ]))]);

    $records = Cpanel::connection('shared')->dns()->records('example.com');

    expect($records)->toHaveCount(2)
        ->and($records[1]->name)->toBe('www')
        ->and($records[1]->content())->toBe('192.0.2.1')
        ->and(Cpanel::connection('shared')->dns()->serial('example.com'))->toBe(2026110401);
});

it('adds dns records with the current serial', function (): void {
    Http::fake([
        TestCase::CPANEL . '/execute/DNS/parse_zone*' => Http::response(TestCase::uapiOk([
            zoneRow(2, 'example.com.', 'SOA', ['ns1.example.com.', 'admin.example.com.', '2026110401', '3600', '1800', '1209600', '86400']),
        ])),
        TestCase::CPANEL . '/execute/DNS/mass_edit_zone' => Http::response(TestCase::uapiOk(['new_serial' => 2026110402])),
    ]);

    $serial = Cpanel::connection('shared')->dns()->add('example.com', '_acme', 'TXT', 'token-value', 300);

    expect($serial)->toBe(2026110402);

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/DNS/mass_edit_zone')
        && ($request->data()['serial'] ?? null) === '2026110401'
        && ($request->data()['add'] ?? null) === '{"dname":"_acme","ttl":300,"record_type":"TXT","data":["token-value"]}');
});

it('sends several mass edit items with numbered parameters', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk(['new_serial' => 5]))]);

    Cpanel::connection('shared')->dns()->massEdit('example.com', remove: [10, 12], serial: 4);

    Http::assertSent(fn(Request $request): bool => ($request->data()['remove-1'] ?? null) === '10'
        && ($request->data()['remove-2'] ?? null) === '12'
        && ! array_key_exists('add', $request->data()));
});

it('edits zone records through whm', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('addzonerecord'))]);

    Cpanel::dns()->addZoneRecord('example.com', 'mail', 'mx', ['exchange' => 'mx1.example.com', 'preference' => 10]);
    Cpanel::dns()->removeZoneRecord('example.com', 24);

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/json-api/addzonerecord')
        && ($request->data()['type'] ?? null) === 'MX'
        && ($request->data()['exchange'] ?? null) === 'mx1.example.com'
        && ($request->data()['preference'] ?? null) === '10');
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/json-api/removezonerecord') && ($request->data()['line'] ?? null) === '24');
});

it('returns whm zone dumps', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('dumpzone', ['zone' => [['record' => [['Line' => 1, 'type' => 'A', 'name' => 'example.com.', 'address' => '192.0.2.1']]]]]))]);

    expect(Cpanel::dns()->dumpZone('example.com')[0]['address'] ?? null)->toBe('192.0.2.1');
});

it('manages mailboxes and forwarders', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);
    $email = Cpanel::connection('shared')->email();

    $email->changePassword('office@example.com', 'n3w');
    $email->setQuota('office@example.com', null);
    $email->delete('office@example.com');
    $email->addForwarder('sales@example.com', 'anna@example.org');
    $email->deleteForwarder('sales@example.com', 'anna@example.org');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Email/passwd_pop') && ($request->data()['email'] ?? null) === 'office' && ($request->data()['domain'] ?? null) === 'example.com');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Email/edit_pop_quota') && ($request->data()['quota'] ?? null) === 'unlimited');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Email/delete_pop'));
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Email/add_forwarder') && ($request->data()['fwdopt'] ?? null) === 'fwd' && ($request->data()['fwdemail'] ?? null) === 'anna@example.org');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Email/delete_forwarder') && ($request->data()['forwarder'] ?? null) === 'anna@example.org');
});

it('lists mailboxes with disk usage', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk([['email' => 'office@example.com', 'diskused' => '3.2', 'diskquota' => '1024']]))]);

    $accounts = Cpanel::connection('shared')->email()->accounts();

    expect($accounts[0]->quotaMb)->toBe(1024.0)
        ->and(Cpanel::connection('shared')->email()->diskUsage())->toBe(['office@example.com' => 3.2]);
});

it('requires a domain for local mailbox names', function (): void {
    expect(fn() => Cpanel::connection('shared')->email()->delete('office'))->toThrow(InvalidArgumentException::class);
});

it('creates a database with a user and privileges', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);

    Cpanel::connection('shared')->mysql()->createWithUser('john_shop', 'john_app', 'pa55');

    Http::assertSentCount(3);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Mysql/create_database') && ($request->data()['name'] ?? null) === 'john_shop');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Mysql/create_user') && ($request->data()['password'] ?? null) === 'pa55');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Mysql/set_privileges_on_database')
        && ($request->data()['privileges'] ?? null) === 'ALL PRIVILEGES' && ($request->data()['database'] ?? null) === 'john_shop');
});

it('stops creating database objects after the first failure', function (): void {
    Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['Database exists']])]);

    expect(fn() => Cpanel::connection('shared')->mysql()->createWithUser('john_shop', 'john_app', 'pa55'))->toThrow(Beziworld\Cpanel\Exceptions\ApiException::class);

    Http::assertSentCount(1);
});

it('installs certificates and starts autossl', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);
    $ssl = Cpanel::connection('shared')->ssl();

    $ssl->install('example.com', "-----BEGIN CERTIFICATE-----\nAAA\n-----END CERTIFICATE-----", 'KEY', 'CA');
    $ssl->startAutoSsl();

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/SSL/install_ssl')
        && str_contains((string) ($request->data()['cert'] ?? ''), 'BEGIN CERTIFICATE') && ($request->data()['cabundle'] ?? null) === 'CA');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/SSL/start_autossl_check') && $request->method() === 'POST');
});

it('manages ftp accounts', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);

    Cpanel::connection('shared')->ftp()->create('deploy', 'pw', 'example.com', 'public_html/app', 500);
    Cpanel::connection('shared')->ftp()->delete('deploy', 'example.com', destroy: true);

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Ftp/add_ftp') && ($request->data()['homedir'] ?? null) === 'public_html/app' && ($request->data()['quota'] ?? null) === '500');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Ftp/delete_ftp') && ($request->data()['destroy'] ?? null) === '1');
});

it('adds cron jobs from an expression', function (): void {
    Http::fake(['*' => Http::response(['cpanelresult' => ['data' => [['status' => 1, 'linekey' => 'k1']], 'event' => ['result' => 1]]])]);

    Cpanel::connection('shared')->cron()->add('*/5 * * * 1-5', 'php /home/john/app/artisan schedule:run');

    Http::assertSent(fn(Request $request): bool => ($request->data()['cpanel_jsonapi_func'] ?? null) === 'add_line'
        && ($request->data()['minute'] ?? null) === '*/5'
        && ($request->data()['weekday'] ?? null) === '1-5'
        && ($request->data()['command'] ?? null) === 'php /home/john/app/artisan schedule:run');
});

it('rejects invalid cron expressions', function (): void {
    expect(fn() => Cpanel::connection('shared')->cron()->add('* * *', 'x'))->toThrow(InvalidArgumentException::class);
});

it('reads and writes files', function (): void {
    Http::fake([
        TestCase::CPANEL . '/execute/Fileman/get_file_content*' => Http::response(TestCase::uapiOk(['content' => 'APP_ENV=production', 'dir' => '/home/john'])),
        TestCase::CPANEL . '/execute/Fileman/save_file_content' => Http::response(TestCase::uapiOk()),
    ]);
    $files = Cpanel::connection('shared')->files();

    expect($files->get('app', '.env'))->toBe('APP_ENV=production');

    $files->put('public_html', 'maintenance.html', '<h1>Back soon</h1>');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Fileman/save_file_content') && ($request->data()['content'] ?? null) === '<h1>Back soon</h1>');
});

it('returns account statistics keyed by name', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk([
        ['name' => 'diskusage', 'count' => '512', 'max' => '1024', 'units' => 'MB'],
        ['name' => 'bandwidthusage', 'count' => '20', 'max' => 'unlimited'],
    ]))]);

    $stats = Cpanel::connection('shared')->stats()->account();

    expect($stats['diskusage']['count'] ?? null)->toBe('512')->and(array_keys($stats))->toBe(['diskusage', 'bandwidthusage']);

    Http::assertSent(fn(Request $request): bool => ($request->data()['display'] ?? null) === 'diskusage|bandwidthusage');
});

it('queues backups', function (): void {
    Http::fake(['*' => Http::response(TestCase::uapiOk())]);

    Cpanel::connection('shared')->backups()->createInHomeDirectory('admin@example.com');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/Backup/fullbackup_to_homedir') && ($request->data()['email'] ?? null) === 'admin@example.com');
});

it('exposes the generic call methods', function (): void {
    Http::fake([
        TestCase::WHM . '/json-api/gethostname*' => Http::response(TestCase::whmOk('gethostname', ['hostname' => 'srv1.example.com'])),
        TestCase::CPANEL . '/execute/Ftp/get_port*' => Http::response(TestCase::uapiOk(['port' => 21])),
    ]);

    expect(Cpanel::whm('gethostname'))->toBe(['hostname' => 'srv1.example.com'])
        ->and(Cpanel::connection('shared')->uapi('Ftp', 'get_port'))->toBe(['port' => 21]);
});
