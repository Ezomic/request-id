<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Thijssensoftware\RequestId\RequestIdContext;

/**
 * Assigns the correlation id for the current request.
 *
 * Prepended to the global stack by the service provider, so it runs before
 * anything that might log, throw or dispatch.
 */
class AssignRequestId
{
    public function __construct(private readonly RequestIdContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $this->header();

        $id = $this->context->begin($request->headers->get($header));

        // Overwrite rather than leave the client's value: downstream code reading
        // the header directly should see the same id that was logged.
        $request->headers->set($header, $id);

        $response = $next($request);

        if (config('request-id.respond_with_header', true) === true) {
            $response->headers->set($header, $id);
        }

        return $response;
    }

    private function header(): string
    {
        $header = config('request-id.header', 'X-Request-Id');

        return is_string($header) ? $header : 'X-Request-Id';
    }
}
