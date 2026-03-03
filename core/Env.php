<?php
/**
 * The Battle 3x3 — Core
 * Environment Variable Loader
 *
 * Parses a .env file and populates $_ENV / getenv().
 * Call Env::load() once at bootstrap (config/app.php).
 *
 * Supports:
 *   KEY=value
 *   KEY="quoted value"
 *   # comments
 *   Blank lines
 */

class Env
{
    private static bool $loaded = false;

    /**
     * Load a .env file. Safe to call multiple times — only loads once.
     *
     * @param string $path  Absolute path to the .env file.
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($path)) {
            // No .env file — rely on system environment (production servers often set env vars directly)
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain an = sign
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes (single or double)
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Don't overwrite values already in the environment (system env takes priority)
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key]  = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with an optional default.
     *
     * @param string $key
     * @param mixed  $default  Returned when the key is not set.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // Cast booleans written as strings
        return match(strtolower((string) $value)) {
            'true', '1', 'yes'  => true,
            'false', '0', 'no'  => false,
            default             => $value,
        };
    }

    /**
     * Get a required environment variable. Throws if missing.
     *
     * @throws RuntimeException
     */
    public static function require(string $key): mixed
    {
        $value = self::get($key);
        if ($value === null) {
            throw new RuntimeException("Required environment variable \"{$key}\" is not set. Check your .env file.");
        }
        return $value;
    }
}
