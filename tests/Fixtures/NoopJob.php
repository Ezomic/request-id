<?php

declare(strict_types=1);

namespace Thijssensoftware\RequestId\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NoopJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}
