# Installing with Docker

This is the "Model B" path: a Linux machine (or WSL) with Docker installed,
and comfort typing a handful of commands. If that's not you, use
[`INSTALL-SHARED-HOSTING.md`](INSTALL-SHARED-HOSTING.md) instead — it needs
nothing but a web browser.

Unlike the shared-hosting path, this one works from the git repository
directly rather than a release zip: `Dockerfile`, `docker-compose.yml`, and
the `Makefile` are dev/Docker-only files, deliberately stripped out of the
shared-hosting zip (see `tools/build-release.sh`), so you need the actual
source checkout to use them.

## Prerequisites

- **Docker** and **Docker Compose** (the `docker compose` subcommand, not the
  older standalone `docker-compose` binary). If `docker compose version`
  prints something, skip straight to Step 2 — you're already set. Otherwise
  Step 1 below walks through installing it.
- **git**, or just a downloaded/extracted copy of the repository — either
  works, but `git pull` is the easiest way to take future updates (see
  [`UPGRADING.md`](../UPGRADING.md)).
- Two free local ports. The defaults are **8117** (the web app) and **8118**
  (MySQL, bound to `127.0.0.1` only — for `mysql -h127.0.0.1 -P8118` if you
  ever want to poke at the database directly, not for exposing it to the
  network).

## Steps

**1. Install Docker.**

These commands are for **Debian or Ubuntu** (including WSL running an Ubuntu
image — the most common default). If `docker compose version` already prints
a version, skip to Step 2. If you're on a different Linux distribution (e.g.
Fedora, Arch, openSUSE), follow the equivalent guide at
[docs.docker.com/engine/install](https://docs.docker.com/engine/install/) —
the rest of this document doesn't change either way.

Remove any old/conflicting packages first (safe to run even if none of these
are installed):

```bash
for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do
  sudo apt-get remove -y $pkg
done
```

Add Docker's official APT repository:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
```

Install Docker Engine and the Compose plugin (this is what gives you the
`docker compose` subcommand):

```bash
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Let your user run `docker` without `sudo` (log out and back in, or run
`newgrp docker`, for this to take effect in your current shell):

```bash
sudo usermod -aG docker $USER
newgrp docker
```

Verify:

```bash
docker compose version
```

You should see something like `Docker Compose version v2.x.x`. If you get
`permission denied` on `docker` commands, the group membership from above
hasn't taken effect yet — fully log out and back in (or reboot WSL with
`wsl --shutdown` from Windows, then reopen your terminal).

**2. Get the source.**

```bash
git clone https://github.com/antonizick/NxCMS.git
cd NxCMS
```

**3. Start it.**

```bash
make up
```

This does several things the first time:

- Copies `.env.example` to `.env`, generating random database passwords —
  you never have to invent or type these yourself.
- Builds the app image and starts three containers: `db` (MySQL 8.4), a
  one-shot `composer` container that installs PHP dependencies into the
  bind-mounted repo, and `web` (PHP 8.3 + Apache).
- Waits for the web container to actually respond before returning control
  to you.
- Prints the installer's setup token automatically.

Expect a minute or two the first time (image build + `composer install`);
seconds on every run after that.

**4. Open the installer.**

The command output ends with a URL — normally
**http://127.0.0.1:8117/install.php**. If you missed the token in the
scroll-back, re-print it with:

```bash
make token
```

**5. Run through the installer.**

This is identical to the shared-hosting installer — same steps, same
screens, same validation rules — with one difference on the **Database**
step: enter host **`db`** and port **`3306`**, not `127.0.0.1`. `db` is the
name Docker Compose gives the MySQL container on its internal network;
`127.0.0.1` from inside the `web` container would mean "the web container
itself," which has no MySQL running on it.

Everything else — the requirements check, admin account creation, MFA
enrollment, recovery codes — works exactly as described in
[`INSTALL-SHARED-HOSTING.md`](INSTALL-SHARED-HOSTING.md) steps 5 onward,
if you want the fuller walkthrough with screenshots.

**6. You're in.** http://127.0.0.1:8117/admin

## Command reference

| Command | Does |
|---|---|
| `make up` | Start (or restart) everything. Safe to re-run — creates `.env` only if missing. |
| `make down` | Stop the containers. Data persists (named volume `db-data`, bind-mounted `.env`/`storage/`). |
| `make logs` | Follow the web container's logs (`Ctrl-C` to stop watching). |
| `make token` | Print the installer's setup token, read from the container logs. |
| `make fresh` | **Destroys the database volume and all app config** (`.env`, `storage/app.env`, `storage/installed.lock`) and starts over from the installer. Asks for a typed confirmation first. Only for throwing away a test install — never run this against real content. |

## Where things live

- **`.env`** (repo root) — Docker Compose's own bootstrap file: database
  passwords and host port numbers. The host CLI (`docker compose ...`)
  reads this; it is not the app's runtime config.
- **`storage/app.env`** — the app's actual runtime config, written by the
  installer, read via the `PORTAL_ENV_FILE` environment variable set in
  `docker-compose.yml`. This split exists so the app-facing config can live
  somewhere `www-data` inside the container is free to write, while the
  Compose bootstrap file stays host-owned.
- Both files are bind-mounted from the repo root, so they survive
  `docker compose restart` / `make down` + `make up` the same way real files
  on a real host would.

## Updating

```bash
git pull
make down
make up
```

New database migrations apply automatically on your next admin login — see
[`UPGRADING.md`](../UPGRADING.md) for the full picture, including the
shared-hosting equivalent.
