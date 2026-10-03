<?php

namespace Tests\Unit;

use App\Jobs\ProcessChatAttachmentJob;
use App\Services\Documents\PrivateFileProcessingDispatcher;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PrivateFileProcessingDispatcherTest extends TestCase
{
    public function test_queue_outage_defers_processing_instead_of_extracting_inside_upload(): void
    {
        config(['queue.default' => 'database']);
        $bus = Mockery::mock(Dispatcher::class);
        $bus->shouldReceive('dispatch')->once()->with(Mockery::type(ProcessChatAttachmentJob::class))
            ->andThrow(new RuntimeException('Queue connection unavailable'));
        $bus->shouldReceive('dispatchAfterResponse')->once()->with(Mockery::type(ProcessChatAttachmentJob::class));
        $bus->shouldNotReceive('dispatchSync');
        $this->app->instance(Dispatcher::class, $bus);

        (new PrivateFileProcessingDispatcher())->dispatch(ProcessChatAttachmentJob::class, 42);
    }
}
