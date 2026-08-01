<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId;

use Illuminate\Support\Str;

/**
 * Holds the correlation id for the current request, job or command.
 *
 * Registered as a singleton, so anything resolving it during one unit of work
 * sees the same value.
 */
class RequestId
{
    private ?string $value = null;

    /**
     * The current id, generating one if nothing has set it yet.
     *
     * Lazily generating matters: a command invoked outside the HTTP stack, or a
     * job picked up before the listener runs, still gets a usable id rather than
     * a null that has to be handled at every call site.
     */
    public function get(): string
    {
        return $this->value ??= self::generate();
    }

    public function set(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Accept an incoming value only if it is one we would have produced.
     *
     * An arbitrary client-supplied string ends up in log files, alert emails and
     * the flare UI, so it is validated rather than trusted.
     */
    public function accept(?string $value): string
    {
        $this->value = $value !== null && self::isValid($value)
            ? $value
            : self::generate();

        return $this->value;
    }

    public function forget(): void
    {
        $this->value = null;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public static function generate(): string
    {
        return (string) Str::ulid();
    }

    public static function isValid(string $value): bool
    {
        return Str::isUlid($value) || Str::isUuid($value);
    }
}
