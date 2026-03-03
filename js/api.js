/**
 * Battle 3x3 — Frontend API Client & Utilities
 * api.js
 *
 * API_BASE is auto-detected from this script's own src URL.
 * This makes the app portable — it works whether installed at
 * domain.com/ or domain.com/battle3x3/ with zero config changes.
 *
 * Override before loading this script if needed:
 *   <script>window.API_BASE = 'https://custom.domain.com/api';</script>
 */

// ── Auto-detect API_BASE from this script's own URL ────────
(function () {
  if (window.API_BASE) return; // already set manually — skip

  // This script lives at [project]/js/api.js
  // The API lives at   [project]/api
  // So we strip /js/api.js and append /api
  var scripts = document.querySelectorAll('script[src]');
  for (var i = 0; i < scripts.length; i++) {
    var src = scripts[i].getAttribute('src');
    if (src && src.match(/\/js\/api\.js(\?.*)?$/)) {
      var base = new URL(src, location.href);
      window.API_BASE = base.pathname.replace(/\/js\/api\.js.*$/, '/api');
      break;
    }
  }
  // Fallback: root-relative (works for domain.com/ installs)
  if (!window.API_BASE) window.API_BASE = '/api';
})();

// ── State (shared across pages) ────────────────────────────
const State = {
  leagues:        [],
  selectedLeague: null,
  selectedSeason: null,

  load() {
    try {
      const l = localStorage.getItem('btl_league');
      const s = localStorage.getItem('btl_season');
      if (l) this.selectedLeague = JSON.parse(l);
      if (s) this.selectedSeason = JSON.parse(s);
    } catch(e) {}
  },

  save() {
    try {
      if (this.selectedLeague) localStorage.setItem('btl_league', JSON.stringify(this.selectedLeague));
      if (this.selectedSeason) localStorage.setItem('btl_season', JSON.stringify(this.selectedSeason));
    } catch(e) {}
  },

  setLeague(league) {
    this.selectedLeague = league;
    this.selectedSeason = league?.season || null;
    this.save();
  }
};

State.load();

// ── API fetch wrapper ───────────────────────────────────────
async function apiFetch(path) {
  const res = await fetch(window.API_BASE + path);
  if (!res.ok) throw new Error(`API error ${res.status}: ${window.API_BASE + path}`);
  const json = await res.json();
  if (!json.success) throw new Error(json.error || 'API returned error');
  return json.data;
}

const API = {
  leagues:  ()                    => apiFetch('/leagues.php'),
  seasons:  (leagueId)            => apiFetch(`/seasons.php?league_id=${leagueId}`),
  players:  (leagueId, seasonId)  => apiFetch(`/players.php?league_id=${leagueId}&season_id=${seasonId || ''}`),
  player:   (id)                  => apiFetch(`/player.php?id=${id}`),
  teams:    (leagueId, seasonId)  => apiFetch(`/teams.php?league_id=${leagueId}&season_id=${seasonId || ''}`),
  team:     (id, seasonId)        => apiFetch(`/team.php?id=${id}&season_id=${seasonId || ''}`),
  games:    (params = {})         => { const q = new URLSearchParams(params).toString(); return apiFetch(`/games.php?${q}`); },
  game:     (id)                  => apiFetch(`/game.php?id=${id}`),
  mvp:      (leagueId, seasonId)  => apiFetch(`/mvp.php?league_id=${leagueId}&season_id=${seasonId || ''}`),
  standings:(leagueId, seasonId)  => apiFetch(`/standings.php?league_id=${leagueId}&season_id=${seasonId || ''}`),
};

// ── Utilities ────────────────────────────────────────────────
const Utils = {
  formatDate(d, opts = {}) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric', ...opts
    });
  },

  formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    return `${((h % 12) || 12)}:${String(m).padStart(2,'0')} ${ampm}`;
  },

  getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
  },

  avatar(src, name, size = 32) {
    if (src) return `<img src="${src}" alt="${name}" class="table-avatar" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover">`;
    return `<div class="table-avatar-placeholder" style="width:${size}px;height:${size}px">${Utils.getInitials(name)}</div>`;
  },

  teamLogo(logo, name) {
    if (logo) return `<img src="${logo}" alt="${name}" class="team-logo-sm">`;
    return `<div class="team-logo-placeholder">${Utils.getInitials(name)}</div>`;
  },

  /**
   * Abbreviate long team names for tight UI spaces (mobile cards, table cells).
   * If name is ≤ 12 chars it comes back unchanged; otherwise returns initials/acronym.
   */
  abbrevTeam(name, maxLen = 12) {
    if (!name) return '—';
    if (name.length <= maxLen) return name;
    // Try acronym from capitalised words first
    const acronym = name.split(/\s+/).map(w => w[0]?.toUpperCase()).join('');
    if (acronym && acronym.length >= 2 && acronym.length <= 4) return acronym;
    // Fall back to truncate with ellipsis
    return name.slice(0, maxLen - 1) + '…';
  },

  statusBadge(status) {
    const map = {
      active:    ['badge-green',  '● Active'],
      playoffs:  ['badge-orange', '🏆 Playoffs'],
      completed: ['badge-muted',  'Completed'],
      upcoming:  ['badge-blue',   'Upcoming'],
    };
    const [cls, label] = map[status] || ['badge-muted', status];
    return `<span class="badge ${cls}">${label}</span>`;
  },

  gameTypeBadge(type, round) {
    if (type === 'playoff') {
      const r = round ? `<span style="margin-left:4px;opacity:.7">${round}</span>` : '';
      return `<span class="badge badge-orange">Playoffs${r}</span>`;
    }
    return `<span class="badge badge-blue">Regular</span>`;
  },

  gameStatus(game) {
    if (game.status === 'completed') return `<span class="badge badge-muted">Final</span>`;
    return `<span class="badge badge-green">Scheduled</span>`;
  },

  params() {
    return new URLSearchParams(window.location.search);
  },

  sortTable(tbody, colIndex, numeric = true) {
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
      const av = a.cells[colIndex]?.dataset.sort ?? a.cells[colIndex]?.textContent.trim();
      const bv = b.cells[colIndex]?.dataset.sort ?? b.cells[colIndex]?.textContent.trim();
      if (numeric) return Number(bv || 0) - Number(av || 0);
      return av.localeCompare(bv);
    });
    rows.forEach(r => tbody.appendChild(r));
  }
};

// ── Nav / League Switcher ────────────────────────────────────
async function initNav() {
  try {
    State.leagues = await API.leagues();
    if (!State.selectedLeague && State.leagues.length > 0) {
      State.setLeague(State.leagues[0]);
    }
    // Refresh selectedLeague from latest API data (catches admin updates)
    if (State.selectedLeague) {
      const fresh = State.leagues.find(l => l.id === State.selectedLeague.id);
      if (fresh) State.setLeague(fresh);
    }
    renderLeaguePill();
    renderLeagueDropdown();
    renderLeagueContextBar();
  } catch (e) {
    console.warn('Nav init error:', e);
    renderLeaguePill();
  }

  // Mobile nav toggle
  const toggle = document.querySelector('.nav-toggle');
  const links  = document.querySelector('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', () => links.classList.toggle('open'));
  }

  // Active nav link highlight — works with clean URLs (/players) and .html URLs
  const _segments = location.pathname.replace(/\/+$/, '').split('/');
  const _page = (_segments[_segments.length - 1] || '').replace(/\.html$/, '') || 'home';
  document.querySelectorAll('.nav-link[data-page]').forEach(a => {
    const pg = a.getAttribute('data-page');
    if (pg === _page || (_page === 'battle' && pg === 'about')) {
      a.classList.add('active');
    }
  });

  // League dropdown toggle
  const pill = document.getElementById('league-pill');
  const menu = document.getElementById('league-dropdown-menu');
  if (pill && menu) {
    pill.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('open');
    });
    document.addEventListener('click', () => menu?.classList.remove('open'));
  }
}

function renderLeaguePill() {
  const el = document.getElementById('league-pill');
  if (!el) return;
  const name = State.selectedLeague?.name || 'Select League';
  el.innerHTML = `<span class="league-pill-dot"></span><span class="pill-text">${name}</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>`;
}

function renderLeagueDropdown() {
  const menu = document.getElementById('league-dropdown-menu');
  if (!menu) return;
  if (!State.leagues.length) {
    menu.innerHTML = '<div class="league-dropdown-label">No leagues found</div>';
    return;
  }
  menu.innerHTML = `<div class="league-dropdown-label">Switch League</div>` +
    State.leagues.map(l => `
      <div class="league-dropdown-item ${State.selectedLeague?.id === l.id ? 'selected' : ''}"
           onclick="switchLeague(${l.id})">
        🏀 ${l.name}
        ${l.season ? Utils.statusBadge(l.season.status) : ''}
      </div>
    `).join('');
}

window.switchLeague = function(id) {
  const l = State.leagues.find(x => x.id === id);
  if (l) {
    State.setLeague(l);
    location.reload();
  }
};

function renderLeagueContextBar() {
  const bar = document.getElementById('league-context-bar');
  if (!bar) return;
  const l = State.selectedLeague;
  if (!l) { bar.style.display = 'none'; return; }
  const s = l.season;
  bar.innerHTML = `
    <div class="container">
      <div class="league-context-inner">
        <span>🏀 <strong>${l.name}</strong></span>
        ${s ? `<span>Season: <strong>${s.name}</strong></span>` : ''}
        ${s ? Utils.statusBadge(s.status) : ''}
        ${s?.start_date ? `<span>${Utils.formatDate(s.start_date)} – ${s.end_date ? Utils.formatDate(s.end_date) : 'TBD'}</span>` : ''}
      </div>
    </div>
  `;
}

// ── HTML Helpers ─────────────────────────────────────────────
function loading(msg = 'Loading...') {
  return `<div class="loading-spinner"><div class="spinner"></div><span>${msg}</span></div>`;
}

function emptyState(icon = '🏀', msg = 'No data available') {
  return `<div class="empty-state"><div class="icon">${icon}</div><p>${msg}</p></div>`;
}

// Auto-init nav on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initNav);
