<?php
$pageTitle = 'Users & Roles';
$activeNav = 'users';
$leagueContext = null;
require_once __DIR__ . '/../../includes/functions.php';

// Scorers can ONLY access this page to edit their own profile
if (isScorer()) {
    $selfEditId = intGet('edit', 0);
    $currentUser = getCurrentAdmin();
    if ($selfEditId !== (int)$currentUser['id']) {
        // If not self-editing, redirect to their games page
        redirect(ADMIN_URL . '/scorer/index.php');
    }
}

// Only admins and super admins can see full user management
if (!isScorer()) {
    requireAdminRole();
}

$pdo = getDB();
$me  = getCurrentAdmin();
$myCreatableRoles = creatableRoles($me);

$errors = [];
$values = ['username' => '', 'email' => '', 'full_name' => '', 'role' => 'scorer'];
$editingId   = intGet('edit', 0);
$editingUser = null;

if ($editingId > 0) {
    $s = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $s->execute([$editingId]);
    $editingUser = $s->fetch();
    if ($editingUser) {
        // Allow self-editing OR if actor can manage the target
        $isSelfEdit = canEditSelf($me, $editingUser);
        if (!$isSelfEdit && !canManageUser($me, $editingUser)) {
            setFlash('error', 'You do not have permission to edit this user.');
            redirect(ADMIN_URL . '/users/index.php');
        }
        $values = [
            'username'  => $editingUser['username'],
            'email'     => $editingUser['email'],
            'full_name' => $editingUser['full_name'],
            'role'      => $editingUser['role'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // ── CREATE ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $values['username']  = trim(post('username'));
        $values['email']     = trim(post('email'));
        $values['full_name'] = trim(post('full_name'));
        $values['role']      = post('role');
        $password            = post('password');

        if (empty($values['username'])) $errors[] = 'Username is required.';
        if (empty($values['email']))    $errors[] = 'Email is required.';
        if (strlen($password) < 8)     $errors[] = 'Password must be at least 8 characters.';
        if (!in_array($values['role'], $myCreatableRoles)) {
            $errors[] = 'You are not allowed to create a user with that role.';
        }

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    'INSERT INTO admins (username, email, full_name, password, role, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $values['username'],
                    $values['email'],
                    $values['full_name'],
                    password_hash($password, PASSWORD_BCRYPT),
                    $values['role'],
                    $me['id'],
                ]);
                setFlash('success', 'User created successfully.');
                redirect(ADMIN_URL . '/users/index.php');
            } catch (PDOException $e) {
                $errors[] = 'Username or email already exists.';
            }
        }
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $uid = intPost('user_id');
        $targetStmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
        $targetStmt->execute([$uid]);
        $target = $targetStmt->fetch();

        $isSelfEdit = $target && canEditSelf($me, $target);

        if (!$target || (!$isSelfEdit && !canManageUser($me, $target))) {
            setFlash('error', 'Permission denied.');
            redirect(ADMIN_URL . '/users/index.php');
        }

        $values['username']  = trim(post('username'));
        $values['email']     = trim(post('email'));
        $values['full_name'] = trim(post('full_name'));
        $values['role']      = post('role');
        $password            = post('password');

        if (empty($values['username'])) $errors[] = 'Username is required.';
        if (empty($values['email']))    $errors[] = 'Email is required.';

        // Self-editing: role stays the same regardless of form value
        if ($isSelfEdit && !canManageUser($me, $target)) {
            $values['role'] = $target['role'];
        } else {
            // Role can only be set to something the actor can create
            $allowedRolesForEdit = array_unique(array_merge($myCreatableRoles, [$target['role']]));
            if (!in_array($values['role'], $allowedRolesForEdit)) {
                $errors[] = 'You cannot assign that role.';
            }
        }

        if (empty($errors)) {
            try {
                if (!empty($password)) {
                    $pdo->prepare(
                        'UPDATE admins SET username=?, email=?, full_name=?, role=?, password=? WHERE id=?'
                    )->execute([
                        $values['username'], $values['email'], $values['full_name'],
                        $values['role'], password_hash($password, PASSWORD_BCRYPT), $uid,
                    ]);
                } else {
                    $pdo->prepare(
                        'UPDATE admins SET username=?, email=?, full_name=?, role=? WHERE id=?'
                    )->execute([
                        $values['username'], $values['email'], $values['full_name'],
                        $values['role'], $uid,
                    ]);
                }
                setFlash('success', 'User updated.');
                redirect(ADMIN_URL . '/users/index.php');
            } catch (PDOException $e) {
                $errors[] = 'Username or email already exists.';
            }
        }
    }

    // ── SOFT DELETE (deactivate) ───────────────────────────────────────────────
    if ($action === 'delete') {
        $uid = intPost('user_id');
        $targetStmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
        $targetStmt->execute([$uid]);
        $target = $targetStmt->fetch();

        if (!$target || !canManageUser($me, $target)) {
            setFlash('error', 'Permission denied — cannot delete this user.');
        } else {
            $pdo->prepare('UPDATE admins SET is_active = 0 WHERE id = ?')->execute([$uid]);
            setFlash('success', 'User deactivated.');
        }
        redirect(ADMIN_URL . '/users/index.php');
    }

    // ── REACTIVATE ────────────────────────────────────────────────────────────
    if ($action === 'reactivate') {
        $uid = intPost('user_id');
        $targetStmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
        $targetStmt->execute([$uid]);
        $target = $targetStmt->fetch();
        if ($target && canManageUser($me, $target)) {
            $pdo->prepare('UPDATE admins SET is_active = 1 WHERE id = ?')->execute([$uid]);
            setFlash('success', 'User reactivated.');
        } else {
            setFlash('error', 'Permission denied.');
        }
        redirect(ADMIN_URL . '/users/index.php');
    }
}

// Fetch all users (show inactive too for super admins)
$showInactive = isSuperAdmin() && intGet('show_inactive', 0);
$query = $showInactive
    ? 'SELECT a.*, c.username as created_by_name FROM admins a LEFT JOIN admins c ON a.created_by = c.id ORDER BY a.role, a.created_at'
    : 'SELECT a.*, c.username as created_by_name FROM admins a LEFT JOIN admins c ON a.created_by = c.id WHERE a.is_active = 1 ORDER BY a.role, a.created_at';
$allUsers = $pdo->query($query)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Users & Roles</h1>
        <p style="color:var(--text-3);font-size:13px;margin:0;">Manage who can access The Battle 3x3</p>
    </div>
    <?php if (isSuperAdmin()): ?>
    <a href="?<?= $showInactive ? '' : 'show_inactive=1' ?>" class="btn btn-light btn-sm">
        <i class="fas fa-<?= $showInactive ? 'eye-slash' : 'eye' ?> fa-xs me-1"></i>
        <?= $showInactive ? 'Hide Inactive' : 'Show Inactive' ?>
    </a>
    <?php endif; ?>
</div>

<!-- Role legend -->
<?php if (isAdmin()): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;border-left:4px solid #f59e0b;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <i class="fas fa-crown" style="color:#f59e0b;font-size:13px;"></i>
                <span style="font-weight:700;font-size:13px;">Super Admin</span>
                <?= roleBadge('super_admin') ?>
            </div>
            <p style="font-size:12px;color:var(--text-3);margin:0;">Full access. Cannot be edited or deleted. First registered user.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;border-left:4px solid #3b82f6;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <i class="fas fa-user-shield" style="color:#3b82f6;font-size:13px;"></i>
                <span style="font-weight:700;font-size:13px;">Admin</span>
                <?= roleBadge('admin') ?>
            </div>
            <p style="font-size:12px;color:var(--text-3);margin:0;">Full system access. Can create Scorer accounts. Cannot touch Super Admin.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;border-left:4px solid #22c55e;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <i class="fas fa-clipboard-list" style="color:#22c55e;font-size:13px;"></i>
                <span style="font-weight:700;font-size:13px;">Scorer</span>
                <?= roleBadge('scorer') ?>
            </div>
            <p style="font-size:12px;color:var(--text-3);margin:0;">Games-only access. Cannot see leagues, teams, or system overview.</p>
        </div>
    </div>
</div>
<?php endif; // isAdmin for role legend ?>

<div class="row g-4">
<?php if (isAdmin()): ?>
<div class="col-lg-8">
    <div class="card">
        <div class="card-header">
            <h2>All Users (<?= count($allUsers) ?>)</h2>
        </div>
        <?php if (empty($allUsers)): ?>
        <div class="card-body empty-state py-5"><p>No users found.</p></div>
        <?php endif; ?>
        <?php foreach ($allUsers as $u): ?>
        <div class="list-item" style="opacity:<?= $u['is_active'] ? '1' : '.5' ?>;">
            <div style="width:40px;height:40px;border-radius:50%;background:<?= $u['role']==='super_admin'?'#92400e':($u['role']==='admin'?'#1e40af':'#166534') ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                <?= strtoupper(substr($u['username'], 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0;margin-left:12px;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-weight:600;font-size:14px;"><?= clean($u['full_name'] ?: $u['username']) ?></span>
                    <?= roleBadge($u['role']) ?>
                    <?php if (!$u['is_active']): ?>
                    <span style="font-size:10px;background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:20px;font-weight:700;">Inactive</span>
                    <?php endif; ?>
                    <?php if ($u['is_protected']): ?>
                    <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-weight:700;"><i class="fas fa-lock fa-xs me-1"></i>Protected</span>
                    <?php endif; ?>
                    <?php if ((int)$u['id'] === (int)$me['id']): ?>
                    <span class="badge-active">You</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text-3);margin-top:2px;">
                    <?= clean($u['username']) ?> · <?= clean($u['email']) ?>
                    <?php if ($u['created_by_name']): ?>· <span style="color:var(--text-4);">Created by <?= clean($u['created_by_name']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                <?php
                $canManage = canManageUser($me, $u);
                $isSelf    = (int)$u['id'] === (int)$me['id'];
                if ($canManage || $isSelf):
                ?>
                    <?php if ($u['is_active']): ?>
                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-light" title="<?= $isSelf && !$canManage ? 'Edit My Profile' : 'Edit' ?>">
                        <i class="fas fa-edit fa-xs"></i>
                    </a>
                    <?php if ($canManage): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light" style="color:#dc2626;"
                            onclick="return confirm('Deactivate user \'<?= clean($u['username']) ?>\'? They will lose access but their data is kept.')"
                            title="Deactivate">
                            <i class="fas fa-user-slash fa-xs"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <?php if ($canManage): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="reactivate">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light" style="color:#16a34a;" title="Reactivate">
                            <i class="fas fa-user-check fa-xs"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; // isAdmin ?>

<div class="<?= isAdmin() ? 'col-lg-4' : 'col-lg-6 offset-lg-3' ?>">
    <?php
    $showForm = !empty($myCreatableRoles) || $editingUser;
    $isSelfEditing = $editingUser && canEditSelf($me, $editingUser) && !canManageUser($me, $editingUser);
    ?>
    <?php if ($showForm): ?>
    <div class="card">
        <div class="card-header"><h2><?= $editingUser ? ($isSelfEditing ? 'My Profile' : 'Edit User') : 'Add User' ?></h2></div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mb-3" style="border-radius:10px;font-size:13px;">
                <?php foreach ($errors as $e): ?><div><?= clean($e) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form method="POST" class="d-flex flex-column gap-3">
                <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
                <?php if ($editingUser): ?>
                <input type="hidden" name="user_id" value="<?= $editingUser['id'] ?>">
                <?php endif; ?>

                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= clean($values['full_name']) ?>">
                </div>
                <div>
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?= clean($values['username']) ?>">
                </div>
                <div>
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= clean($values['email']) ?>">
                </div>
                <?php if (!$isSelfEditing): ?>
                <div>
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <?php
                        $roleOptions = $myCreatableRoles;
                        if ($editingUser && !in_array($editingUser['role'], $roleOptions)) {
                            $roleOptions[] = $editingUser['role'];
                        }
                        foreach ($roleOptions as $r):
                        ?>
                        <option value="<?= $r ?>" <?= $values['role'] === $r ? 'selected' : '' ?>>
                            <?= roleLabel($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:11px;color:var(--text-3);margin-top:4px;">
                        <?php if (isSuperAdmin()): ?>You can create Super Admins, Admins and Scorers.
                        <?php else: ?>Admins can create Admin and Scorer accounts.
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="role" value="<?= clean($values['role']) ?>">
                <div style="font-size:12px;color:var(--text-3);background:var(--surface-3);padding:10px 12px;border-radius:8px;">
                    <i class="fas fa-lock fa-xs me-1"></i> Your role: <strong><?= roleLabel($values['role']) ?></strong> (cannot be changed by yourself)
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label">Password <?= $editingUser ? '(leave blank to keep)' : '<span class="text-danger">*</span>' ?></label>
                    <input type="password" name="password" class="form-control"
                        <?= !$editingUser ? 'required minlength="8"' : '' ?>
                        placeholder="<?= $editingUser ? 'Enter new password to change' : 'Min. 8 characters' ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-brand">
                        <i class="fas fa-<?= $editingUser ? 'check' : 'plus' ?> me-1"></i>
                        <?= $editingUser ? 'Update' : 'Create User' ?>
                    </button>
                    <?php if ($editingUser): ?>
                    <a href="<?= ADMIN_URL ?>/users/index.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:32px 20px;">
            <i class="fas fa-lock" style="font-size:28px;color:var(--text-4);margin-bottom:12px;display:block;"></i>
            <p style="font-size:13px;color:var(--text-3);margin:0;">Your role does not allow creating new users.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Access Matrix info card -->
    <div class="card mt-3">
        <div class="card-header"><h2 style="font-size:13px;">Access Matrix</h2></div>
        <div class="card-body" style="padding:0;">
            <table class="table mb-0" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="font-size:11px;">Feature</th>
                        <th class="text-center" style="font-size:11px;">Scorer</th>
                        <th class="text-center" style="font-size:11px;">Admin</th>
                        <th class="text-center" style="font-size:11px;">Super</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $matrix = [
                        ['View Leagues/Seasons',    false, true,  true],
                        ['Enter Game Stats',         true,  true,  true],
                        ['Edit Leagues/Teams',       false, true,  true],
                        ['Edit Matchups',            false, true,  true],
                        ['Manage Players',           false, true,  true],
                        ['Create Seasons',           false, true,  true],
                        ['View Users',               false, true,  true],
                        ['Create Admin Users',       false, true,  true],
                        ['Create Scorer Users',      false, true,  true],
                        ['Create Super Admin Users', false, false, true],
                        ['Deactivate/Reactivate Users', false, true, true],
                        ['Edit Super Admin',         false, false, false],
                    ];
                    foreach ($matrix as [$feat, $sc, $ad, $sa]):
                    ?>
                    <tr>
                        <td style="color:var(--text-2);"><?= $feat ?></td>
                        <td class="text-center"><?= $sc ? '<i class="fas fa-check" style="color:#22c55e;font-size:11px;"></i>' : '<i class="fas fa-times" style="color:#e2e8f0;font-size:11px;"></i>' ?></td>
                        <td class="text-center"><?= $ad ? '<i class="fas fa-check" style="color:#22c55e;font-size:11px;"></i>' : '<i class="fas fa-times" style="color:#e2e8f0;font-size:11px;"></i>' ?></td>
                        <td class="text-center"><?= $sa ? '<i class="fas fa-check" style="color:#22c55e;font-size:11px;"></i>' : '<i class="fas fa-times" style="color:#e2e8f0;font-size:11px;"></i>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
