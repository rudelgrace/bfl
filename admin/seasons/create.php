<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$leagueId=intGet('league_id'); $league=requireLeague($leagueId);
$leagueContext=$league; $activeSidebar='seasons'; $activeNav='leagues'; $pageTitle='New Season';

$pdo=getDB();
$teams=$pdo->prepare('SELECT * FROM teams WHERE league_id=? ORDER BY name'); $teams->execute([$leagueId]); $teams=$teams->fetchAll();
$errors=[]; $values=['name'=>'','start_date'=>'','end_date'=>'','games_per_team'=>4,'playoff_teams_count'=>4,'selected_teams'=>[]];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $values['name']=trim(post('name')); $values['start_date']=trim(post('start_date')); $values['end_date']=trim(post('end_date'));
    $values['games_per_team']=intPost('games_per_team',4); $values['playoff_teams_count']=intPost('playoff_teams_count',4);
    $values['selected_teams']=array_map('intval',$_POST['teams']??[]);
    if(empty($values['name'])) $errors[]='Season name is required.';
    if(!empty($values['name'])) {
        $dup = $pdo->prepare('SELECT id FROM seasons WHERE league_id = ? AND LOWER(name) = LOWER(?)');
        $dup->execute([$leagueId, $values['name']]);
        if($dup->fetch()) $errors[] = 'A season named "' . htmlspecialchars($values['name'], ENT_QUOTES) . '" already exists in this league. Please choose a different name.';
    }
    if(count($values['selected_teams'])<4) $errors[]='Select at least 4 teams.';
    if($values['games_per_team']<1||$values['games_per_team']>20) $errors[]='Games per team must be 1–20.';
    if($values['playoff_teams_count']>count($values['selected_teams'])) $errors[]='Playoff teams cannot exceed selected teams.';
    if(empty($errors)){
        $s=$pdo->prepare('INSERT INTO seasons (league_id,name,start_date,end_date,games_per_team,playoff_teams_count) VALUES (?,?,?,?,?,?)');
        $s->execute([$leagueId,$values['name'],$values['start_date']?:null,$values['end_date']?:null,$values['games_per_team'],$values['playoff_teams_count']]);
        $sid=$pdo->lastInsertId();
        $it=$pdo->prepare('INSERT INTO season_teams (season_id,team_id) VALUES (?,?)');
        $is=$pdo->prepare('INSERT INTO standings (season_id,team_id) VALUES (?,?)');
        foreach($values['selected_teams'] as $tid){$it->execute([$sid,$tid]);$is->execute([$sid,$tid]);}
        setFlash('success',"Season '{$values['name']}' created! Now assign rosters.");
        redirect(ADMIN_URL.'/seasons/view.php?id='.$sid.'&league_id='.$leagueId);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $leagueId ?>">Seasons</a></li>
    <li class="breadcrumb-item active">New Season</li>
</ol></nav>
<div style="max-width:640px">
    <h1 class="h4 fw-bold mb-4">Create Season</h1>
    <?php if(!empty($errors)): ?><div class="alert alert-danger" style="border-radius:10px;font-size:13px"><?php foreach($errors as $e): ?><div><?= clean($e) ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if(count($teams)<4): ?>
    <div class="alert alert-warning" style="border-radius:10px;font-size:13px">
        <i class="fas fa-triangle-exclamation me-2"></i>You need at least 4 teams before creating a season.
        <a href="<?= ADMIN_URL ?>/teams/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm ms-2">Add Teams</a>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body">
        <form method="POST">
            <div class="mb-3"><label class="form-label">Season Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= clean($values['name']) ?>" placeholder="e.g. Summer League 2025"></div>
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= clean($values['start_date']) ?>"></div>
                <div class="col-6"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="<?= clean($values['end_date']) ?>"></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6"><label class="form-label">Games Per Team</label>
                    <input type="number" name="games_per_team" class="form-control" min="1" max="20" value="<?= $values['games_per_team'] ?>">
                    <div class="form-text">Regular season games per team</div></div>
                <div class="col-6"><label class="form-label">Playoff Teams</label>
                    <select name="playoff_teams_count" class="form-select">
                        <?php foreach([2,4,6,8] as $n): ?><option value="<?=$n?>" <?=$values['playoff_teams_count']==$n?'selected':''?>><?=$n?> teams</option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="mb-4">
                <label class="form-label">Select Teams <span class="text-danger">*</span></label>
                <div class="form-text mb-2">Choose teams for this season (minimum 4)</div>
                <div class="row g-2">
                    <?php foreach($teams as $t): ?>
                    <div class="col-6">
                        <label class="d-flex align-items-center gap-2 p-3 border rounded-3 cursor-pointer" style="cursor:pointer;transition:all .15s" onmouseover="this.style.borderColor='#f97316';this.style.background='#fff7ed'" onmouseout="this.style.borderColor='';this.style.background=''">
                            <input type="checkbox" name="teams[]" value="<?= $t['id'] ?>" class="form-check-input mt-0" style="accent-color:#f97316" <?=in_array($t['id'],$values['selected_teams'])?'checked':''?>>
                            <?php if(!empty($t['logo'])): ?><img src="<?= UPLOADS_URL.'/'.$t['logo'] ?>" style="width:28px;height:28px;border-radius:6px;object-fit:cover;flex-shrink:0" alt="">
                            <?php else: ?><div style="width:28px;height:28px;border-radius:6px;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0"><?= strtoupper(substr($t['name'],0,1)) ?></div><?php endif; ?>
                            <span style="font-size:13px;font-weight:500"><?= clean($t['name']) ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-brand"><i class="fas fa-plus me-1"></i> Create Season</button>
                <a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $leagueId ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
