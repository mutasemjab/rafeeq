<?php

namespace App\Jobs\Concerns;

use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;

trait DispatchesWithSyncFallback
{
    public static function dispatchWithSyncFallback(...$arguments): void
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = app(Dispatcher::class);
        $job = new static(...$arguments);

        try {
            $dispatcher->dispatch($job);
        } catch (Throwable $e) {
            report($e);

            $dispatcher->dispatchSync($job);
        }
    }
}
