<?php

/**
 * Upsert keys into an existing production .env without touching anything else.
 *
 * rewriting the file is what regenerates APP_KEY, and a new APP_KEY logs
 * everyone out. So when a key arrives later (an API key handed over after the
 * first deploy), add it surgically instead of re-running the writer.
 *
 * Usage: php scripts/add-env-keys.php OPENAI_API_KEY OPENAI_MODEL
 *
 * Values are read from the keys' own names in the local checkout's .env. Nothing
 * is printed except which keys were written.
 */

$envPath = '/srv/http/rolodex.ryanrapini.com/shared/.env';
$localPath = '/home/ryan/volleyball-rolodex/.env';

$names = array_slice($argv, 1);

if ($names === []) {
    fwrite(STDERR, "Usage: php scripts/add-env-keys.php KEY_NAME [KEY_NAME...]\n");
    exit(1);
}

if (! is_readable($envPath) || ! is_readable($localPath)) {
    fwrite(STDERR, "Need both {$envPath} and {$localPath} to be readable.\n");
    exit(1);
}

/**
 * @return array<string, string>
 */
function parseEnv(string $path): array
{
    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value);
    }

    return $values;
}

$local = parseEnv($localPath);
$production = parseEnv($envPath);
$lines = file($envPath, FILE_IGNORE_NEW_LINES);

$written = [];

foreach ($names as $name) {
    if (! array_key_exists($name, $local) || trim($local[$name]) === '') {
        fwrite(STDERR, "  {$name}: not set in the local .env, skipping\n");

        continue;
    }

    $line = $name.'='.$local[$name];

    if (array_key_exists($name, $production)) {
        $lines = array_map(
            fn (string $existing): string => str_starts_with($existing, $name.'=') ? $line : $existing,
            $lines,
        );
    } else {
        $lines[] = $line;
    }

    $written[] = $name;
}

file_put_contents($envPath, implode("\n", $lines)."\n");
chmod($envPath, 0640);

echo 'updated '.$envPath."\n";
echo 'keys written: '.($written === [] ? 'none' : implode(', ', $written))."\n";
