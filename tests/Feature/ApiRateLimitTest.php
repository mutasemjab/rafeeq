<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    public function test_api_throttle_resolves_the_api_guard_before_route_authentication(): void
    {
        $limiter = RateLimiter::limiter('api');
        $request = Request::create('/api/v1/chat/usage', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);
        $first = new User();
        $first->id = 10;
        $second = new User();
        $second->id = 11;
        // The default web guard is anonymous at this middleware stage.
        $request->setUserResolver(fn ($guard = null) => $guard === 'user-api' ? $first : null);
        $firstLimit = $limiter($request);
        $request->setUserResolver(fn ($guard = null) => $guard === 'user-api' ? $second : null);
        $secondLimit = $limiter($request);
        $this->assertNotSame($firstLimit->key, $secondLimit->key);
        $this->assertSame(60, $firstLimit->maxAttempts);
        $request->setUserResolver(fn () => null);
        $this->assertSame('ip:192.0.2.1', $limiter($request)->key);
    }
}
