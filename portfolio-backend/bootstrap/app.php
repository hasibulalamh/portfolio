<?php

use App\Http\Middleware\Cors;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS policy enforcement. Prepended so this first-party layer runs
        // outermost and has the final word on ACAO/credentials; Laravel's
        // built-in HandleCors middleware (framework default, runs inside this)
        // still answers preflight OPTIONS requests. Allowlist lives in
        // config/cors.php — the single source of truth for both layers.
        $middleware->prepend(Cors::class);

        // Global security headers: strip X-Powered-By and add nosniff / CORP /
        // CSP. Runs on every response, including exceptions rendered by the
        // handlers below and the /up health check.
        $middleware->append(SecurityHeaders::class);

        // This is an API-only backend: there is no named `login` route to send
        // guests to. Laravel's default is redirectGuestsTo(fn () => route('login')),
        // which throws RouteNotFoundException while building the redirect — so an
        // unauthenticated request that did not send `Accept: application/json`
        // got a 500 (leaking a stack trace under APP_DEBUG) instead of a 401.
        //
        // Returning null keeps AuthenticationException redirect-less, so the
        // handler below renders the normal 401 envelope for every client.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Map framework exceptions onto the {data, message, errors} envelope so
        // failures have the same shape as successes. Laravel's own JSON for a
        // 422 omits `data`, and its 404 omits both `data` and `errors`.

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::validationError($e->errors(), $e->getMessage());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::unauthorized();
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::notFound('The requested record does not exist.');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::notFound('The requested endpoint does not exist.');
        });
    })->create();
