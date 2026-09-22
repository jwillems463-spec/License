#!/usr/bin/env bash
# End-to-end API smoke test against a FRESHLY IMPORTED database.
# Usage: tests/smoke.sh http://localhost:8080
# Requires: curl, python3. Changes the default admin password to "NewAdminPass2026".
set -u
BASE="${1:-http://127.0.0.1:8080}"
JAR=$(mktemp); JAR2=$(mktemp)
PASS=0; FAIL=0
trap 'rm -f "$JAR" "$JAR2"' EXIT

check() { # name expected actual
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected $2, got $3)"; fi
}
csrf() { # jar
  curl -s -b "$1" -c "$1" "$BASE/api/auth/me" | python3 -c 'import json,sys; print(json.load(sys.stdin)["csrf_token"])'
}
req() { # jar method path [json] -> prints "status body"
  local jar=$1 m=$2 p=$3 d=${4:-}
  local tok; tok=$(csrf "$jar")
  if [ -n "$d" ]; then
    curl -s -b "$jar" -c "$jar" -X "$m" -H "X-CSRF-Token: $tok" -H 'Content-Type: application/json' -d "$d" -w '\n%{http_code}' "$BASE$p"
  else
    curl -s -b "$jar" -c "$jar" -X "$m" -H "X-CSRF-Token: $tok" -w '\n%{http_code}' "$BASE$p"
  fi
}
code() { tail -n1; }
json() { head -n -1 | python3 -c "import json,sys; d=json.load(sys.stdin); print($1)"; }

echo "Public API"
check "GET /api/evs"               200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/evs")"
check "published count = 15"       15  "$(curl -s "$BASE/api/evs" | python3 -c 'import json,sys; print(json.load(sys.stdin)["meta"]["total"])')"
check "filter suv+AWD"             3   "$(curl -s "$BASE/api/evs?body_type=suv&drivetrain=AWD" | python3 -c 'import json,sys; print(json.load(sys.stdin)["meta"]["total"])')"
check "draft hidden from public"   404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/evs/rivian-r1s-dual-large-2025")"
check "compare needs ids"          422 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/compare")"
check "admin requires login"       401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/admin/evs")"

echo "Auth"
check "login without CSRF rejected" 403 "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{"email":"admin@e-carscompare.com","password":"ChangeMe123!"}' "$BASE/api/auth/login")"
check "wrong password"             401 "$(req "$JAR" POST /api/auth/login '{"email":"admin@e-carscompare.com","password":"nope"}' | code)"
check "login"                      200 "$(req "$JAR" POST /api/auth/login '{"email":"admin@e-carscompare.com","password":"ChangeMe123!"}' | code)"
check "blocked until pw changed"   403 "$(req "$JAR" GET /api/admin/stats | code)"
check "weak new password rejected" 422 "$(req "$JAR" POST /api/auth/password '{"current_password":"ChangeMe123!","new_password":"short"}' | code)"
check "change password"            200 "$(req "$JAR" POST /api/auth/password '{"current_password":"ChangeMe123!","new_password":"NewAdminPass2026"}' | code)"
check "stats now allowed"          200 "$(req "$JAR" GET /api/admin/stats | code)"

echo "Brands CRUD"
R=$(req "$JAR" POST /api/admin/brands '{"name":"Lucid","country":"United States","website":"https://lucidmotors.com"}')
check "create brand"               201 "$(echo "$R" | code)"
BID=$(echo "$R" | json 'd["data"]["id"]')
check "duplicate brand rejected"   422 "$(req "$JAR" POST /api/admin/brands '{"name":"Lucid"}' | code)"
check "update brand"               200 "$(req "$JAR" PUT /api/admin/brands/$BID '{"country":"USA"}' | code)"

echo "EV CRUD"
R=$(req "$JAR" POST /api/admin/evs "{\"brand_id\":$BID,\"model\":\"Air\",\"variant\":\"Grand Touring\",\"model_year\":2025,\"body_type\":\"sedan\",\"drivetrain\":\"AWD\",\"price_usd\":110900,\"range_km\":830,\"status\":\"draft\"}")
check "create EV"                  201 "$(echo "$R" | code)"
EID=$(echo "$R" | json 'd["data"]["id"]')
SLUG=$(echo "$R" | json 'd["data"]["slug"]')
check "auto slug"                  "lucid-air-grand-touring-2025" "$SLUG"
check "validation errors"          422 "$(req "$JAR" POST /api/admin/evs '{"model":"","body_type":"boat"}' | code)"
check "draft not public"           404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/evs/$SLUG")"
check "publish EV"                 200 "$(req "$JAR" PUT /api/admin/evs/$EID '{"status":"published","is_featured":true}' | code)"
check "instantly public"           200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/evs/$SLUG")"
check "detail page live"           200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/ev/$SLUG")"
check "price edit reflected"       99000.0 "$(req "$JAR" PUT /api/admin/evs/$EID '{"price_usd":99000}' >/dev/null; curl -s "$BASE/api/evs/$EID" | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["price_usd"])')"
check "brand with EVs not deletable" 409 "$(req "$JAR" DELETE /api/admin/brands/$BID | code)"
check "bulk unpublish"             200 "$(req "$JAR" POST /api/admin/evs/bulk "{\"ids\":[$EID],\"action\":\"unpublish\"}" | code)"
check "hidden after unpublish"     404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/evs/$SLUG")"

echo "Users & roles"
R=$(req "$JAR" POST /api/admin/users '{"name":"Ed Itor","email":"editor@e-carscompare.com","role":"editor","password":"EditorPass2026","must_change_password":false}')
check "create editor"              201 "$(echo "$R" | code)"
UID2=$(echo "$R" | json 'd["data"]["id"]')
check "editor login"               200 "$(req "$JAR2" POST /api/auth/login '{"email":"editor@e-carscompare.com","password":"EditorPass2026"}' | code)"
check "editor can edit EV"         200 "$(req "$JAR2" PUT /api/admin/evs/$EID '{"seats":5}' | code)"
check "editor cannot delete EV"    403 "$(req "$JAR2" DELETE /api/admin/evs/$EID | code)"
check "editor cannot bulk delete"  403 "$(req "$JAR2" POST /api/admin/evs/bulk "{\"ids\":[$EID],\"action\":\"delete\"}" | code)"
check "editor cannot list users"   403 "$(req "$JAR2" GET /api/admin/users | code)"
check "editor cannot edit settings" 403 "$(req "$JAR2" PUT /api/admin/settings '{"site_name":"Hacked"}' | code)"
check "deactivate editor"          200 "$(req "$JAR" PUT /api/admin/users/$UID2 '{"is_active":false}' | code)"
check "deactivated = logged out"   401 "$(req "$JAR2" GET /api/admin/evs | code)"
check "cannot delete self"         422 "$(req "$JAR" DELETE /api/admin/users/1 | code)"

echo "Settings"
check "save settings"              200 "$(req "$JAR" PUT /api/admin/settings '{"site_name":"Volt Garage","max_compare":"3"}' | code)"
check "reflected in meta"          "Volt Garage 3" "$(curl -s "$BASE/api/meta" | python3 -c 'import json,sys; s=json.load(sys.stdin)["settings"]; print(s["site_name"], s["max_compare"])')"
check "reflected in HTML"          1   "$(curl -s "$BASE/" | grep -c '<title>Volt Garage')"
check "invalid setting"            422 "$(req "$JAR" PUT /api/admin/settings '{"max_compare":"9"}' | code)"
req "$JAR" PUT /api/admin/settings '{"site_name":"e-carscompare","max_compare":"4"}' >/dev/null

echo "Cleanup"
check "delete EV"                  200 "$(req "$JAR" DELETE /api/admin/evs/$EID | code)"
check "delete brand"               200 "$(req "$JAR" DELETE /api/admin/brands/$BID | code)"
check "delete user"                200 "$(req "$JAR" DELETE /api/admin/users/$UID2 | code)"
check "audit log"                  200 "$(req "$JAR" GET /api/admin/audit | code)"
check "logout"                     200 "$(req "$JAR" POST /api/auth/logout | code)"
check "logged out"                 401 "$(req "$JAR" GET /api/admin/stats | code)"

echo; echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
