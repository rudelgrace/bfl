<?php
/**
 * The Battle 3x3 — Config
 * config/database.php
 *
 * Exposes the getDB() procedural function for backward compatibility.
 * All DB constants (DB_HOST, DB_NAME, etc.) are defined in config/app.php.
 *
 * New code should use Database::getInstance() directly or inject
 * PDO via the service container (app()).
 */

/**
 * Return the shared PDO instance.
 * Compatible with all existing admin files.
 *
 * @throws RuntimeException if connection fails.
 */
function getDB(): PDO
{
    return Database::getInstance();
}
