<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class TrackVisitor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Only track for web requests (not admin or api)
        if ($request->is('admin/*') || $request->is('api/*')) {
            return $next($request);
        }

        $visitorId = Cookie::get('visitor_id');

        if (!$visitorId) {
            $visitorId = (string) Str::uuid();
        }

        $today = now()->toDateString();
        $ipAddress = $request->ip();

        // Check if visitor exists for today
        $visitor = Visitor::where('visitor_id', $visitorId)
                          ->where('visit_date', $today)
                          ->first();

        if (!$visitor) {
            // Create new visitor record
            Visitor::create([
                'visitor_id' => $visitorId,
                'ip_address' => $ipAddress,
                'visit_date' => $today,
                'visits' => 1
            ]);
        }

        return $next($request)->withCookie(cookie('visitor_id', $visitorId, 60)); // 1 hour
    }
}
