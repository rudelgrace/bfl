<?php
/**
 * The Battle 3x3 — v3
 * Scorer Landing Page — game entry only view
 */
$pageTitle  = 'My Games';
$activeNav  = 'scorer_games';
$leagueContext = null;
require_once __DIR__ . '/../../includes/functions.php';

// Scorers only — admins/super_admins have the full dashboard
requireAuth();
if (!isScorer()) {
    redirect(ADMIN_URL . '/index.php');
}

$pdo = getDB();

// Pagination settings
$gamesPerPage = 10;
$currentPendingPage = max(1, intGet('pending_page', 1));
$currentRecentPage = max(1, intGet('recent_page', 1));

// Calculate offsets
$pendingOffset = ($currentPendingPage - 1) * $gamesPerPage;
$recentOffset = ($currentRecentPage - 1) * $gamesPerPage;

// Fetch all leagues that have at least one active/playoff season with pending games
$leagues = $pdo->query("
    SELECT DISTINCT l.id, l.name, l.logo
    FROM leagues l
    JOIN seasons s ON s.league_id = l.id
    JOIN games g ON g.season_id = s.id
    WHERE s.status IN ('active', 'scheduled', 'playoffs')
      AND g.status = 'scheduled'
    ORDER BY l.name
")->fetchAll();

// For each league, fetch active/playoff seasons + their pending games (with pagination)
$leagueData = [];
$totalPendingGames = 0;
foreach ($leagues as $league) {
    $seasons = $pdo->prepare("
        SELECT s.*
        FROM seasons s
        WHERE s.league_id = ?
          AND s.status IN ('active', 'scheduled', 'playoffs')
        ORDER BY s.created_at DESC
    ");
    $seasons->execute([$league['id']]);
    $seasons = $seasons->fetchAll();

    $seasonData = [];
    foreach ($seasons as $season) {
        // Count total games for this season (excluding locked downstream playoff games)
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM games g
            WHERE g.season_id = ?
              AND g.status = 'scheduled'
              AND (
                  g.game_type != 'playoff'
                  OR (
                      (g.home_source_game_id IS NULL OR EXISTS (
                          SELECT 1 FROM games src
                          WHERE src.id = g.home_source_game_id AND src.status = 'completed'
                      ))
                      AND
                      (g.away_source_game_id IS NULL OR EXISTS (
                          SELECT 1 FROM games src
                          WHERE src.id = g.away_source_game_id AND src.status = 'completed'
                      ))
                  )
              )
        ");
        $countStmt->execute([$season['id']]);
        $seasonTotal = $countStmt->fetch()['total'];
        $totalPendingGames += $seasonTotal;

        // Get paginated games for this season
        // For playoff games: only show a game once BOTH its source (feeder) games are completed.
        // This prevents scorers from entering semi-final or final scores before prior rounds finish.
        $games = $pdo->prepare("
            SELECT g.*,
                   ht.name AS home_team, at.name AS away_team
            FROM games g
            JOIN teams ht ON g.home_team_id = ht.id
            JOIN teams at ON g.away_team_id = at.id
            WHERE g.season_id = ?
              AND g.status = 'scheduled'
              AND (
                  g.game_type != 'playoff'
                  OR (
                      (g.home_source_game_id IS NULL OR EXISTS (
                          SELECT 1 FROM games src
                          WHERE src.id = g.home_source_game_id AND src.status = 'completed'
                      ))
                      AND
                      (g.away_source_game_id IS NULL OR EXISTS (
                          SELECT 1 FROM games src
                          WHERE src.id = g.away_source_game_id AND src.status = 'completed'
                      ))
                  )
              )
            ORDER BY g.game_type ASC, g.game_date ASC, g.id ASC
            LIMIT $gamesPerPage OFFSET $pendingOffset
        ");
        $games->execute([$season['id']]);
        $games = $games->fetchAll();

        if (!empty($games)) {
            $seasonData[] = [
                'season' => $season,
                'games'  => $games,
                'total'  => $seasonTotal
            ];
        }
    }

    if (!empty($seasonData)) {
        $leagueData[] = [
            'league'  => $league,
            'seasons' => $seasonData,
        ];
    }
}


// Fetch recent completed games (last 7 days) for review with pagination
$recentGamesQuery = "
    SELECT g.*,
           ht.name AS home_team, at.name AS away_team,
           l.name AS league_name, l.logo AS league_logo,
           s.name AS season_name,
           CASE 
               WHEN g.home_score > g.away_score THEN ht.name
               WHEN g.away_score > g.home_score THEN at.name
               ELSE 'Tie'
           END AS winner,
           ABS(g.home_score - g.away_score) AS point_diff
    FROM games g
    JOIN teams ht ON g.home_team_id = ht.id
    JOIN teams at ON g.away_team_id = at.id
    JOIN seasons s ON g.season_id = s.id
    JOIN leagues l ON s.league_id = l.id
    WHERE g.status = 'completed'
      AND g.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY g.updated_at DESC
";

// Get total count for recent games
$recentGamesCount = $pdo->query(str_replace("ORDER BY g.updated_at DESC", "", $recentGamesQuery))->rowCount();
$recentGames = $pdo->query($recentGamesQuery . " LIMIT $gamesPerPage OFFSET $recentOffset")->fetchAll();

// Pagination helper function — always includes &tab= so page reload restores tab
function renderPagination(string $tab, int $currentPage, int $totalItems, int $perPage = 10): string {
    $totalPages = ceil($totalItems / $perPage);
    if ($totalPages <= 1) return '';

    $base = '?' . $tab . '_page=';  // e.g. ?pending_page=2&tab=pending
    $suffix = '&tab=' . $tab;

    $html = '<div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-top:20px;padding:16px;border-top:1px solid var(--border);">';

    if ($currentPage > 1) {
        $html .= '<a href="' . $base . ($currentPage - 1) . $suffix . '" class="pagination-btn">← Previous</a>';
    }

    $startPage = max(1, $currentPage - 2);
    $endPage   = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<a href="' . $base . '1' . $suffix . '" class="pagination-btn">1</a>';
        if ($startPage > 2) $html .= '<span style="color:var(--text-4);padding:0 4px;">...</span>';
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $cls = $i === $currentPage ? 'pagination-btn active' : 'pagination-btn';
        $html .= '<a href="' . $base . $i . $suffix . '" class="' . $cls . '">' . $i . '</a>';
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) $html .= '<span style="color:var(--text-4);padding:0 4px;">...</span>';
        $html .= '<a href="' . $base . $totalPages . $suffix . '" class="pagination-btn">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $base . ($currentPage + 1) . $suffix . '" class="pagination-btn">Next →</a>';
    }

    $html .= '</div>';
    return $html;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title"><?= date('l, F j, Y') ?></h1>
        <p class="page-sub"><?= date('g:i A') ?> - Game Entry & Management</p>
    </div>
    <div><?= roleBadge('scorer') ?></div>
</div>

<!-- ── TABS ── -->
<div style="margin-bottom:24px;">
    <div style="display:flex;border-bottom:1px solid var(--border);gap:4px;">
        <button onclick="showTab('pending')" id="tab-pending" class="scorer-tab active">
            <i class="fas fa-clipboard-list me-2"></i>Pending Games
            <?php if ($totalPendingGames > 0): ?>
            <span class="tab-badge"><?= $totalPendingGames ?></span>
            <?php endif; ?>
        </button>
        <button onclick="showTab('recent')" id="tab-recent" class="scorer-tab">
            <i class="fas fa-history me-2"></i>Recent Games
            <?php if ($recentGamesCount > 0): ?>
            <span class="tab-badge"><?= $recentGamesCount ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- ── TAB CONTENTS ── -->
<div id="pending-content" class="tab-content">
    <?php if (empty($leagueData)): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:60px 32px;text-align:center;box-shadow:var(--shadow);">
        <div style="font-size:48px;margin-bottom:16px;">🏀</div>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:8px;">No Games to Enter</h2>
        <p style="color:var(--text-3);font-size:14px;margin:0;">There are no pending games at the moment. Check back when the schedule is ready.</p>
    </div>
    <?php else: ?>
        <?php foreach ($leagueData as $ld): ?>
        <div style="margin-bottom:32px;">
            <!-- League Header -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <?php if (!empty($ld['league']['logo'])): ?>
                <img src="<?= UPLOADS_URL . '/' . $ld['league']['logo'] ?>"
                     style="width:36px;height:36px;border-radius:var(--r-sm);object-fit:cover;" alt="">
                <?php else: ?>
                <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--brand),var(--brand-dark));border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;">
                    <?= strtoupper(substr($ld['league']['name'], 0, 1)) ?>
                </div>
                <?php endif; ?>
                <h2 style="font-size:17px;font-weight:800;margin:0;color:var(--text-1);">
                    <?= clean($ld['league']['name']) ?>
                </h2>
            </div>

            <?php foreach ($ld['seasons'] as $sd): ?>
            <?php
                $isPlayoffSeason  = $sd['season']['status'] === 'playoffs';
                $regularGames     = array_filter($sd['games'], fn($g) => $g['game_type'] === 'regular');
                $playoffGames     = array_filter($sd['games'], fn($g) => $g['game_type'] === 'playoff');
            ?>

            <?php if ($isPlayoffSeason): ?>
            <!-- ── PLAYOFFS BANNER ── -->
            <div style="background:linear-gradient(135deg,#92400e,#b45309);border-radius:var(--r-lg);padding:16px 20px;margin-bottom:12px;display:flex;align-items:center;gap:12px;">
                <div style="font-size:24px;">🏆</div>
                <div>
                    <div style="color:#fff;font-weight:800;font-size:15px;"><?= clean($sd['season']['name']) ?> — Playoffs</div>
                    <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;"><?= count($playoffGames) ?> playoff game<?= count($playoffGames) !== 1 ? 's' : '' ?> pending</div>
                </div>
            </div>
            <?php else: ?>
            <!-- ── REGULAR SEASON HEADER ── -->
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:0 4px;">
                <i class="fas fa-calendar-days" style="color:var(--brand);font-size:12px;"></i>
                <span style="font-weight:700;font-size:13px;"><?= clean($sd['season']['name']) ?></span>
                <?= seasonStatusBadge($sd['season']['status']) ?>
                <span style="font-size:12px;color:var(--text-3);margin-left:auto;"><?= count($sd['games']) ?> game<?= count($sd['games']) !== 1 ? 's' : '' ?> pending</span>
            </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:20px;">
                <?php foreach ($sd['games'] as $game): ?>
                <?php $isPlayoff = $game['game_type'] === 'playoff'; ?>
                <div class="list-item" style="padding:14px 20px;<?= $isPlayoff ? 'border-left:3px solid #f59e0b;' : '' ?>">
                    <!-- Game date -->
                    <div style="flex-shrink:0;width:72px;text-align:center;">
                        <?php if ($game['game_date']): ?>
                        <div style="font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">
                            <?= date('M', strtotime($game['game_date'])) ?>
                        </div>
                        <div style="font-size:22px;font-weight:800;color:var(--text-1);line-height:1;">
                            <?= date('d', strtotime($game['game_date'])) ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size:11px;color:var(--text-4);">TBD</div>
                        <?php endif; ?>
                    </div>

                    <!-- Teams -->
                    <div style="flex:1;min-width:0;margin:0 16px;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="font-weight:700;font-size:14px;"><?= clean($game['home_team']) ?></span>
                            <span style="font-size:11px;font-weight:700;color:var(--text-4);padding:1px 7px;background:var(--surface-3);border-radius:20px;">VS</span>
                            <span style="font-weight:700;font-size:14px;"><?= clean($game['away_team']) ?></span>
                        </div>
                        <div style="font-size:11px;color:var(--text-3);margin-top:3px;">
                            <?php if ($isPlayoff): ?>
                            <i class="fas fa-trophy fa-xs me-1" style="color:#f59e0b;"></i>
                            <span style="color:#b45309;font-weight:600;"><?= !empty($game['playoff_round']) ? clean($game['playoff_round']) : 'Playoff Game' ?></span>
                            <?php else: ?>
                            <i class="fas fa-calendar-check fa-xs me-1" style="color:var(--text-4);"></i>Regular Season
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action button -->
                    <div style="flex-shrink:0;">
                        <a href="<?= ADMIN_URL ?>/games/enter_results.php?id=<?= $game['id'] ?>&season_id=<?= $sd['season']['id'] ?>&league_id=<?= $ld['league']['id'] ?>"
                           class="btn btn-sm <?= $isPlayoff ? 'btn-warning' : 'btn-brand' ?>"
                           style="<?= $isPlayoff ? 'background:#f59e0b;border-color:#f59e0b;color:#fff;font-weight:700;' : '' ?>">
                            <i class="fas fa-<?= $isPlayoff ? 'trophy' : 'clipboard-list' ?> fa-xs me-1"></i>
                            <?= $isPlayoff ? 'Enter Playoff' : 'Enter Game' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?= renderPagination('pending', $currentPendingPage, $totalPendingGames, $gamesPerPage) ?>
</div>


<!-- ── RECENT GAMES TAB ── -->
<div id="recent-content" class="tab-content" style="display:none;">
    <?php if (empty($recentGames)): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:60px 32px;text-align:center;box-shadow:var(--shadow);">
        <div style="font-size:48px;margin-bottom:16px;">📊</div>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:8px;">No Recent Games</h2>
        <p style="color:var(--text-3);font-size:14px;margin:0;">No games have been completed in the last 7 days.</p>
    </div>
    <?php else: ?>
    <div class="card">
        <?php foreach ($recentGames as $game): ?>
        <?php $isPlayoff = $game['game_type'] === 'playoff'; ?>
        <div class="list-item" style="padding:16px 20px;<?= $isPlayoff ? 'border-left:3px solid #10b981;' : '' ?>">
            <!-- Game info -->
            <div style="flex-shrink:0;width:80px;text-align:center;">
                <div style="font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;">
                    <?= date('M', strtotime($game['updated_at'])) ?>
                </div>
                <div style="font-size:20px;font-weight:800;color:var(--text-1);line-height:1;">
                    <?= date('d', strtotime($game['updated_at'])) ?>
                </div>
                <div style="font-size:9px;color:var(--text-4);margin-top:2px;">
                    <?= date('H:i', strtotime($game['updated_at'])) ?>
                </div>
            </div>

            <!-- Teams & Score -->
            <div style="flex:1;min-width:0;margin:0 16px;">
                <!-- League & Season -->
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <?php if (!empty($game['league_logo'])): ?>
                    <img src="<?= UPLOADS_URL . '/' . $game['league_logo'] ?>" 
                         style="width:14px;height:14px;border-radius:2px;object-fit:cover;" alt="">
                    <?php endif; ?>
                    <span style="font-size:11px;color:var(--text-3);font-weight:600;"><?= clean($game['league_name']) ?></span>
                    <span style="font-size:10px;color:var(--text-4);">•</span>
                    <span style="font-size:11px;color:var(--text-4);"><?= clean($game['season_name']) ?></span>
                    <?php if ($isPlayoff): ?>
                    <span style="font-size:10px;color:var(--text-4);">•</span>
                    <i class="fas fa-trophy fa-xs" style="color:#10b981;"></i>
                    <span style="font-size:10px;color:#059669;font-weight:600;">Playoffs</span>
                    <?php endif; ?>
                </div>

                <!-- Teams with scores -->
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-weight:700;font-size:14px;<?= $game['home_score'] > $game['away_score'] ? 'color:#10b981;' : '' ?>">
                        <?= clean($game['home_team']) ?>
                    </span>
                    <span style="font-size:13px;font-weight:800;color:var(--text-1);background:var(--surface-2);padding:2px 6px;border-radius:4px;min-width:32px;text-align:center;">
                        <?= $game['home_score'] ?>
                    </span>
                    <span style="font-size:11px;font-weight:700;color:var(--text-4);padding:1px 4px;">:</span>
                    <span style="font-size:13px;font-weight:800;color:var(--text-1);background:var(--surface-2);padding:2px 6px;border-radius:4px;min-width:32px;text-align:center;">
                        <?= $game['away_score'] ?>
                    </span>
                    <span style="font-weight:700;font-size:14px;<?= $game['away_score'] > $game['home_score'] ? 'color:#10b981;' : '' ?>">
                        <?= clean($game['away_team']) ?>
                    </span>
                </div>

                <!-- Winner info -->
                <div style="font-size:11px;color:var(--text-3);margin-top:4px;">
                    <?php if ($game['home_score'] != $game['away_score']): ?>
                    <i class="fas fa-crown fa-xs me-1" style="color:#f59e0b;"></i>
                    <span style="color:#059669;font-weight:600;"><?= clean($game['winner']) ?></span>
                    <span style="color:var(--text-4);">won by <?= $game['point_diff'] ?> pts</span>
                    <?php else: ?>
                    <i class="fas fa-handshake fa-xs me-1" style="color:var(--text-4);"></i>
                    <span style="color:var(--text-4);">Game tied</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit button -->
            <div style="flex-shrink:0;">
                <a href="<?= ADMIN_URL ?>/games/enter_results.php?id=<?= $game['id'] ?>&edit=1"
                   class="btn btn-sm btn-outline-secondary"
                   style="color:var(--text-2);border-color:var(--border);">
                    <i class="fas fa-edit fa-xs me-1"></i>Edit
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?= renderPagination('recent', $currentRecentPage, $recentGamesCount, $gamesPerPage) ?>
</div>

<!-- ── TAB STYLES & SCRIPT ── -->
<style>
.scorer-tab {
    background:none;
    border:none;
    padding:12px 20px;
    font-size:14px;
    font-weight:600;
    color:var(--text-3);
    cursor:pointer;
    border-bottom:3px solid transparent;
    border-radius:var(--r-sm) var(--r-sm) 0 0;
    transition:all 0.2s ease;
    display:flex;
    align-items:center;
    gap:8px;
    position:relative;
}

.scorer-tab:hover {
    color:var(--text-1);
    background:var(--surface);
}

.scorer-tab.active {
    color:var(--brand);
    border-bottom-color:var(--brand);
    background:var(--surface);
}

.tab-badge {
    background:var(--brand);
    color:#fff;
    font-size:10px;
    font-weight:700;
    padding:2px 6px;
    border-radius:10px;
    min-width:18px;
    text-align:center;
    line-height:1.2;
}

.tab-content {
    animation:fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from { opacity:0; transform:translateY(-10px); }
    to { opacity:1; transform:translateY(0); }
}

/* Pagination Styles */
.pagination-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:36px;
    height:32px;
    padding:0 8px;
    font-size:12px;
    font-weight:600;
    color:var(--text-2);
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-sm);
    text-decoration:none;
    transition:all 0.2s ease;
    cursor:pointer;
}

.pagination-btn:hover {
    background:var(--surface-2);
    color:var(--text-1);
    border-color:var(--brand);
    transform:translateY(-1px);
}

.pagination-btn.active {
    background:var(--brand);
    color:#fff;
    border-color:var(--brand);
    font-weight:700;
}
</style>

<script>
// Enhanced tab switching with pagination reset
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    document.querySelectorAll('.scorer-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.getElementById(tabName + '-content').style.display = 'block';
    document.getElementById('tab-' + tabName).classList.add('active');

    // Update URL so tab survives pagination reloads
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url.toString());
}

// Restore tab from URL on every page load (including after pagination)
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const savedTab = params.get('tab');
    const validTabs = ['pending', 'recent'];
    showTab(validTabs.includes(savedTab) ? savedTab : 'pending');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
