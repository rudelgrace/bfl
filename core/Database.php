<?php
/**
 * The Battle 3x3 — Core
 * Database Connection Manager
 *
 * Wraps PDO in a proper singleton class.
 * Always access via Database::getInstance() or the getDB() shim in functions.php.
 *
 * Usage in service classes:
 *   $pdo = Database::getInstance();
 *
 * Usage via legacy shim (existing admin files):
 *   $pdo = getDB();
 */

class Database
{
    private static ?PDO $instance = null;

    /** Prevent direct construction */
    private function __construct() {}
    private function __clone() {}

    /**
     * Return the shared PDO instance, creating it on first call.
     *
     * Configuration is read from constants defined in config/app.php,
     * which in turn reads from the .env file via Env::get().
     *
     * @throws RuntimeException on connection failure (wraps PDOException).
     */
    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Mask credentials — never expose them in output
            throw new RuntimeException(
                'Database connection failed. Check your .env configuration.',
                (int) $e->getCode(),
                $e
            );
        }

        return self::$instance;
    }

    /**
     * Force-close the connection. Useful in long-running CLI scripts.
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
