#!/bin/sh
set -e

# storage/ is bind-mounted from the host repo, so it's owned by whichever
# host user ran `docker compose up` — not this container's www-data, which
# is what actually needs to write here (install-token.txt, .env, uploads,
# installed.lock). Fix it once at boot rather than asking the operator to
# chmod anything by hand. Scoped to storage/ only — public/, app/, vendor/
# etc. stay host-owned and freely editable from outside the container.
mkdir -p /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage

exec "$@"
