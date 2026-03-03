<?php
/**
 * The Battle 3x3 — v3
 * Authentication & Role Helpers
 */

// ─── Role Constants ───────────────────────────────────────────────────────────

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_ADMIN       = 'admin';
const ROLE_SCORER      = 'scorer';

/** Numeric weight — higher = more powerful */
const ROLE_WEIGHT = [
    'super_admin' => 100,
    'admin'       => 50,
    'scorer'      => 10,
];

// ─── Session Helpers ──────────────────────────────────────────────────────────

function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        redirect(ADMIN_URL . '/login.php');
    }
}

/**
 * Get current logged-in admin (cached per request).
 */
function getCurrentAdmin(): ?array
{
    if (!isLoggedIn()) return null;

    static $admin = null;
    if ($admin === null) {
        $stmt = getDB()->prepare(
            'SELECT id, username, email, full_name, role, is_active, is_protected, created_by
             FROM admins WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
    }
    return $admin;
}

// ─── Role Checks ──────────────────────────────────────────────────────────────

function currentRole(): string
{
    return getCurrentAdmin()['role'] ?? 'scorer';
}

function isSuperAdmin(): bool
{
    return currentRole() === 'super_admin';
}

function isAdmin(): bool
{
    return in_array(currentRole(), ['super_admin', 'admin']);
}

function isScorer(): bool
{
    return currentRole() === 'scorer';
}

function hasRole(string ...$roles): bool
{
    return in_array(currentRole(), $roles);
}

/**
 * Require a minimum role — redirects with error if insufficient.
 */
function requireRole(string ...$allowedRoles): void
{
    requireAuth();
    if (!hasRole(...$allowedRoles)) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect(ADMIN_URL . '/index.php');
    }
}

function requireAdminRole(): void
{
    requireRole('super_admin', 'admin');
}

function requireSuperAdminRole(): void
{
    requireRole('super_admin');
}

// ─── User Hierarchy / Permission Logic ───────────────────────────────────────

/**
 * Can $actor edit or delete $target?
 *
 * Rules:
 * 1. Super Admin (is_protected) can never be edited/deleted by anyone.
 * 2. Self-management is handled separately via canEditSelf().
 * 3. Super Admin can manage any non-protected user (including other super_admins).
 * 4. Admin can manage other admins and scorers (not super_admins).
 * 5. Scorer cannot manage anyone.
 */
function canManageUser(array $actor, array $target): bool
{
    // Protected first-super-admin — untouchable
    if (!empty($target['is_protected'])) return false;

    // Self-editing is handled via canEditSelf(), not here
    if ((int)$actor['id'] === (int)$target['id']) return false;

    $actorWeight  = ROLE_WEIGHT[$actor['role']]  ?? 0;
    $targetWeight = ROLE_WEIGHT[$target['role']] ?? 0;

    // Actor must have strictly HIGHER or EQUAL weight (super_admin can touch other super_admins)
    // But admin (50) cannot touch super_admin (100)
    if ($actorWeight < $targetWeight) return false;

    return true;
}

/**
 * Can a user edit their own profile?
 * Anyone logged in can edit themselves (username/email/full_name/password) but NOT role.
 */
function canEditSelf(array $actor, array $target): bool
{
    return (int)$actor['id'] === (int)$target['id'];
}

/**
 * Which roles can $actor create?
 */
function creatableRoles(array $actor): array
{
    return match($actor['role']) {
        'super_admin' => ['super_admin', 'admin', 'scorer'],
        'admin'       => ['admin', 'scorer'],
        default       => [],
    };
}

// ─── Login / Logout ───────────────────────────────────────────────────────────

function loginAdmin(string $username, string $password): bool
{
    $stmt = getDB()->prepare(
        'SELECT id, password, is_active FROM admins WHERE username = ?'
    );
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function logoutAdmin(): void
{
    $_SESSION = [];
    session_destroy();
}
