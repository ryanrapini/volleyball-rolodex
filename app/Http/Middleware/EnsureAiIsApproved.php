<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * New accounts have no AI until an admin approves them, so this is the gate in
 * front of the assistant. It is deliberately separate from the UI, which simply
 * hides the button — the rule has to hold for a hand-made request too.
 */
class EnsureAiIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->canUseAi()) {
            return $next($request);
        }

        $message = 'The AI assistant is not switched on for this account yet.';

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
