# Local test rig / Model B ("Linux + Docker") entry points.
# See PACKAGING-PLAN.md Phase 3.

.PHONY: up down fresh logs token

up: .env
	docker compose up -d --build
	@echo "Waiting on the app container to come up..."
	@# /install.php, not /health: /health depends on the app already being
	@# configured, which hasn't happened yet on a fresh install — waiting for
	@# 200 there would hang forever on the human step that hasn't occurred
	@# yet. /install.php answers 200 before install and 403 after, so we wait
	@# for it to respond at all (curl's own exit code), not for a fixed code.
	@until curl -s -o /dev/null "http://127.0.0.1:$$(grep -m1 ^WEB_PORT .env | cut -d= -f2)/install.php" 2>/dev/null; do sleep 1; done
	@$(MAKE) --no-print-directory token
	@echo "Portal running at http://127.0.0.1:$$(grep -m1 ^WEB_PORT .env | cut -d= -f2)/install.php"

down:
	docker compose down

# Drops the database volume and app config — a clean slate to re-run the
# installer from scratch. Confirms first: this destroys real data if you
# have ever entered any.
fresh:
	@echo "This deletes the database volume, .env, and storage/installed.lock."
	@read -p "Type 'fresh' to confirm: " ans; [ "$$ans" = "fresh" ] || (echo "Aborted."; exit 1)
	docker compose down -v
	@# The entrypoint hands storage/ to www-data (uid inside the container),
	@# so a plain host-side rm can fail with "Permission denied" — clear these
	@# from a throwaway container instead.
	docker run --rm -v "$$(pwd)":/app alpine sh -c 'rm -f /app/.env /app/storage/app.env /app/storage/install-token.txt /app/storage/installed.lock'
	@$(MAKE) --no-print-directory up

logs:
	docker compose logs -f

# Prints the install setup token from the web container's log — the value
# public/install.php asks for on its first screen.
token:
	@docker compose logs web 2>&1 | grep -oE 'setup token: [a-f0-9]+' | tail -1 \
		|| echo "Token not in the log yet — reload http://127.0.0.1:$$(grep -m1 ^WEB_PORT .env | cut -d= -f2)/install.php once, then re-run 'make token'."

.env: .env.example
	cp .env.example .env
	@# Random per-install passwords rather than the example's placeholders —
	@# skipped if .env already existed (this target only fires when it's missing).
	@sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$$(openssl rand -hex 16)/" .env
	@sed -i "s/^DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=$$(openssl rand -hex 16)/" .env
	@echo "Created .env with generated database passwords."
