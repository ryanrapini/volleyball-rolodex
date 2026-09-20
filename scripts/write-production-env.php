<?php

/**
 * Writes the production .env for the rolodex.ryanrapini.com release layout.
 *
 * Run as the deploying user. The file lives in shared/ so every release symlinks
 * to the same one and no release ever needs secrets copied into it.
 *
 * Mail credentials are lifted from the sibling app that already has a working
 * Mailgun setup, so nothing has to be retyped — and nothing is echoed here.
 */

$app = '/srv/http/rolodex.ryanrapini.com';
$envPath = $app.'/shared/.env';
$mailSource = '/home/ryan/family-tree/.env';

if (file_exists($envPath) && ! in_array('--force', $argv, true)) {
    fwrite(STDERR, "Refusing to overwrite existing {$envPath} (pass --force to replace).\n");
    exit(1);
}

/**
 * Read the mail keys we need out of another app's env file.
 *
 * @return array<string, string>
 */
function mailValues(string $path): array
{
    if (! is_readable($path)) {
        return [];
    }

    $wanted = ['MAIL_DOMAIN', 'MAILGUN_DOMAIN', 'MAILGUN_SECRET', 'MAILGUN_ENDPOINT', 'MAIL_FROM_ADDRESS'];
    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);

        if (in_array($key, $wanted, true)) {
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    return $values;
}

// A Laravel-shaped key: base64 of 32 random bytes.
$key = 'base64:'.base64_encode(random_bytes(32));

$mail = mailValues($mailSource);
$mailDomain = $mail['MAILGUN_DOMAIN'] ?? $mail['MAIL_DOMAIN'] ?? '';
$mailSecret = $mail['MAILGUN_SECRET'] ?? '';
$mailEndpoint = $mail['MAILGUN_ENDPOINT'] ?? 'api.mailgun.net';
$mailFrom = $mail['MAIL_FROM_ADDRESS'] ?? 'hello@ryanrapini.com';

$hasMailgun = $mailDomain !== '' && $mailSecret !== '';

$mailBlock = $hasMailgun
    ? <<<MAIL
        MAIL_MAILER=mailgun
        MAIL_DOMAIN={$mailDomain}
        MAIL_FROM_ADDRESS="{$mailFrom}"
        MAIL_FROM_NAME="Volleyball Rolodex"
        MAILGUN_SECRET={$mailSecret}
        MAILGUN_ENDPOINT={$mailEndpoint}
        MAIL
    : <<<'MAIL'
        MAIL_MAILER=log
        MAIL_FROM_ADDRESS="hello@ryanrapini.com"
        MAIL_FROM_NAME="Volleyball Rolodex"
        MAIL;

// Heredocs indent to the closing marker; strip the leading whitespace again.
$mailBlock = implode("\n", array_map(
    fn (string $line): string => ltrim($line),
    explode("\n", $mailBlock),
));

$template = <<<ENV
APP_NAME="Volleyball Rolodex"
APP_ENV=production
APP_KEY={$key}
APP_DEBUG=false
APP_TIMEZONE=America/New_York
APP_URL=https://rolodex.ryanrapini.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# Shared SQLite database, outside the release directories so it survives deploys.
DB_CONNECTION=sqlite
DB_DATABASE={$app}/shared/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
# avatars are served from /storage, so the public disk is the default here
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
CACHE_STORE=database

{$mailBlock}

VITE_APP_NAME="\${APP_NAME}"
ENV;

if (file_put_contents($envPath, $template) === false) {
    fwrite(STDERR, "Could not write {$envPath}\n");
    exit(1);
}

chmod($envPath, 0640);

echo "wrote {$envPath}\n";
echo 'mail transport: '.($hasMailgun ? "mailgun ({$mailDomain})" : 'log — Mailgun values not found')."\n";
echo 'mail keys imported: '.($mail === [] ? 'none' : implode(', ', array_keys($mail)))."\n";
echo "APP_KEY={$key}\n";
