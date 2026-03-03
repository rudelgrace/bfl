<?php

/**
 * The Battle 3x3 — v3
 * Header / Layout — Custom Design System (Bootstrap 5 + custom CSS)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

requireAuth();

$admin         = getCurrentAdmin();
$flash         = getFlash();
$leagueCtx     = $leagueContext ?? null;
$inLeague      = $leagueCtx !== null;
$lid           = $inLeague ? $leagueCtx['id'] : null;
$activeNav     ??= 'leagues';
$activeSidebar ??= 'overview';

function sidebarUrl(string $section, int $leagueId): string
{
    return match ($section) {
        'overview' => ADMIN_URL . '/leagues/dashboard.php?id=' . $leagueId,
        'seasons'  => ADMIN_URL . '/seasons/index.php?league_id=' . $leagueId,
        'teams'    => ADMIN_URL . '/teams/index.php?league_id=' . $leagueId,
        'players'  => ADMIN_URL . '/players/index.php?league_id=' . $leagueId,
        'settings' => ADMIN_URL . '/leagues/edit.php?id=' . $leagueId,
        default    => '#',
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? clean($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/uploads/logos/favicon.ico">

    <style>
        /* ── Tokens ── */
        :root {
            --brand: #f97316;
            --brand-dark: #ea580c;
            --brand-deep: #c2410c;
            --brand-light: #fff7ed;
            --brand-subtle: #ffedd5;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --border: #e2e8f0;
            --border-str: #cbd5e1;
            --text-1: #0f172a;
            --text-2: #475569;
            --text-3: #94a3b8;
            --text-4: #cbd5e1;
            --sidebar-w: 252px;
            --topbar-h: 62px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, .05);
            --shadow: 0 1px 3px rgba(0, 0, 0, .07), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, .07), 0 2px 4px -1px rgba(0, 0, 0, .04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .08), 0 4px 6px -2px rgba(0, 0, 0, .04);
            --r-sm: 6px;
            --r: 10px;
            --r-lg: 14px;
            --r-xl: 18px;
        }

        /* ── Base ── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
            background: var(--surface-2);
            color: var(--text-1);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
        }

        /* ── Topbar ── */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            height: var(--topbar-h);
            background: #0f172a;
            display: flex;
            align-items: center;
            padding: 0 20px;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .topbar-left {
            display: flex;
            align-items: center;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 8px 12px 8px 0;
            font-size: 18px;
            transition: color .15s;
        }

        .sidebar-toggle:hover {
            color: #fff;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: -.01em;
            padding-right: 18px;
            border-right: 1px solid rgba(255, 255, 255, .08);
            margin-right: 14px;
        }

        .topbar-brand:hover {
            color: #fff;
        }

        .brand-icon {
            width: 34px;
            height: 34px;
            background: var(--brand);
            border-radius: var(--r-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .topbar-nav {
            display: flex;
            gap: 2px;
        }

        .topbar-nav a {
            color: #64748b;
            padding: 7px 13px;
            border-radius: var(--r-sm);
            font-size: 13px;
            font-weight: 500;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .topbar-nav a:hover {
            color: #fff;
            background: rgba(255, 255, 255, .07);
        }

        .topbar-nav a.active {
            color: #fff;
            background: rgba(255, 255, 255, .1);
        }

        .topbar-nav a.active i {
            color: var(--brand);
        }

        .topbar-user-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .08);
            cursor: pointer;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            padding: 6px 12px 6px 7px;
            border-radius: 9999px;
            transition: all .15s;
        }

        .topbar-user-btn:hover {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }

        .topbar-user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* ── Layout ── */
        .page-body {
            padding-top: var(--topbar-h);
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            position: fixed;
            top: var(--topbar-h);
            bottom: 0;
            left: 0;
            background: var(--surface);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
            transition: transform .25s cubic-bezier(.4, 0, .2, 1);
        }

        .sidebar-league-header {
            padding: 18px 16px 14px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-back {
            font-size: 11px;
            color: var(--text-3);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 14px;
            transition: color .15s;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .sidebar-back:hover {
            color: var(--text-2);
        }

        .sidebar-league-avatar {
            width: 42px;
            height: 42px;
            border-radius: var(--r);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            font-weight: 800;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 10px rgba(249, 115, 22, .3);
        }

        .sidebar-nav {
            padding: 10px 10px;
            flex-grow: 1;
        }

        .sidebar-section-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-4);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 10px 8px 5px;
            display: block;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: var(--r-sm);
            color: var(--text-2);
            font-size: 13px;
            font-weight: 500;
            transition: all .15s;
            margin-bottom: 1px;
        }

        .sidebar-link .si {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
            color: var(--text-3);
            transition: all .15s;
        }

        .sidebar-link:hover {
            color: var(--text-1);
            background: var(--surface-3);
        }

        .sidebar-link:hover .si {
            color: var(--brand);
        }

        .sidebar-link.active {
            color: var(--brand-dark);
            background: var(--brand-subtle);
            font-weight: 600;
        }

        .sidebar-link.active .si {
            color: var(--brand);
        }

        .sidebar-divider {
            margin: 8px 10px;
            border: none;
            border-top: 1px solid var(--border);
        }

        /* Overlay (mobile) */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 899;
        }

        /* ── Main content ── */
        .main-content {
            flex: 1;
            min-width: 0;
            padding: 28px 32px;
        }

        .main-content.with-sidebar {
            margin-left: var(--sidebar-w);
        }

        /* ── Page header ── */
        .page-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.2;
        }

        .page-sub {
            font-size: 13px;
            color: var(--text-3);
            margin-top: 3px;
        }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2,
        .card-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-1);
            letter-spacing: -.01em;
        }

        .card-body {
            padding: 20px;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card-accent {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--r);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 14px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.03em;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .07em;
            font-weight: 600;
            margin-top: 5px;
        }

        .stat-trend {
            font-size: 11px;
            margin-top: 6px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── League cards ── */
        .league-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px;
            display: block;
            color: inherit;
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .league-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            border-color: var(--border-str);
            color: inherit;
        }

        .league-avatar {
            width: 48px;
            height: 48px;
            border-radius: var(--r);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(249, 115, 22, .25);
        }

        .league-card-new {
            background: var(--surface);
            border: 2px dashed var(--border);
            border-radius: var(--r-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 148px;
            color: var(--text-3);
            transition: all .2s;
        }

        .league-card-new:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }

        /* ── Badges ── */
        .badge-active {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #dcfce7;
            color: #15803d;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge-active::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #15803d;
            animation: blink 1.8s ease infinite;
        }

        .badge-upcoming {
            display: inline-flex;
            align-items: center;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge-playoffs {
            display: inline-flex;
            align-items: center;
            background: #fef3c7;
            color: #d97706;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge-completed {
            display: inline-flex;
            align-items: center;
            background: var(--surface-3);
            color: var(--text-2);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .4
            }
        }

        /* ── Buttons ── */
        .btn {
            font-weight: 500;
            font-size: 13px;
            border-radius: var(--r-sm);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-brand:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
        }

        .btn-brand:focus {
            box-shadow: 0 0 0 3px rgba(249, 115, 22, .25);
        }

        .btn-light {
            background: var(--surface);
            border-color: var(--border);
            color: var(--text-2);
        }

        .btn-light:hover {
            background: var(--surface-3);
        }

        /* ── Tables ── */
        .table {
            font-size: 13px;
            margin-bottom: 0;
        }

        .table thead th {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .07em;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            padding: 10px 16px;
        }

        /* Ensure sticky thead keeps background when scrolling */
        thead[style*="sticky"] th {
            background: var(--surface-2);
        }

        .table tbody td {
            padding: 13px 16px;
            vertical-align: middle;
            border-color: var(--border);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr:hover td {
            background: var(--surface-2);
        }

        /* ── Forms ── */
        .form-control,
        .form-select {
            font-size: 13px;
            border-color: var(--border);
            border-radius: var(--r-sm);
            color: var(--text-1);
            background: var(--surface);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, .15);
        }

        .form-label {
            font-weight: 600;
            font-size: 12px;
            color: var(--text-2);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .form-text {
            font-size: 11px;
            color: var(--text-3);
        }

        .stat-input {
            width: 50px;
            text-align: center;
            padding: 5px 3px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            background: var(--surface-2);
            color: var(--text-1);
            transition: all .15s;
        }

        .stat-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 2px rgba(249, 115, 22, .15);
            background: var(--surface);
            outline: none;
        }

        /* ── Breadcrumb ── */
        .breadcrumb {
            font-size: 12px;
            margin-bottom: 0;
            padding: 0;
            background: none;
        }

        .breadcrumb-item a {
            color: var(--text-3);
            font-weight: 500;
        }

        .breadcrumb-item a:hover {
            color: var(--text-2);
        }

        .breadcrumb-item.active {
            color: var(--text-1);
            font-weight: 600;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            color: var(--text-4);
        }

        /* ── Flash ── */
        .flash-bar {
            padding: 13px 16px;
            border-radius: var(--r);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid;
        }

        .flash-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            opacity: .6;
            margin-left: auto;
            padding: 0 0 0 8px;
        }

        .flash-close:hover {
            opacity: 1;
        }

        /* ── Progress ── */
        .tb-progress {
            height: 6px;
            border-radius: 3px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .tb-progress-bar {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--brand), var(--brand-dark));
            transition: width .4s;
        }

        .tb-progress-bar.progress-complete {
            background: linear-gradient(90deg, #22c55e, #16a34a);
        }

        /* ── Tabs ── */
        .nav-tabs {
            border-bottom: 2px solid var(--border);
            gap: 0;
        }

        .nav-tabs .nav-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-3);
            padding: 10px 16px;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .15s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--text-1);
            border-bottom-color: var(--border-str);
        }

        .nav-tabs .nav-link.active {
            color: var(--brand-dark);
            border-bottom-color: var(--brand);
            background: transparent;
            font-weight: 700;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 56px 24px;
            color: var(--text-3);
        }

        .empty-state-icon {
            width: 72px;
            height: 72px;
            border-radius: var(--r-xl);
            background: var(--surface-3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: var(--text-4);
        }

        .empty-state h5 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-1);
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 13px;
            color: var(--text-3);
            margin-bottom: 20px;
        }

        /* ── Active season banner ── */
        .asb {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 60%, var(--brand-deep) 100%);
            border-radius: var(--r-lg);
            padding: 22px 24px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(249, 115, 22, .25);
            position: relative;
            overflow: hidden;
        }

        .asb::after {
            content: '🏀';
            position: absolute;
            right: 90px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 72px;
            opacity: .08;
            pointer-events: none;
        }

        .asb-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 255, 255, .2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .asb-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #fff;
            animation: blink 1.5s ease infinite;
        }

        /* ── Scoreboard ── */
        .scoreboard {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: var(--r-lg);
            padding: 28px 24px;
            color: #fff;
        }

        .scoreboard-score {
            font-size: 52px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -.04em;
            font-variant-numeric: tabular-nums;
        }

        /* ── List items ── */
        .list-item {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: var(--surface-2);
        }

        /* ── Setup steps ── */
        .setup-step {
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 16px;
            background: var(--surface);
        }

        .setup-step.done {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .setup-step.active-step {
            border-color: var(--brand);
        }

        .setup-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .setup-num.done {
            background: #16a34a;
            color: #fff;
        }

        .setup-num.pending {
            background: var(--surface-3);
            color: var(--text-3);
        }

        .setup-num.ready {
            background: var(--brand);
            color: #fff;
        }

        /* ── Dropdowns ── */
        .dropdown-menu {
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--shadow-lg);
            font-size: 13px;
            padding: 4px;
        }

        .dropdown-item {
            border-radius: 6px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-1);
        }

        .dropdown-item:hover {
            background: var(--surface-3);
        }

        .dropdown-item.text-danger {
            color: #dc2626 !important;
        }

        .dropdown-item.text-danger:hover {
            background: #fef2f2;
        }

        .dropdown-divider {
            border-color: var(--border);
            margin: 4px 0;
        }

        /* ── Avatars ── */
        .team-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #94a3b8, #64748b);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .player-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* ── Rank badges ── */
        .rank-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .rank-1 {
            background: #fef3c7;
            color: #92400e;
        }

        .rank-2 {
            background: var(--surface-3);
            color: var(--text-2);
        }

        .rank-3 {
            background: #ffedd5;
            color: #9a3412;
        }

        .rank-n {
            background: var(--surface-2);
            color: var(--text-3);
        }

        /* ── Mobile ── */
        @media (max-width: 767px) {
            :root {
                --topbar-h: 56px;
            }

            .sidebar-toggle {
                display: block;
            }

            .topbar-nav {
                display: none !important;
            }

            .topbar-brand {
                border-right: none;
                margin-right: 0;
                padding-right: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: var(--shadow-lg);
            }

            .sidebar-overlay.open {
                display: block;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 16px;
            }

            .scoreboard-score {
                font-size: 38px;
            }
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--border-str);
        }

        /* ── Utils ── */
        .text-brand {
            color: var(--brand) !important;
        }

        .fw-800 {
            font-weight: 800 !important;
        }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 4px;
        }

        .page-item {
            border-radius: var(--r);
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--r);
            color: var(--text-2);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            min-width: 40px;
            transition: all .15s;
        }

        .page-link:hover {
            background: var(--surface-2);
            border-color: var(--brand);
            color: var(--brand);
        }

        .page-item.active .page-link {
            background: var(--brand);
            border-color: var(--brand);
            color: white;
        }
    </style>
</head>

<body>

    <!-- ═══ TOPBAR ═══ -->
    <div class="topbar">
        <div class="topbar-left">
            <?php if ($inLeague): ?>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
                    <i class="fas fa-bars"></i>
                </button>
            <?php endif; ?>

            <a href="<?= ADMIN_URL ?>/index.php" class="topbar-brand">
                <div class="brand-icon">🏀</div>
                <span class="d-none d-sm-block"><?= APP_NAME ?></span>
            </a>

            <nav class="topbar-nav">
                <?php if (isScorer()): ?>
                    <a href="<?= ADMIN_URL ?>/scorer/index.php" class="<?= $activeNav === 'scorer_games' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-list"></i><span>My Games</span>
                    </a>
                <?php else: ?>
                    <a href="<?= ADMIN_URL ?>/index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-gauge-high"></i><span>Dashboard</span>
                    </a>
                    <a href="<?= ADMIN_URL ?>/leagues/index.php" class="<?= $activeNav === 'leagues' ? 'active' : '' ?>">
                        <i class="fas fa-trophy"></i><span>Leagues</span>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="<?= ADMIN_URL ?>/users/index.php" class="<?= $activeNav === 'users' ? 'active' : '' ?>">
                            <i class="fas fa-users-gear"></i><span>Users</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <div class="dropdown">
            <button class="topbar-user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-user-avatar"><?= strtoupper(substr($admin['username'] ?? 'A', 0, 1)) ?></div>
                <span class="d-none d-sm-block"><?= clean($admin['full_name'] ?: $admin['username']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:200px;">
                <li class="px-3 py-2" style="border-bottom:1px solid var(--border);margin-bottom:4px;">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);font-weight:700;">Signed in as</div>
                    <div style="font-weight:700;font-size:13px;margin-top:2px;"><?= clean($admin['username'] ?? '') ?></div>
                    <?php if (!empty($admin['role'])): ?>
                        <div style="margin-top:4px;"><?= roleBadge($admin['role']) ?></div>
                    <?php endif; ?>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= ADMIN_URL ?>/users/index.php?edit=<?= $admin['id'] ?? '' ?>">
                        <i class="fas fa-user fa-sm"></i> Edit My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="<?= ADMIN_URL ?>/logout.php">
                        <i class="fas fa-right-from-bracket fa-sm"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ═══ PAGE BODY ═══ -->
    <div class="page-body">

        <?php if ($inLeague): ?>
            <!-- ═══ SIDEBAR ═══ -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-league-header">
                    <a href="<?= ADMIN_URL ?>/leagues/index.php" class="sidebar-back">
                        <i class="fas fa-arrow-left fa-xs"></i> All Leagues
                    </a>
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($leagueCtx['logo'])): ?>
                            <img src="<?= UPLOADS_URL . '/' . $leagueCtx['logo'] ?>"
                                style="width:42px;height:42px;border-radius:var(--r);object-fit:cover;flex-shrink:0;" alt="">
                        <?php else: ?>
                            <div class="sidebar-league-avatar"><?= strtoupper(substr($leagueCtx['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div style="min-width:0;">
                            <div style="font-size:14px;font-weight:700;color:var(--text-1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= clean($leagueCtx['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-3);margin-top:2px;font-weight:500;">League</div>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav flex-grow-1">
                    <span class="sidebar-section-label">Manage</span>
                    <?php
                    $sidebarItems = [
                        'overview' => ['fa-chart-pie',      'Overview'],
                        'seasons'  => ['fa-calendar-days',  'Seasons'],
                        'teams'    => ['fa-shield-halved',   'Teams'],
                        'players'  => ['fa-person-running',  'Players'],
                    ];
                    foreach ($sidebarItems as $key => [$icon, $label]):
                    ?>
                        <a href="<?= sidebarUrl($key, $lid) ?>" class="sidebar-link <?= $activeSidebar === $key ? 'active' : '' ?>">
                            <span class="si"><i class="fas <?= $icon ?>"></i></span>
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                    <hr class="sidebar-divider">
                    <?php if (isAdmin()): ?>
                        <span class="sidebar-section-label">Config</span>
                        <a href="<?= sidebarUrl('settings', $lid) ?>" class="sidebar-link <?= $activeSidebar === 'settings' ? 'active' : '' ?>">
                            <span class="si"><i class="fas fa-gear"></i></span> Settings
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- ═══ MAIN CONTENT ═══ -->
        <main class="main-content <?= $inLeague ? 'with-sidebar' : '' ?>">

            <?php if ($flash):
                [$bg, $border, $color, $ico] = match ($flash['type']) {
                    'success' => ['#f0fdf4', '#86efac', '#15803d', 'fa-circle-check text-success'],
                    'error'   => ['#fef2f2', '#fca5a5', '#dc2626', 'fa-circle-xmark text-danger'],
                    'warning' => ['#fffbeb', '#fde68a', '#92400e', 'fa-triangle-exclamation text-warning'],
                    default   => ['#eff6ff', '#93c5fd', '#1d4ed8', 'fa-circle-info text-info'],
                };
            ?>
                <div class="flash-bar" style="background:<?= $bg ?>;border-color:<?= $border ?>;color:<?= $color ?>;">
                    <i class="fas <?= $ico ?>" style="flex-shrink:0;"></i>
                    <span style="flex:1;"><?= clean($flash['message']) ?></span>
                    <button class="flash-close" style="color:<?= $color ?>;" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>