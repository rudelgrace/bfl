<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$leagueId=intGet('league_id'); $teamId=intGet('id'); $isEdit=$teamId>0;
$league=requireLeague($leagueId); $leagueContext=$league; $activeSidebar='teams'; $activeNav='leagues';
$team=['name'=>'','logo'=>null]; $errors=[];
if ($isEdit) { $s=getDB()->prepare('SELECT * FROM teams WHERE id=? AND league_id=?'); $s->execute([$teamId,$leagueId]); $team=$s->fetch(); if(!$team){setFlash('error','Team not found.');redirect(ADMIN_URL.'/teams/index.php?league_id='.$leagueId);} }
$pageTitle=$isEdit?'Edit Team':'New Team';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim(post('name')); if(empty($name)) $errors[]='Team name required.';
    if (empty($errors)) { $logo=$team['logo'];
        if(!empty($_FILES['logo']['name'])){$up=handleUpload($_FILES['logo'],'logos');if(!$up['success'])$errors[]=$up['error'];else{deleteUpload($logo);$logo=$up['filename'];}}
        if(empty($errors)){
            if($isEdit){getDB()->prepare('UPDATE teams SET name=?,logo=? WHERE id=?')->execute([$name,$logo,$teamId]);setFlash('success','Team updated.');redirect(ADMIN_URL.'/teams/index.php?league_id='.$leagueId);}
            else{getDB()->prepare('INSERT INTO teams (league_id,name,logo) VALUES (?,?,?)')->execute([$leagueId,$name,$logo]);setFlash('success',"Team '$name' created.");}
            redirect(ADMIN_URL.'/teams/index.php?league_id='.$leagueId);
        }
    }
    $team['name']=$name;
}

require_once __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/teams/index.php?league_id=<?= $leagueId ?>">Teams</a></li>
    <li class="breadcrumb-item active"><?= $isEdit?'Edit':'New Team' ?></li>
</ol></nav>
<div style="max-width:480px">
    <h1 class="h4 fw-bold mb-4"><?= $isEdit?'Edit Team':'New Team' ?></h1>
    <?php if(!empty($errors)): ?><div class="alert alert-danger" style="border-radius:10px;font-size:13px"><?php foreach($errors as $e): ?><div><?= clean($e) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="card"><div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3"><label class="form-label">Team Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= clean($team['name']) ?>" placeholder="e.g. Ballers FC"></div>
            <div class="mb-4"><label class="form-label">Team Logo</label>
                <div class="d-flex align-items-center gap-3 mt-1">
                    <div id="logo-preview" style="width:52px;height:52px;border-radius:10px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                        <?php if(!empty($team['logo'])): ?><img src="<?= UPLOADS_URL.'/'.$team['logo'] ?>" style="width:100%;height:100%;object-fit:cover">
                        <?php else: ?><i class="fas fa-image text-muted"></i><?php endif; ?></div>
                    <input type="file" name="logo" id="logo-input" class="form-control form-control-sm" accept="image/*">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-brand"><i class="fas fa-<?= $isEdit?'check':'plus' ?> me-1"></i> <?= $isEdit?'Save Changes':'Create Team' ?></button>
                <a href="<?= ADMIN_URL ?>/teams/index.php?league_id=<?= $leagueId ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
<script>document.getElementById('logo-input')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=ev=>{document.getElementById('logo-preview').innerHTML=`<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`;};r.readAsDataURL(f);});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
