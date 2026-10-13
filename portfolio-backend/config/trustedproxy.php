<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Every request to this API arrives through a proxy chain, so REMOTE_ADDR is
| the address of the last hop — not the visitor. Without this list Laravel
| never trusts X-Forwarded-For, `$request->ip()` returns the same proxy
| address for every visitor, and every IP-keyed rate limiter collapses onto
| one global bucket: the first few visitors exhaust it and everyone else is
| refused. That is the production incident this file exists to prevent.
|
| The real chain, outermost first:
|
|   browser -> Cloudflare (proxying api.hasibulalam.com)
|           -> Render's load balancer
|           -> this container (php artisan serve)
|
| So two proxies have to be trusted, and they are named differently on
| purpose:
|
|   * Cloudflare's published egress ranges (https://www.cloudflare.com/ips/)
|     — these addresses are known up front and can be listed.
|   * 'REMOTE_ADDR' — a literal Laravel replaces at request time with the
|     address the request actually came from. That is Render's load balancer,
|     whose address is not knowable here, and trusting "whoever is calling
|     directly" is exactly right for the hop that terminates the connection.
|
| With both trusted, Symfony walks X-Forwarded-For from the right, skips the
| entries belonging to our own proxies, and returns the first address that is
| not ours — the visitor's. A client-supplied X-Forwarded-For cannot win that
| walk, because the value a proxy appends is always further right than the
| value the client sent.
|
| Deliberately NOT '*' (trust-everything): with Cloudflare in front, XFF is
| appended to, not replaced, so trusting every hop makes the leftmost — the
| client's own, forgeable — value the answer. That would let anyone mint a new
| bucket per request and walk straight through the login and form limits.
|
| Override without touching this file by setting TRUSTED_PROXIES in the
| environment (the Render dashboard): a comma-separated list of IPs/CIDRs, or
| 'REMOTE_ADDR' to trust only the load balancer in front of the container.
| Laravel reads it at config-cache time, so the value must be present when the
| container boots.
|
| If Cloudflare's ranges ever change and this list goes stale, the failure is
| degraded rather than broken: requests from an unlisted Cloudflare range
| resolve to that range's address, so those visitors share one bucket again —
| the same behaviour as trusting nothing. Widening the list (or setting
| TRUSTED_PROXIES=*) restores per-visitor accounting.
|
*/

$cloudflare = [
    // IPv4 — https://www.cloudflare.com/ips-v4
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22',
    // IPv6 — https://www.cloudflare.com/ips-v6
    '2400:cb00::/32',
    '2606:4700::/32',
    '2803:f800::/32',
    '2405:b500::/32',
    '2405:8100::/32',
    '2a06:98c0::/29',
    '2c0f:f248::/32',
];

return [

    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))) ?: [...$cloudflare, 'REMOTE_ADDR'],

];
