<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRateLimitedRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::withoutContext();

        $rayId = substr(trim((string) $request->header('CF-Ray')), 0, 120);
        if ($rayId !== '') {
            Log::withContext(['cf_ray_id' => $rayId]);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 429) {
            Log::notice('Request rate limited', [
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }
}
