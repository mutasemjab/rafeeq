<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Log;
use Throwable;

class PrivateFileProcessingDispatcher
{
    public function dispatch(string $jobClass, int $id): void
    {
        if (config('queue.default') === 'sync') {
            $jobClass::dispatchAfterResponse($id);

            return;
        }
        try {
            $jobClass::dispatch($id);
        } catch (Throwable $exception) {
            // A queue outage must not turn upload into a blocking OCR/API call.
            Log::warning('private_file.queue_unavailable', ['job' => $jobClass, 'exception' => $exception::class]);
            $jobClass::dispatchAfterResponse($id);
        }
    }
}
