<?php

declare(strict_types=1);

namespace Beziworld\Cpanel;

use Beziworld\Cpanel\Exceptions\ApiException;
use Beziworld\Cpanel\Exceptions\AuthenticationException;
use Beziworld\Cpanel\Exceptions\UnsupportedOperation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * HTTP transport for UAPI, WHM API 1 and the legacy cPanel API 2.
 *
 * WHM API 1 reports failures with HTTP 200 and "metadata.result" set to 0,
 * UAPI with "status" set to 0 and a list of "errors"; both are converted to
 * an ApiException. Read-only functions are requested with GET and functions
 * that change state with a form-encoded POST, so that passwords do not end up
 * in web server access logs. Requests are never retried.
 */
class Connection
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConnectionConfig $config,
        private readonly ?string $account = null,
    ) {}

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * cPanel account the UAPI and API 2 calls are executed as.
     *
     * For a cPanel connection this is the configured username; for a WHM
     * connection it is the account selected with forAccount(), if any.
     */
    public function account(): ?string
    {
        return $this->config->isWhm() ? $this->account : $this->config->username;
    }

    /**
     * Returns a WHM connection executing UAPI and API 2 calls as the given cPanel account.
     *
     * @throws UnsupportedOperation When the connection is not a WHM connection.
     */
    public function forAccount(string $username): self
    {
        if (! $this->config->isWhm()) {
            throw UnsupportedOperation::requiresWhm('forUser()');
        }

        return new self($this->http, $this->config, $username);
    }

    /**
     * Calls a WHM API 1 function and returns its "data" member.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     * @return array<array-key, mixed>
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @throws UnsupportedOperation When the connection is not a WHM connection.
     */
    public function whm(string $function, array $parameters = [], bool $write = false): array
    {
        if (! $this->config->isWhm()) {
            throw UnsupportedOperation::requiresWhm($function);
        }

        $function = trim($function, '/ ');
        $payload = $this->send('json-api/' . $function, ['api.version' => '1'], self::encode($parameters), $write, $function);

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null;

        if ($metadata === null) {
            throw ApiException::unexpectedResponse($function, 200);
        }

        if ((int) self::scalar($metadata['result'] ?? 0) !== 1) {
            $reason = self::scalar($metadata['reason'] ?? null);

            throw ApiException::failed($function, $reason === '' ? [] : [$reason], 200, $payload);
        }

        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * Calls a UAPI function and returns its "data" member.
     *
     * On a WHM connection the call goes through the WHM API 1 function
     * "uapi_cpanel" as the account selected with forAccount().
     *
     * @param array<string, scalar|list<scalar>|null> $parameters Lists are sent as name-1, name-2, ...
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @throws UnsupportedOperation When a WHM connection has no target account.
     */
    public function uapi(string $module, string $function, array $parameters = [], bool $write = false): mixed
    {
        $name = $module . '::' . $function;

        if ($this->config->isWhm()) {
            if ($this->account === null) {
                throw UnsupportedOperation::requiresAccount($name);
            }

            $data = $this->whm('uapi_cpanel', [
                'cpanel.user' => $this->account,
                'cpanel.module' => $module,
                'cpanel.function' => $function,
            ] + $parameters, $write);

            $result = $data['uapi'] ?? null;

            if (! is_array($result)) {
                throw ApiException::unexpectedResponse($name, 200);
            }

            return self::uapiData($name, $result);
        }

        $payload = $this->send('execute/' . $module . '/' . $function, [], self::encode($parameters), $write, $name);

        return self::uapiData($name, $payload);
    }

    /**
     * Calls a legacy cPanel API 2 function (e.g. CronJob) and returns its "data" member.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     * @return array<array-key, mixed>
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @throws UnsupportedOperation When a WHM connection has no target account.
     */
    public function api2(string $module, string $function, array $parameters = [], bool $write = false): array
    {
        $name = $module . '::' . $function;
        $account = $this->account() ?? throw UnsupportedOperation::requiresAccount($name);

        $payload = $this->send('json-api/cpanel', [], [
            'cpanel_jsonapi_user' => $account,
            'cpanel_jsonapi_apiversion' => '2',
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ] + self::encode($parameters), $write, $name);

        $result = $payload['cpanelresult'] ?? null;

        if (! is_array($result)) {
            throw ApiException::unexpectedResponse($name, 200);
        }

        $event = is_array($result['event'] ?? null) ? $result['event'] : [];
        $error = self::scalar($result['error'] ?? null);

        if ($error !== '' || (array_key_exists('result', $event) && (int) self::scalar($event['result']) !== 1)) {
            throw ApiException::failed($name, $error === '' ? [] : [$error], 200, $payload);
        }

        $data = $result['data'] ?? [];

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * Converts parameters into the flat string map understood by cPanel.
     *
     * Booleans become 1 or 0, null values are dropped and lists are expanded
     * into numbered names (name-1, name-2, ...); a single-item list keeps the plain name.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     * @return array<string, string>
     */
    public static function encode(array $parameters): array
    {
        $encoded = [];

        foreach ($parameters as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (! is_array($value)) {
                $encoded[$name] = self::scalarValue($value);

                continue;
            }

            $values = $value;

            if (count($values) === 1) {
                $encoded[$name] = self::scalarValue($values[0]);

                continue;
            }

            foreach ($values as $index => $item) {
                $encoded[$name . '-' . ($index + 1)] = self::scalarValue($item);
            }
        }

        return $encoded;
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private static function uapiData(string $name, array $result): mixed
    {
        // The CLI and LiveAPI response shape wraps the envelope in "result".
        if (! array_key_exists('status', $result) && is_array($result['result'] ?? null)) {
            $result = $result['result'];
        }

        if (! array_key_exists('status', $result)) {
            throw ApiException::unexpectedResponse($name, 200);
        }

        if ((int) self::scalar($result['status']) !== 1) {
            $errors = [];

            foreach (is_array($result['errors'] ?? null) ? $result['errors'] : [] as $error) {
                if (is_scalar($error)) {
                    $errors[] = (string) $error;
                }
            }

            throw ApiException::failed($name, $errors, 200, $result);
        }

        return $result['data'] ?? null;
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $parameters
     * @return array<array-key, mixed>
     */
    private function send(string $path, array $query, array $parameters, bool $write, string $function): array
    {
        try {
            $response = $write
                ? $this->request()->asForm()->post($path . ($query === [] ? '' : '?' . http_build_query($query)), $parameters)
                : $this->request()->get($path, $query + $parameters);
        } catch (ConnectionException $exception) {
            throw ApiException::transport($function, $exception);
        }

        return $this->decode($function, $response);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $function, Response $response): array
    {
        if (in_array($response->status(), [401, 403], true) || $response->redirect()) {
            throw AuthenticationException::rejected($this->config->name, $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw ApiException::unexpectedResponse($function, $response->status());
        }

        if ($response->serverError() && ! isset($payload['metadata']) && ! isset($payload['status'])) {
            throw ApiException::unexpectedResponse($function, $response->status());
        }

        return $payload;
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function scalarValue(bool|float|int|string $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->createPendingRequest()
            ->baseUrl($this->config->host . '/')
            ->withHeaders(['Authorization' => $this->config->authorization()])
            ->acceptJson()
            ->withUserAgent('beziworld-laravel-cpanel/1.0')
            ->withoutRedirecting()
            ->withOptions(['verify' => $this->config->verify])
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);
    }
}
