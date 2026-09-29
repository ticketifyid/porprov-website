<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi akses berdasarkan role. Admin selalu lolos, apa pun role yang diminta.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if ($user->isAdmin()) {
            return $next($request);
        }

        $allowed = collect($roles)->contains(fn (string $role) => $user->role === UserRole::from($role));

        abort_unless($allowed, 403);

        return $next($request);
    }
}
