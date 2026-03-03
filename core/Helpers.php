<?php
/**
 * The Battle 3x3 — Core
 * Utility Helpers
 *
 * Pure, stateless utility functions gathered in one place.
 * These have no database dependency — only PHP built-ins.
 *
 * All functions are also exposed as global procedural wrappers
 * in includes/functions.php for backward compatibility.
 */

class Helpers
{
    // ── Output / XSS ─────────────────────────────────────────────────────────

    /**
     * HTML-encode a value for safe output.
     * Accepts any scalar; trims whitespace first.
     */
    public static function clean(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    // ── HTTP ─────────────────────────────────────────────────────────────────

    /**
     * Redirect to $url and exit.
     */
    public static function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    // ── Flash Messages ───────────────────────────────────────────────────────

    /**
     * Store a flash message for the next request.
     *
     * @param string $type    'success' | 'error' | 'warning' | 'info'
     * @param string $message Human-readable message.
     */
    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Consume and return the flash message (cleared after reading).
     *
     * @return array{type: string, message: string}|null
     */
    public static function getFlash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    // ── Input ────────────────────────────────────────────────────────────────

    /**
     * Safe $_POST accessor with default.
     */
    public static function post(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Safe $_GET accessor with default.
     */
    public static function get(string $key, mixed $default = ''): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Integer-cast $_GET value.
     */
    public static function intGet(string $key, int $default = 0): int
    {
        return intval($_GET[$key] ?? $default);
    }

    /**
     * Integer-cast $_POST value.
     */
    public static function intPost(string $key, int $default = 0): int
    {
        return intval($_POST[$key] ?? $default);
    }

    // ── Date / Time ──────────────────────────────────────────────────────────

    /**
     * Format a Y-m-d date string for display.
     *
     * @param string|null $date    Y-m-d string or null.
     * @param string      $format  Any PHP date format string.
     */
    public static function formatDate(?string $date, string $format = 'M j, Y'): string
    {
        if (!$date) {
            return '—';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $dt->format($format) : $date;
    }

    // ── UI Badges ────────────────────────────────────────────────────────────

    /**
     * Render an HTML badge for a season status value.
     */
    public static function seasonStatusBadge(string $status): string
    {
        return match ($status) {
            'active'    => '<span class="badge-active">● Active</span>',
            'playoffs'  => '<span class="badge-playoffs">Playoffs</span>',
            'completed' => '<span class="badge-completed" style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;">Season Done</span>',
            default     => '<span class="badge-upcoming">Upcoming</span>',
        };
    }

    /**
     * Render an HTML badge for an admin role.
     */
    public static function roleBadge(string $role): string
    {
        $styles = match ($role) {
            'super_admin' => 'background:#fef3c7;color:#92400e;',
            'admin'       => 'background:#dbeafe;color:#1e40af;',
            'scorer'      => 'background:#f0fdf4;color:#166534;',
            default       => 'background:#f1f5f9;color:#475569;',
        };
        $label = self::roleLabel($role);
        return "<span style=\"font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;{$styles}\">{$label}</span>";
    }

    /**
     * Human-readable label for a role slug.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin',
            'admin'       => 'Admin',
            'scorer'      => 'Scorer',
            default       => ucfirst($role),
        };
    }
}
