<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Thijssensoftware\RequestId\RequestIdContext;

beforeEach(function (): void {
    Route::get('/rid', fn (): array => [
        'id' => app(RequestIdContext::class)->current(),
    ]);
});

it('generates an id when the request carries no header', function (): void {
    $response = $this->get('/rid');

    $id = $response->json('id');

    expect($id)->toBeString()
        ->and(Str::isUlid($id))->toBeTrue()
        ->and($response->headers->get('X-Request-Id'))->toBe($id);
});

it('adopts a valid incoming id', function (): void {
    $incoming = (string) Str::ulid();

    $response = $this->withHeader('X-Request-Id', $incoming)->get('/rid');

    expect($response->json('id'))->toBe($incoming)
        ->and($response->headers->get('X-Request-Id'))->toBe($incoming);
});

it('adopts a valid incoming uuid as well as a ulid', function (): void {
    $incoming = (string) Str::uuid();

    expect($this->withHeader('X-Request-Id', $incoming)->get('/rid')->json('id'))
        ->toBe($incoming);
});

it('replaces a junk incoming id rather than trusting it', function (): void {
    $junk = '<script>alert(1)</script>';

    $id = $this->withHeader('X-Request-Id', $junk)->get('/rid')->json('id');

    expect($id)->not->toBe($junk)
        ->and(Str::isUlid($id))->toBeTrue();
});

it('overwrites the incoming header so downstream code sees the canonical id', function (): void {
    Route::get('/rid-header', fn (): array => [
        'header' => request()->headers->get('X-Request-Id'),
        'id' => app(RequestIdContext::class)->current(),
    ]);

    $response = $this->withHeader('X-Request-Id', 'not-valid')->get('/rid-header');

    expect($response->json('header'))->toBe($response->json('id'));
});

it('can be told not to echo the id back', function (): void {
    config()->set('request-id.respond_with_header', false);

    expect($this->get('/rid')->headers->has('X-Request-Id'))->toBeFalse();
});

it('puts the id into the context', function (): void {
    $id = $this->get('/rid')->json('id');

    Route::get('/rid-context', fn (): array => ['ctx' => Context::get('request_id')]);

    expect($this->withHeader('X-Request-Id', $id)->get('/rid-context')->json('ctx'))
        ->toBe($id);
});

it('puts the id onto the log records the app writes', function (): void {
    $captured = [];

    Log::listen(function (MessageLogged $event) use (&$captured): void {
        $captured[] = $event->context;
    });

    Route::get('/rid-log', function (): array {
        Log::info('something happened');

        return ['id' => app(RequestIdContext::class)->current()];
    });

    $id = $this->get('/rid-log')->json('id');

    expect($captured)->not->toBeEmpty()
        ->and($captured[0]['request_id'] ?? null)->toBe($id);
});
