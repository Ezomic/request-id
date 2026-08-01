<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Thijssensoftware\RequestId\Http\Middleware\AssignRequestId;

class RequestIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/request-id.php', 'request-id');

        $this->app->singleton(RequestId::class);
        $this->app->singleton(RequestIdContext::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/request-id.php' => config_path('request-id.php'),
        ], 'request-id-config');

        $this->registerMiddleware();
        $this->registerQueuePropagation();
        $this->registerConsoleHook();
        $this->registerOutboundForwarding();
    }

    /**
     * Prepended, not appended: the id has to exist before anything else in the
     * stack gets a chance to log, throw or dispatch.
     */
    private function registerMiddleware(): void
    {
        $this->app->booted(function (): void {
            $kernel = $this->app->make(HttpKernel::class);

            if ($kernel instanceof FoundationHttpKernel) {
                $kernel->prependMiddleware(AssignRequestId::class);
            }
        });
    }

    /**
     * Both halves of the queue hop are explicit.
     *
     * Context carries the id on its own in Laravel 11+, but only once it has
     * been rehydrated, which happens after JobProcessing fires. Writing the id
     * into the payload ourselves means the listener below has something to read
     * no matter how the framework's own dehydration is ordered.
     */
    private function registerQueuePropagation(): void
    {
        $key = $this->contextKey();

        Queue::createPayloadUsing(function () use ($key): array {
            return [$key => $this->app->make(RequestIdContext::class)->current()];
        });

        Event::listen(function (JobProcessing $event) use ($key): void {
            $payload = $event->job->payload();

            $incoming = $payload[$key] ?? null;

            $this->app->make(RequestIdContext::class)
                ->begin(is_string($incoming) ? $incoming : null);
        });
    }

    /**
     * A fresh id per command invocation, so a scheduled task that fails is
     * traceable back to the run that produced it.
     */
    private function registerConsoleHook(): void
    {
        Event::listen(function (CommandStarting $event): void {
            $this->app->make(RequestId::class)->forget();

            $this->app->make(RequestIdContext::class)->begin();
        });
    }

    /**
     * Forwarding is what makes a cross-app trace possible, and the allowlist is
     * what stops an internal correlation id being handed to third parties.
     */
    private function registerOutboundForwarding(): void
    {
        PendingRequest::macro('withRequestId', function () {
            /** @var PendingRequest $this */
            return $this->withHeader(
                (string) config('request-id.header', 'X-Request-Id'),
                app(RequestIdContext::class)->current(),
            );
        });

        if (config('request-id.forward.enabled', true) !== true) {
            return;
        }

        $header = (string) config('request-id.header', 'X-Request-Id');

        Http::globalRequestMiddleware(function (RequestInterface $request) use ($header): RequestInterface {
            if ($request->hasHeader($header)) {
                return $request;
            }

            if (! $this->shouldForwardTo($request->getUri()->getHost())) {
                return $request;
            }

            return $request->withHeader(
                $header,
                $this->app->make(RequestIdContext::class)->current(),
            );
        });
    }

    private function shouldForwardTo(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        /** @var array<int, string> $patterns */
        $patterns = (array) config('request-id.forward.hosts', []);

        return Str::is($patterns, $host);
    }

    private function contextKey(): string
    {
        $key = config('request-id.log_context_key', 'request_id');

        return is_string($key) ? $key : 'request_id';
    }
}
