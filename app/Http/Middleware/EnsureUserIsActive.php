<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Ensure the authenticated user has an active account.
     *
     * Rejects pending and inactive users with a 403 response.
     * Must run after auth:sanctum so $request->user() is available.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->status->canLogin()) {
            return ErrorCode::forAccountStatus($user?->status)->response();
        }

        return $next($request);
    }
}
