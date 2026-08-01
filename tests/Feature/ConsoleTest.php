<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Thijssensoftware\RequestId\RequestId;

// Laravel skips rerouting the Symfony console events to their Laravel
// counterparts while running unit tests (see Foundation\Console\Kernel's
// constructor), so CommandStarting never fires without this trait. Production
// invocations are unaffected.
uses(WithConsoleEvents::class);

it('assigns a fresh id per command invocation', function (): void {
    $seen = [];

    Artisan::command('rid:probe', function () use (&$seen): void {
        $seen[] = app(RequestId::class)->get();
    });

    $this->artisan('rid:probe')->assertOk();
    $this->artisan('rid:probe')->assertOk();

    expect($seen)->toHaveCount(2)
        ->and(Str::isUlid($seen[0]))->toBeTrue()
        ->and($seen[0])->not->toBe($seen[1]);
});

it('publishes the command id to the context', function (): void {
    $seen = null;

    Artisan::command('rid:ctx', function () use (&$seen): void {
        $seen = Context::get('request_id');
    });

    $this->artisan('rid:ctx')->assertOk();

    expect($seen)->toBeString()
        ->and(Str::isUlid((string) $seen))->toBeTrue();
});
