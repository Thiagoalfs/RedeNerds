<?php
$paginaAtiva = 'email';
$tituloPagina = 'E-Mail';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.email-frame-shell {
    position: relative;
    width: 100%;
    height: calc(100vh - 112px);
    min-height: 540px;
    background: var(--admin-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.email-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: var(--admin-bg);
}

.email-frame-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

@media (max-width: 768px) {
    .email-frame-shell {
        height: calc(100vh - 130px);
    }
}
</style>

<div class="email-frame-toolbar">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Caixa de E-Mail</h2>
        <small class="text-muted">SnappyMail conectado via IMAP/SMTP (<code>h58.servidorhh.com</code>)</small>
    </div>
    <div class="d-flex gap-2">
        <a href="/webmail/?admin" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" title="Configurar domínio/IMAP no SnappyMail">
            <i class="fa-solid fa-sliders me-1"></i> Config SnappyMail
        </a>
        <a href="/webmail/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir em Nova Aba
        </a>
    </div>
</div>

<div class="email-frame-shell">
    <iframe
        id="snappymail-frame"
        class="email-frame"
        src="/webmail/"
        title="Cliente de E-Mail SnappyMail"
        loading="eager"
    ></iframe>
</div>

<script>
(function() {
    const frame = document.getElementById('snappymail-frame');
    if (!frame) return;

    function buildThemeCss(isDark) {
        return isDark
            ? `
                :root { color-scheme: dark; }
                body, #rl-app, .b-main, .b-content {
                    background: #090d16 !important;
                    color: #f1f5f9 !important;
                    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif !important;
                }
                .b-toolbar, .b-folders, .b-list, .b-message, .modal-content {
                    background: #111827 !important;
                    border-color: rgba(255, 255, 255, 0.08) !important;
                    color: #e2e8f0 !important;
                }
                .btn-primary, .button-primary, .b-compose-btn {
                    background: #2563eb !important;
                    border-color: #2563eb !important;
                    color: #ffffff !important;
                }
                .btn-primary:hover, .button-primary:hover, .b-compose-btn:hover {
                    background: #1d4ed8 !important;
                }
                .selected, .active > a, .b-list .messageItem.selected {
                    background: rgba(37, 99, 235, 0.22) !important;
                }
                input, textarea, select {
                    background: #0f172a !important;
                    color: #f1f5f9 !important;
                    border-color: rgba(255, 255, 255, 0.12) !important;
                }
            `
            : `
                :root { color-scheme: light; }
                body, #rl-app, .b-main, .b-content {
                    background: #f8fafc !important;
                    color: #0f172a !important;
                    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif !important;
                }
                .b-toolbar, .b-folders, .b-list, .b-message, .modal-content {
                    background: #ffffff !important;
                    border-color: #e2e8f0 !important;
                }
                .btn-primary, .button-primary, .b-compose-btn {
                    background: #2563eb !important;
                    border-color: #2563eb !important;
                }
            `;
    }

    function applyThemeToIframe() {
        try {
            const doc = frame.contentDocument;
            if (!doc || !doc.head) return;
            const isDark = (document.documentElement.getAttribute('data-theme') || 'dark') === 'dark';
            let styleEl = doc.getElementById('admin-snappymail-theme');
            if (!styleEl) {
                styleEl = doc.createElement('style');
                styleEl.id = 'admin-snappymail-theme';
                doc.head.appendChild(styleEl);
            }
            styleEl.textContent = buildThemeCss(isDark);
        } catch (_) {
            // Ignora se o iframe ainda não estiver no mesmo domínio (ex.: erro de proxy)
        }
    }

    frame.addEventListener('load', applyThemeToIframe);

    const observer = new MutationObserver(applyThemeToIframe);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
