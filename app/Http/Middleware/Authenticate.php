<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            if ($this->isAdminRequest($request)) {
                return route('admin.showlogin');
            }

            return route('front.home');
        }
    }

    private function isAdminRequest(Request $request): bool
    {
        if ($request->routeIs('admin.*')) {
            return true;
        }

        $segments = $request->segments();

        if ($segments === []) {
            return false;
        }

        if ($segments[0] === 'admin') {
            return true;
        }

        return ($segments[1] ?? null) === 'admin';
    }
}
