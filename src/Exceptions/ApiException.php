<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Exceptions;

use Throwable;

/**
 * Raised when a UAPI, WHM API 1 or API 2 function fails or the server cannot be reached.
 */
final class ApiException extends CpanelException
{
    /**
     * @param list<string> $errors
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        string $message,
        private readonly string $function,
        private readonly array $errors = [],
        private readonly ?int $httpStatus = null,
        private readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param list<string> $errors
     * @param array<array-key, mixed> $payload
     */
    public static function failed(string $function, array $errors, int $httpStatus, array $payload): self
    {
        $errors = $errors === [] ? ['unknown error'] : $errors;

        return new self(
            sprintf('cPanel %s failed: %s', $function, implode('; ', $errors)),
            $function,
            $errors,
            $httpStatus,
            $payload,
        );
    }

    public static function transport(string $function, Throwable $previous): self
    {
        return new self(sprintf('Could not connect to cPanel for %s: %s', $function, $previous->getMessage()), $function, previous: $previous);
    }

    public static function unexpectedResponse(string $function, int $httpStatus): self
    {
        return new self(sprintf('cPanel %s returned an unexpected response (HTTP %d).', $function, $httpStatus), $function, httpStatus: $httpStatus);
    }

    /**
     * Function name, e.g. "createacct" or "Email::add_pop".
     */
    public function function(): string
    {
        return $this->function;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
