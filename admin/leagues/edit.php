<?php
/**
 * Admin — League Settings
 * Includes: name, description, rules, structure, logo
 *
 * v1.2 — Added `rules` and `structure` fields so the admin can manage
 *         content displayed on the public About / Battle 3x3 page.
 */
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();

$leagueId      = intGet('id');
$league        = requireLeague($leagueId);
$leagueContext = $league;
$activeSidebar = 'settings';
$activeNav     = 'leagues';
$pageTitle     = 'Settings';
$errors        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim(post('name'));
    $description = trim(post('description'));
    $rules       = trim(post('rules'));
    $structure   = trim(post('structure'));

    if (empty($name)) {
        $errors[] = 'League name is required.';
    }

    if (!empty($name)) {
        $dup = getDB()->prepare('SELECT id FROM leagues WHERE LOWER(name) = LOWER(?) AND id != ?');
        $dup->execute([$name, $leagueId]);
        if ($dup->fetch()) {
            $errors[] = 'A league named "' . htmlspecialchars($name, ENT_QUOTES) . '" already exists. Please choose a different name.';
        }
    }

    if (empty($errors)) {
        $logo = $league['logo'];

        if (!empty($_FILES['logo']['name'])) {
            $up = handleUpload($_FILES['logo'], 'logos');
            if (!$up['success']) {
                $errors[] = $up['error'];
            } else {
                deleteUpload($logo);
                $logo = $up['filename'];
            }
        }

        if (empty($errors)) {
            getDB()->prepare(
                'UPDATE leagues SET name=?, description=?, rules=?, structure=?, logo=? WHERE id=?'
            )->execute([$name, $description, $rules ?: null, $structure ?: null, $logo, $leagueId]);

            setFlash('success', 'League updated.');
            redirect(ADMIN_URL . '/leagues/dashboard.php?id=' . $leagueId);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>">
                <?= clean($league['name']) ?>
            </a>
        </li>
        <li class="breadcrumb-item active">Settings</li>
    </ol>
</nav>

<div style="max-width:680px">
    <h1 class="h4 fw-bold mb-4">League Settings</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="border-radius:10px;font-size:13px">
        <?php foreach ($errors as $e): ?>
            <div><?= clean($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <!-- ── General ──────────────────────────────────────── -->
        <div class="card mb-3">
            <div class="card-header"><h2>General</h2></div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">
                        League Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= clean($league['name']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="Short description shown on the public About page…"><?= clean($league['description'] ?? '') ?></textarea>
                    <div class="form-text">
                        Displayed in the "What is Battle 3x3?" section of the public website.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Logo</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                        <div id="logo-preview"
                             style="width:56px;height:56px;border-radius:12px;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <?php if (!empty($league['logo'])): ?>
                                <img src="<?= UPLOADS_URL . '/' . $league['logo'] ?>"
                                     style="width:100%;height:100%;object-fit:cover">
                            <?php else: ?>
                                <i class="fas fa-image text-muted" style="font-size:18px"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" id="logo-input"
                               class="form-control form-control-sm"
                               accept="image/*" style="max-width:260px">
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Public Content ────────────────────────────────── -->
        <div class="card mb-3">
            <div class="card-header">
                <h2>Public About Page Content</h2>
                <div class="text-muted" style="font-size:12px;margin-top:2px">
                    These fields are displayed on the public "About / Battle 3x3" page.
                    Leave blank to hide the section.
                </div>
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Rules</label>
                    <textarea name="rules" class="form-control" rows="6"
                              placeholder="e.g. Game to 50 points • Win by 2 • 14-second possession clock&#10;Inside arc: 2 points • Outside arc: 3 points • Free throw: 1 point…"><?= clean($league['rules'] ?? '') ?></textarea>
                    <div class="form-text">
                        Shown in the "Rules & Structure" section. Supports plain text with line breaks.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Structure</label>
                    <textarea name="structure" class="form-control" rows="5"
                              placeholder="e.g. Teams play a round-robin regular season, followed by a single-elimination playoff bracket for the top 4 teams…"><?= clean($league['structure'] ?? '') ?></textarea>
                    <div class="form-text">
                        Describes the season format (regular season, playoffs, roster rules, etc.).
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Actions ──────────────────────────────────────── -->
        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-brand">
                <i class="fas fa-check me-1"></i> Save Changes
            </button>
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"
               class="btn btn-light">Cancel</a>
        </div>

    </form>

    <!-- ── Danger Zone ────────────────────────────────────────── -->
    <div class="card border-danger" style="border-color:#fca5a5!important">
        <div class="card-header" style="border-bottom-color:#fee2e2">
            <h2 class="text-danger">Danger Zone</h2>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-medium mb-1">Delete this league</div>
                    <div class="text-muted" style="font-size:12px">
                        Permanently deletes all teams, players, seasons and games. Cannot be undone.
                    </div>
                </div>
                <a href="<?= ADMIN_URL ?>/leagues/delete.php?id=<?= $leagueId ?>"
                   data-confirm="DELETE league '<?= clean($league['name']) ?>'?\n\nAll teams, players, seasons and games will be lost."
                   class="btn btn-danger btn-sm ms-3 flex-shrink-0">
                    <i class="fas fa-trash me-1"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('logo-input')?.addEventListener('change', function (e) {
    const f = e.target.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = ev => {
        document.getElementById('logo-preview').innerHTML =
            `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`;
    };
    r.readAsDataURL(f);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
