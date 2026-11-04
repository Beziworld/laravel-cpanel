<?php

declare(strict_types=1);

use Beziworld\Cpanel\Connection;
use Beziworld\Cpanel\ConnectionConfig;
use Beziworld\Cpanel\Data\Account;
use Beziworld\Cpanel\Data\AccountRequest;
use Beziworld\Cpanel\Data\DnsRecord;
use Beziworld\Cpanel\Data\EmailAccount;
use Beziworld\Cpanel\Data\HostingPackage;
use Beziworld\Cpanel\Data\UserSession;
use Beziworld\Cpanel\Exceptions\InvalidConfiguration;

it('normalizes the host and adds the default port per type', function (string $type, string $host, string $expected): void {
    expect(ConnectionConfig::fromArray('x', ['type' => $type, 'host' => $host, 'username' => 'u', 'token' => 't'])->host)->toBe($expected);
})->with([
    ['whm', 'server.example.com', 'https://server.example.com:2087'],
    ['cpanel', 'server.example.com/', 'https://server.example.com:2083'],
    ['WHM', 'http://10.0.0.1:2086', 'http://10.0.0.1:2086'],
]);

it('builds the authorization header value', function (): void {
    expect(ConnectionConfig::fromArray('x', ['host' => 'h', 'username' => 'root', 'token' => 'ABC'])->authorization())->toBe('whm root:ABC')
        ->and(ConnectionConfig::fromArray('x', ['type' => 'cpanel', 'host' => 'h', 'username' => 'john', 'token' => 'XYZ'])->authorization())->toBe('cpanel john:XYZ');
});

it('parses the tls verification option', function (mixed $input, bool|string $expected): void {
    expect(ConnectionConfig::fromArray('x', ['host' => 'h', 'username' => 'u', 'token' => 't', 'verify' => $input])->verify)->toBe($expected);
})->with([
    [true, true],
    ['false', false],
    ['0', false],
    [null, true],
    ['/etc/ssl/ca.pem', '/etc/ssl/ca.pem'],
]);

it('requires host, username and token and a valid type', function (array $config, string $message): void {
    expect(fn() => ConnectionConfig::fromArray('main', $config))->toThrow(InvalidConfiguration::class, $message);
})->with([
    [[], '"host"'],
    [['host' => 'h'], '"username"'],
    [['host' => 'h', 'username' => 'u'], '"token"'],
    [['type' => 'plesk', 'host' => 'h', 'username' => 'u', 'token' => 't'], 'invalid type "plesk"'],
]);

it('encodes booleans, nulls and lists the way cPanel expects', function (): void {
    expect(Connection::encode(['a' => true, 'b' => false, 'c' => null, 'd' => 5, 'e' => ['x'], 'f' => ['1', '2']]))
        ->toBe(['a' => '1', 'b' => '0', 'd' => '5', 'e' => 'x', 'f-1' => '1', 'f-2' => '2']);
});

it('maps createacct parameters', function (): void {
    $request = new AccountRequest('john', 'example.com', 'secret', package: 'basic', contactEmail: 'john@example.com', dedicatedIp: false, extra: ['maxpop' => 10]);

    expect($request->toParameters())->toMatchArray([
        'username' => 'john', 'domain' => 'example.com', 'password' => 'secret', 'plan' => 'basic',
        'contactemail' => 'john@example.com', 'ip' => 'n', 'maxpop' => 10,
    ]);
});

it('validates account requests', function (): void {
    expect(fn() => new AccountRequest('John', 'example.com'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new AccountRequest('1john', 'example.com'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new AccountRequest('john', 'example.com', contactEmail: 'nope'))->toThrow(InvalidArgumentException::class);
});

it('hydrates accounts and packages', function (): void {
    $account = Account::fromArray(['user' => 'john', 'domain' => 'example.com', 'plan' => 'basic', 'suspended' => 1, 'suspendreason' => 'Nonpayment', 'unix_startdate' => 1369256589, 'diskused' => '14M']);
    $active = Account::fromArray(['user' => 'jane', 'domain' => 'example.org', 'suspended' => 0, 'suspendreason' => 'not suspended']);
    $package = HostingPackage::fromArray(['name' => 'basic', 'QUOTA' => '1024', 'MAXPOP' => 'unlimited', 'HASSHELL' => 'n']);

    expect($account->suspended)->toBeTrue()
        ->and($account->suspendReason)->toBe('Nonpayment')
        ->and($account->createdAt)->toBe(1369256589)
        ->and($active->suspendReason)->toBeNull()
        ->and($package->diskQuota)->toBe('1024')
        ->and($package->maxEmailAccounts)->toBe('unlimited')
        ->and($package->shellAccess)->toBeFalse();
});

it('decodes base64 records of parse_zone and skips comments', function (): void {
    $record = DnsRecord::fromParsedZone([
        'line_index' => 12, 'type' => 'record', 'record_type' => 'MX', 'ttl' => 3600,
        'dname_b64' => base64_encode('example.com.'), 'data_b64' => [base64_encode('10'), base64_encode('mail.example.com.')],
    ]);

    expect($record)->not->toBeNull()
        ->and($record?->content())->toBe('10 mail.example.com.')
        ->and($record?->name)->toBe('example.com.')
        ->and(DnsRecord::fromParsedZone(['line_index' => 0, 'type' => 'comment', 'text_b64' => 'eA==']))->toBeNull();
});

it('hydrates e-mail accounts and sessions', function (): void {
    $mailbox = EmailAccount::fromArray(['email' => 'office@example.com', 'diskused' => '12.5', 'diskquota' => 0, 'suspended_login' => 0]);
    $session = UserSession::fromArray(['url' => 'https://h:2083/cpsess1/login/?session=x', 'expires' => 1401993893, 'service' => 'cpaneld']);

    expect($mailbox->user)->toBe('office')
        ->and($mailbox->domain)->toBe('example.com')
        ->and($mailbox->quotaMb)->toBeNull()
        ->and($mailbox->diskUsedMb)->toBe(12.5)
        ->and($session->expiresAt?->getTimestamp())->toBe(1401993893);
});
