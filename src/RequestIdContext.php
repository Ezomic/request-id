<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Starts a unit of work and publishes its correlation id everywhere it needs
 * to be visible.
 *
 * One place rather than three: the HTTP middleware, the queue listener and the
 * console listener all begin a unit of work in exactly the same way, and the
 * only thing that differs is where the incoming id comes from.
 */
class RequestIdContext
{
    public function __construct(private readonly RequestId $requestId) {}

    /**
     * Begin a unit of work, adopting $incoming when it is a value we trust.
     */
    public function begin(?string $incoming = null): string
    {
        $id = $this->requestId->accept($incoming);

        $this->publish($id);

        return $id;
    }

    /**
     * The current id, publishing it first if this is the first time it's asked
     * for (a command that ran before any listener fired, for instance).
     */
    public function current(): string
    {
        $existed = $this->requestId->hasValue();

        $id = $this->requestId->get();

        if (! $existed) {
            $this->publish($id);
        }

        return $id;
    }

    public function key(): string
    {
        $key = config('request-id.log_context_key', 'request_id');

        return is_string($key) ? $key : 'request_id';
    }

    /**
     * Log::shareContext puts the id on every channel, including ones resolved
     * later. Context makes it readable by application code and carries it into
     * queued jobs on its own.
     */
    private function publish(string $id): void
    {
        Log::shareContext([$this->key() => $id]);

        Context::add($this->key(), $id);
    }
}
