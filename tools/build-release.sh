#!/usr/bin/env bash
# tools/build-release.sh — produce the shared-hosting release zip.
#
# This is the artifact a friend with no CLI downloads: unzip into public_html
# (or point the docroot at public/) and run the installer. It bundles vendor/
# (they have no Composer) and strips everything a Docker/dev checkout needs
# but a shared-hosting install never touches.
#
# Staged OUTSIDE the repo (mktemp -d), not under dist/: `git ls-files -co
# --exclude-standard` from a cwd inside an ignored directory (dist/ is
# gitignored) returns nothing for that subtree, which would make the leak
# check below silently pass with zero files scanned.
#
# Usage: tools/build-release.sh [version]   (defaults to the VERSION file)

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

VERSION="${1:-$(cat VERSION 2>/dev/null || true)}"
if [ -z "$VERSION" ]; then
    echo "Usage: tools/build-release.sh <version>   (e.g. 0.1.0)" >&2
    exit 1
fi

# Dev/Docker-only — never shipped in the shared-hosting zip. Docker users get
# these from the git repo directly, which they already need for `make`.
STRIP=(
    Dockerfile docker-compose.yml docker .dockerignore Makefile
    .editorconfig .gitignore .github
)

REPO_ROOT="$(pwd)"
DIST_DIR="dist"
ZIP="$DIST_DIR/nxcms-$VERSION.zip"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "==> Staging publishable files ($VERSION)"
git ls-files -co --exclude-standard | while IFS= read -r f; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp "$f" "$STAGE/$f"
done

echo "==> Installing production dependencies (composer --no-dev, containerized)"
# Host-UID, matching docker-compose.yml's composer service: composer:2 runs
# as root by default, which would leave vendor/ un-removable by the host user
# (bit us once already in the Docker rig — see docker-compose.yml's comment).
docker run --rm --user "$(id -u):$(id -g)" -v "$STAGE":/app -w /app composer:2 install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

echo "$VERSION" > "$STAGE/VERSION"

echo "==> Stripping dev/Docker-only files"
for f in "${STRIP[@]}"; do
    rm -rf "${STAGE:?}/$f"
done

echo "==> Leak check on the staged tree"
# STAGE isn't a git repo, so leakcheck.sh's own git detection falls back to a
# plain file walk over what's actually about to ship — exactly what we want.
if ! ( cd "$STAGE" && bash tools/leakcheck.sh ); then
    echo "Leak check failed — refusing to build a release. See output above." >&2
    exit 1
fi

# leakcheck.sh itself is dev tooling; it excludes itself from its own scan
# but has no reason to ship to an end user.
rm -rf "$STAGE/tools"

echo "==> Zipping"
mkdir -p "$DIST_DIR"
rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$REPO_ROOT/$ZIP" . )

echo "==> Built $ZIP"
du -h "$ZIP"
