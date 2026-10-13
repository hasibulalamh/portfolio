#!/usr/bin/env bash
#
# Rate limiting / proxy trust check — local only.
#
# Answers three questions against a running API, with evidence you can paste
# into a report:
#
#   1. Is the real client IP resolved from X-Forwarded-For, or is every visitor
#      collapsed onto the proxy's address? (trustProxies)
#   2. Do public GETs share one global bucket, or one bucket per client IP?
#   3. Are the strict limits on the visitor forms and click tracking still
#      enforced — and are they per client IP too?
#
# WRITE-FREE BY DESIGN: every POST body fails validation (or is outside the
# event allow-list), so the request still passes through the throttle
# middleware and nothing is written to the database or the admin inbox. This
# script only ever targets a local API; never point it at production (the same
# rule load/run-load-test.sh states).
#
# Throttle counters live in the cache store, so the script clears the local
# cache first to make each run independent.
#
# Usage:
#   ./scripts/rate-limit-check.sh                 # defaults to 127.0.0.1:8000
#   API_URL=http://127.0.0.1:8011/api ./scripts/rate-limit-check.sh
#
# Exit status: 0 when every expectation is met, 1 otherwise.

set -uo pipefail

HERE="$(cd "$(dirname "$0")/.." && pwd)"
API="${API_URL:-http://127.0.0.1:8000/api}"
# Budgets as registered in app/Providers/AppServiceProvider.php.
PUBLIC_READ_LIMIT="${PUBLIC_READ_LIMIT:-120}" # public-read
TRACK_LIMIT="${TRACK_LIMIT:-30}"              # click-tracking
FORM_LIMIT="${FORM_LIMIT:-10}"                # public-write

failures=0
report_fail() { echo "   FAIL  $1"; failures=$((failures + 1)); }
histogram() { sort | uniq -c | sed 's/^ *\([0-9]*\) *\(.*\)/    \2 x\1/'; }
count_of() { printf '%s\n' "$1" | grep -c "^$2$" || true; }

# POST /api/track: an event_type outside the allow-list is a 422, never a row.
track_probe() {
  curl -sS -o /dev/null -w '%{http_code}\n' -X POST \
    -H 'Content-Type: application/json' -H "X-Forwarded-For: $1" \
    -d '{"event_type":"__rate_limit_check__"}' "$API/track"
}

# POST /api/contact-messages: a malformed email is a 422, never a row.
form_probe() {
  curl -sS -o /dev/null -w '%{http_code}\n' -X POST \
    -H 'Content-Type: application/json' -H "X-Forwarded-For: $1" \
    -d '{"name":"rate check","email":"not-an-email","message":"probe"}' \
    "$API/contact-messages"
}

# Throttle counters live in the cache store; clearing between scenarios keeps
# one measurement from consuming the next one's budget.
reset_counters() { (cd "$HERE" && php artisan cache:clear >/dev/null 2>&1); }
reset_counters

echo "Target: $API"
echo

# ---------------------------------------------------------------------------
echo "0. Resolved client IP when a trusted proxy forwards 203.0.113.9"
echo "   (REMOTE_ADDR is the load balancer's address, as it is in production)"
resolved=$(cd "$HERE" && php artisan tinker --execute='
$request = Illuminate\Http\Request::create("/api/settings", "GET", [], [], [], [
    "REMOTE_ADDR" => "10.0.0.5",
    "HTTP_X_FORWARDED_FOR" => "203.0.113.9",
]);
app(Illuminate\Contracts\Http\Kernel::class)->handle($request);
echo $request->ip();
' 2>/dev/null | tail -1)
echo "   \$request->ip() => ${resolved:-<no output>}"
if [ "$resolved" = "203.0.113.9" ]; then
  echo "   PASS  X-Forwarded-For is trusted (real client IP)"
else
  report_fail "proxy address used instead of the real client IP"
fi
echo

# ---------------------------------------------------------------------------
echo "1. Public read: $((PUBLIC_READ_LIMIT + 1)) GETs to /settings from ONE client IP"
echo "   (the limit is $PUBLIC_READ_LIMIT/min, so only the last one may be refused)"
one_ip_reads=$(for _ in $(seq 1 $((PUBLIC_READ_LIMIT + 1))); do
  curl -sS -o /dev/null -w '%{http_code}\n' \
    -H "X-Forwarded-For: 203.0.113.10" "$API/settings"
done)
printf '%s\n' "$one_ip_reads" | histogram
if [ "$(count_of "$one_ip_reads" 429)" -eq 1 ]; then
  echo "   PASS  throttle:public-read refused exactly the $((PUBLIC_READ_LIMIT + 1))th read"
else
  report_fail "expected exactly one 429 after $PUBLIC_READ_LIMIT reads"
fi
echo "   ...then one more from a DIFFERENT client IP (must stay 200)"
different_ip=$(curl -sS -o /dev/null -w '%{http_code}' \
  -H "X-Forwarded-For: 203.0.113.99" "$API/settings")
echo "    $different_ip"
if [ "$different_ip" != "200" ]; then
  report_fail "one client IP's exhausted bucket blocks another client IP"
fi
echo

# ---------------------------------------------------------------------------
echo "2. Strict limit on /track: $((TRACK_LIMIT + 1)) POSTs, one per client IP"
reset_counters
per_ip_track=$(for i in $(seq 1 $((TRACK_LIMIT + 1))); do
  track_probe "198.51.100.$i"
done)
printf '%s\n' "$per_ip_track" | histogram
echo "   (expect all 422 and no 429: $TRACK_LIMIT requests/minute each)"
if [ "$(count_of "$per_ip_track" 429)" -ne 0 ]; then
  report_fail "distinct client IPs shared one /track bucket"
fi
echo
echo "   ...and $((TRACK_LIMIT + 1)) POSTs from ONE client IP (expect 429 on the last)"
reset_counters
one_ip_track=$(for _ in $(seq 1 $((TRACK_LIMIT + 1))); do
  track_probe "198.51.100.200"
done)
printf '%s\n' "$one_ip_track" | histogram
if [ "$(count_of "$one_ip_track" 429)" -eq 1 ]; then
  echo "   PASS  throttle:click-tracking still refuses the $((TRACK_LIMIT + 1))th request"
else
  report_fail "throttle:click-tracking did not refuse exactly one request"
fi
echo

# ---------------------------------------------------------------------------
echo "3. Contact form: $((FORM_LIMIT + 1)) POSTs from ONE client IP (expect 429 on the last)"
reset_counters
one_ip_form=$(for _ in $(seq 1 $((FORM_LIMIT + 1))); do
  form_probe "192.0.2.77"
done)
printf '%s\n' "$one_ip_form" | histogram
if [ "$(count_of "$one_ip_form" 429)" -eq 1 ]; then
  echo "   PASS  throttle:public-write still refuses the $((FORM_LIMIT + 1))th request"
else
  report_fail "throttle:public-write did not refuse exactly one request"
fi
echo
echo "   ...and $((FORM_LIMIT + 1)) POSTs, one per client IP (expect all 422)"
reset_counters
per_ip_form=$(for i in $(seq 1 $((FORM_LIMIT + 1))); do
  form_probe "192.0.2.$i"
done)
printf '%s\n' "$per_ip_form" | histogram
if [ "$(count_of "$per_ip_form" 429)" -ne 0 ]; then
  report_fail "distinct client IPs shared one contact-form bucket"
fi
echo

if [ "$failures" -eq 0 ]; then
  echo "All rate-limit / proxy-trust expectations met."
  exit 0
fi

echo "$failures expectation(s) NOT met."
exit 1
