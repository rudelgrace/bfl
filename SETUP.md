# Battle 3x3 — Setup Guide

## Project Structure

```
battle3x3/
├── index.html          ← Public home page
├── players.html        ← Public players page
├── teams.html          ← Public teams / standings
├── games.html          ← Public games / schedule
├── mvp.html            ← Public MVP rankings
├── player.html         ← Individual player profile
├── team.html           ← Individual team page
├── battle.html         ← About / Rules page
├── 404.html            ← Custom 404 error page
├── css/battle.css      ← All public styles
├── js/
│   ├── api.js          ← API client (auto-detects backend URL)
│   └── nav.js          ← Shared nav + footer injection
│
├── admin/              ← Admin panel (PHP, login-protected)
│   ├── index.php       ← Admin dashboard
│   ├── login.php       ← Admin login form
│   ├── logout.php      ← Logout + redirect
│   └── ...             ← League/season/team/player/game management
│
├── bfl-admin/          ← Public-facing admin entry point (clean URL)
│   └── index.php       ← Checks auth, routes to login or dashboard
│
├── api/                ← JSON API endpoints (PHP)
│   ├── health.php      ← Health check / status endpoint
│   ├── leagues.php
│   ├── seasons.php
│   ├── players.php
│   ├── player.php
│   ├── teams.php
│   ├── team.php
│   ├── games.php
│   ├── game.php
│   ├── standings.php
│   └── mvp.php
│
├── app/Services/       ← Backend business logic (services)
├── config/             ← App bootstrap + DB config
├── core/               ← Framework core (DB, Env, Helpers)
├── includes/           ← Auth, header template, functions
├── uploads/            ← Player photos + team logos
│   ├── logos/
│   └── players/
│
├── .env                ← YOUR CREDENTIALS (never commit this)
├── .env.example        ← Template for .env
├── .htaccess           ← Apache URL routing + security
├── php.ini             ← Per-directory PHP settings
└── database_v1.2_FULL.sql  ← Full DB schema + seed data
```

---

## Step 1 — Create the Database

1. Open **phpMyAdmin** (or MySQL CLI)
2. Create a new database named `battle3x3`
3. Select that database
4. Click **Import** → choose `database_v1.2_FULL.sql` → **Go**

Default admin credentials created by the SQL:
- **Username:** `admin`
- **Password:** `password`

> ⚠️ Change this password immediately after first login via Users → Edit Profile.

---

## Step 2 — Configure `.env`

Copy `.env.example` to `.env` and set these values:

```env
APP_ENV=development       # Use 'development' locally to see errors

# Your site URL — NO trailing slash
BASE_URL=http://localhost/battle3x3    # subdirectory install
# BASE_URL=http://localhost            # domain-root install
# BASE_URL=https://yourdomain.com      # live server

DB_HOST=localhost
DB_NAME=battle3x3
DB_USER=your_db_username
DB_PASS=your_db_password
```

### Local XAMPP / WAMP (subdirectory)
- Place the `battle3x3/` folder inside `htdocs/` (XAMPP) or `www/` (WAMP)
- Set `BASE_URL=http://localhost/battle3x3`
- No `.htaccess` changes needed

### Local XAMPP / WAMP (domain root)
- Place contents of `battle3x3/` directly inside `htdocs/`
- Set `BASE_URL=http://localhost`
- No `.htaccess` changes needed

### Live Server / cPanel
- Upload the entire `battle3x3/` folder to `public_html/`
- Set `BASE_URL=https://yourdomain.com`
- No `.htaccess` changes needed

---

## Step 3 — Upload & Test

1. Upload the project folder to your server
2. Visit the public site and confirm it loads
3. Visit `/bfl-admin` and log in with `admin` / `password`
4. **Change the admin password immediately**
5. Create a League, Season, Teams, and Players
6. Return to the public site — data appears automatically

---

## URL Reference

| What | URL |
|------|-----|
| **Public Home** | `yourdomain.com/` |
| Players | `yourdomain.com/players` |
| Teams & Standings | `yourdomain.com/teams` |
| Games | `yourdomain.com/games` |
| MVP Race | `yourdomain.com/mvp` |
| About / Rules | `yourdomain.com/about` |
| **Admin Login** | **`yourdomain.com/bfl-admin`** |
| Admin Dashboard | `yourdomain.com/admin/index.php` |
| API Health Check | `yourdomain.com/api/health.php` |

---

## API Endpoints

All API endpoints are under `/api/` and return JSON:

| Endpoint | Parameters |
|----------|-----------|
| `/api/health.php` | — |
| `/api/leagues.php` | — |
| `/api/seasons.php` | `?league_id=` |
| `/api/players.php` | `?league_id=&season_id=` |
| `/api/player.php` | `?id=` |
| `/api/teams.php` | `?league_id=&season_id=` |
| `/api/team.php` | `?id=&season_id=` |
| `/api/games.php` | `?league_id=&season_id=&type=&status=` |
| `/api/game.php` | `?id=` |
| `/api/standings.php` | `?league_id=&season_id=` |
| `/api/mvp.php` | `?league_id=&season_id=` |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| White screen / 500 error | Set `APP_ENV=development` in `.env` to see the actual error |
| API returns 404 | Verify `BASE_URL` in `.env` has no trailing slash |
| `/bfl-admin` gives 404 | Confirm `mod_rewrite` is enabled on your server. Check `AllowOverride All` in Apache config |
| Photos not showing | Check `uploads/` folder permissions (755) |
| Public pages show spinners but no data | Select a league from the top nav dropdown; make sure a league has an active season |
| Everything was working, now broken | Check `.env` — ensure `DB_PASS` doesn't have special shell characters without quoting |
| `admin/login.php` not found | Use `/bfl-admin` as the admin entry point |
| 404 on clean URLs (e.g. `/players`) | Confirm Apache `AllowOverride All` is set for your directory. On Nginx you need separate config (see below) |

### Nginx Configuration (if not using Apache)

If your server uses Nginx instead of Apache, the `.htaccess` has no effect.
Add this to your server block:

```nginx
location / {
    try_files $uri $uri/ $uri.html =404;
}

location ~ ^/(core|config|app|includes) {
    deny all;
    return 403;
}

rewrite ^/players/?$  /players.html  last;
rewrite ^/teams/?$    /teams.html    last;
rewrite ^/games/?$    /games.html    last;
rewrite ^/mvp/?$      /mvp.html      last;
rewrite ^/about/?$    /battle.html   last;
rewrite ^/player/?$   /player.html   last;
rewrite ^/team/?$     /team.html     last;
rewrite ^/login/?$    /admin/login.php last;
```
