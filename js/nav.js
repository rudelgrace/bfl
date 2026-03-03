/**
 * Battle 3x3 — Shared Nav + Footer Injector
 * nav.js — Include BEFORE api.js
 */
(function () {

  const NAV_HTML = `
    <nav class="nav">
      <div class="container">
        <div class="nav-inner">
          <a href="./" class="nav-logo">
            <div class="nav-logo-mark">3x3</div>
            <span class="nav-logo-text">BATTLE<span>3x3</span></span>
          </a>
          <ul class="nav-links" id="nav-links">
            <li><a href="./"       class="nav-link" data-page="home">Home</a></li>
            <li><a href="players"  class="nav-link" data-page="players">Players</a></li>
            <li><a href="teams"    class="nav-link" data-page="teams">Teams</a></li>
            <li><a href="games"    class="nav-link" data-page="games">Games</a></li>
            <li><a href="mvp"      class="nav-link" data-page="mvp">MVP</a></li>
            <li><a href="about"    class="nav-link" data-page="about">About</a></li>
            <li class="nav-login-mobile"><a href="admin/login.php" class="btn btn-primary btn-sm">Login</a></li>
          </ul>
          <div class="nav-right">
            <div class="league-dropdown" id="league-dropdown-wrapper">
              <button class="league-pill" id="league-pill" aria-haspopup="true">
                <span class="league-pill-dot"></span>
                <span class="pill-text">Loading\u2026</span>
              </button>
              <div class="league-dropdown-menu" id="league-dropdown-menu"></div>
            </div>
            <a href="admin/login.php" class="btn btn-primary btn-sm nav-login-btn-desktop">Login</a>
            <button class="nav-toggle" id="nav-toggle" aria-label="Menu">
              <span></span><span></span><span></span>
            </button>
          </div>
        </div>
      </div>
    </nav>
    <div class="league-context-bar" id="league-context-bar"></div>
  `;

  const FOOTER_HTML = `
    <footer class="footer">
      <div class="footer-logo">BATTLE<span>3x3</span></div>
      <div class="footer-links">
        <a href="./">Home</a>
        <a href="players">Players</a>
        <a href="teams">Teams</a>
        <a href="games">Games</a>
        <a href="mvp">MVP</a>
        <a href="about">About</a>
      </div>
      <div class="footer-copy">
        &copy; ${new Date().getFullYear()} Battle 3x3. All stats are official.
      </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/27671671416" target="_blank" rel="noopener" class="whatsapp-fab" aria-label="Chat on WhatsApp">
      <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" width="26" height="26">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
    </a>
  `;

  // Inject nav
  const navPlaceholder = document.getElementById('nav-placeholder');
  if (navPlaceholder) navPlaceholder.outerHTML = NAV_HTML;

  // Inject footer
  const footerPlaceholder = document.getElementById('footer-placeholder');
  if (footerPlaceholder) footerPlaceholder.outerHTML = FOOTER_HTML;

  // Inject favicon
  document.head.insertAdjacentHTML('beforeend', '<link rel="icon" type="image/x-icon" href="/uploads/logos/favicon.ico">');

})();
