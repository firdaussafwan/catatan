<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A paused account is read-only: every route that creates or edits posts goes through here. */
class EnsureCanWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($reason = $request->user()?->pauseReason()) {
            return back()->withInput()->withErrors(['body' => $reason]);
        }

        return $next($request);
    }
}
