<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Ensure the authenticated user has one of the allowed roles.
     *
     * Usage in routes:
     *   ->middleware('role:admin')           — admin only
     *   ->middleware('role:admin,manager')   — admin or manager
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  One or more role values (must match Role enum values)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ErrorCode::Unauthenticated->response();
        }

        $allowedRoles = array_map(
            fn (string $role) => Role::from($role),
            $roles,
        );

        if (! in_array($user->role, $allowedRoles, strict: true)) {
            return ErrorCode::Forbidden->response();
        }

        return $next($request);
    }
}
