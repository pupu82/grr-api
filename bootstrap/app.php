<?php

use App\Enums\ErrorCode;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RequireRole::class,
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        // Every expected API error carries a stable machine-readable `code`
        // next to the human-readable message. Clients branch on the code only.
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::Unauthenticated->response()
            : null);

        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::Forbidden->response()
            : null);

        $exceptions->render(fn (ValidationException $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::ValidationFailed->response($exception->getMessage(), ['errors' => $exception->errors()])
            : null);

        $exceptions->render(fn (ThrottleRequestsException $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::TooManyRequests->response()->withHeaders($exception->getHeaders())
            : null);

        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::NotFound->response()
            : null);

        $exceptions->render(fn (HttpExceptionInterface $exception, Request $request) => $wantsJson($request)
            ? ErrorCode::fromStatus($exception->getStatusCode())->response(
                $exception->getMessage() !== '' ? $exception->getMessage() : null,
            )
            : null);
    })->create();
