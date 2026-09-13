<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }
}
