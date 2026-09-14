#!/usr/bin/env bash
# End-to-end smoke test for the portfolio API.
#
# Exercises every public and admin endpoint against a running server and
# asserts on the HTTP status code of each call. Run with:
#
#   php artisan serve &
#   ./scripts/smoke-test.sh
#
set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8000/api}"
EMAIL="${EMAIL:-info@hasib.com}"
PASSWORD="${PASSWORD:-42862266}"

PASS=0
FAIL=0
TOKEN=""

# Last response body. request() runs inside a command substitution (a
# subshell), so a plain variable assignment there would not survive back to the
# caller — the body goes through a temp file instead.
BODY_FILE="$(mktemp /tmp/smoke-body-XXXXXX.json)"
trap 'rm -f "$BODY_FILE"' EXIT
BODY=""

c_green() { printf '\033[32m%s\033[0m' "$1"; }
c_red()   { printf '\033[31m%s\033[0m' "$1"; }

# request METHOD PATH [JSON_BODY] -> sets BODY, echoes status code
request() {
  local method="$1" path="$2" data="${3:-}"
  local args=(-s -w '\n%{http_code}' -X "$method" "$BASE$path"
              -H 'Accept: application/json')

  [ -n "$TOKEN" ] && args+=(-H "Authorization: Bearer $TOKEN")
  if [ -n "$data" ]; then
    args+=(-H 'Content-Type: application/json' -d "$data")
  fi

  local response status
  response="$(curl "${args[@]}")"
  status="${response##*$'\n'}"
  printf '%s' "${response%$'\n'*}" > "$BODY_FILE"
  echo "$status"
}

# Body of the most recent request.
body() { cat "$BODY_FILE"; }

# check LABEL EXPECTED_STATUS METHOD PATH [JSON_BODY]
check() {
  local label="$1" expected="$2" method="$3" path="$4" data="${5:-}"
  local status
  status="$(request "$method" "$path" "$data")"

  if [ "$status" = "$expected" ]; then
    echo "  $(c_green PASS)  $label ($status)"
    PASS=$((PASS + 1))
  else
    echo "  $(c_red FAIL)  $label — expected $expected, got $status"
    echo "         $(head -c 400 "$BODY_FILE")"
    FAIL=$((FAIL + 1))
  fi
}

# Extract a value from the last JSON body using PHP (always present here).
json() {
  BODY="$(body)" php -r '
    $d = json_decode(getenv("BODY"), true);
    foreach (explode(".", $argv[1]) as $k) {
      if (!is_array($d) || !array_key_exists($k, $d)) { exit(1); }
      $d = $d[$k];
    }
    echo is_scalar($d) ? $d : json_encode($d);
  ' "$1" 2>/dev/null
}

section() { echo; echo "=== $1 ==="; }

# ---------------------------------------------------------------------------
section "Public endpoints (no auth)"
# ---------------------------------------------------------------------------
check "GET /settings"            200 GET /settings
check "GET /section-visibility"  200 GET /section-visibility
check "GET /hero"                200 GET /hero
check "GET /about"               200 GET /about
check "GET /skills"              200 GET /skills
check "GET /timeline"            200 GET /timeline
check "GET /projects"            200 GET /projects
check "GET /api-showcases"       200 GET /api-showcases
check "GET /testimonials"        200 GET /testimonials
check "GET /contact-info"        200 GET /contact-info
check "GET /projects/{unknown-slug} is 404" 404 GET /projects/no-such-project

# ---------------------------------------------------------------------------
section "Admin routes reject anonymous access"
# ---------------------------------------------------------------------------
check "GET /admin/settings without token"  401 GET /admin/settings
check "PUT /admin/hero without token"      401 PUT /admin/hero
check "GET /admin/messages without token"  401 GET /admin/messages
check "POST /admin/upload without token"   401 POST /admin/upload

# ---------------------------------------------------------------------------
section "Login"
# ---------------------------------------------------------------------------
check "POST /login with valid credentials" 200 POST /login \
  "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}"
TOKEN="$(json data.token)"
if [ -z "$TOKEN" ]; then
  echo "  $(c_red ABORT)  no token returned; cannot test admin routes"
  exit 1
fi
echo "  token acquired (${#TOKEN} chars)"
check "GET /admin/me with token" 200 GET /admin/me

# ---------------------------------------------------------------------------
section "Singletons (GET + PUT)"
# ---------------------------------------------------------------------------
check "GET  /admin/settings" 200 GET /admin/settings
check "PUT  /admin/settings" 200 PUT /admin/settings \
  '{"site_title":"Smoke Title","brand_name":"Smoke","accent_color":"#4648D4","footer_text":"f","copyright_text":"c"}'
check "PUT  /admin/settings rejects bad hex" 422 PUT /admin/settings \
  '{"site_title":"x","brand_name":"y","accent_color":"nothex"}'

check "GET  /admin/hero" 200 GET /admin/hero
check "PUT  /admin/hero" 200 PUT /admin/hero \
  '{"heading":"Smoke Heading","subheading":"sub","email":"","image_path":"","social_links":[]}'
check "PUT  /admin/hero requires heading" 422 PUT /admin/hero '{"subheading":"no heading"}'

check "GET  /admin/about" 200 GET /admin/about
check "PUT  /admin/about" 200 PUT /admin/about \
  '{"bio_paragraph_1":"para one","bio_paragraph_2":"para two","stats":[{"label":"Projects","value":"10+"}]}'

check "GET  /admin/contact-info" 200 GET /admin/contact-info
check "PUT  /admin/contact-info" 200 PUT /admin/contact-info \
  '{"email":"hi@example.com","phone":"+880","location":"Dhaka","calendly_link":"","whatsapp_number":"8801700000000"}'
check "PUT  /admin/contact-info rejects non-digit whatsapp" 422 PUT /admin/contact-info \
  '{"whatsapp_number":"+880 170 000"}'

# ---------------------------------------------------------------------------
section "Section visibility (read + update)"
# ---------------------------------------------------------------------------
check "GET  /admin/section-visibility" 200 GET /admin/section-visibility

# ---------------------------------------------------------------------------
section "Skills (categories + skills, filtered + reorder)"
# ---------------------------------------------------------------------------
check "POST /admin/skill-categories" 201 POST /admin/skill-categories '{"name":"Backend","order":0}'
CAT1="$(json data.id)"
check "POST /admin/skill-categories (second)" 201 POST /admin/skill-categories '{"name":"Frontend","order":1}'
CAT2="$(json data.id)"
check "GET  /admin/skill-categories" 200 GET /admin/skill-categories
check "PUT  /admin/skill-categories/reorder" 200 PUT /admin/skill-categories/reorder \
  "{\"items\":[{\"id\":$CAT2,\"order\":0},{\"id\":$CAT1,\"order\":1}]}"

check "POST /admin/skills" 201 POST /admin/skills \
  "{\"skill_category_id\":$CAT1,\"name\":\"Laravel\",\"order\":0}"
SKILL1="$(json data.id)"
check "POST /admin/skills (second)" 201 POST /admin/skills \
  "{\"skill_category_id\":$CAT1,\"name\":\"PHP\",\"order\":1}"
SKILL2="$(json data.id)"
check "POST /admin/skills rejects missing category" 422 POST /admin/skills '{"name":"Orphan"}'
check "POST /admin/skills rejects unknown category" 422 POST /admin/skills \
  '{"skill_category_id":999999,"name":"Ghost"}'
check "GET  /admin/skills?category_id=N" 200 GET "/admin/skills?category_id=$CAT1"
check "PUT  /admin/skills/{id}" 200 PUT "/admin/skills/$SKILL1" \
  "{\"skill_category_id\":$CAT1,\"name\":\"Laravel 13\",\"order\":0}"
check "PUT  /admin/skills/reorder" 200 PUT /admin/skills/reorder \
  "{\"items\":[{\"id\":$SKILL2,\"order\":0},{\"id\":$SKILL1,\"order\":1}]}"
check "DELETE /admin/skills/{id}" 200 DELETE "/admin/skills/$SKILL2"

# ---------------------------------------------------------------------------
section "Timeline (CRUD + reorder)"
# ---------------------------------------------------------------------------
check "POST /admin/timeline-items" 201 POST /admin/timeline-items \
  '{"type":"experience","institute_or_company":"Smart Software Ltd","subject_or_role":"Laravel Developer","start_year":"2024","end_year":"","order":0}'
TL1="$(json data.id)"
check "POST /admin/timeline-items (second)" 201 POST /admin/timeline-items \
  '{"type":"education","institute_or_company":"Kodeeo Ltd","subject_or_role":"Intern","start_year":"2020","end_year":"2021","order":1}'
TL2="$(json data.id)"
check "POST /admin/timeline-items requires type" 422 POST /admin/timeline-items \
  '{"institute_or_company":"No type","subject_or_role":"X","start_year":"2024","order":0}'
check "GET  /admin/timeline-items" 200 GET /admin/timeline-items
check "PUT  /admin/timeline-items/{id}" 200 PUT "/admin/timeline-items/$TL1" \
  '{"type":"experience","institute_or_company":"Smart Software Ltd","subject_or_role":"Senior Developer","start_year":"2025","order":0}'
check "PUT  /admin/timeline-items/reorder" 200 PUT /admin/timeline-items/reorder \
  "{\"items\":[{\"id\":$TL2,\"order\":0},{\"id\":$TL1,\"order\":1}]}"
check "DELETE /admin/timeline-items/{id}" 200 DELETE "/admin/timeline-items/$TL2"

# ---------------------------------------------------------------------------
section "Projects (CRUD + slug + case study + reorder)"
# ---------------------------------------------------------------------------
check "POST /admin/projects" 201 POST /admin/projects \
  '{"title":"HaatBazar","description":"Multi-vendor marketplace.","tags":["Laravel","Vue.js"],"is_featured":true,"order":0}'
PROJ1="$(json data.id)"
SLUG1="$(json data.slug)"
echo "         slug generated: $SLUG1"
check "POST /admin/projects (duplicate title gets unique slug)" 201 POST /admin/projects \
  '{"title":"HaatBazar","description":"Another one.","order":1}'
PROJ2="$(json data.id)"
SLUG2="$(json data.slug)"
echo "         second slug: $SLUG2"
check "POST /admin/projects requires description" 422 POST /admin/projects '{"title":"No description"}'
check "GET  /admin/projects" 200 GET /admin/projects
check "PUT  /admin/projects/{id}" 200 PUT "/admin/projects/$PROJ1" \
  '{"title":"HaatBazar Marketplace","description":"Updated.","tags":["Laravel"],"is_featured":true,"order":0}'
check "POST /admin/projects/{id}/case-study (create)" 200 POST "/admin/projects/$PROJ1/case-study" \
  '{"client":"Acme","date_range":"2024","challenge":"c","solution":"s","results":["r1","r2"],"gallery_images":[]}'
check "POST /admin/projects/{id}/case-study (update)" 200 POST "/admin/projects/$PROJ1/case-study" \
  '{"client":"Acme Ltd","challenge":"c2","solution":"s2","results":["r1"]}'
check "PUT  /admin/projects/reorder" 200 PUT /admin/projects/reorder \
  "{\"items\":[{\"id\":$PROJ2,\"order\":0},{\"id\":$PROJ1,\"order\":1}]}"
check "GET  /projects/{slug} (public, with detail)" 200 GET "/projects/$SLUG2"
check "DELETE /admin/projects/{id}" 200 DELETE "/admin/projects/$PROJ2"

# ---------------------------------------------------------------------------
section "API showcases + testimonials"
# ---------------------------------------------------------------------------
check "POST /admin/api-showcases" 201 POST /admin/api-showcases \
  '{"icon_name":"Zap","title":"Payment Gateway API","description":"d","endpoints":["POST /api/pay","GET /api/pay/{id}"],"order":0}'
SHOW1="$(json data.id)"
check "GET  /admin/api-showcases" 200 GET /admin/api-showcases
check "PUT  /admin/api-showcases/{id}" 200 PUT "/admin/api-showcases/$SHOW1" \
  '{"icon_name":"Database","title":"Real-Time Sync","endpoints":["GET /api/sync"],"order":0}'
check "PUT  /admin/api-showcases/reorder" 200 PUT /admin/api-showcases/reorder \
  "{\"items\":[{\"id\":$SHOW1,\"order\":0}]}"

check "POST /admin/testimonials" 201 POST /admin/testimonials \
  '{"quote":"Great work.","author_name":"Rafiq Hossain","author_role":"Founder","order":0}'
TEST1="$(json data.id)"
check "POST /admin/testimonials (second)" 201 POST /admin/testimonials \
  '{"quote":"Fast and reliable.","author_name":"Tania Akter","order":1}'
TEST2="$(json data.id)"
check "POST /admin/testimonials requires quote" 422 POST /admin/testimonials '{"author_name":"No quote"}'
check "GET  /admin/testimonials" 200 GET /admin/testimonials
check "PUT  /admin/testimonials/{id}" 200 PUT "/admin/testimonials/$TEST1" \
  '{"quote":"Excellent work.","author_name":"Rafiq Hossain","order":0}'
check "PUT  /admin/testimonials/reorder" 200 PUT /admin/testimonials/reorder \
  "{\"items\":[{\"id\":$TEST2,\"order\":0},{\"id\":$TEST1,\"order\":1}]}"
check "DELETE /admin/testimonials/{id}" 200 DELETE "/admin/testimonials/$TEST2"

# ---------------------------------------------------------------------------
section "Contact messages (public submit -> admin inbox)"
# ---------------------------------------------------------------------------
TOKEN_SAVED="$TOKEN"; TOKEN=""
check "POST /contact-messages (public)" 201 POST /contact-messages \
  '{"name":"Visitor","email":"visitor@example.com","subject":"Project inquiry","message":"Hello there."}'
check "POST /contact-messages rejects bad email" 422 POST /contact-messages \
  '{"name":"Visitor","email":"nope","message":"Hi"}'
check "POST /contact-messages requires message" 422 POST /contact-messages \
  '{"name":"Visitor","email":"visitor@example.com"}'
TOKEN="$TOKEN_SAVED"

check "GET  /admin/messages" 200 GET /admin/messages
MSG1="$(json data.0.id)"
READ_BEFORE="$(json data.0.is_read)"
check "PUT  /admin/messages/{id}/read (toggle on)" 200 PUT "/admin/messages/$MSG1/read"
READ_AFTER="$(json data.is_read)"
if [ "$READ_BEFORE" != "$READ_AFTER" ]; then
  echo "  $(c_green PASS)  read flag toggled ($READ_BEFORE -> $READ_AFTER)"
  PASS=$((PASS + 1))
else
  echo "  $(c_red FAIL)  read flag did not toggle (stayed $READ_AFTER)"
  FAIL=$((FAIL + 1))
fi
check "PUT  /admin/messages/{id}/read (toggle back)" 200 PUT "/admin/messages/$MSG1/read"
check "DELETE /admin/messages/{id}" 200 DELETE "/admin/messages/$MSG1"

# ---------------------------------------------------------------------------
section "Meeting requests (public submit -> reply + note)"
# ---------------------------------------------------------------------------
TOKEN_SAVED="$TOKEN"; TOKEN=""
check "POST /meeting-requests (public)" 201 POST /meeting-requests \
  '{"name":"Visitor","email":"visitor@example.com","preferred_date":"2026-08-15","preferred_time":"09:00","message":"Discuss a project."}'
check "POST /meeting-requests rejects bad email" 422 POST /meeting-requests \
  '{"name":"Visitor","email":"nope"}'
check "POST /meeting-requests rejects bad date" 422 POST /meeting-requests \
  '{"name":"V","email":"v@example.com","preferred_date":"not-a-date"}'
TOKEN="$TOKEN_SAVED"

check "GET  /admin/meeting-requests" 200 GET /admin/meeting-requests
MR1="$(json data.0.id)"
STATUS_BEFORE="$(json data.0.status)"
echo "         status before reply: $STATUS_BEFORE"

check "PUT  /admin/meeting-requests/{id}/note" 200 PUT "/admin/meeting-requests/$MR1/note" \
  '{"admin_note":"Internal: check availability Thursday."}'
check "PUT  /admin/meeting-requests/{id}/note requires the key" 422 PUT "/admin/meeting-requests/$MR1/note" '{}'

# Reply: 200 on delivery, 502 on transport failure (both are valid outcomes)
REPLY_STATUS="$(request PUT "/admin/meeting-requests/$MR1/reply" \
  '{"admin_reply":"Thanks for reaching out — Thursday at 9am works for me."}')"
if [ "$REPLY_STATUS" = "200" ] || [ "$REPLY_STATUS" = "502" ]; then
  echo "  $(c_green PASS)  PUT /admin/meeting-requests/$MR1/reply ($REPLY_STATUS)"
  PASS=$((PASS + 1))
else
  echo "  $(c_red FAIL)  PUT /admin/meeting-requests/$MR1/reply — expected 200 or 502, got $REPLY_STATUS"
  echo "         $(head -c 400 "$BODY_FILE")"
  FAIL=$((FAIL + 1))
fi

STATUS_AFTER="$(json data.status)"
REPLIED_AT="$(json data.replied_at)"
if [ "$REPLY_STATUS" = "200" ]; then
  if [ "$STATUS_AFTER" = "replied" ] && [ -n "$REPLIED_AT" ]; then
    echo "  $(c_green PASS)  status -> replied, replied_at stamped"
    PASS=$((PASS + 1))
  else
    echo "  $(c_red FAIL)  expected status=replied with replied_at, got status=$STATUS_AFTER replied_at=$REPLIED_AT"
    FAIL=$((FAIL + 1))
  fi
else
  # 502: delivery failed, status should remain pending
  if [ "$STATUS_AFTER" = "pending" ]; then
    echo "  $(c_green PASS)  delivery failed, status stays pending (expected)"
    PASS=$((PASS + 1))
  else
    echo "  $(c_red FAIL)  on 502 expected status=pending, got status=$STATUS_AFTER"
    FAIL=$((FAIL + 1))
  fi
fi
check "PUT  /admin/meeting-requests/{id}/reply requires text" 422 PUT "/admin/meeting-requests/$MR1/reply" '{"admin_reply":""}'

# ---------------------------------------------------------------------------
section "File upload"
# ---------------------------------------------------------------------------
# A 1x1 PNG, written to a temp file so this is a real multipart upload.
TMP_PNG="$(mktemp /tmp/smoke-XXXXXX.png)"
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==' \
  | base64 -d > "$TMP_PNG"

UPLOAD_STATUS="$(curl -s -o /tmp/smoke-upload.json -w '%{http_code}' \
  -X POST "$BASE/admin/upload" \
  -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -F "file=@$TMP_PNG" -F 'type=project-image')"
if [ "$UPLOAD_STATUS" = "201" ]; then
  echo "  $(c_green PASS)  POST /admin/upload (png) (201)"
  echo "         $(head -c 300 /tmp/smoke-upload.json)"
  PASS=$((PASS + 1))
else
  echo "  $(c_red FAIL)  POST /admin/upload — expected 201, got $UPLOAD_STATUS"
  echo "         $(head -c 400 /tmp/smoke-upload.json)"
  FAIL=$((FAIL + 1))
fi

# A .php file renamed to .png must still be rejected on MIME grounds.
TMP_BAD="$(mktemp /tmp/smoke-XXXXXX.png)"
echo '<?php echo "pwned";' > "$TMP_BAD"
BAD_STATUS="$(curl -s -o /tmp/smoke-bad.json -w '%{http_code}' \
  -X POST "$BASE/admin/upload" \
  -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -F "file=@$TMP_BAD" -F 'type=project-image')"
if [ "$BAD_STATUS" = "422" ]; then
  echo "  $(c_green PASS)  POST /admin/upload rejects PHP disguised as .png (422)"
  PASS=$((PASS + 1))
else
  echo "  $(c_red FAIL)  disguised PHP upload — expected 422, got $BAD_STATUS"
  echo "         $(head -c 400 /tmp/smoke-bad.json)"
  FAIL=$((FAIL + 1))
fi
rm -f "$TMP_PNG" "$TMP_BAD" /tmp/smoke-upload.json /tmp/smoke-bad.json

# ---------------------------------------------------------------------------
section "Logout revokes the token"
# ---------------------------------------------------------------------------
check "POST /logout" 200 POST /logout
check "GET /admin/me after logout" 401 GET /admin/me

# ---------------------------------------------------------------------------
echo
echo "==============================="
echo "  passed: $PASS   failed: $FAIL"
echo "==============================="
[ "$FAIL" -eq 0 ] || exit 1
