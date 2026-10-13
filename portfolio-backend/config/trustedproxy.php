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
| refused. That is the production incident this file exists to prevent — and
| the one it kept causing while the load-balancer hop below went unnamed.
|
| The real chain, outermost first:
|
|   visitor -> Render's edge (Cloudflare egress ranges, e.g. 172.71.247.163)
|           -> Render's load balancer (private 10.x, e.g. 10.199.220.25)
|           -> a local hop inside the container (127.0.0.1)
|           -> this process (php artisan serve)
|
| The TCP connection that terminates at the container always comes from the
| local 127.0.0.1 hop, so REMOTE_ADDR is that loopback address — and the raw
| X-Forwarded-For the app sees ends with the load balancer's private address:
|
|   X-Forwarded-For:  160.25.9.210, 172.71.247.163, 10.199.220.25
|   REMOTE_ADDR:      127.0.0.1
|
| Symfony resolves the client by walking X-Forwarded-For from the right —
| starting at REMOTE_ADDR — and skipping every entry that is one of ours; the
| first entry that is *not* ours wins. Is that address the visitor? Only if
| every hop in front of them is listed here, so all three are:
|
|   * '127.0.0.0/8' — the local hop inside the container. A loopback *range*
|     rather than the literal 'REMOTE_ADDR': that token means "trust whoever
|     opened the connection", so any request that reached the container
|     directly from a public address would be trusted and could forge its own
|     X-Forwarded-For. The loopback range trusts only the local hop, which is
|     the only address that actually connects.
|
|   * '10.0.0.0/8' — Render's load balancer. This is the hop that was missing.
|     With it unlisted, Symfony stopped at the balancer's private address, so
|     `$request->ip()` returned a 10.199.x.x node for every visitor and the
|     buckets were keyed on whichever balancer node answered. That also
|     explains X-RateLimit-Remaining appearing to rise again: a different node
|     meant a different, barely-used bucket.
|
|   * Cloudflare's published egress ranges (https://www.cloudflare.com/ips/)
|     — Render's edge sits in these (the 172.71.x addresses). Because the
|     domain is DNS-only they may never be the immediate peer, but they are
|     kept so the same list still works if the API is ever put behind
|     Cloudflare.
|
| Trusting 10.0.0.0/8 and 127.0.0.0/8 is safe even though neither is the
| visitor's address: both are private/loopback space, unroutable from the
| public internet, so a client can never *be* one of them. The visitor is the
| first address walking X-Forwarded-For from the right that is not ours, and a
| client-forged prefix sits to the left of that, so it cannot win — the value a
| proxy appends is always further right than anything the client sent.
|
| Deliberately NOT '*' (trust-everything): a client can send its own
| X-Forwarded-For, and trusting every hop makes the leftmost — the client's
| own, forgeable — value the answer. That would let anyone mint a new bucket
| per request and walk straight through the login and form limits.
|
| Override without touching this file by setting TRUSTED_PROXIES in the
| environment (the Render dashboard): a comma-separated list of IPs/CIDRs, or
| 'REMOTE_ADDR' to trust only the load balancer in front of the container.
| Laravel reads it at config-cache time, so the value must be present when the
| container boots.
|
| If the list ever goes stale, the failure is degraded rather than broken: a
| request from an unlisted peer resolves to that peer's address, so those
| visitors share one bucket again — the same behaviour as trusting nothing.
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
    ))) ?: [...$cloudflare, '10.0.0.0/8', '127.0.0.0/8'],

];
