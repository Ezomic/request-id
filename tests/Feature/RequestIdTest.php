<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Thijssensoftware\RequestId\RequestId;

it('generates lazily and keeps the same value', function (): void {
    $id = new RequestId;

    expect($id->hasValue())->toBeFalse();

    $first = $id->get();

    expect($id->hasValue())->toBeTrue()
        ->and($id->get())->toBe($first);
});

it('accepts a valid value and rejects anything else', function (string $value, bool $accepted): void {
    $id = new RequestId;

    $result = $id->accept($value);

    expect($result === $value)->toBe($accepted)
        ->and(RequestId::isValid($result))->toBeTrue();
})->with([
    'ulid' => [(string) Str::ulid(), true],
    'uuid' => ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', true],
    'empty' => ['', false],
    'sql' => ["' OR 1=1--", false],
    'newline injection' => ["abc\nfake log line", false],
    'over long' => [str_repeat('a', 500), false],
]);

it('generates when given null', function (): void {
    $id = new RequestId;

    expect(RequestId::isValid($id->accept(null)))->toBeTrue();
});

it('is shared as a singleton', function (): void {
    expect(app(RequestId::class))->toBe(app(RequestId::class));
});
