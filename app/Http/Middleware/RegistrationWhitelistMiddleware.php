<?php

namespace App\Http\Middleware;

use App\Services\Auth\RegistrationWhitelistService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationWhitelistMiddleware
{
    public function __construct(
        protected RegistrationWhitelistService $whitelist
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $email = (string) $request->input('email', '');

        if (! empty($email) && ! $this->whitelist->isEmailAllowed($email)) {
            abort(403, 'Registration is restricted to authorized corporate emails.');
        }

        return $next($request);
    }
}
