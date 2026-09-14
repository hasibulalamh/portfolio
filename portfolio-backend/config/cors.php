<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The two Next.js apps that consume this API run on their own origins, so
    | every browser request to /api/* is cross-origin and needs these headers.
    |
    |   portfolio-frontend (public site) — FRONTEND_URL  (default http://localhost:3000)
    |   portfolio-admin    (admin panel) — ADMIN_URL     (default http://localhost:3001)
    |
    | The policy is applied in two layers:
    |
    |   1. Laravel's built-in HandleCors middleware (framework default) answers
    |      preflight OPTIONS requests and adds the CORS headers, reading this
    |      config file.
    |   2. App\Http\Middleware\Cors (registered outermost in bootstrap/app.php)
    |      enforces the same allowlist on every actual response, so the policy
    |      has a first-party seam in code and a disallowed origin can never
    |      receive CORS headers, even if this config later drifts.
    |
    | This file is the single source of truth for the allowlist — both layers
    | consume it, so they cannot disagree.
    |
    | `supports_credentials` is true because portfolio-admin's axios client is
    | configured with `withCredentials: true`. That also means a wildcard
    | origin is not allowed — browsers reject `Access-Control-Allow-Origin: *`
    | on credentialed requests — so every origin has to be listed explicitly.
    | Production origins come from FRONTEND_URL / ADMIN_URL; there are no
    | hardcoded production domains in this file.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Explicit rather than '*' so a preflight response advertises a fixed set
    // instead of echoing the requestor's method back.
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('ADMIN_URL', 'http://localhost:3001'),

        // Next.js sometimes picks the next free port when 3000/3001 are taken,
        // and 127.0.0.1 vs localhost are different origins.
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
    ]))),

    'allowed_origins_patterns' => [],

    // Headers the API actually reads. The admin client sends Authorization
    // (Sanctum bearer token) and Content-Type (JSON bodies).
    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // Cache preflight answers for a day instead of re-checking every request.
    'max_age' => 86400,

    'supports_credentials' => true,

];
