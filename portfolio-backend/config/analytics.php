<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 — Analytics Data API v1
    |--------------------------------------------------------------------------
    |
    | Read-only configuration for the admin dashboard's GA4 summary widget.
    | The service only ever calls runReport() for aggregate traffic metrics;
    | nothing here grants write access to the property.
    |
    | Both values come from environment configuration — NEVER hardcode them:
    |
    |   Production (Render): the service-account JSON is mounted as a Secret
    |   File and its path is exported as GOOGLE_APPLICATION_CREDENTIALS, while
    |   GA4_PROPERTY_ID is set as a plain environment variable on the service.
    |
    |   Local development: put both values in .env and place the credential
    |   JSON file at the path GOOGLE_APPLICATION_CREDENTIALS points to
    |   (default: storage/credentials/ga4-service-account.json, gitignored).
    |
    | Until GA4_PROPERTY_ID is set, GA4Service reports "ga4_unavailable"
    | instead of attempting an API call, so the app never 500s over missing
    | analytics configuration.
    */

    'property_id' => env('GA4_PROPERTY_ID'),

    /*
    | Path to the Google service-account JSON key file. The official client
    | reads this to authenticate; the file itself must stay outside version
    | control (it is gitignored). On Render it lives in the Secret Files
    | mount; locally under storage/credentials/.
    */
    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),

    /*
    | How long the dashboard summary is cached, in seconds. GA4 aggregates
    | change at most daily for the trailing window, and every admin page load
    | would otherwise burn a quota'd API request on numbers a day stale.
    */
    'cache_ttl_seconds' => env('GA4_CACHE_TTL', 3600),

    /*
    | Trailing reporting window for the summary, in days.
    */
    'period_days' => env('GA4_PERIOD_DAYS', 30),

    /*
    | Bounded HTTP timeout for the API transport so a hung Google endpoint
    | cannot hold an admin request open — the same bounded-timeout approach
    | HealthCheckService takes for its storage probe.
    */
    'timeout_seconds' => env('GA4_TIMEOUT_SECONDS', 10),

];
