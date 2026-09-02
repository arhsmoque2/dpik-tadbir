<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginBypassMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('auth.enabled', true)) {
            if (Auth::guard('web')->check() || Filament::auth()->check()) {
                return $next($request);
            }

            $user = User::query()->where('role', 'super_admin')->first()
                ?? User::query()->first()
                ?? User::firstOrCreate(
                    ['email' => 'admin@dpik.com.my'],
                    [
                        'first_name' => 'Admin',
                        'last_name' => 'DPIK',
                        'password' => bcrypt('password'),
                        'role' => 'super_admin',
                    ]
                );

            $guard = Filament::auth();
            $guard->login($user);
            Auth::guard('web')->login($user);
            $request->setUserResolver(fn () => $user);

            if ($request->is('admin/login')) {
                return redirect('/admin');
            }
        }

        return $next($request);
    }
}
