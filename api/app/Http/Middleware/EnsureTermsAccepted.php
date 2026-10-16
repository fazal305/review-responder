<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasAcceptedTerms()) {
            return response()->json([
                'message' => 'Please confirm the terms before continuing.',
                'code' => 'terms_required',
            ], 403);
        }

        return $next($request);
    }
}
