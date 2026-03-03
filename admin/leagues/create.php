<?php
$pageTitle='Create League'; $activeNav='leagues'; $leagueContext=null;
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
requireAdminRole();

$errors=[]; $values=['name'=>'','description'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $values['name']=trim(post('name')); $values['description']=trim(post('description'));
    if (empty($values['name'])) $errors[]='League name is required.';
    if (!empty($values['name'])) {
        $dup = getDB()->prepare('SELECT id FROM leagues WHERE LOWER(name) = LOWER(?)');
        $dup->execute([$values['name']]);
        if ($dup->fetch()) $errors[] = 'A league named "' . htmlspecialchars($values['name'], ENT_QUOTES) . '" already exists. Please choose a different name.';
    }
    if (empty($errors)) {
        $logo=null;
        if (!empty($_FILES['logo']['name'])) { $up=handleUpload($_FILES['logo'],'logos'); if(!$up['success']) $errors[]=$up['error']; else $logo=$up['filename']; }
        if (empty($errors)) {
            $admin=getCurrentAdmin();
            getDB()->prepare('INSERT INTO leagues (name,description,logo,created_by) VALUES (?,?,?,?)')->execute([$values['name'],$values['description'],$logo,$admin['id']]);
            $id=getDB()->lastInsertId();
            setFlash('success',"League '{$values['name']}' created!"); redirect(ADMIN_URL.'/leagues/dashboard.php?id='.$id);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/index.php">Leagues</a></li>
        <li class="breadcrumb-item active">New League</li>
    </ol>
</nav>
<div style="max-width:600px">
    <h1 class="h4 fw-bold mb-1">Create League</h1>
    <p class="text-muted mb-4" style="font-size:13px">Set up a new league. Teams, players, and seasons will live inside it.</p>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="border-radius:10px;font-size:13px">
        <?php foreach($errors as $e): ?><div><i class="fas fa-circle-xmark me-1"></i><?= clean($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">League Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= clean($values['name']) ?>" placeholder="e.g. Street Kings 3x3">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description..."><?= clean($values['description']) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label">League Logo</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                        <div id="logo-preview" style="width:60px;height:60px;border-radius:12px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#adb5bd;overflow:hidden;flex-shrink:0">
                            <i class="fas fa-image" style="font-size:20px"></i>
                        </div>
                        <div>
                            <input type="file" name="logo" id="logo-input" class="form-control form-control-sm" accept="image/*" style="max-width:280px">
                            <div class="text-muted mt-1" style="font-size:12px">PNG, JPG, WebP — max 2MB</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-brand"><i class="fas fa-plus me-1"></i> Create League</button>
                    <a href="<?= ADMIN_URL ?>/leagues/index.php" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('logo-input').addEventListener('change',function(e){
    const f=e.target.files[0]; if(!f) return;
    const r=new FileReader(); r.onload=ev=>{ const p=document.getElementById('logo-preview'); p.innerHTML=`<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`; }; r.readAsDataURL(f);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
