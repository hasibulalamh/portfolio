#!/usr/bin/env bash
# Lightweight load test for the portfolio backend API.
# Uses autocannon (available via npx, no global install needed).
#
# Targets LOCAL instance only — never load-test a real production URL.
# Tests: (1) public browsing endpoints under 50 concurrent connections,
#        (2) contact submission endpoint under burst traffic.
set -euo pipefail

API="${API_URL:-http://127.0.0.1:8000/api}"
DURATION=10    # seconds per scenario
CONNECTIONS=50 # concurrent connections for browsing

echo "═══════════════════════════════════════════════════════════════"
echo "  Load Test — Portfolio Backend API"
echo "  Target: $API"
echo "  Duration: ${DURATION}s per scenario"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# ── Scenario 1: Public browsing (read-only GET endpoints) ──
echo "▸ Scenario 1: Browsing — 50 concurrent users hitting public endpoints"
echo ""

ENDPOINTS=(
  "$API/settings"
  "$API/hero"
  "$API/projects"
  "$API/skills"
  "$API/testimonials"
  "$API/about"
  "$API/section-visibility"
  "$API/contact-info"
)

BROWSE_URL=""
for ep in "${ENDPOINTS[@]}"; do
  if [ -z "$BROWSE_URL" ]; then
    BROWSE_URL="$ep"
  else
    BROWSE_URL="$BROWSE_URL $ep"
  fi
done

npx autocannon -c "$CONNECTIONS" -d "$DURATION" \
  -m GET \
  --highlightLatency \
  $BROWSE_URL 2>&1

echo ""
echo "───────────────────────────────────────────────────────────────"
echo ""

# ── Scenario 2: Contact submission burst ──
echo "▸ Scenario 2: Contact submission — burst of 10 concurrent submitters"
echo ""

npx autocannon -c 10 -d 5 \
  -m POST \
  -H "Content-Type=application/json" \
  -b '{"name":"Load Visitor","email":"load-test@example.test","message":"Load test submission.","subject":"Load test"}' \
  "$API/contact-messages" 2>&1

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  Load test complete. Check p95 latency and error rate above."
echo "═══════════════════════════════════════════════════════════════"
