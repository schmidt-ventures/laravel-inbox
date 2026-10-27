<?php

namespace SchmidtVentures\Inbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SchmidtVentures\Inbox\MessageStore;
use Symfony\Component\HttpFoundation\Response;

class VerifyInboxToken
{
    public function __construct(private MessageStore $messages)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $token = $request->input('inbox_token');

        if (! $this->messages->tokenMatches(is_string($token) ? $token : null)) {
            abort(419);
        }

        return $next($request);
    }
}
