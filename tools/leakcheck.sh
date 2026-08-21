#!/usr/bin/env bash
# leakcheck.sh — refuse to ship anything carrying the origin install's identity.
#
# NxCMS began life as one person's live site. Every release must be free of that
# origin's hostnames, shell user, database name, and key paths. This runs before the
# first commit and again on every release build — the check is the mechanism, not the
# one-time cleanup.
#
# Scans exactly the set of files git would publish (tracked + untracked-not-ignored),
# so gitignored local files never produce noise. Falls back to a plain file walk if
# this is not a git repo yet.
#
# Exit 0 = clean (warnings allowed) · Exit 1 = a FAIL pattern matched.

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

SELF="tools/leakcheck.sh"

publishable_files() {
    if git rev-parse --git-dir >/dev/null 2>&1; then
        git ls-files -co --exclude-standard
    else
        find . -path ./.git -prune -o -path ./vendor -prune -o -type f -print \
            | sed 's|^\./||'
    fi
}

# Files allowed to name the author: MIT copyright line and README credit.
ALLOW_AUTHOR='^(LICENSE|README\.md)$'

fails=0
warns=0

scan() {  # scan <FAIL|WARN> <label> <regex> [allow-regex]
    local tier="$1" label="$2" re="$3" allow="${4:-}"
    local hits
    hits="$(publishable_files \
        | grep -vxF "$SELF" \
        | { [ -n "$allow" ] && grep -vE "$allow" || cat; } \
        | tr '\n' '\0' \
        | xargs -0 -r grep -IniE -- "$re" 2>/dev/null)"

    [ -z "$hits" ] && return 0

    if [ "$tier" = FAIL ]; then
        echo "  [FAIL] $label"
        fails=$((fails + 1))
    else
        echo "  [warn] $label"
        warns=$((warns + 1))
    fi
    echo "$hits" | sed 's/^/         /'
    echo
}

# The origin install's real shell user / server / DB name / hostname / SSH key
# name are NOT hardcoded here — that would ship them in this public repo. They
# live in a local, gitignored tools/leakcheck.local.env (see
# tools/leakcheck.local.env.example), sourced below if present. Without it,
# the origin-identity checks are silently skipped (harmless for a clone that
# never had that identity to leak in the first place).
LOCAL_ENV="$(dirname "${BASH_SOURCE[0]}")/leakcheck.local.env"
[ -f "$LOCAL_ENV" ] && . "$LOCAL_ENV"

echo "== leakcheck: must never ship =="
[ -n "${LEAKCHECK_ORIGIN_USER:-}" ]     && scan FAIL "origin shell user / home path" "/home/${LEAKCHECK_ORIGIN_USER}|${LEAKCHECK_ORIGIN_USER}@"
[ -n "${LEAKCHECK_ORIGIN_SERVER:-}" ]   && scan FAIL "origin hosting server"         "$LEAKCHECK_ORIGIN_SERVER"
[ -n "${LEAKCHECK_ORIGIN_DB:-}" ]       && scan FAIL "origin database name"          "$LEAKCHECK_ORIGIN_DB"
[ -n "${LEAKCHECK_ORIGIN_HOSTNAME:-}" ] && scan FAIL "origin live hostname"          "${LEAKCHECK_ORIGIN_HOSTNAME//./\\.}"
[ -n "${LEAKCHECK_ORIGIN_SSH_KEY:-}" ]  && scan FAIL "origin SSH key name"           "${LEAKCHECK_ORIGIN_SSH_KEY}|\\.ssh/"
scan FAIL "private key material"          'BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY'

# Not a content match — a real .env must never become a publishable file.
if publishable_files | grep -qxE '\.env|.*/\.env'; then
    echo "  [FAIL] a .env file is publishable (must be gitignored)"
    publishable_files | grep -xE '\.env|.*/\.env' | sed 's/^/         /'
    echo
    fails=$((fails + 1))
fi

echo "== leakcheck: review these =="
scan WARN "author name"        'Nick Antonizick|nick\.antonizick' "$ALLOW_AUTHOR"
scan WARN "author domain"      'antonizick' "$ALLOW_AUTHOR"
scan WARN "absolute home path" '/home/[a-z]'
scan WARN "hardcoded password" 'password[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']+'

if [ "$fails" -gt 0 ]; then
    echo "leakcheck: FAILED — $fails blocking pattern(s), $warns warning(s)."
    exit 1
fi
echo "leakcheck: clean — 0 blocking, $warns warning(s) to review."
