<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Thijssensoftware\RequestId\RequestId;
use Thijssensoftware\RequestId\RequestIdContext;
use Thijssensoftware\RequestId\Tests\Fixtures\NoopJob;

it('writes the current id into the queued job payload', function (): void {
    config()->set('queue.default', 'database');

    Schema::create('jobs', function ($table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    $id = app(RequestIdContext::class)->begin((string) Str::ulid());

    dispatch(new NoopJob);

    $raw = DB::table('jobs')->value('payload');

    expect($raw)->toBeString();

    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $raw, true);

    expect($payload['request_id'] ?? null)->toBe($id);
});

it('adopts the id from a job payload when the worker picks it up', function (): void {
    $id = (string) Str::ulid();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['request_id' => $id]);

    event(new JobProcessing('database', $job));

    expect(app(RequestId::class)->get())->toBe($id);
});

it('generates a fresh id when a job payload carries none', function (): void {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);

    event(new JobProcessing('database', $job));

    expect(Str::isUlid(app(RequestId::class)->get()))->toBeTrue();
});

it('does not adopt a junk id from a job payload', function (): void {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['request_id' => 'not-a-real-id']);

    event(new JobProcessing('database', $job));

    expect(app(RequestId::class)->get())->not->toBe('not-a-real-id');
});
