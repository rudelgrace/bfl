</main>
</div><!-- .page-body -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Confirm dialogs ──
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// ── Mobile sidebar toggle ──
const sidebarToggle  = document.getElementById('sidebarToggle');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar?.classList.add('open');
    sidebarOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    sidebar?.classList.remove('open');
    sidebarOverlay?.classList.remove('open');
    document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
});
sidebarOverlay?.addEventListener('click', closeSidebar);

// Close sidebar on resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) closeSidebar();
});

// ── Logo/photo preview ──
document.querySelectorAll('[data-preview-target]').forEach(input => {
    input.addEventListener('change', function() {
        const target = document.getElementById(this.dataset.previewTarget);
        if (!target || !this.files[0]) return;
        const reader = new FileReader();
        reader.onload = ev => {
            target.style.backgroundImage = `url(${ev.target.result})`;
            target.innerHTML = `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
        };
        reader.readAsDataURL(this.files[0]);
    });
});

// Legacy preview support (logo-input / photo-input)
['logo-input', 'photo-input'].forEach(id => {
    const input = document.getElementById(id);
    const preview = document.getElementById(id === 'logo-input' ? 'logo-preview' : 'photo-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function() {
        if (!this.files[0]) return;
        const r = new FileReader();
        r.onload = ev => {
            preview.innerHTML = `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
        };
        r.readAsDataURL(this.files[0]);
    });
});
</script>
</body>
</html>
