<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$leagueId=intGet('league_id'); $playerId=intGet('id'); $isEdit=$playerId>0;
$league=requireLeague($leagueId); $leagueContext=$league; $activeSidebar='players'; $activeNav='leagues';
$player=['first_name'=>'','last_name'=>'','date_of_birth'=>'','position'=>'','height'=>'','photo'=>null,'bio'=>'']; $errors=[];
if ($isEdit) { $s=getDB()->prepare('SELECT * FROM players WHERE id=? AND league_id=?'); $s->execute([$playerId,$leagueId]); $player=$s->fetch(); if(!$player){setFlash('error','Not found.');redirect(ADMIN_URL.'/players/index.php?league_id='.$leagueId);} }
$pageTitle=$isEdit?'Edit Player':'New Player';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach(['first_name','last_name','date_of_birth','position','height','bio'] as $f) $player[$f]=trim(post($f));
    if(empty($player['first_name'])) $errors[]='First name required.';
    if(empty($player['last_name']))  $errors[]='Last name required.';
    if(empty($errors)){
        // Check for duplicate name in same league
        $dupCheck = getDB()->prepare(
            'SELECT id FROM players WHERE league_id = ? AND LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?) AND id != ?'
        );
        $dupCheck->execute([$leagueId, $player['first_name'], $player['last_name'], $isEdit ? $playerId : 0]);
        if ($dupCheck->fetch()) {
            $errors[] = 'A player named "' . htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) . '" already exists in this league.';
        }
    }
    if(empty($errors)){
        $photo=$player['photo'];
        if(!empty($_FILES['photo']['name'])){$up=handleUpload($_FILES['photo'],'photos');if(!$up['success'])$errors[]=$up['error'];else{deleteUpload($photo);$photo=$up['filename'];}}
        if(empty($errors)){
            $dob=!empty($player['date_of_birth'])?$player['date_of_birth']:null;
            if($isEdit){getDB()->prepare('UPDATE players SET first_name=?,last_name=?,date_of_birth=?,position=?,height=?,photo=?,bio=? WHERE id=?')->execute([$player['first_name'],$player['last_name'],$dob,$player['position'],$player['height'],$photo,$player['bio'],$playerId]);setFlash('success','Player updated.');redirect(ADMIN_URL.'/players/index.php?league_id='.$leagueId);}
            else{getDB()->prepare('INSERT INTO players (league_id,first_name,last_name,date_of_birth,position,height,photo,bio) VALUES (?,?,?,?,?,?,?,?)')->execute([$leagueId,$player['first_name'],$player['last_name'],$dob,$player['position'],$player['height'],$photo,$player['bio']]);setFlash('success','Player created.');}
            redirect(ADMIN_URL.'/players/index.php?league_id='.$leagueId);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
$positions=['Guard','Forward','Center','Point Guard','Shooting Guard','Small Forward','Power Forward'];
?>
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/players/index.php?league_id=<?= $leagueId ?>">Players</a></li>
    <li class="breadcrumb-item active"><?= $isEdit?'Edit':'New Player' ?></li>
</ol></nav>
<div style="max-width:540px">
    <h1 class="h4 fw-bold mb-4"><?= $isEdit?'Edit Player':'New Player' ?></h1>
    <?php if(!empty($errors)): ?><div class="alert alert-danger" style="border-radius:10px;font-size:13px"><?php foreach($errors as $e): ?><div><?= clean($e) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="card"><div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control" required value="<?= clean($player['first_name']) ?>"></div>
                <div class="col-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control" required value="<?= clean($player['last_name']) ?>"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= clean($player['date_of_birth']??'') ?>"></div>
                <div class="col-6"><label class="form-label">Height</label><input type="text" name="height" class="form-control" placeholder='e.g. 6&apos;2"' value="<?= clean($player['height']??'') ?>"></div>
            </div>
            <div class="mb-3"><label class="form-label">Position</label>
                <select name="position" class="form-select"><option value="">Select position</option>
                    <?php foreach($positions as $pos): ?><option value="<?=$pos?>" <?=($player['position']??'')===$pos?'selected':''?>><?=$pos?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4"><label class="form-label">Biography</label>
                <textarea name="bio" class="form-control" rows="4" placeholder="Player biography, achievements, background story..."><?= clean($player['bio']??'') ?></textarea>
                <div class="form-text">Optional: Tell the player's story for future public profiles</div>
            </div>
            <div class="mb-4"><label class="form-label">Photo</label>
                <div class="d-flex align-items-center gap-3 mt-1">
                    <div id="photo-preview" style="width:52px;height:52px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                        <?php if(!empty($player['photo'])): ?><img src="<?= UPLOADS_URL.'/'.$player['photo'] ?>" style="width:100%;height:100%;object-fit:cover">
                        <?php else: ?><i class="fas fa-user text-muted" style="font-size:18px"></i><?php endif; ?></div>
                    <input type="file" name="photo" id="photo-input" class="form-control form-control-sm" accept="image/*">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-brand"><i class="fas fa-<?= $isEdit?'check':'plus' ?> me-1"></i> <?= $isEdit?'Save Changes':'Create Player' ?></button>
                <a href="<?= ADMIN_URL ?>/players/index.php?league_id=<?= $leagueId ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
<script>document.getElementById('photo-input')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=ev=>{document.getElementById('photo-preview').innerHTML=`<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`;};r.readAsDataURL(f);});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
