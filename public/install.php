<?php

declare(strict_types=1);

/**
 * portal.lift — web installer.
 *
 * Standalone by design. This file runs before .env exists, before the database
 * exists, and before the front controller can boot, so it must not depend on
 * any of them. It loads the application's own classes only after it has written
 * .env — so TOTP enrolment here uses exactly the code paths the live site uses,
 * rather than a second implementation that could drift.
 *
 * Security posture. Until the final step deletes this file, /install.php is an
 * unauthenticated route that can create an administrator. Two gates:
 *
 *   1. A token written to storage/install-token.txt, which the operator must
 *      read off the filesystem and paste in. Possession proves they are the
 *      person who uploaded the files, which is the only ownership signal
 *      available before any account exists. (Same approach as Jenkins'
 *      initialAdminPassword.) It doubles as the CSRF secret for every POST.
 *   2. A refusal to run at all once the install is complete — detected from
 *      storage/installed.lock, and independently from the database itself, so
 *      losing the lock file does not reopen the installer.
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────

// Mirrors the discovery in public/index.php. Deliberately duplicated rather
// than shared: this file must resolve the app root *before* anything in the
// app tree is reachable, and it is deleted after setup, so the copies cannot
// drift for long. Keep them in step while both exist.
define('APP_ROOT', (static function (): string {
    if ($fromEnv = getenv('PORTAL_APP_ROOT')) {
        return rtrim($fromEnv, '/');
    }
    if (is_file(__DIR__ . '/app-root.php')) {
        return rtrim((string) require __DIR__ . '/app-root.php', '/');
    }
    if (is_file(dirname(__DIR__) . '/config/config.php')) {
        return dirname(__DIR__);
    }
    http_response_code(500);
    exit('Cannot locate the application root. Set PORTAL_APP_ROOT or create public/app-root.php.');
})());

define('ENV_PATH', getenv('PORTAL_ENV_FILE') ?: APP_ROOT . '/.env');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('LOCK_PATH', STORAGE_PATH . '/installed.lock');
define('TOKEN_PATH', STORAGE_PATH . '/install-token.txt');
define('MIN_PASSWORD_LEN', 12);

// The installer's session is its own, so it can never collide with, or be
// mistaken for, an authenticated admin session.
session_name('portal_install');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
]);
session_start();

// ── Small helpers ────────────────────────────────────────────────────────────

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $step): never
{
    header('Location: install.php?step=' . urlencode($step));
    exit;
}

/** Same parser as config/config.php — kept identical so a value that works here works there. */
function parse_env_file(string $path): array
{
    $out = [];
    foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $out[trim($key)] = trim($value, " \t\"'");
    }

    return $out;
}

/**
 * config/config.php strips quotes and surrounding whitespace from every value,
 * so a password that starts or ends with them would be silently altered and the
 * site would fail to connect *after* setup, with no obvious cause. Reject it
 * here, while the operator can still change it.
 */
function env_safe(string $value): bool
{
    return $value === trim($value, " \t\"'") && !preg_match('/[\r\n]/', $value);
}

// ── Gate 1: already installed? ───────────────────────────────────────────────

/** Ground truth, independent of the lock file: does a usable install already exist? */
function install_complete(): bool
{
    if (!is_file(ENV_PATH)) {
        return false;
    }

    $env = parse_env_file(ENV_PATH);
    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $env['DB_HOST'] ?? '127.0.0.1',
                (int) ($env['DB_PORT'] ?? 3306),
                $env['DB_DATABASE'] ?? ''
            ),
            $env['DB_USERNAME'] ?? '',
            $env['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (Throwable) {
        // Unreachable database or missing tables means the previous attempt did
        // not finish. Allow a resume — the token gate still stands in the way.
        return false;
    }
}

// An authenticated session that has already created its administrator is
// mid-wizard: MFA enrolment still has to happen, and install_complete() has
// started returning true for it. Let that one session through — reaching this
// state required the setup token in the first place.
$midInstall = !empty($_SESSION['authed']) && !empty($_SESSION['admin_id']);

if (is_file(LOCK_PATH) || (!$midInstall && install_complete())) {
    http_response_code(403);
    render('Already installed', <<<HTML
        <p class="lead">This portal is already set up.</p>
        <p>The installer will not run again. If you need to reset it, remove
        <code>storage/installed.lock</code> and drop the database — that destroys
        all existing content.</p>
        <p><a class="btn" href="/admin/login">Go to the sign-in page</a></p>
        <p class="muted">For security, delete <code>public/install.php</code> from
        the server if it is still there.</p>
    HTML);
}

// ── Gate 2: setup token ──────────────────────────────────────────────────────

function setup_token(): ?string
{
    // An explicitly supplied token wins. Container deployments set this in the
    // environment: there the web server runs as its own user, so a 0600 file it
    // creates is unreadable by the person doing the install.
    if ($fromEnv = getenv('PORTAL_SETUP_TOKEN')) {
        return trim($fromEnv);
    }

    if (is_file(TOKEN_PATH)) {
        $existing = trim((string) file_get_contents(TOKEN_PATH));
        if ($existing !== '') {
            return $existing;
        }
    }

    if (!is_dir(STORAGE_PATH) || !is_writable(STORAGE_PATH)) {
        return null;
    }

    $token = bin2hex(random_bytes(16));
    file_put_contents(TOKEN_PATH, $token . "\n");
    @chmod(TOKEN_PATH, 0600);

    // Mirrored to the error log, which is where a container operator can
    // actually reach it (`docker compose logs`). On shared hosting PHP runs as
    // the account user, so the file itself is readable and this is redundant.
    error_log('portal.lift setup token: ' . $token);

    return $token;
}

/** The token is the shared secret for the whole wizard, so it is also the CSRF check. */
function require_post_token(): void
{
    $sent = (string) ($_POST['_token'] ?? '');
    $held = (string) ($_SESSION['token'] ?? '');
    if ($held === '' || !hash_equals($held, $sent)) {
        http_response_code(400);
        render('Session expired', '<p class="lead">That form expired. <a href="install.php">Start again</a>.</p>');
    }
}

function token_field(): string
{
    return '<input type="hidden" name="_token" value="' . h((string) ($_SESSION['token'] ?? '')) . '">';
}

$step = (string) ($_GET['step'] ?? 'token');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (empty($_SESSION['authed'])) {
    $token = setup_token();

    if ($token === null) {
        render('Setup blocked', <<<HTML
            <p class="lead">The <code>storage/</code> directory is not writable.</p>
            <p>The installer needs to write a one-time setup token there before it
            can start. Set the directory's permissions to <code>755</code> (or
            <code>775</code>) and reload this page.</p>
            <p class="muted">Full path: <code>STORAGE</code></p>
        HTML, ['STORAGE' => STORAGE_PATH]);
    }

    $error = null;
    if ($isPost) {
        $sent = trim((string) ($_POST['setup_token'] ?? ''));
        // Length-independent comparison; hash_equals leaks length otherwise.
        if (hash_equals(hash('sha256', $token), hash('sha256', $sent))) {
            session_regenerate_id(true);
            $_SESSION['authed'] = true;
            $_SESSION['token'] = $token;
            redirect('requirements');
        }
        $error = 'That token does not match the one in the file.';
    }

    render('Setup token', <<<HTML
        <p class="lead">Prove you are the person who uploaded these files.</p>
        <p>Open this file on your server and paste what is inside:</p>
        <p><code class="path">TOKENPATH</code></p>
        <p class="muted">Most hosting control panels have a File Manager that can
        open it. It is a single line of letters and numbers.</p>
        <p class="muted">Running in Docker? The same value is in the server log
        (<code>docker compose logs web</code>), or set <code>PORTAL_SETUP_TOKEN</code>
        in your environment to choose it yourself.</p>
        ERROR
        <form method="post">
            <label for="setup_token">Setup token</label>
            <input id="setup_token" name="setup_token" autocomplete="off" autofocus
                   spellcheck="false" required>
            <button class="btn" type="submit">Continue</button>
        </form>
    HTML, [
        'TOKENPATH' => TOKEN_PATH,
        'ERROR' => $error ? '<p class="err">' . h($error) . '</p>' : '',
    ]);
}

// ── Step: requirements ───────────────────────────────────────────────────────

if ($step === 'requirements') {
    $envDir = dirname(ENV_PATH);
    $uploads = APP_ROOT . '/storage/uploads';

    $checks = [
        ['PHP 8.3 or newer', PHP_VERSION_ID >= 80300, PHP_VERSION, true],
        ['pdo_mysql extension', extension_loaded('pdo_mysql'), 'database access', true],
        ['gd extension', extension_loaded('gd'), 'image resizing on upload', true],
        ['mbstring extension', extension_loaded('mbstring'), 'multi-byte text', true],
        ['openssl extension', extension_loaded('openssl'), 'encrypts your MFA secret', true],
        [
            'Dependencies installed',
            is_file(APP_ROOT . '/vendor/autoload.php'),
            'the vendor/ folder — included in the release download',
            true,
        ],
        [
            'Config location writable',
            is_writable($envDir) || (is_file(ENV_PATH) && is_writable(ENV_PATH)),
            $envDir,
            true,
        ],
        ['Uploads folder writable', is_dir($uploads) && is_writable($uploads), $uploads, true],
    ];

    // Advisory only — the site runs without these, with reduced function.
    $gd = extension_loaded('gd') ? gd_info() : [];
    $checks[] = ['JPEG support in GD', !empty($gd['JPEG Support']), 'photo uploads', false];
    $checks[] = ['PNG support in GD', !empty($gd['PNG Support']), 'logo uploads', false];
    $checks[] = ['WebP support in GD', !empty($gd['WebP Support']), 'WebP uploads', false];

    $blocked = false;
    $rows = '';
    foreach ($checks as [$label, $ok, $detail, $required]) {
        $blocked = $blocked || (!$ok && $required);
        $state = $ok ? 'ok' : ($required ? 'bad' : 'warn');
        $mark = $ok ? '&check;' : ($required ? '&times;' : '!');
        $rows .= sprintf(
            '<tr class="%s"><td class="mark">%s</td><td>%s</td><td class="muted">%s</td></tr>',
            $state,
            $mark,
            h($label),
            h($detail)
        );
    }

    $next = $blocked
        ? '<p class="err">Fix the items marked &times; above, then re-check. '
          . 'Your hosting provider can enable missing PHP extensions.</p>'
          . '<p><a class="btn" href="install.php?step=requirements">Re-check</a></p>'
        : '<p><a class="btn" href="install.php?step=database">Continue</a></p>';

    render('Server check', <<<HTML
        <p class="lead">Checking what this server can do.</p>
        <table class="checks">ROWS</table>
        NEXT
    HTML, ['ROWS' => $rows, 'NEXT' => $next]);
}

// ── Step: database ───────────────────────────────────────────────────────────

if ($step === 'database') {
    $f = $_SESSION['db'] ?? ['host' => '127.0.0.1', 'port' => '3306', 'name' => '', 'user' => '', 'pass' => ''];
    $error = null;
    $notes = [];

    if ($isPost) {
        require_post_token();
        $f = [
            'host' => trim((string) ($_POST['host'] ?? '')),
            'port' => trim((string) ($_POST['port'] ?? '3306')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'user' => trim((string) ($_POST['user'] ?? '')),
            // Not trimmed: a password may legitimately contain spaces.
            'pass' => (string) ($_POST['pass'] ?? ''),
        ];

        if ($f['host'] === '' || $f['name'] === '' || $f['user'] === '') {
            $error = 'Host, database name and username are all required.';
        } elseif (!ctype_digit($f['port']) || (int) $f['port'] < 1 || (int) $f['port'] > 65535) {
            $error = 'Port must be a number between 1 and 65535.';
        } elseif (!env_safe($f['pass']) || !env_safe($f['user']) || !env_safe($f['name'])) {
            $error = 'Values cannot begin or end with a space or a quote mark, '
                   . 'and cannot contain line breaks. Change the database password '
                   . 'in your control panel if necessary.';
        } else {
            [$ok, $message] = db_probe($f);
            if ($ok) {
                $_SESSION['db'] = $f;
                redirect('site');
            }
            $error = $message;
        }
    }

    render('Database', <<<HTML
        <p class="lead">Where should the portal store its content?</p>
        <p class="muted">On shared hosting, create an empty MySQL database and a user
        in your control panel first, then copy the details here. The name and username
        usually have your account name as a prefix.</p>
        ERROR
        <form method="post">
            TOKEN
            <label for="host">Database host</label>
            <input id="host" name="host" value="HOST" required spellcheck="false">
            <p class="hint">Usually <code>localhost</code> or <code>127.0.0.1</code>.</p>

            <label for="port">Port</label>
            <input id="port" name="port" value="PORT" required spellcheck="false">

            <label for="name">Database name</label>
            <input id="name" name="name" value="NAME" required spellcheck="false">

            <label for="user">Database username</label>
            <input id="user" name="user" value="USER" required spellcheck="false" autocomplete="off">

            <label for="pass">Database password</label>
            <input id="pass" name="pass" type="password" value="PASS" autocomplete="off">

            <button class="btn" type="submit">Test connection and continue</button>
        </form>
    HTML, [
        'ERROR' => $error ? '<p class="err">' . h($error) . '</p>' : '',
        'TOKEN' => token_field(),
        'HOST' => h($f['host']),
        'PORT' => h($f['port']),
        'NAME' => h($f['name']),
        'USER' => h($f['user']),
        'PASS' => h($f['pass']),
    ]);
}

/**
 * Connects, and creates the database if the server will allow it.
 *
 * @return array{0: bool, 1: string} ok, and a message the operator can act on
 */
function db_probe(array $f): array
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $f['host'], (int) $f['port']);
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8];

    try {
        $pdo = new PDO($dsn . ';dbname=' . $f['name'], $f['user'], $f['pass'], $opts);
    } catch (PDOException $e) {
        // getCode() carries the SQLSTATE ('42000', 'HY000'), not the driver's
        // number — errorInfo[1] is the one worth branching on.
        $errno = (int) ($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();

        if ($errno === 1049 || str_contains($message, 'Unknown database')) {
            // Managed hosting normally forbids CREATE DATABASE; self-hosted
            // setups normally allow it. Try, and give a useful answer either way.
            try {
                $server = new PDO($dsn, $f['user'], $f['pass'], $opts);
                $server->exec(sprintf(
                    'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    str_replace('`', '``', $f['name'])
                ));

                return [true, ''];
            } catch (PDOException) {
                return [false, sprintf(
                    'The database "%s" does not exist, and this user is not allowed to create it. '
                    . 'Create it in your hosting control panel, then try again.',
                    $f['name']
                )];
            }
        }

        // MySQL answers a wrong database name with 1044 "access denied to
        // database" rather than 1049, deliberately refusing to reveal whether
        // it exists. Checking 1045 first would therefore report a wrong name as
        // a wrong password — the single most misleading thing this page could
        // say to someone on shared hosting.
        if ($errno === 1044 || str_contains($message, 'to database')) {
            return [false, sprintf(
                'The user "%s" cannot open a database called "%s". Either the name is '
                . 'wrong, or the user has not been added to it. In cPanel-style control '
                . 'panels this is a separate step: "Add User To Database".',
                $f['user'],
                $f['name']
            )];
        }

        if ($errno === 1045 || str_contains($message, 'Access denied for user')) {
            return [false, 'The server rejected that username or password.'];
        }

        // 2002 covers refused connections, unknown hosts and bad sockets alike.
        if ($errno === 2002 || str_contains($message, 'getaddrinfo') || str_contains($message, "Can't connect")) {
            return [false, sprintf(
                'Could not reach a database server at %s:%s. Check the host and port.',
                $f['host'],
                $f['port']
            )];
        }

        return [false, 'Could not connect: ' . $message];
    }

    return [true, ''];
}

// ── Step: site and administrator ─────────────────────────────────────────────

if ($step === 'site') {
    if (empty($_SESSION['db'])) {
        redirect('database');
    }

    $guessUrl = (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $f = $_SESSION['site'] ?? [
        'title' => '', 'initials' => '', 'display_name' => '', 'url' => $guessUrl, 'username' => 'admin',
    ];
    $error = null;

    if ($isPost) {
        require_post_token();
        $f = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'initials' => strtoupper(trim((string) ($_POST['initials'] ?? ''))),
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'url' => rtrim(trim((string) ($_POST['url'] ?? '')), '/'),
            'username' => trim((string) ($_POST['username'] ?? '')),
        ];
        $pass = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($f['title'] === '') {
            $error = 'Give the site a title.';
        } elseif (!preg_match('/^[A-Za-z0-9]{1,8}$/', $f['initials'])) {
            $error = 'Initials must be 1-8 letters or numbers — they become the monogram and the icon.';
        } elseif (!filter_var($f['url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $f['url'])) {
            $error = 'The site address must be a full URL, starting with http:// or https://.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $f['username'])) {
            $error = 'The username must be 3-64 characters: letters, numbers, dot, underscore or hyphen.';
        } elseif (strlen($pass) < MIN_PASSWORD_LEN) {
            $error = 'The password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        } elseif (!hash_equals($pass, $confirm)) {
            $error = 'The two passwords do not match.';
        } else {
            $_SESSION['site'] = $f;
            [$ok, $message] = run_install($_SESSION['db'], $f, $pass);
            if ($ok) {
                redirect('mfa');
            }
            $error = $message;
        }
    }

    $min = MIN_PASSWORD_LEN;
    render('Your site', <<<HTML
        <p class="lead">Name the site, and create the administrator account.</p>
        ERROR
        <form method="post">
            TOKEN
            <label for="title">Site title</label>
            <input id="title" name="title" value="TITLE" required maxlength="255" autofocus>
            <p class="hint">Shown in the browser tab, and in your authenticator app.</p>

            <label for="initials">Initials</label>
            <input id="initials" name="initials" value="INITIALS" required maxlength="8" spellcheck="false">
            <p class="hint">Used for the monogram and the site icon, e.g. <code>NX</code>.</p>

            <label for="display_name">Your name</label>
            <input id="display_name" name="display_name" value="DISPLAYNAME" maxlength="128">
            <p class="hint">Shown on the public page and in the footer. Optional — you can set it later.</p>

            <label for="url">Site address</label>
            <input id="url" name="url" value="URL" required spellcheck="false">
            <p class="hint">The public address of this site, with no trailing slash.</p>

            <hr>

            <label for="username">Administrator username</label>
            <input id="username" name="username" value="USERNAME" required spellcheck="false" autocomplete="off">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <p class="hint">At least MIN characters. Use a password manager — this account
            controls the whole site.</p>

            <label for="password_confirm">Repeat password</label>
            <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">

            <button class="btn" type="submit">Install</button>
        </form>
    HTML, [
        'ERROR' => $error ? '<p class="err">' . h($error) . '</p>' : '',
        'TOKEN' => token_field(),
        'TITLE' => h($f['title']),
        'INITIALS' => h($f['initials']),
        'DISPLAYNAME' => h($f['display_name']),
        'URL' => h($f['url']),
        'USERNAME' => h($f['username']),
        'MIN' => (string) $min,
    ]);
}

/**
 * Writes .env, applies every migration, and creates the first administrator.
 *
 * Ordered so the irreversible database work happens only after .env is on disk:
 * if this fails halfway, re-running the installer resumes against the same
 * config rather than generating a second APP_KEY — which would make every
 * already-encrypted MFA secret undecryptable.
 *
 * @return array{0: bool, 1: string}
 */
function run_install(array $db, array $site, string $password): array
{
    try {
        // Reuse an existing APP_KEY if a previous attempt already wrote one.
        $existing = is_file(ENV_PATH) ? parse_env_file(ENV_PATH) : [];
        $appKey = $existing['APP_KEY'] ?? base64_encode(random_bytes(32));

        $env = <<<ENV
        # Written by the portal.lift installer. Keep this file private.
        APP_ENV=production
        APP_DEBUG=false
        APP_URL={$site['url']}

        # Encrypts your multi-factor secret at rest. Changing it locks every
        # administrator out of MFA and forces re-enrolment. Never regenerate it.
        APP_KEY={$appKey}

        DB_HOST={$db['host']}
        DB_PORT={$db['port']}
        DB_DATABASE={$db['name']}
        DB_USERNAME={$db['user']}
        DB_PASSWORD={$db['pass']}

        ENV;

        if (file_put_contents(ENV_PATH, $env) === false) {
            return [false, 'Could not write the configuration file at ' . ENV_PATH . '.'];
        }
        @chmod(ENV_PATH, 0600);

        // Multi-statement execution: each migration file holds several
        // statements. The application's own connection deliberately leaves this
        // off — only offline schema work needs it.
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['host'],
                (int) $db['port'],
                $db['name']
            ),
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
        );

        migrate($pdo);

        $pdo->prepare(
            'UPDATE site_settings SET page_title = :t, initials = :i, display_name = :d,
                    copyright_year = :y, copyright_text = :c
              WHERE id = 1'
        )->execute([
            ':t' => $site['title'],
            ':i' => $site['initials'],
            ':d' => $site['display_name'],
            ':y' => date('Y'),
            ':c' => $site['display_name'],
        ]);

        // Argon2id where the build supports it; PHP's default otherwise. Shared
        // hosts do not always compile in libargon2, and failing the install over
        // a hashing algorithm would be worse than using bcrypt.
        $algo = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u');
        $stmt->execute([':u' => $site['username']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $pdo->prepare('UPDATE admins SET password_hash = :h, is_protected = 1, force_mfa_setup = 1,
                                  mfa_enabled = 0, mfa_secret = NULL, status = \'active\' WHERE id = :id')
                ->execute([':h' => password_hash($password, $algo), ':id' => $existingId]);
            $adminId = (int) $existingId;
        } else {
            $pdo->prepare(
                'INSERT INTO admins (username, password_hash, is_protected, force_mfa_setup, mfa_enabled, status)
                 VALUES (:u, :h, 1, 1, 0, \'active\')'
            )->execute([':u' => $site['username'], ':h' => password_hash($password, $algo)]);
            $adminId = (int) $pdo->lastInsertId();
        }

        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_username'] = $site['username'];

        return [true, ''];
    } catch (Throwable $e) {
        return [false, 'Setup failed: ' . $e->getMessage()];
    }
}

/** Applies any migrations/*.sql not already recorded. Same contract as database/migrate.php. */
function migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
    sort($files);

    if ($files === []) {
        throw new RuntimeException('No migration files found in database/migrations/.');
    }

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }
        $pdo->exec((string) file_get_contents($file));
        $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)')->execute([$name]);
    }
}

// ── Step: multi-factor enrolment ─────────────────────────────────────────────

if ($step === 'mfa') {
    if (empty($_SESSION['admin_id'])) {
        redirect('site');
    }

    require APP_ROOT . '/vendor/autoload.php';

    // Held in the session, never in the database, until a code proves the
    // authenticator actually works. An unverified secret written to the admins
    // row would lock the account behind a QR code nobody successfully scanned.
    if (empty($_SESSION['mfa_secret'])) {
        $_SESSION['mfa_secret'] = App\Support\Totp::generateSecret();
    }
    $secret = (string) $_SESSION['mfa_secret'];
    $username = (string) $_SESSION['admin_username'];
    $error = null;

    if ($isPost) {
        require_post_token();
        $code = trim((string) ($_POST['code'] ?? ''));

        if (App\Support\Totp::verify($secret, $code)) {
            App\Models\Admin::setMfaSecret((int) $_SESSION['admin_id'], App\Support\Crypto::encrypt($secret));
            App\Models\Admin::activateMfa((int) $_SESSION['admin_id']);

            $codes = App\Support\RecoveryCodes::generate();
            App\Models\MfaRecoveryCode::replaceAll((int) $_SESSION['admin_id'], $codes);

            $_SESSION['recovery_codes'] = $codes;
            unset($_SESSION['mfa_secret']);
            redirect('codes');
        }

        // Wrong-clock is the most common cause on shared hosting and produces
        // exactly this symptom, so name it rather than just saying "invalid".
        $error = 'That code was not accepted. Codes change every 30 seconds — try the current one. '
               . 'If it keeps failing, this server\'s clock may be wrong: it currently reads '
               . gmdate('H:i') . ' UTC.';
    }

    $qr = App\Support\Totp::qrDataUri($username, $secret);
    $manual = App\Support\Totp::manualKey($secret);

    render('Security setup', <<<HTML
        <p class="lead">Set up your authenticator app.</p>
        <p>Signing in always requires a code from your phone as well as your password.
        Scan this with Google Authenticator, 1Password, Authy, or any similar app.</p>
        <p class="qr"><img src="QR" alt="Setup QR code" width="220" height="220"></p>
        <p class="muted">No camera? Type this key in instead:</p>
        <p><code class="path">MANUAL</code></p>
        ERROR
        <form method="post">
            TOKEN
            <label for="code">Enter the 6-digit code from your app</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" required autofocus spellcheck="false">
            <button class="btn" type="submit">Verify and continue</button>
        </form>
    HTML, [
        'QR' => $qr,
        'MANUAL' => h($manual),
        'ERROR' => $error ? '<p class="err">' . h($error) . '</p>' : '',
        'TOKEN' => token_field(),
    ]);
}

// ── Step: recovery codes ─────────────────────────────────────────────────────

if ($step === 'codes') {
    if (empty($_SESSION['recovery_codes'])) {
        redirect('mfa');
    }

    // The install is not sealed until the operator confirms they have saved
    // these. Sealing on arrival would mean a refresh — or a closed tab — loses
    // the only copy, with no way back in if the phone is later replaced.
    if ($isPost) {
        require_post_token();
        redirect('done');
    }

    $list = implode("\n", array_map('h', $_SESSION['recovery_codes']));

    render('Recovery codes', <<<HTML
        <p class="lead">Save these somewhere safe. They are shown once.</p>
        <p>Each code signs you in once if you lose your phone. Without them, and
        without your authenticator, nobody can get into this site.</p>
        <pre class="codes">CODES</pre>
        <form method="post">
            TOKEN
            <button class="btn" type="submit">I have saved these codes — finish</button>
        </form>
    HTML, ['CODES' => $list, 'TOKEN' => token_field()]);
}

// ── Step: done ───────────────────────────────────────────────────────────────

if ($step === 'done') {
    if (empty($_SESSION['admin_id'])) {
        redirect('token');
    }

    $sealed = @file_put_contents(LOCK_PATH, 'installed ' . gmdate('c') . "\n") !== false;
    @chmod(LOCK_PATH, 0600);
    @unlink(TOKEN_PATH);

    // Deleting the running file is safe on Linux: PHP has already read it.
    // If the web user cannot delete it, say so plainly — leaving it in place
    // is the one outcome that matters here.
    $removed = @unlink(__FILE__);

    $warning = '';
    if (!$removed) {
        $warning .= '<p class="err">Could not delete <code>public/install.php</code>. '
                  . 'Delete it yourself now, using your control panel\'s File Manager.</p>';
    }
    if (!$sealed) {
        $warning .= '<p class="err">Could not write <code>storage/installed.lock</code>. '
                  . 'The site still works, but make sure <code>public/install.php</code> is gone.</p>';
    }

    $body = <<<HTML
        <p class="lead">Your portal is ready.</p>
        WARNING
        <p>Sign in with the username and password you just chose, plus a code from
        your authenticator app.</p>
        <p><a class="btn" href="/admin/login">Sign in to the admin area</a></p>
        <p class="muted">The site itself is at <a href="/">the home page</a>. It is
        currently showing example content — you can replace or delete it from the
        admin area.</p>
    HTML;

    $rendered = strtr($body, ['WARNING' => $warning]);
    $_SESSION = [];
    session_destroy();
    render('All done', $rendered);
}

redirect('token');

// ── Rendering ────────────────────────────────────────────────────────────────

/**
 * Renders one page and stops. Placeholders are substituted rather than
 * interpolated so the templates above stay readable as plain HTML.
 *
 * @param array<string, string> $vars already-escaped substitutions
 */
function render(string $title, string $body, array $vars = []): never
{
    if ($vars !== []) {
        $body = strtr($body, $vars);
    }
    // Unindent the heredocs above without disturbing <pre> content.
    $body = preg_replace('/^[ \t]{4,}/m', '', $body);

    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    // These pages carry the database password and the recovery codes; no
    // intermediary or browser cache should ever hold on to them.
    header('Cache-Control: no-store, private');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">',
         '<meta name="viewport" content="width=device-width,initial-scale=1">',
         '<meta name="robots" content="noindex">',
         '<title>', h($title), ' — portal.lift setup</title><style>', styles(), '</style></head>',
         '<body><main><h1>', h($title), '</h1>', $body, '</main></body></html>';
    exit;
}

function styles(): string
{
    return <<<'CSS'
    :root { --bg:#04090b; --card:#07161a; --text:#e6f3f5; --muted:#7f9ba1;
            --accent:#22d3ee; --bad:#fb7185; --warn:#fb923c; --line:#123038; }
    * { box-sizing:border-box; }
    body { margin:0; padding:2.5rem 1rem; background:var(--bg); color:var(--text);
           font:16px/1.6 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
    main { max-width:34rem; margin:0 auto; background:var(--card); padding:2rem;
           border:1px solid var(--line); border-radius:10px; }
    h1 { margin:0 0 1.25rem; font-size:1.35rem; font-weight:600; letter-spacing:-0.01em; }
    .lead { font-size:1.05rem; margin-top:0; }
    .muted, .hint { color:var(--muted); font-size:0.875rem; }
    .hint { margin:0.25rem 0 1rem; }
    p { margin:0 0 1rem; }
    hr { border:0; border-top:1px solid var(--line); margin:1.75rem 0; }
    label { display:block; margin:0 0 0.35rem; font-weight:600; font-size:0.9rem; }
    input { width:100%; padding:0.6rem 0.7rem; margin-bottom:0.35rem; background:var(--bg);
            color:var(--text); border:1px solid var(--line); border-radius:6px; font:inherit; }
    input:focus { outline:2px solid var(--accent); outline-offset:1px; }
    .btn { display:inline-block; margin-top:1rem; padding:0.65rem 1.2rem; background:var(--accent);
           color:#04090b; border:0; border-radius:6px; font:inherit; font-weight:600;
           text-decoration:none; cursor:pointer; }
    .btn:hover { filter:brightness(1.1); }
    .err { padding:0.75rem 0.9rem; border-radius:6px; border:1px solid var(--bad);
           color:var(--bad); background:rgba(251,113,133,0.08); }
    code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:0.875em; }
    .path { display:block; padding:0.6rem 0.7rem; background:var(--bg); border:1px solid var(--line);
            border-radius:6px; word-break:break-all; }
    .codes { padding:1rem; background:var(--bg); border:1px solid var(--line); border-radius:6px;
             font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:0.06em;
             line-height:2; overflow-x:auto; user-select:all; }
    .qr { text-align:center; }
    .qr img { background:#fff; padding:0.5rem; border-radius:6px; max-width:100%; height:auto; }
    table.checks { width:100%; border-collapse:collapse; margin-bottom:1.25rem; }
    table.checks td { padding:0.45rem 0.5rem; border-bottom:1px solid var(--line); vertical-align:top; }
    table.checks .mark { width:1.5rem; font-weight:700; }
    table.checks tr.ok .mark { color:var(--accent); }
    table.checks tr.bad .mark { color:var(--bad); }
    table.checks tr.warn .mark { color:var(--warn); }
    a { color:var(--accent); }
    CSS;
}
