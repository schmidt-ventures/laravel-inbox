<?php

namespace SchmidtVentures\Inbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SchmidtVentures\Inbox\Inbox;
use Symfony\Component\HttpFoundation\Response;

class EnsureInboxEnabled
{
    public function __construct(private Inbox $inbox)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->inbox->captures()) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
