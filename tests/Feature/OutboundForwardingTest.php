<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Thijssensoftware\RequestId\RequestIdContext;

it('forwards the id to an allowlisted host', function (): void {
    Http::fake();

    $id = app(RequestIdContext::class)->current();

    Http::get('https://chronos.thijssensoftware.nl/api/events');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Request-Id') === [$id]);
});

it('forwards to a nested arbo host', function (): void {
    Http::fake();

    $id = app(RequestIdContext::class)->current();

    Http::get('https://employers.arbo.thijssensoftware.nl/api/cases');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Request-Id') === [$id]);
});

it('does not forward the id to a third party', function (): void {
    Http::fake();

    app(RequestIdContext::class)->current();

    Http::get('https://api.stripe.com/v1/charges');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Request-Id') === []);
});

it('leaves an explicitly set header alone', function (): void {
    Http::fake();

    Http::withHeader('X-Request-Id', 'explicitly-set')
        ->get('https://chronos.thijssensoftware.nl/api/events');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Request-Id') === ['explicitly-set']);
});

it('attaches the id on demand through the macro', function (): void {
    Http::fake();

    $id = app(RequestIdContext::class)->current();

    Http::withRequestId()->get('https://api.stripe.com/v1/charges');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Request-Id') === [$id]);
});
