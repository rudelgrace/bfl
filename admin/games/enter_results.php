<?php
require_once __DIR__ . '/../../includes/functions.php';
$gameId=intGet('id'); $seasonId=intGet('season_id'); $leagueId=intGet('league_id');
// Which tab to return to after saving (passed by the Enter Score link)
$returnTab = in_array(get('return_tab'), ['schedule','playoffs','recent']) ? get('return_tab') : '';
$pdo=getDB();
$game=$pdo->prepare("SELECT g.*,s.league_id,s.name as season_name,ht.name as home_team_name,at.name as away_team_name
    FROM games g JOIN seasons s ON g.season_id=s.id JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.id=?");
$game->execute([$gameId]); $game=$game->fetch();
if(!$game){setFlash('error','Game not found.');redirect(ADMIN_URL.'/leagues/index.php');}
$seasonId=$game['season_id']; $leagueId=$leagueId?:$game['league_id'];
$season=requireSeason($seasonId); $league=requireLeague($leagueId);

// Block editing when season is completed
if ($season['status'] === 'completed') {
    setFlash('error', 'This season is completed. Game results cannot be changed.');
    redirect(isScorer()
        ? ADMIN_URL . '/scorer/index.php'
        : ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $leagueId
    );
}

// Block editing regular-season games once playoffs have started
if ($game['game_type'] === 'regular' && $season['status'] === 'playoffs') {
    setFlash('error', 'Regular-season games cannot be edited after playoffs have started.');
    redirect(isScorer()
        ? ADMIN_URL . '/scorer/index.php'
        : ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $leagueId
    );
}
$leagueContext=$league; $activeSidebar='seasons'; $activeNav='leagues'; $pageTitle='Enter Results';
// Scorers get no sidebar — they don't need league navigation
if (isScorer()) { $leagueContext = null; $activeNav = 'scorer_games'; }

function rosterQ(PDO $pdo,int $sid,int $tid):array{
    $s=$pdo->prepare("SELECT sr.jersey_number,p.id as player_id,p.first_name,p.last_name,p.position
        FROM season_rosters sr JOIN players p ON sr.player_id=p.id
        WHERE sr.season_id=? AND sr.team_id=? AND sr.status='active' ORDER BY sr.jersey_number");
    $s->execute([$sid,$tid]); return $s->fetchAll();
}
function statsQ(PDO $pdo,int $gid,int $tid):array{
    $s=$pdo->prepare("SELECT * FROM player_game_stats WHERE game_id=? AND team_id=?");
    $s->execute([$gid,$tid]); $r=$s->fetchAll(); $i=[];
    foreach($r as $x) $i[$x['player_id']]=$x; return $i;
}
function sv(array $st,int $pid,string $f):int{ return (int)($st[$pid][$f]??0); }

$homeRoster=rosterQ($pdo,$seasonId,$game['home_team_id']);
$awayRoster=rosterQ($pdo,$seasonId,$game['away_team_id']);
$homeStats=statsQ($pdo,$gameId,$game['home_team_id']);
$awayStats=statsQ($pdo,$gameId,$game['away_team_id']);
$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $pdo->beginTransaction();
    try{
        foreach(['home'=>$game['home_team_id'],'away'=>$game['away_team_id']] as $side=>$tid){
            foreach($_POST['stats'][$side]??[] as $pid=>$s){
                $pid=intval($pid);
                $s2m=max(0,intval($s['two_points_made']??0)); $s2a=max($s2m,intval($s['two_points_attempted']??0));
                $s3m=max(0,intval($s['three_points_made']??0)); $s3a=max($s3m,intval($s['three_points_attempted']??0));
                $ftm=max(0,intval($s['free_throws_made']??0)); $fta=max($ftm,intval($s['free_throws_attempted']??0));
                $chk=$pdo->prepare('SELECT id FROM player_game_stats WHERE game_id=? AND player_id=?');
                $chk->execute([$gameId,$pid]); $eid=$chk->fetchColumn();
                if($eid) $pdo->prepare("UPDATE player_game_stats SET two_points_made=?,two_points_attempted=?,three_points_made=?,three_points_attempted=?,free_throws_made=?,free_throws_attempted=? WHERE id=?")->execute([$s2m,$s2a,$s3m,$s3a,$ftm,$fta,$eid]);
                else $pdo->prepare("INSERT INTO player_game_stats (game_id,player_id,team_id,two_points_made,two_points_attempted,three_points_made,three_points_attempted,free_throws_made,free_throws_attempted) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$gameId,$pid,$tid,$s2m,$s2a,$s3m,$s3a,$ftm,$fta]);
            }
        }
        $sc=$pdo->prepare("SELECT team_id,SUM(total_points) as ts FROM player_game_stats WHERE game_id=? GROUP BY team_id");
        $sc->execute([$gameId]); $scores=[];
        foreach($sc->fetchAll() as $row) $scores[$row['team_id']]=(int)$row['ts'];
        $homeScore=$scores[$game['home_team_id']]??0;
        $awayScore=$scores[$game['away_team_id']]??0;
        if(($_POST['manual_score']??'0')==='1'){
            $homeScore=max(0,intPost('home_score'));
            $awayScore=max(0,intPost('away_score'));
        }
        $gd=trim($_POST['game_date']??'')?:$game['game_date'];
        $pdo->prepare("UPDATE games SET home_score=?,away_score=?,status='completed',game_date=? WHERE id=?")->execute([$homeScore,$awayScore,$gd,$gameId]);
        $pdo->commit();
        if($game['game_type']==='regular') {
            updateStandings($seasonId);
            checkAndMoveToPlayoffs($seasonId);
        }
        if($game['game_type']==='playoff') {
            updateStandings($seasonId);
            // Propagate winner into downstream bracket games
            propagatePlayoffWinner($gameId);
            checkAndCompleteSeason($seasonId);
        }
        setFlash('success',"Results saved — {$game['home_team_name']} $homeScore – $awayScore {$game['away_team_name']}");
        // Determine which tab to return to
        if (!$returnTab) {
            $returnTab = ($game['game_type'] === 'playoff') ? 'playoffs' : 'schedule';
        }
        redirect(isScorer()
            ? ADMIN_URL.'/scorer/index.php?tab=recent'
            : ADMIN_URL.'/seasons/view.php?id='.$seasonId.'&league_id='.$leagueId.'&tab='.$returnTab
        );
        exit;
    }catch(Exception $e){ $pdo->rollBack(); $errors[]='Save failed: '.$e->getMessage(); }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (isScorer()): ?>
<div class="mb-3">
    <a href="<?= ADMIN_URL ?>/scorer/index.php" style="font-size:12px;color:var(--text-3);font-weight:600;display:inline-flex;align-items:center;gap:5px;text-transform:uppercase;letter-spacing:.05em;transition:color .15s;" onmouseover="this.style.color='var(--text-2)'" onmouseout="this.style.color='var(--text-3)'">
        <i class="fas fa-arrow-left fa-xs"></i> Back to My Games
    </a>
</div>
<?php else: ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $leagueId ?>"><?= clean($season['name']) ?></a></li>
        <li class="breadcrumb-item active">Enter Results</li>
    </ol>
</nav>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 style="font-size:20px;font-weight:800;margin:0;letter-spacing:-.02em;">Enter Game Results</h1>
        <p style="font-size:12px;color:var(--text-3);margin:4px 0 0;">
            <?= clean($season['name']) ?> · <?= ucfirst($game['game_type']) ?> game
        </p>
    </div>
</div>

<!-- Scoreboard -->
<div class="scoreboard mb-4">
    <div class="d-flex align-items-center justify-content-center gap-0">
        <div style="flex:1;text-align:center;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);font-weight:700;margin-bottom:6px;">Home</div>
            <div style="font-size:15px;font-weight:700;color:rgba(255,255,255,.9);margin-bottom:8px;"><?= clean($game['home_team_name']) ?></div>
            <div id="display-home" style="font-size:56px;font-weight:900;line-height:1;font-variant-numeric:tabular-nums;"><?= $game['home_score']??0 ?></div>
        </div>
        <div style="text-align:center;padding:0 24px;padding-top:28px;">
            <div style="font-size:28px;color:rgba(255,255,255,.2);font-weight:900;display:block;">:</div>
            <div style="font-size:11px;color:rgba(255,255,255,.3);margin-top:4px;"><?= formatDate($game['game_date']) ?></div>
        </div>
        <div style="flex:1;text-align:center;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);font-weight:700;margin-bottom:6px;">Away</div>
            <div style="font-size:15px;font-weight:700;color:rgba(255,255,255,.9);margin-bottom:8px;"><?= clean($game['away_team_name']) ?></div>
            <div id="display-away" style="font-size:56px;font-weight:900;line-height:1;font-variant-numeric:tabular-nums;"><?= $game['away_score']??0 ?></div>
        </div>
    </div>
</div>

<?php if(!empty($errors)): ?>
<div class="flash-bar" style="background:#fef2f2;border-color:#fca5a5;color:#dc2626;margin-bottom:20px;">
    <i class="fas fa-circle-xmark" style="flex-shrink:0;"></i>
    <span><?= clean(implode('. ',$errors)) ?></span>
</div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="manual_score" id="manual_flag" value="0">

<!-- Player Stats -->
<div class="row g-4 mb-4">
<?php foreach([['home',$game['home_team_name'],$homeRoster,$homeStats,$game['home_team_id']],['away',$game['away_team_name'],$awayRoster,$awayStats,$game['away_team_id']]] as [$side,$team,$roster,$stats,$tid]): ?>
<div class="col-lg-6">
    <div class="card">
        <div class="card-header">
            <h2 style="font-size:14px;"><?= clean($team) ?></h2>
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-3);">
                Total: <span id="total-<?=$side?>" style="font-size:18px;font-weight:800;color:var(--brand);"><?php
                    $t=0; foreach($stats as $s) $t+=$s['total_points']; echo $t;
                ?></span> pts
            </div>
        </div>
        <?php if(empty($roster)): ?>
        <div class="card-body empty-state py-4">
            <div class="empty-state-icon" style="width:52px;height:52px;"><i class="fas fa-users" style="font-size:20px;"></i></div>
            <p>No roster set. <a href="<?= ADMIN_URL ?>/seasons/roster.php?season_id=<?=$seasonId?>&team_id=<?=$tid?>&league_id=<?=$leagueId?>" style="color:var(--brand);font-weight:600;">Set roster</a></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:28px;">#</th>
                        <th>Player</th>
                        <th class="text-center" style="width:48px;" title="2-Pointers Made">2M</th>
                        <th class="text-center" style="width:48px;" title="2-Pointers Attempted">2A</th>
                        <th class="text-center" style="width:48px;" title="3-Pointers Made">3M</th>
                        <th class="text-center" style="width:48px;" title="3-Pointers Attempted">3A</th>
                        <th class="text-center" style="width:48px;" title="Free Throws Made">FM</th>
                        <th class="text-center" style="width:48px;" title="Free Throws Attempted">FA</th>
                        <th class="text-center" style="width:52px;color:var(--brand);">PTS</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($roster as $p): $pid=$p['player_id']; ?>
                <tr>
                    <td style="font-family:monospace;font-size:12px;color:var(--text-3);font-weight:600;"><?= $p['jersey_number'] ?></td>
                    <td style="font-weight:600;"><?= clean($p['first_name'][0].'. '.$p['last_name']) ?></td>
                    <?php foreach(['two_points_made','two_points_attempted','three_points_made','three_points_attempted','free_throws_made','free_throws_attempted'] as $f): ?>
                    <td style="padding:8px 4px;">
                        <input type="number" min="0" max="99"
                               name="stats[<?=$side?>][<?=$pid?>][<?=$f?>]"
                               value="<?= sv($stats,$pid,$f) ?>"
                               class="stat-input s-input"
                               data-side="<?=$side?>"
                               data-field="<?=$f?>"
                               data-player="<?=$pid?>">
                    </td>
                    <?php endforeach; ?>
                    <td class="text-center">
                        <span class="p-pts fw-800" style="color:var(--brand);font-size:14px;"
                              data-player="<?=$pid?>" data-side="<?=$side?>">
                            <?= sv($stats,$pid,'two_points_made')*2+sv($stats,$pid,'three_points_made')*3+sv($stats,$pid,'free_throws_made') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Game Details -->
<div class="card mb-4">
    <div class="card-header"><h2>Game Details</h2></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label">Game Date</label>
                <input type="date" name="game_date" class="form-control" style="width:180px;"
                       value="<?= $game['game_date']?:date('Y-m-d') ?>">
            </div>
            <div class="col-auto d-flex align-items-end" style="padding-bottom:2px;">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="manualToggle" style="accent-color:var(--brand);">
                    <label class="form-check-label" for="manualToggle" style="font-size:13px;font-weight:500;text-transform:none;letter-spacing:0;">
                        Override total score manually
                    </label>
                </div>
            </div>
            <div class="col-auto" id="manualScores" style="display:none;">
                <div class="d-flex gap-3 align-items-end">
                    <div>
                        <label class="form-label"><?= clean($game['home_team_name']) ?> Score</label>
                        <input type="number" name="home_score" class="form-control" style="width:90px;" min="0"
                               value="<?= $game['home_score']??0 ?>">
                    </div>
                    <div>
                        <label class="form-label"><?= clean($game['away_team_name']) ?> Score</label>
                        <input type="number" name="away_score" class="form-control" style="width:90px;" min="0"
                               value="<?= $game['away_score']??0 ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-brand">
        <i class="fas fa-check"></i> Save Results
    </button>
    <a href="<?= isScorer() ? ADMIN_URL.'/scorer/index.php' : ADMIN_URL.'/seasons/view.php?id='.$seasonId.'&league_id='.$leagueId ?>" class="btn btn-light">
        Cancel
    </a>
</div>
</form>

<script>
const inputs = document.querySelectorAll('.s-input');

function calcPlayer(side, pid) {
    const g = f => parseInt(document.querySelector(`input[data-side="${side}"][data-field="${f}"][data-player="${pid}"]`)?.value || 0);
    const pts = g('two_points_made') * 2 + g('three_points_made') * 3 + g('free_throws_made');
    const el = document.querySelector(`.p-pts[data-player="${pid}"][data-side="${side}"]`);
    if (el) el.textContent = pts;
    return pts;
}

function calcTotal(side) {
    let t = 0;
    document.querySelectorAll(`.p-pts[data-side="${side}"]`).forEach(e => t += parseInt(e.textContent || 0));
    document.getElementById('total-' + side).textContent = t;
    document.getElementById('display-' + side).textContent = t;
}

inputs.forEach(i => i.addEventListener('input', () => {
    const { side, player } = i.dataset;
    calcPlayer(side, player);
    calcTotal(side);
}));

document.getElementById('manualToggle').addEventListener('change', function () {
    document.getElementById('manualScores').style.display = this.checked ? 'flex' : 'none';
    document.getElementById('manual_flag').value = this.checked ? '1' : '0';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
