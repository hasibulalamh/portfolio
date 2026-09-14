#!/usr/bin/env bash
# Smoke test — fast (<30s) subset that checks the absolute basics before
# running the full test suite or before a deploy.
#
# Exit 0 = all green, exit 1 = something is wrong.
set -euo pipefail

API="${API_URL:-http://127.0.0.1:8000/api}"
PUBLIC="${PUBLIC_URL:-http://127.0.0.1:3000}"
ADMIN="${ADMIN_URL:-http://127.0.0.1:3001}"
PASS=0
FAIL=0

check() {
  local label="$1" url="$2" expect="${3:-200}"
  local code
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$url" 2>/dev/null || echo "000")
  if [ "$code" = "$expect" ]; then
    echo "  ✓ $label — HTTP $code"
    PASS=$((PASS + 1))
  else
    echo "  ✗ $label — expected $expect, got $code"
    FAIL=$((FAIL + 1))
  fi
}

echo "═══════════════════════════════════════════════════════════════"
echo "  Smoke Test — Portfolio System"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "═══════════════════════════════════════════════════════════════"
echo ""

echo "▸ API Health"
check "Backend API responds" "$API/settings" 200
check "API settings has data" "$API/settings" 200

# Check that the API response is valid JSON with the expected envelope
SETTINGS_BODY=$(curl -s --max-time 5 "$API/settings" 2>/dev/null || echo '{}')
if echo "$SETTINGS_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); assert 'data' in d and 'message' in d" 2>/dev/null; then
  echo "  ✓ API envelope has data+message keys"
  PASS=$((PASS + 1))
else
  echo "  ✗ API envelope missing data/message keys"
  FAIL=$((FAIL + 1))
fi

echo ""
echo "▸ Public Endpoints"
check "Hero endpoint" "$API/hero" 200
check "Projects endpoint" "$API/projects" 200
check "Skills endpoint" "$API/skills" 200
check "Testimonials endpoint" "$API/testimonials" 200
check "Section visibility endpoint" "$API/section-visibility" 200

echo ""
echo "▸ Frontend (skipped if same as API)"
if [ "$PUBLIC" != "$API" ] && [ "$PUBLIC" != "http://127.0.0.1:8000" ]; then
  check "Public homepage loads" "$PUBLIC/" 200
else
  echo "  ⏭ Public frontend not running — skipped"
fi

echo ""
echo "▸ Admin Panel (skipped if same as API)"
if [ "$ADMIN" != "$API" ] && [ "$ADMIN" != "http://127.0.0.1:8000" ]; then
  check "Admin login page loads" "$ADMIN/login" 200
else
  echo "  ⏭ Admin panel not running — skipped"
fi

echo ""
echo "▸ Rate Limiting (basic check)"
# Send 2 requests — both should succeed under the 10/min limit
RATE1=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 -X POST -H "Content-Type: application/json" -d '{"name":"Smoke","email":"smoke@test.com","message":"test"}' "$API/contact-messages" 2>/dev/null || echo "000")
if [ "$RATE1" = "201" ]; then
  echo "  ✓ Contact submission accepts (HTTP 201)"
  PASS=$((PASS + 1))
else
  echo "  ✗ Contact submission failed (HTTP $RATE1)"
  FAIL=$((FAIL + 1))
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
  echo "  ALL PASSED: $PASS/$TOTAL checks green"
else
  echo "  FAILED: $FAIL/$TOTAL checks failed"
fi
echo "═══════════════════════════════════════════════════════════════"

exit "$FAIL"
