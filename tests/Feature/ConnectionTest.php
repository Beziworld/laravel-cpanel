<?php

declare(strict_types=1);

use Beziworld\Cpanel\Exceptions\ApiException;
use Beziworld\Cpanel\Exceptions\AuthenticationException;
use Beziworld\Cpanel\Exceptions\InvalidConfiguration;
use Beziworld\Cpanel\Exceptions\UnsupportedOperation;
use Beziworld\Cpanel\Facades\Cpanel;
use Beziworld\Cpanel\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('calls whm api 1 with the whm authorization header', function (): void {
    Http::fake([TestCase::WHM . '/json-api/version*' => Http::response(TestCase::whmOk('version', ['version' => '11.130.0.5']))]);

    expect(Cpanel::stats()->version())->toBe('11.130.0.5');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
        && $request->url() === TestCase::WHM . '/json-api/version?api.version=1'
        && $request->hasHeader('Authorization', 'whm root:WHMTOKEN'));
});

it('raises the reason of a failed whm call', function (): void {
    Http::fake(['*' => Http::response(['metadata' => ['command' => 'suspendacct', 'result' => 0, 'reason' => 'User does not exist.'], 'data' => null])]);

    try {
        Cpanel::accounts()->suspend('ghost');
        test()->fail('Expected exception was not thrown.');
    } catch (ApiException $exception) {
        expect($exception->getMessage())->toBe('cPanel suspendacct failed: User does not exist.')
            ->and($exception->function())->toBe('suspendacct')
            ->and($exception->errors())->toBe(['User does not exist.']);
    }
});

it('sends state-changing whm calls as a post form with api.version in the query', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('passwd'))]);

    Cpanel::accounts()->changePassword('john', 's3cret!', true);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->url() === TestCase::WHM . '/json-api/passwd?api.version=1'
        && $request->isForm()
        && ($request->data()['password'] ?? null) === 's3cret!'
        && ($request->data()['db_pass_update'] ?? null) === '1'
        && ! str_contains($request->url(), 's3cret'));
});

it('calls uapi directly on a cpanel account connection', function (): void {
    Http::fake([TestCase::CPANEL . '/execute/DomainInfo/list_domains*' => Http::response(TestCase::uapiOk([
        'main_domain' => 'example.com', 'addon_domains' => ['shop.com'], 'sub_domains' => ['blog.example.com'], 'parked_domains' => [],
    ]))]);

    expect(Cpanel::connection('shared')->domains()->list())->toBe([
        'main' => 'example.com', 'addon' => ['shop.com'], 'sub' => ['blog.example.com'], 'parked' => [],
    ]);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::CPANEL . '/execute/DomainInfo/list_domains'
        && $request->hasHeader('Authorization', 'cpanel john:CPTOKEN'));
});

it('raises uapi errors', function (): void {
    Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['The account already exists.', 'Second line.'], 'data' => null, 'messages' => null])]);

    expect(fn() => Cpanel::connection('shared')->email()->create('office@example.com', 'pass'))
        ->toThrow(ApiException::class, 'cPanel Email::add_pop failed: The account already exists.; Second line.');
});

it('unwraps the result envelope of the cli response shape', function (): void {
    Http::fake(['*' => Http::response(['apiversion' => 3, 'func' => 'list_databases', 'module' => 'Mysql', 'result' => TestCase::uapiOk([['database' => 'john_shop', 'disk_usage' => 2048, 'users' => ['john_app']]])])]);

    $databases = Cpanel::connection('shared')->mysql()->databases();

    expect($databases)->toHaveCount(1)
        ->and($databases[0]->name)->toBe('john_shop')
        ->and($databases[0]->users)->toBe(['john_app']);
});

it('proxies uapi through whm uapi_cpanel for a selected account', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('uapi_cpanel', ['uapi' => TestCase::uapiOk('office@example.com')]))]);

    Cpanel::forUser('john')->email()->create('office', 'pass', 'example.com', 1024);

    Http::assertSent(fn(Request $request): bool => $request->url() === TestCase::WHM . '/json-api/uapi_cpanel?api.version=1'
        && ($request->data()['cpanel.user'] ?? null) === 'john'
        && ($request->data()['cpanel.module'] ?? null) === 'Email'
        && ($request->data()['cpanel.function'] ?? null) === 'add_pop'
        && ($request->data()['email'] ?? null) === 'office'
        && ($request->data()['domain'] ?? null) === 'example.com'
        && ($request->data()['quota'] ?? null) === '1024');
});

it('raises uapi errors returned through whm', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('uapi_cpanel', ['uapi' => ['status' => 0, 'errors' => ['Invalid zone.']]]))]);

    expect(fn() => Cpanel::forUser('john')->dns()->records('nope.com'))->toThrow(ApiException::class, 'DNS::parse_zone failed: Invalid zone.');
});

it('requires an account for uapi calls on a whm connection', function (): void {
    Http::fake();

    expect(fn() => Cpanel::email()->accounts())->toThrow(UnsupportedOperation::class, "forUser('username')");

    Http::assertNothingSent();
});

it('rejects whm functions on a cpanel account connection', function (): void {
    expect(fn() => Cpanel::connection('shared')->accounts()->list())->toThrow(UnsupportedOperation::class, 'listaccts is a WHM API 1 function')
        ->and(fn() => Cpanel::connection('shared')->forUser('jane'))->toThrow(UnsupportedOperation::class);
});

it('recognises rejected tokens and never retries', function (int $status): void {
    Http::fake(['*' => Http::response('Access denied', $status)]);

    try {
        Cpanel::accounts()->list();
        test()->fail('Expected exception was not thrown.');
    } catch (AuthenticationException $exception) {
        expect($exception->getMessage())->toContain('connection "default"');
    }

    Http::assertSentCount(1);
})->with([401, 403]);

it('wraps transport errors', function (): void {
    Http::fake(fn() => throw new ConnectionException('cURL error 28: timed out'));

    expect(fn() => Cpanel::accounts()->list())->toThrow(ApiException::class, 'Could not connect to cPanel for listaccts: cURL error 28: timed out');
});

it('reports responses that are not json', function (): void {
    Http::fake(['*' => Http::response('<html>maintenance</html>', 503)]);

    expect(fn() => Cpanel::accounts()->list())->toThrow(ApiException::class, 'unexpected response (HTTP 503)');
});

it('builds runtime clients with shared defaults', function (): void {
    config()->set('cpanel.timeout', 15);
    Http::fake(['*' => Http::response(TestCase::whmOk('listpkgs', ['pkg' => []]))]);

    $client = Cpanel::using(['host' => 'node7.example.net', 'username' => 'reseller', 'token' => 'RTOKEN']);
    $client->packages()->list();

    expect($client->connection()->config()->timeout)->toBe(15);

    Http::assertSent(fn(Request $request): bool => str_starts_with($request->url(), 'https://node7.example.net:2087/json-api/listpkgs')
        && $request->hasHeader('Authorization', 'whm reseller:RTOKEN'));
});

it('lists configured connections and fails for unknown ones', function (): void {
    expect(Cpanel::connectionNames())->toBe(['default', 'shared'])
        ->and(fn() => Cpanel::connection('missing'))->toThrow(InvalidConfiguration::class, '"missing"');
});

it('caches resolved clients until purged', function (): void {
    $first = Cpanel::connection('shared');

    expect(Cpanel::connection('shared'))->toBe($first);

    Cpanel::purge('shared');

    expect(Cpanel::connection('shared'))->not->toBe($first);
});

it('calls any api 2 function', function (): void {
    Http::fake(['*' => Http::response(['cpanelresult' => ['data' => [['linekey' => 'abc', 'command' => 'php artisan schedule:run']], 'event' => ['result' => 1]]])]);

    expect(Cpanel::connection('shared')->cron()->list()[0]['linekey'] ?? null)->toBe('abc');

    Http::assertSent(fn(Request $request): bool => str_starts_with($request->url(), TestCase::CPANEL . '/json-api/cpanel?')
        && str_contains($request->url(), 'cpanel_jsonapi_module=CronJob')
        && str_contains($request->url(), 'cpanel_jsonapi_apiversion=2')
        && str_contains($request->url(), 'cpanel_jsonapi_user=john'));
});

it('raises api 2 errors', function (): void {
    Http::fake(['*' => Http::response(['cpanelresult' => ['data' => [], 'event' => ['result' => 0], 'error' => 'Invalid line key']])]);

    expect(fn() => Cpanel::connection('shared')->cron()->remove('zzz'))->toThrow(ApiException::class, 'CronJob::remove_line failed: Invalid line key');
});

it('runs the check command', function (): void {
    Http::fake(['*' => Http::response(TestCase::whmOk('version', ['version' => '11.130.0.5']))]);

    $this->artisan('cpanel:check')
        ->expectsOutputToContain('Connected to https://whm.example.com:2087 as root (whm)')
        ->assertSuccessful();

    TestCase::refake(['*' => Http::response('', 401)]);

    $this->artisan('cpanel:check', ['connection' => 'shared'])->assertFailed();
});
