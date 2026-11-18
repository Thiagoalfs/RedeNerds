<?php
$paginaAtiva = 'email';
$tituloPagina = 'E-Mail';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.admin-content:has(.email-frame-shell) {
    padding: 0 !important;
    max-width: none !important;
    margin: 0 !important;
}

.email-frame-shell {
    position: relative;
    width: 100%;
    height: calc(100vh - 60px);
    min-height: 480px;
    background: var(--admin-surface);
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: hidden;
}

.email-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: var(--admin-bg);
}

@media (max-width: 768px) {
    .email-frame-shell {
        height: calc(100dvh - 60px);
        min-height: 0;
    }
}

.email-fallback-banner {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.85rem;
    padding: 1.5rem;
    text-align: center;
    background: var(--admin-bg);
    color: var(--text-main);
}

.email-fallback-banner[hidden] {
    display: none;
}
</style>

<div class="email-frame-shell">
    <iframe
        id="snappymail-frame"
        class="email-frame"
        src="/webmail/"
        title="Cliente de E-Mail RNMail"
        loading="eager"
    ></iframe>
    <div id="email-fallback-banner" class="email-fallback-banner" hidden>
        <p class="mb-0 text-muted">O cliente de e-mail demorou para responder.</p>
        <button type="button" id="btn-reload-webmail" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-rotate-right me-1"></i> Recarregar E-Mail
        </button>
    </div>
</div>

<script>
(function() {
    const frame = document.getElementById('snappymail-frame');
    if (!frame) return;

    function buildThemeCss(isDark) {
        const tokens = isDark
            ? `
                :root {
                    color-scheme: dark;
                    --fontSans: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    --fontMono: 'JetBrains Mono', monospace;
                    --main-bg-image: none;
                    --main-bg-color: #090d16;
                    --main-color: #e2e8f0;
                    --panel-bg-clr: #111827;
                    --border-color: rgba(255, 255, 255, 0.07);
                    --border-radius: 0px;
                    --hr-color: rgba(255, 255, 255, 0.07);
                    --link-color: #60a5fa;
                    --loading-color: #94a3b8;
                    --loading-text-shadow: none;
                    --spinner-color: #3b82f6;
                    --smMainShadow: none;

                    --login-bg-color: #111827;
                    --login-color: #f1f5f9;
                    --login-border: 1px solid rgba(255, 255, 255, 0.08);
                    --login-border-radius: 14px;
                    --login-box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45);

                    --folders-color: #94a3b8;
                    --folders-disabled-color: #475569;
                    --folders-hover-bg-color: rgba(255, 255, 255, 0.05);
                    --folders-hover-color: #f8fafc;
                    --folders-selected-bg-color: rgba(37, 99, 235, 0.18);
                    --folders-selected-color: #ffffff;
                    --folders-focused-bg-color: rgba(37, 99, 235, 0.14);
                    --folders-focused-color: #ffffff;
                    --unread-count-bg-color: #2563eb;
                    --unread-count-color: #ffffff;

                    --message-list-toolbar-bg-color: #0f172a;
                    --message-header-bg-clr: #111827;
                    --tr-hover-bg-clr: rgba(255, 255, 255, 0.035);
                    --tr-odd-bg-clr: transparent;

                    --btn-bg-clr: #1e293b;
                    --btn-border-clr: rgba(255, 255, 255, 0.1);
                    --btn-border-radius: 8px;
                    --btn-clr: #e2e8f0;
                    --btn-success-bg-clr: #10b981;
                    --btn-danger-bg-clr: #ef4444;

                    --input-bg-clr: #0b1120;
                    --input-border-clr: rgba(255, 255, 255, 0.11);
                    --input-border-radius: 8px;
                    --input-clr: #f1f5f9;
                    --input-focus-border-clr: #3b82f6;

                    --dialog-bg-clr: #111827;
                    --dialog-border-clr: rgba(255, 255, 255, 0.1);
                    --dialog-border-radius: 12px;
                    --dialog-clr: #f1f5f9;

                    --dropdown-menu-bg-color: #111827;
                    --dropdown-menu-border-clr: rgba(255, 255, 255, 0.1);
                    --dropdown-menu-color: #e2e8f0;
                    --dropdown-menu-hover-bg-color: rgba(37, 99, 235, 0.18);
                    --dropdown-menu-hover-color: #ffffff;
                    --dropdown-menu-disabled-color: #475569;

                    --settings-menu-color: #94a3b8;
                    --settings-menu-hover-bg-color: rgba(255, 255, 255, 0.05);
                    --settings-menu-hover-color: #f8fafc;
                    --settings-menu-selected-bg-color: rgba(37, 99, 235, 0.2);
                    --settings-menu-selected-color: #ffffff;
                    --tab-active-bg-clr: #1e293b;
                    --tab-hover-border-clr: rgba(255, 255, 255, 0.12);
                }
            `
            : `
                :root {
                    color-scheme: light;
                    --fontSans: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    --fontMono: 'JetBrains Mono', monospace;
                    --main-bg-image: none;
                    --main-bg-color: #f8fafc;
                    --main-color: #0f172a;
                    --panel-bg-clr: #ffffff;
                    --border-color: #e2e8f0;
                    --border-radius: 0px;
                    --hr-color: #e2e8f0;
                    --link-color: #2563eb;
                    --spinner-color: #2563eb;
                    --smMainShadow: none;

                    --login-bg-color: #ffffff;
                    --login-color: #0f172a;
                    --login-border: 1px solid #e2e8f0;
                    --login-border-radius: 14px;
                    --login-box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);

                    --folders-color: #475569;
                    --folders-hover-bg-color: #f1f5f9;
                    --folders-hover-color: #0f172a;
                    --folders-selected-bg-color: rgba(37, 99, 235, 0.1);
                    --folders-selected-color: #1d4ed8;
                    --folders-focused-bg-color: rgba(37, 99, 235, 0.08);
                    --folders-focused-color: #1d4ed8;
                    --unread-count-bg-color: #2563eb;
                    --unread-count-color: #ffffff;

                    --message-list-toolbar-bg-color: #ffffff;
                    --message-header-bg-clr: #ffffff;
                    --tr-hover-bg-clr: #f8fafc;
                    --tr-odd-bg-clr: transparent;

                    --btn-bg-clr: #ffffff;
                    --btn-border-clr: #cbd5e1;
                    --btn-border-radius: 8px;
                    --btn-clr: #0f172a;

                    --input-bg-clr: #ffffff;
                    --input-border-clr: #cbd5e1;
                    --input-border-radius: 8px;
                    --input-clr: #0f172a;
                    --input-focus-border-clr: #2563eb;

                    --dialog-bg-clr: #ffffff;
                    --dialog-border-clr: #e2e8f0;
                    --dialog-border-radius: 12px;
                    --dialog-clr: #0f172a;

                    --dropdown-menu-bg-color: #ffffff;
                    --dropdown-menu-border-clr: #e2e8f0;
                    --dropdown-menu-color: #0f172a;
                    --dropdown-menu-hover-bg-color: #eff6ff;
                    --dropdown-menu-hover-color: #1d4ed8;
                }
            `;

        return tokens + `
            html, body, button, input, textarea, select, .messageItem, .b-message-view-desc, .b-message, .b-text-part {
                font-family: var(--fontSans) !important;
            }
            code, pre, .e-code {
                font-family: var(--fontMono) !important;
            }
            body {
                background: var(--main-bg-color) !important;
                color: var(--main-color) !important;
                -webkit-font-smoothing: antialiased;
            }
            ::selection {
                background: rgba(37, 99, 235, 0.28);
                color: inherit;
            }
            ::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            ::-webkit-scrollbar-track {
                background: transparent;
            }
            ::-webkit-scrollbar-thumb {
                background: ${isDark ? 'rgba(148, 163, 184, 0.22)' : 'rgba(100, 116, 139, 0.28)'};
                border-radius: 999px;
            }
            ::-webkit-scrollbar-thumb:hover {
                background: ${isDark ? 'rgba(148, 163, 184, 0.4)' : 'rgba(100, 116, 139, 0.45)'};
            }
            /* Remove bordas arredondadas e gradientes cinzas padrão do tema Default */
            .messageList,
            .messageView,
            #V-MailMessageList.focused .messageList,
            #V-MailMessageView.focused .messageView,
            #V-SettingsPane {
                border-radius: 0 !important;
                border: 1px solid var(--border-color) !important;
                box-shadow: none !important;
                background: var(--panel-bg-clr) !important;
            }
            /* Header unificado estilo Gmail no topo (50px) + sidebar independente abaixo */
            #rl-app::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 50px;
                background: var(--main-bg-color);
                border-bottom: 1px solid var(--border-color);
                z-index: 100;
                pointer-events: none;
            }
            #rl-left {
                margin-top: 50px !important;
                height: calc(100vh - 50px) !important;
                padding-top: 8px !important;
                box-sizing: border-box !important;
            }
            @media screen and (min-width: 800px) {
                html:not(.rl-left-panel-disabled) #rl-left {
                    min-width: 240px !important;
                    max-width: 320px !important;
                }
                html.sm-msgView-side #V-MailMessageList {
                    flex: 0 0 auto !important;
                    width: auto !important;
                    min-width: 300px !important;
                    max-width: 420px !important;
                }
                html.sm-msgView-side #V-MailMessageList .messageList {
                    width: clamp(300px, 32vw, 420px);
                    min-width: 300px !important;
                    max-width: 420px !important;
                    box-sizing: border-box !important;
                }
                html.sm-msgView-side #V-MailMessageView {
                    flex: 1 1 0% !important;
                    min-width: 0 !important;
                }
            }
            .admin-snappymail-brand {
                position: fixed;
                top: 0;
                left: 0;
                width: 200px;
                height: 50px;
                padding: 0 14px;
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 14px;
                background: transparent;
                z-index: 104;
            }
            .admin-hamburger-btn,
            .admin-back-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                padding: 0;
                border: 1px solid transparent;
                border-radius: 6px;
                background: transparent;
                color: var(--folders-color);
                cursor: pointer;
                flex-shrink: 0;
                transition: background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease;
            }
            .admin-back-btn {
                display: none;
            }
            .admin-hamburger-btn:hover,
            .admin-back-btn:hover {
                background: var(--folders-hover-bg-color);
                color: var(--folders-hover-color);
                border-color: var(--border-color);
            }
            .admin-hamburger-btn svg,
            .admin-back-btn svg {
                width: 18px;
                height: 18px;
            }
            .b-footer .toggleLeft {
                display: none !important;
            }
            #rl-left:has(#V-SettingsMenu:not([hidden])) .admin-hamburger-btn,
            body:has(#V-SettingsMenu:not([hidden])) .admin-hamburger-btn {
                display: none !important;
            }
            body:has(#V-SettingsMenu:not([hidden])) .admin-back-btn {
                display: inline-flex !important;
            }
            #V-SettingsMenu .b-toolbar {
                display: none !important;
            }
            .admin-snappymail-brand img {
                height: 24px;
                width: auto;
                display: block;
                object-fit: contain;
                ${isDark ? '' : 'filter: brightness(0.16) contrast(1.2);'}
            }
            #V-MailMessageList .btn-toolbar,
            #V-MailMessageView .top-toolbar,
            #V-SystemDropDown {
                position: relative;
                z-index: 103;
            }
            #V-SystemDropDown {
                position: absolute !important;
            }
            #V-SystemDropDown > .btn-toolbar {
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
            }
            #V-SystemDropDown .admin-header-settings-btn {
                background: transparent !important;
                border: 1px solid transparent !important;
                box-shadow: none !important;
                color: var(--folders-color) !important;
                width: 32px !important;
                height: 32px !important;
                padding: 0 !important;
                border-radius: 6px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 16px !important;
                line-height: 1 !important;
                cursor: pointer;
            }
            #V-SystemDropDown .admin-header-settings-btn:hover {
                background: var(--folders-hover-bg-color) !important;
                color: var(--folders-hover-color) !important;
                border-color: var(--border-color) !important;
            }
            #V-SystemDropDown #top-system-dropdown-id {
                display: inline-flex !important;
                align-items: center !important;
                gap: 7px !important;
                height: 32px !important;
                padding: 0 10px !important;
                border-radius: 8px !important;
                background: ${isDark ? 'rgba(255, 255, 255, 0.04)' : '#f8fafc'} !important;
                border: 1px solid var(--border-color) !important;
                box-shadow: none !important;
                outline: none !important;
                color: var(--main-color) !important;
                font-family: var(--fontSans) !important;
                font-size: 0.84rem !important;
                transition: background-color 0.14s ease, border-color 0.14s ease !important;
            }
            #V-SystemDropDown #top-system-dropdown-id:hover,
            #V-SystemDropDown .open #top-system-dropdown-id {
                background: ${isDark ? 'rgba(255, 255, 255, 0.08)' : '#f1f5f9'} !important;
                border-color: ${isDark ? 'rgba(255, 255, 255, 0.16)' : '#cbd5e1'} !important;
            }
            #V-SystemDropDown #top-system-dropdown-id:focus-visible {
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35) !important;
                border-color: #3b82f6 !important;
            }
            #V-SystemDropDown #top-system-dropdown-id .accountPlace {
                border: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                line-height: 1.2 !important;
                font-weight: 500 !important;
            }
            #V-SystemDropDown #top-system-dropdown-id .fontastic {
                opacity: 0.78;
                font-size: 0.9rem !important;
                line-height: 1 !important;
            }
            #V-SystemDropDown #top-system-dropdown-id::after {
                font-size: 0.62rem !important;
                opacity: 0.65;
                margin-left: 1px;
            }
            @keyframes adminDropdownPop {
                from { opacity: 0; transform: scale(0.97); }
                to { opacity: 1; transform: scale(1); }
            }
            #V-SystemDropDown .dropdown-menu {
                margin-top: 6px !important;
                min-width: 230px !important;
                padding: 6px !important;
                border-radius: 10px !important;
                border: 1px solid var(--dropdown-menu-border-clr) !important;
                box-shadow: ${isDark ? '0 14px 32px rgba(0, 0, 0, 0.48)' : '0 12px 28px rgba(15, 23, 42, 0.12)'} !important;
                animation: adminDropdownPop 0.14s cubic-bezier(0.16, 1, 0.3, 1);
                transform-origin: top right;
            }
            #V-SystemDropDown .dropdown-menu li > a {
                border-radius: 6px !important;
                padding: 7px 10px !important;
                font-family: var(--fontSans) !important;
                font-size: 0.86rem !important;
                line-height: 1.35 !important;
                transition: background-color 0.12s ease, color 0.12s ease !important;
            }
            #V-SystemDropDown .dropdown-menu * + .dividerbar {
                margin-top: 5px !important;
                padding-top: 5px !important;
                border-top: 1px solid var(--border-color) !important;
            }
            #V-SystemDropDown .dropdown-menu li > a[data-i18n="GLOBAL/LOGOUT"]:hover,
            #V-SystemDropDown .dropdown-menu li > a[data-i18n="GLOBAL/LOGOUT"]:focus {
                background-color: rgba(239, 68, 68, 0.14) !important;
                color: ${isDark ? '#f87171' : '#dc2626'} !important;
            }
            #V-MailFolderList .b-footer {
                display: none !important;
            }
            #V-MailMessageView .top-toolbar {
                height: 50px !important;
                min-height: 50px !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 0 !important;
                z-index: 1 !important;
            }
            #V-MailMessageView .top-toolbar > .btn-group {
                display: none !important;
            }
            /* Lista de e-mails começa abaixo do header de 50px e barra de ações fica abaixo da busca */
            #V-MailMessageList {
                padding-top: 50px !important;
                box-sizing: border-box !important;
            }
            #V-MailMessageList:has(.open) {
                position: relative !important;
                z-index: 102 !important;
            }
            #V-MailMessageList .messageList {
                height: calc(100vh - 50px) !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar {
                display: flex !important;
                align-items: center !important;
                flex-wrap: wrap !important;
                gap: 4px !important;
                padding: 4px 8px !important;
                margin: 0 !important;
                background: var(--panel-bg-clr) !important;
                border-bottom: 1px solid var(--border-color) !important;
                position: relative !important;
                z-index: 30 !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .dropdown-menu {
                z-index: 200 !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn:where(:not([hidden]):not([style*="display: none"]):not([style*="display:none"])),
            #V-MailMessageList .btn-toolbar.admin-actions-bar .icon-folder-add:where(:not([hidden]):not([style*="display: none"]):not([style*="display:none"])) {
                background: transparent !important;
                border-color: transparent !important;
                box-shadow: none !important;
                color: var(--folders-color) !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 2px !important;
                height: 30px !important;
                min-width: 30px !important;
                padding: 0 7px !important;
                margin: 0 !important;
                line-height: 1 !important;
                vertical-align: middle !important;
                border-radius: 6px !important;
                box-sizing: border-box !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn > i,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn > span,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn::before,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn::after,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .icon-folder-add::before {
                line-height: 1 !important;
                vertical-align: middle !important;
                margin: 0 !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn:hover,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .icon-folder-add:hover {
                background: var(--folders-hover-bg-color) !important;
                color: var(--folders-hover-color) !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn-group {
                display: inline-flex !important;
                align-items: center !important;
                height: 30px !important;
                margin: 0 !important;
                gap: 2px !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn.disabled,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn[disabled],
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn[hidden],
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn[style*="display: none"],
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn[style*="display:none"],
            #V-MailMessageList .btn-toolbar.admin-actions-bar .toggleLeft,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .buttonCompose,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn-group:not(:has(.btn:not(.disabled):not([disabled]):not([hidden]):not([style*="display: none"]):not([style*="display:none"]):not(.toggleLeft):not(.buttonCompose))),
            #V-MailMessageList .btn-toolbar.admin-actions-bar .btn-group:has(#no-sort-list),
            #no-sort-list {
                display: none !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar > .btn-group:has(#more-list-dropdown-id) {
                margin-left: auto !important;
                position: relative !important;
            }
            html.rl-left-panel-disabled #more-list-dropdown-id + .dropdown-menu,
            #V-MailMessageList .btn-toolbar.admin-actions-bar #more-list-dropdown-id + .dropdown-menu {
                position: absolute !important;
                top: calc(100% + 4px) !important;
                right: 0 !important;
                left: auto !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar i.icon-spinner {
                width: 15px !important;
                height: 15px !important;
                border: none !important;
                border-radius: 0 !important;
                display: inline-block !important;
                vertical-align: middle !important;
                background-color: currentColor !important;
                -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 12a9 9 0 1 1-2.64-6.36'/%3E%3Cpolyline points='21 3 21 9 15 9'/%3E%3C/svg%3E") no-repeat center / contain !important;
                mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 12a9 9 0 1 1-2.64-6.36'/%3E%3Cpolyline points='21 3 21 9 15 9'/%3E%3C/svg%3E") no-repeat center / contain !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar i.icon-spinner::before {
                content: '' !important;
            }
            #V-MailMessageList .btn-toolbar.admin-actions-bar .e-checkbox,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .checkboxMessage {
                margin: 0 6px 0 4px !important;
                flex-shrink: 0 !important;
            }
            #V-SettingsMenu {
                padding: 0 2px 0 0 !important;
                margin: 0 !important;
            }
            #V-MailFolderList,
            #V-MailFolderList .b-content {
                padding-top: 0 !important;
            }
            #V-SettingsMenu nav {
                margin: 0 !important;
                padding: 0 !important;
            }
            .b-folders li a,
            #V-SettingsMenu nav a {
                font-family: var(--fontSans) !important;
                font-size: 0.92rem !important;
                height: 36px !important;
                line-height: 36px !important;
                max-height: 36px !important;
                padding: 0 10px !important;
                border-left: 3px solid transparent !important;
                border-radius: 6px !important;
                color: var(--folders-color) !important;
                transition: background-color 0.14s ease, color 0.14s ease !important;
            }
            .b-folders li a::before,
            .b-folders li a > i,
            .b-folders li a > svg,
            .b-folders li a > [class*="icon"]:not(.flag-icon) {
                font-size: 1.05rem !important;
            }
            .b-folders-user li a::before {
                width: 17px !important;
                height: 17px !important;
            }
            #V-SettingsMenu nav a {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                font-weight: 700 !important;
            }
            .admin-settings-icon {
                width: 17px;
                height: 17px;
                flex-shrink: 0;
                opacity: 0.85;
            }
            .b-folders li a.selectable:hover,
            #V-SettingsMenu nav a:hover,
            #V-SettingsMenu nav a:focus {
                background-color: var(--folders-hover-bg-color) !important;
                color: var(--folders-hover-color) !important;
            }
            .b-folders li a.selectable.selected,
            #V-SettingsMenu nav a.selected {
                background-color: var(--folders-selected-bg-color) !important;
                color: var(--folders-selected-color) !important;
            }
            /* Alinha e estiliza checkboxes da barra (.checkboxCheckAll) e da lista (.checkboxMessage) */
            .messageList .checkboxCheckAll,
            #V-MailMessageList .btn-toolbar.admin-actions-bar .checkboxCheckAll {
                margin: 0 8px 0 6px !important;
                padding: 0 !important;
                width: 16px !important;
                height: 16px !important;
                font-size: 16px !important;
                line-height: 16px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
            }
            .checkboxCheckAll,
            .checkboxMessage {
                color: ${isDark ? '#94a3b8' : '#64748b'} !important;
                transition: color 0.14s ease !important;
            }
            .checkboxCheckAll:hover,
            .checkboxMessage:hover {
                color: #3b82f6 !important;
            }
            .messageItem.checked .checkboxMessage,
            .checkboxCheckAll.icon-checkbox-checked,
            .checkboxMessage.icon-checkbox-checked {
                color: #2563eb !important;
            }
            .e-checkbox.material-design > div > div,
            .e-checkbox.material-design input:checked + div {
                animation: none !important;
            }
            .e-checkbox.material-design > div > div {
                width: 16px !important;
                height: 16px !important;
                top: 1px !important;
                left: 1px !important;
                border: 1.5px solid ${isDark ? 'rgba(148, 163, 184, 0.45)' : '#94a3b8'} !important;
                border-radius: 4px !important;
                background: transparent !important;
                transition: border-color 0.14s ease !important;
            }
            .e-checkbox.material-design:hover > div > div {
                border-color: #3b82f6 !important;
            }
            .e-checkbox.material-design input:checked + div {
                top: 1px !important;
                left: 6px !important;
                width: 6px !important;
                height: 11px !important;
                border: solid #3b82f6 !important;
                border-width: 0 2px 2px 0 !important;
                border-radius: 0 !important;
                transform: rotate(45deg) !important;
            }
            .b-list-toolbar,
            .b-list-footer-toolbar,
            .second-toolbar,
            .footer-toolbar,
            .messageList .b-toolbar,
            .messageView .b-toolbar,
            .messageList .b-footer,
            .messageList .b-header,
            .messageView .b-footer,
            .messageView .b-header {
                background: var(--panel-bg-clr) !important;
                background-image: none !important;
                background-color: var(--panel-bg-clr) !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                border-color: var(--border-color) !important;
            }
            .b-list-toolbar,
            .second-toolbar,
            .messageList .b-header {
                border-bottom: 1px solid var(--border-color) !important;
            }
            #V-MailMessageList .second-toolbar {
                align-items: center !important;
            }
            .b-list-footer-toolbar,
            .footer-toolbar,
            .messageList .b-footer {
                border-top: 1px solid var(--border-color) !important;
                color: ${isDark ? '#94a3b8' : '#64748b'} !important;
            }
            .admin-quota-widget {
                position: relative;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin: 12px 6px 4px !important;
                padding: 10px 8px 4px !important;
                border-top: 1px solid var(--border-color) !important;
                color: var(--folders-color) !important;
                cursor: default !important;
            }
            .admin-quota-widget .e-quota {
                display: none !important;
            }
            .admin-quota-icon {
                flex-shrink: 0;
                width: 16px;
                height: 16px;
                opacity: 0.85;
            }
            .admin-quota-track {
                flex: 1;
                height: 6px;
                border-radius: 999px;
                background: ${isDark ? 'rgba(148, 163, 184, 0.22)' : '#e2e8f0'};
                overflow: hidden;
            }
            .admin-quota-fill {
                height: 100%;
                width: 0%;
                border-radius: 999px;
                background: #2563eb;
                transition: width 0.25s ease;
            }
            .admin-quota-pct {
                font-size: 0.78rem;
                font-weight: 500;
                font-variant-numeric: tabular-nums;
                color: var(--folders-color);
                min-width: 28px;
                text-align: right;
            }
            .admin-quota-tooltip {
                position: absolute;
                left: 6px;
                right: 6px;
                bottom: calc(100% + 4px);
                padding: 5px 8px;
                border-radius: 6px;
                font-size: 0.74rem;
                line-height: 1.35;
                text-align: center;
                background: ${isDark ? '#020617' : '#0f172a'};
                color: #f8fafc;
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.15s ease;
                z-index: 30;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .admin-quota-widget:hover .admin-quota-tooltip {
                opacity: 1;
            }
            html.rl-left-panel-disabled .admin-quota-widget .admin-quota-tooltip {
                display: none !important;
            }
            .admin-quota-floating-tooltip {
                position: fixed;
                left: 0;
                top: 0;
                transform: translateY(-50%);
                padding: 6px 10px;
                border-radius: 6px;
                font-size: 0.75rem;
                line-height: 1.35;
                white-space: nowrap;
                max-width: 280px;
                background: ${isDark ? '#020617' : '#0f172a'};
                color: #f8fafc;
                border: 1px solid rgba(255, 255, 255, 0.14);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.15s ease;
                z-index: 99999;
            }
            .admin-quota-floating-tooltip.visible {
                opacity: 1;
            }
            /* Corrige sidebar retraída (60px) sem vazar texto nem esmagar a barra */
            html.rl-left-panel-disabled #rl-left {
                width: 60px !important;
                min-width: 60px !important;
                overflow: hidden !important;
            }
            html.rl-left-panel-disabled #rl-left .b-toolbar {
                padding: 10px 0 !important;
                display: flex !important;
                justify-content: center !important;
            }
            html.rl-left-panel-disabled #rl-left .b-content {
                min-width: 0 !important;
                width: 100% !important;
                opacity: 1 !important;
                overflow-x: hidden !important;
            }
            /* Na aba de configurações, remove o botão de retract (.toggleLeft) e mantém a sidebar aberta */
            #rl-left:has(#V-SettingsMenu:not([hidden])) .toggleLeft {
                display: none !important;
            }
            html.rl-left-panel-disabled #rl-left:has(#V-SettingsMenu:not([hidden])) {
                width: 200px !important;
                min-width: 155px !important;
            }
            html.rl-left-panel-disabled #rl-left:has(#V-SettingsMenu:not([hidden])) .hide-on-panel-disabled {
                display: block !important;
            }
            html.rl-left-panel-disabled #rl-left .b-folders li a {
                font-size: 0 !important;
                padding: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                height: 36px !important;
                border-radius: 6px !important;
            }
            html.rl-left-panel-disabled #rl-left .b-folders li a::before,
            html.rl-left-panel-disabled #rl-left .b-folders li a > i,
            html.rl-left-panel-disabled #rl-left .b-folders li a > svg,
            html.rl-left-panel-disabled #rl-left .b-folders li a > [class*="icon"]:not(.flag-icon) {
                font-size: 1.05rem !important;
                margin: 0 !important;
            }
            html.rl-left-panel-disabled #rl-left .b-folders li a .flag-icon,
            html.rl-left-panel-disabled #rl-left .b-folders li a .e-collapsed-sign,
            #V-MailFolderList .b-content > *:not(ul):not(#admin-quota-widget):not(.b-toolbar),
            #V-SystemDropDown .color-scheme-toggle,
            .b-folders li a .flag-icon,
            .b-folders-user li a .e-collapsed-sign:empty {
                display: none !important;
            }
            .b-folders li a.selectable.selected.admin-muted-selected {
                background-color: transparent !important;
                color: var(--folders-color) !important;
            }
            .b-folders li a.selectable.selected.admin-muted-selected:hover {
                background-color: var(--folders-hover-bg-color) !important;
                color: var(--folders-hover-color) !important;
            }
            /* Seção separada para pastas customizadas (ex.: spam) */
            .b-folders li a {
                padding-left: 10px !important;
            }
            .b-folders-user {
                margin-top: 12px !important;
                padding-top: 10px !important;
                border-top: 1px solid var(--border-color) !important;
            }
            .b-folders-user::before {
                content: 'PASTAS';
                display: block;
                padding: 0 10px 6px;
                font-size: 0.68rem;
                font-weight: 600;
                letter-spacing: 0.06em;
                color: ${isDark ? '#64748b' : '#94a3b8'};
            }
            html.rl-left-panel-disabled #rl-left .b-folders-user::before {
                display: none !important;
            }
            .b-folders-user li a {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                text-indent: 0 !important;
            }
            .b-folders-user li a::before {
                content: '';
                display: inline-block;
                flex-shrink: 0;
                width: 14px;
                height: 14px;
                background-color: currentColor;
                opacity: 0.78;
                -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3C/svg%3E") no-repeat center / contain;
                mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3C/svg%3E") no-repeat center / contain;
            }
            html.rl-left-panel-disabled #rl-left .b-folders-user li a::before {
                width: 16px !important;
                height: 16px !important;
            }
            html.rl-left-panel-disabled #rl-left .admin-quota-widget {
                justify-content: center !important;
                padding: 10px 0 4px !important;
                margin: 12px 6px 4px !important;
            }
            html.rl-left-panel-disabled #rl-left .admin-quota-track,
            html.rl-left-panel-disabled #rl-left .admin-quota-pct {
                display: none !important;
            }
            .b-folders .b-content .b-toolbar {
                padding: 10px 8px 4px !important;
                margin: 0 !important;
            }
            .b-folders .b-content .buttonCompose {
                width: 100% !important;
                max-width: none !important;
                min-height: 36px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
            }
            html.rl-left-panel-disabled #rl-left .b-folders .b-content .b-toolbar {
                padding: 8px 0 4px !important;
                display: flex !important;
                justify-content: center !important;
            }
            html.rl-left-panel-disabled #rl-left .b-folders .b-content .buttonCompose {
                width: 36px !important;
                height: 36px !important;
                padding: 0 !important;
            }
            .buttonCompose, .buttonLogin {
                background: #2563eb !important;
                border-color: #2563eb !important;
                color: #ffffff !important;
                font-weight: 500 !important;
                border-radius: 6px !important;
                box-shadow: none !important;
                transition: background-color 0.16s ease !important;
            }
            .buttonCompose:hover, .buttonLogin:hover {
                background: #1d4ed8 !important;
            }
            .btn {
                background-image: none !important;
                box-shadow: none !important;
                border-radius: 6px !important;
            }
            .b-folders .folder-item, .b-folders a {
                border-radius: 6px !important;
                transition: background-color 0.14s ease, color 0.14s ease !important;
            }
            .messageItem {
                background: transparent !important;
                border-bottom: 1px solid var(--border-color) !important;
                transition: background-color 0.12s ease !important;
            }
            .messageItem:hover {
                background: ${isDark ? 'rgba(255, 255, 255, 0.03)' : '#f8fafc'} !important;
            }
            .messageItem.selected {
                background: ${isDark ? 'rgba(37, 99, 235, 0.14)' : 'rgba(37, 99, 235, 0.08)'} !important;
                box-shadow: inset 3px 0 0 #2563eb !important;
            }
            .messageListItem .sizeParent {
                display: none !important;
            }
            .messageListItem {
                align-items: center !important;
            }
            .messageListItem .messageCheckbox {
                align-self: center !important;
                padding: 0 0.5em !important;
                display: inline-flex !important;
                align-items: center !important;
            }
            html.sm-msgView-side .messageListItem > div + div {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto auto auto !important;
                grid-template-rows: auto auto !important;
                align-items: center !important;
                column-gap: 8px !important;
                row-gap: 2px !important;
                padding: 8px 10px 8px 4px !important;
            }
            html.sm-msgView-side .messageListItem .senderParent {
                grid-column: 1 !important;
                grid-row: 1 !important;
                min-width: 0 !important;
            }
            html.sm-msgView-side .messageListItem .subjectParent {
                grid-column: 1 !important;
                grid-row: 2 !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            html.sm-msgView-side .messageListItem .attachmentParent {
                grid-column: 2 !important;
                grid-row: 1 / span 2 !important;
                align-self: center !important;
                margin: 0 !important;
            }
            html.sm-msgView-side .messageListItem time {
                grid-column: 3 !important;
                grid-row: 1 / span 2 !important;
                align-self: center !important;
                margin: 0 !important;
            }
            html.sm-msgView-side .messageListItem .flagParent {
                grid-column: 4 !important;
                grid-row: 1 / span 2 !important;
                align-self: center !important;
                margin: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                line-height: 1 !important;
            }
            .messageView .b-message-view-desc {
                color: ${isDark ? '#64748b' : '#94a3b8'} !important;
                font-size: 1rem !important;
                font-weight: 400 !important;
            }
            /* Barra superior do preview alinhada verticalmente à barra de pesquisa */
            #V-MailMessageView .admin-preview-bar {
                position: sticky !important;
                top: 0 !important;
                z-index: 5 !important;
                height: var(--admin-preview-bar-h, 44px) !important;
                min-height: var(--admin-preview-bar-h, 44px) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 12px !important;
                padding: 0 18px !important;
                box-sizing: border-box !important;
                background: var(--panel-bg-clr) !important;
                border-bottom: 1px solid var(--border-color) !important;
                font-family: var(--fontSans) !important;
                flex-shrink: 0 !important;
            }
            #V-MailMessageView .admin-preview-bar .admin-preview-title {
                display: flex !important;
                align-items: center !important;
                min-width: 0 !important;
                flex: 1 1 auto !important;
            }
            #V-MailMessageView .admin-preview-bar .admin-preview-actions {
                display: flex !important;
                align-items: center !important;
                gap: 4px !important;
                flex-shrink: 0 !important;
            }
            #V-MailMessageView .admin-preview-bar .admin-preview-tag-trigger {
                position: relative !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 28px !important;
                height: 28px !important;
                border-radius: 6px !important;
                cursor: pointer !important;
                opacity: 0.72 !important;
                transition: background-color 0.14s ease, opacity 0.14s ease !important;
            }
            #V-MailMessageView .admin-preview-bar .admin-preview-tag-trigger:hover {
                background: var(--folders-hover-bg-color) !important;
                opacity: 1 !important;
            }
            #V-MailMessageView .admin-preview-bar .admin-preview-tag-trigger .dropdown-menu {
                right: 0 !important;
                left: auto !important;
                z-index: 200 !important;
            }
            /* Visualização de e-mail: tipografia sans-serif, hierarquia limpa e respiro */
            #V-MailMessageView .messageView {
                font-family: var(--fontSans) !important;
                height: calc(100vh - 50px) !important;
                padding: 0 !important;
                box-sizing: border-box !important;
            }
            #V-MailMessageView .b-message {
                position: relative;
            }
            #V-MailMessageView .b-message > .messageItemHeader {
                padding: 18px 24px 10px !important;
                border-bottom: none !important;
                background: transparent !important;
                font-family: var(--fontSans) !important;
            }
            #V-MailMessageView .admin-preview-bar .subjectParent {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                min-width: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: var(--fontSans) !important;
            }
            #V-MailMessageView .admin-preview-bar .subjectParent .subject {
                font-family: var(--fontSans) !important;
                font-size: 1rem !important;
                font-weight: 600 !important;
                line-height: 1.3 !important;
                letter-spacing: -0.01em !important;
                color: var(--main-color) !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            #V-MailMessageView .admin-preview-bar .subjectParent .infoParent,
            #V-MailMessageView .admin-preview-bar .subjectParent .flagParent {
                flex-shrink: 0 !important;
                opacity: 0.72;
                transition: opacity 0.14s ease, color 0.14s ease;
            }
            #V-MailMessageView .admin-preview-bar .subjectParent .infoParent:hover,
            #V-MailMessageView .admin-preview-bar .subjectParent .flagParent:hover {
                opacity: 1;
            }
            #V-MailMessageView .admin-preview-bar .close {
                position: static !important;
                margin: 0 !important;
                width: 28px !important;
                height: 28px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 6px !important;
                opacity: 0.7 !important;
                transition: background-color 0.14s ease, opacity 0.14s ease !important;
            }
            #V-MailMessageView .admin-preview-bar .close:hover {
                background: var(--folders-hover-bg-color) !important;
                opacity: 1 !important;
            }
            #V-MailMessageView .admin-preview-bar .message-fixed-button-toolbar {
                position: static !important;
                top: auto !important;
                right: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
            }
            #V-MailMessageView .admin-preview-bar .message-fixed-button-toolbar .btn {
                width: 28px !important;
                height: 28px !important;
                padding: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 6px !important;
            }
            #V-MailMessageView .admin-preview-bar .message-fixed-button-toolbar .btn:hover {
                background: var(--folders-hover-bg-color) !important;
            }
            #V-MailMessageView .admin-preview-bar .message-fixed-button-toolbar .dropdown-menu {
                z-index: 200 !important;
            }
            #V-MailMessageView .admin-preview-bar .message-fixed-button-toolbar .btn.buttonEdit,
            #V-MailMessageView .message-fixed-button-toolbar .buttonEdit {
                display: none !important;
            }
            #V-MailMessageView .admin-reply-bar {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-top: 10px !important;
            }
            #V-MailMessageView .admin-reply-bar:not(:has(.btn:not([style*="display: none"]):not([style*="display:none"]))) {
                display: none !important;
            }
            #V-MailMessageView .admin-reply-bar > span {
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            #V-MailMessageView .admin-reply-bar .btn {
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                height: 28px !important;
                padding: 0 10px !important;
                border-radius: 6px !important;
                border: 1px solid var(--border-color) !important;
                background: transparent !important;
                box-shadow: none !important;
                color: var(--folders-color) !important;
                font-size: 0.8rem !important;
                line-height: 1 !important;
                text-decoration: none !important;
                transition: background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease !important;
            }
            #V-MailMessageView .admin-reply-bar .btn:hover {
                background: var(--folders-hover-bg-color) !important;
                color: var(--folders-hover-color) !important;
            }
            #V-MailMessageView .admin-reply-bar .buttonReply::after,
            #V-MailMessageView .admin-reply-bar .buttonReplyAll::after {
                font-family: var(--fontSans) !important;
                font-size: 0.8rem !important;
                font-weight: 500 !important;
            }
            #V-MailMessageView .admin-reply-bar .buttonReply::after {
                content: 'Responder';
            }
            #V-MailMessageView .admin-reply-bar .buttonReplyAll::after {
                content: 'Responder a todos';
            }
            #V-MailMessageView .b-message > .messageItemHeader .informationShort {
                display: flex !important;
                align-items: flex-start !important;
                flex-wrap: wrap !important;
                gap: 8px !important;
                font-family: var(--fontSans) !important;
                font-size: 0.88rem !important;
                line-height: 1.4 !important;
            }
            #V-MailMessageView .b-message > .messageItemHeader .from,
            #V-MailMessageView .b-message > .messageItemHeader .from * {
                font-family: var(--fontSans) !important;
            }
            #V-MailMessageView .b-message > .messageItemHeader .from {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 3px !important;
            }
            #V-MailMessageView .admin-sender-top {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            #V-MailMessageView .admin-sender-avatar {
                width: 26px;
                height: 26px;
                border-radius: 999px;
                background: ${isDark ? 'rgba(37, 99, 235, 0.22)' : 'rgba(37, 99, 235, 0.12)'};
                color: ${isDark ? '#93c5fd' : '#1d4ed8'};
                font-family: var(--fontSans);
                font-size: 0.78rem;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                text-transform: uppercase;
            }
            #V-MailMessageView .admin-sender-name {
                font-weight: 600;
                font-size: 0.92rem;
                color: var(--main-color);
            }
            #V-MailMessageView .admin-sender-email {
                font-size: 0.8rem;
                color: ${isDark ? '#94a3b8' : '#64748b'};
            }
            #V-MailMessageView .b-message > .messageItemHeader .from a {
                text-decoration: none !important;
                color: ${isDark ? '#93c5fd' : '#2563eb'} !important;
            }
            #V-MailMessageView .b-message > .messageItemHeader .from a:hover {
                text-decoration: underline !important;
            }
            #V-MailMessageView #messageItem > .messageItemHeader {
                padding: 0 24px 14px !important;
                border-bottom: 1px solid var(--border-color) !important;
                background: transparent !important;
                font-family: var(--fontSans) !important;
                font-size: 0.8rem !important;
                color: ${isDark ? '#94a3b8' : '#64748b'} !important;
            }
            #V-MailMessageView #messageItem > .messageItemHeader time.date {
                float: none !important;
                display: block !important;
                margin-bottom: 2px !important;
                font-size: 0.78rem !important;
                color: ${isDark ? '#94a3b8' : '#64748b'} !important;
            }
            #V-MailMessageView #messageItem .messageTags {
                display: flex !important;
                align-items: center !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
                margin-top: 6px !important;
                font-family: var(--fontSans) !important;
                font-size: 0.78rem !important;
            }
            #V-MailMessageView #messageItem .messageTags.admin-no-user-tags > span[data-i18n="MESSAGE/TAGS"],
            #V-MailMessageView #messageItem .messageTags.admin-no-user-tags {
                gap: 0 !important;
            }
            #V-MailMessageView #messageItem .messageAssignedTags > * {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px !important;
                border-radius: 999px !important;
                border: 1px solid var(--border-color) !important;
                background: ${isDark ? 'rgba(148, 163, 184, 0.1)' : '#f1f5f9'} !important;
                font-size: 0.74rem !important;
                line-height: 1.4 !important;
            }
            #V-MailMessageView #messageItem .bodyText {
                padding: 20px 24px 28px !important;
                font-family: var(--fontSans) !important;
                line-height: 1.65 !important;
            }
            #V-MailMessageView #messageItem .bodyText .plain,
            #V-MailMessageView #messageItem .bodyText pre {
                font-family: var(--fontSans) !important;
                white-space: pre-wrap !important;
                max-width: 72ch !important;
                font-size: 0.94rem !important;
                line-height: 1.65 !important;
            }
            input:focus, textarea:focus, select:focus {
                outline: none !important;
                border-color: var(--input-focus-border-clr) !important;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18) !important;
            }
            /* Modais sem animação visual, mantendo transitionend de opacity para o SnappyMail fechar o dialog */
            dialog,
            dialog.animate {
                transition: opacity 0.01s linear !important;
                transform: none !important;
                animation: none !important;
            }
            dialog::backdrop,
            .dialog-backdrop {
                transition: none !important;
                animation: none !important;
            }
            #V-PopupsCompose {
                width: min(760px, calc(100vw - 32px)) !important;
                max-width: 760px !important;
                height: min(640px, 86vh) !important;
                max-height: 86vh !important;
                margin: auto !important;
                border-radius: 10px !important;
                border: 1px solid var(--border-color) !important;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45) !important;
                overflow: hidden !important;
                font-family: var(--fontSans) !important;
            }
            #V-PopupsCompose header {
                background: var(--main-bg-color) !important;
                color: var(--main-color) !important;
                border-bottom: 1px solid var(--border-color) !important;
                padding: 10px 16px !important;
                font-family: var(--fontSans) !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            #V-PopupsCompose header .admin-compose-title {
                font-size: 0.92rem;
                font-weight: 600;
                color: var(--main-color);
            }
            #V-PopupsCompose header .pull-right {
                margin-left: auto !important;
                float: none !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
            }
            #V-PopupsCompose .modal-body {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                height: auto !important;
            }
            #V-PopupsCompose .admin-compose-footer {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: var(--main-bg-color);
                border-top: 1px solid var(--border-color);
                flex-shrink: 0;
                font-family: var(--fontSans);
            }
            #V-PopupsCompose .admin-compose-footer .saved-text {
                margin-left: auto !important;
                font-size: 0.78rem;
                color: ${isDark ? '#94a3b8' : '#64748b'};
            }
            #V-PopupsCompose header .btn,
            #V-PopupsCompose .admin-compose-footer .btn {
                font-family: var(--fontSans) !important;
                border-radius: 6px !important;
            }
            #V-PopupsCompose .admin-compose-footer .btn-success {
                background: #2563eb !important;
                border-color: #2563eb !important;
                color: #ffffff !important;
                font-weight: 500 !important;
            }
            #V-PopupsCompose .admin-compose-footer .btn-success:hover {
                background: #1d4ed8 !important;
                border-color: #1d4ed8 !important;
            }
            #V-PopupsCompose .b-header {
                padding: 10px 16px !important;
                background: var(--panel-bg-clr) !important;
                border-bottom: 1px solid var(--border-color) !important;
                font-family: var(--fontSans) !important;
            }
            #V-PopupsCompose .b-header td,
            #V-PopupsCompose .b-header label,
            #V-PopupsCompose .b-header input,
            #V-PopupsCompose .b-header .e-identity {
                font-family: var(--fontSans) !important;
                font-size: 0.88rem !important;
            }
            #V-PopupsCompose .b-header td:first-child {
                color: ${isDark ? '#94a3b8' : '#64748b'} !important;
                font-weight: 500 !important;
            }
            #V-PopupsCompose .b-header td > div input,
            #V-PopupsCompose .b-header td > div input:focus {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                outline: none !important;
            }
            #V-PopupsCompose .b-header td > div:focus-within {
                border-color: var(--input-focus-border-clr) !important;
                box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18) !important;
            }
            #V-PopupsCompose .b-header table tr:has([data-i18n="GLOBAL/FROM"]) {
                display: none !important;
            }
            #V-PopupsCompose .admin-hidden-row {
                display: none !important;
            }
            #V-PopupsCompose .squire-toolbar #composeUploadButton {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 0 5px !important;
            }
            #V-PopupsCompose .tabs,
            #V-PopupsCompose .tabs > label {
                font-family: var(--fontSans) !important;
            }
            #V-PopupsCompose .tabs > label[for="tab-body"],
            #V-PopupsCompose .tabs > label[for="tab-attachments"],
            #V-PopupsCompose .attachmentAreaParent {
                display: none !important;
            }
            #V-PopupsCompose .tabs > .tab-content:first-of-type {
                display: block !important;
            }
            #V-PopupsCompose .squire-toolbar {
                margin: 8px 14px 4px !important;
                padding: 3px 10px !important;
                border-radius: 999px !important;
                border: 1px solid var(--border-color) !important;
                background: ${isDark ? '#1e293b' : '#f1f5f9'} !important;
                display: flex !important;
                align-items: center !important;
                flex-wrap: nowrap !important;
                overflow: hidden !important;
                gap: 0 !important;
                min-height: 34px !important;
            }
            #V-PopupsCompose .squire-toolbar .btn-group {
                display: inline-flex !important;
                align-items: center !important;
                gap: 1px !important;
                flex-shrink: 0 !important;
                margin: 0 !important;
            }
            #V-PopupsCompose .squire-toolbar .btn-group:has(+ .btn-group) {
                border-right: 1px solid ${isDark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(15, 23, 42, 0.12)'} !important;
                padding-right: 6px !important;
                margin-right: 6px !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow {
                margin-left: auto !important;
                padding-left: 6px !important;
                border-left: 1px solid ${isDark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(15, 23, 42, 0.12)'} !important;
                display: inline-flex !important;
                align-items: center !important;
                flex-shrink: 0 !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow[hidden] {
                display: none !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 26px !important;
                height: 26px !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 999px !important;
                background: transparent !important;
                color: var(--folders-color) !important;
                cursor: pointer !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-btn:hover {
                background: ${isDark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(15, 23, 42, 0.08)'} !important;
                color: var(--folders-hover-color) !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-btn svg {
                width: 14px;
                height: 14px;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu {
                position: fixed;
                z-index: 3000;
                background: var(--dropdown-menu-bg-color);
                border: 1px solid var(--dropdown-menu-border-clr);
                border-radius: 10px;
                padding: 6px;
                box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 2px;
                min-width: 195px;
                max-width: 260px;
                max-height: 280px;
                overflow-y: auto;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu[hidden] {
                display: none !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu .btn-group {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 2px !important;
                width: 100% !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu .btn-group:has(+ .btn-group) {
                border-bottom: 1px solid var(--border-color) !important;
                padding-bottom: 4px !important;
                margin-bottom: 4px !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu button,
            #V-PopupsCompose .admin-toolbar-overflow-menu .btn {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                border-radius: 6px !important;
                color: var(--dropdown-menu-color) !important;
                width: 100% !important;
                height: 30px !important;
                padding: 0 10px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: 10px !important;
                text-align: left !important;
                font-size: 13px !important;
                line-height: 30px !important;
                cursor: pointer !important;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu button[data-admin-label]::after,
            #V-PopupsCompose .admin-toolbar-overflow-menu .btn[data-admin-label]::after {
                content: attr(data-admin-label);
                font-family: var(--fontSans);
                font-size: 0.82rem;
                font-weight: 400;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            #V-PopupsCompose .admin-toolbar-overflow-menu button:hover,
            #V-PopupsCompose .admin-toolbar-overflow-menu .btn:hover {
                background: ${isDark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(15, 23, 42, 0.08)'} !important;
                color: var(--folders-hover-color) !important;
            }
            #V-PopupsCompose .admin-align-btn {
                display: inline-flex !important;
                align-items: center !important;
                gap: 3px !important;
                padding: 0 6px !important;
            }
            #V-PopupsCompose .admin-align-menu {
                position: fixed;
                z-index: 3000;
                background: var(--dropdown-menu-bg-color);
                border: 1px solid var(--dropdown-menu-border-clr);
                border-radius: 10px;
                padding: 4px;
                box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            #V-PopupsCompose .admin-align-menu[hidden] {
                display: none !important;
            }
            #V-PopupsCompose .admin-align-option {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 34px;
                height: 30px;
                padding: 0;
                border: none;
                border-radius: 6px;
                background: transparent;
                color: var(--dropdown-menu-color);
                cursor: pointer;
            }
            #V-PopupsCompose .admin-align-option:hover,
            #V-PopupsCompose .admin-align-option.is-active {
                background: ${isDark ? 'rgba(148, 163, 184, 0.18)' : 'rgba(15, 23, 42, 0.08)'};
                color: var(--folders-hover-color);
            }
            #V-PopupsCompose > ul.attachmentList {
                list-style: none !important;
                margin: 0 !important;
                padding: 8px 16px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 6px !important;
                max-height: 140px !important;
                overflow-y: auto !important;
                background: var(--panel-bg-clr) !important;
                border-top: 1px solid var(--border-color) !important;
                flex-shrink: 0 !important;
            }
            #V-PopupsCompose > ul.attachmentList:empty {
                display: none !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentItem {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                width: min(380px, 100%) !important;
                margin: 0 !important;
                padding: 6px 10px !important;
                border-radius: 6px !important;
                border: 1px solid var(--border-color) !important;
                background: ${isDark ? 'rgba(148, 163, 184, 0.08)' : '#f8fafc'} !important;
                box-sizing: border-box !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentIcon {
                display: none !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentNameParent {
                display: flex !important;
                align-items: baseline !important;
                gap: 6px !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentName {
                font-family: var(--fontSans) !important;
                font-size: 0.84rem !important;
                font-weight: 600 !important;
                color: ${isDark ? '#60a5fa' : '#1d4ed8'} !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentSize {
                font-family: var(--fontSans) !important;
                font-size: 0.82rem !important;
                color: ${isDark ? '#94a3b8' : '#475569'} !important;
                flex-shrink: 0 !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentSize::before {
                content: '(';
            }
            #V-PopupsCompose > ul.attachmentList .attachmentSize::after {
                content: ')';
            }
            #V-PopupsCompose > ul.attachmentList .attachmentNameParent .close {
                order: 3 !important;
                margin: 0 0 0 auto !important;
                float: none !important;
                font-size: 16px !important;
                line-height: 1 !important;
                opacity: 0.65 !important;
                text-decoration: none !important;
                padding: 0 2px !important;
            }
            #V-PopupsCompose > ul.attachmentList .attachmentNameParent .close:hover {
                opacity: 1 !important;
            }
            #V-PopupsCompose .squire-toolbar button,
            #V-PopupsCompose .squire-toolbar .btn {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                border-radius: 6px !important;
                color: var(--folders-color) !important;
                height: 26px !important;
                min-width: 24px !important;
                padding: 0 5px !important;
                margin: 0 !important;
                font-size: 13px !important;
                line-height: 26px !important;
                flex-shrink: 0 !important;
                filter: grayscale(1) contrast(1.1);
            }
            #V-PopupsCompose .squire-toolbar button:hover,
            #V-PopupsCompose .squire-toolbar .btn:hover {
                background: ${isDark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(15, 23, 42, 0.08)'} !important;
                color: var(--folders-hover-color) !important;
            }
            #V-PopupsCompose .squire-toolbar select {
                width: auto !important;
                min-width: 0 !important;
                max-width: 78px;
                height: 26px !important;
                padding: 0 14px 0 6px !important;
                margin: 0 !important;
                border: none !important;
                border-radius: 6px !important;
                background: transparent !important;
                font-family: var(--fontSans) !important;
                font-size: 0.76rem !important;
                color: var(--folders-color) !important;
                color-scheme: ${isDark ? 'dark' : 'light'};
                flex-shrink: 0 !important;
            }
            #V-PopupsCompose .squire-toolbar select[data-action="fontSize"] {
                max-width: 56px;
            }
            #V-PopupsCompose .squire-wysiwyg {
                font-family: 'Nimbus Sans L', 'Liberation sans', 'Arial Unicode MS', Arial, Helvetica, sans-serif !important;
                font-size: 13px;
            }
            #V-PopupsCompose .squire-toolbar .btn-group:has(> select:not([data-action="fontFamily"]):not([data-action="fontSize"]):only-child),
            #V-PopupsCompose .squire-toolbar select:not([data-action="fontFamily"]):not([data-action="fontSize"]) {
                display: none !important;
            }
            select {
                color-scheme: ${isDark ? 'dark' : 'light'};
            }
            select option,
            select optgroup {
                background-color: ${isDark ? '#0f172a' : '#ffffff'} !important;
                color: ${isDark ? '#e2e8f0' : '#0f172a'} !important;
            }
            :focus-visible {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
            }
            @media screen and (max-width: 799px) {
                .admin-snappymail-brand {
                    width: auto !important;
                    padding: 0 10px !important;
                    gap: 10px !important;
                    border-bottom: none !important;
                }
                html.rl-left-panel-disabled #rl-left,
                html.rl-left-panel-disabled #rl-left:has(#V-SettingsMenu:not([hidden])) {
                    display: none !important;
                }
                #rl-left {
                    width: min(260px, 78vw) !important;
                    min-width: 0 !important;
                    background: var(--main-bg-color) !important;
                    border-right: 1px solid var(--border-color) !important;
                    z-index: 105 !important;
                }
                html.rl-left-panel-disabled #V-MailMessageView .top-toolbar {
                    margin-left: 0 !important;
                }
                #V-MailMessageList .messageList {
                    height: calc(100dvh - 50px) !important;
                }
                #V-MailMessageView {
                    padding-top: 50px !important;
                    box-sizing: border-box !important;
                }
                #V-MailMessageView .top-toolbar {
                    display: flex !important;
                    align-items: center !important;
                    gap: 4px !important;
                    padding: 6px 12px !important;
                    margin: 0 !important;
                    background: var(--panel-bg-clr) !important;
                    border-bottom: 1px solid var(--border-color) !important;
                }
                #V-MailMessageView .top-toolbar .btn-group {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 2px !important;
                    margin: 0 !important;
                }
                #V-MailMessageView .top-toolbar .btn-group:last-child {
                    margin-left: auto !important;
                }
                #V-MailMessageView .top-toolbar .btn {
                    background: transparent !important;
                    border-color: transparent !important;
                    box-shadow: none !important;
                    color: var(--folders-color) !important;
                    width: 32px !important;
                    height: 32px !important;
                    padding: 0 !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    border-radius: 6px !important;
                }
                #V-MailMessageView .top-toolbar .btn:hover {
                    background: var(--folders-hover-bg-color) !important;
                    color: var(--folders-hover-color) !important;
                }
                #V-MailMessageView .messageView {
                    height: calc(100dvh - 50px) !important;
                }
                #V-MailMessageView .admin-preview-bar {
                    padding: 0 12px !important;
                }
                #V-SettingsPane {
                    padding: 58px 14px 20px !important;
                    box-sizing: border-box !important;
                }
                #V-MailMessageView .b-message > .messageItemHeader {
                    padding: 14px 16px 10px !important;
                }
                #V-MailMessageView #messageItem > .messageItemHeader {
                    padding: 0 16px 12px !important;
                }
                #V-MailMessageView #messageItem .bodyText {
                    padding: 16px 16px 24px !important;
                }
                #V-PopupsCompose {
                    width: 100vw !important;
                    max-width: 100vw !important;
                    height: 100dvh !important;
                    max-height: 100dvh !important;
                    margin: 0 !important;
                    border-radius: 0 !important;
                    border: none !important;
                }
                #V-PopupsCompose .admin-compose-footer .btn .hide-mobile {
                    display: inline !important;
                }
                #V-PopupsCompose > ul.attachmentList {
                    padding: 8px 12px !important;
                }
                #V-PopupsCompose > ul.attachmentList .attachmentItem {
                    width: 100% !important;
                }
            }
        `;
    }

    function syncQuotaWidget(widget, quota) {
        const rawText = (quota.textContent || '').trim();
        const match = rawText.match(/(\d+(?:[.,]\d+)?)/);
        const pct = match ? Math.min(100, Math.max(0, parseFloat(match[1].replace(',', '.')))) : 0;

        const fill = widget.querySelector('.admin-quota-fill');
        const pctEl = widget.querySelector('.admin-quota-pct');
        const tipEl = widget.querySelector('.admin-quota-tooltip');

        const nextWidth = pct > 0 ? Math.max(pct, 3) + '%' : '0%';
        const nextBg = pct >= 90 ? '#ef4444' : (pct >= 75 ? '#f59e0b' : '#2563eb');
        const nextPct = rawText || (pct + '%');
        const rawTitle = (quota.getAttribute('title') || quota.dataset.originalTitle || '').trim();
        const sizes = rawTitle.match(/\d+(?:[.,]\d+)?\s*[KMGT]?i?B/gi);
        const nextTip = sizes && sizes.length >= 2
            ? `Disco: ${sizes[0]} / ${sizes[1]}`
            : `Disco: ${rawText || (pct + '%')}`;

        if (fill) {
            if (fill.style.width !== nextWidth) fill.style.width = nextWidth;
            if (fill.style.background !== nextBg) fill.style.background = nextBg;
        }
        if (pctEl && pctEl.textContent !== nextPct) {
            pctEl.textContent = nextPct;
        }
        if (tipEl && tipEl.textContent !== nextTip) {
            tipEl.textContent = nextTip;
        }
    }

    function syncFlaggedCategory(doc, systemList) {
        if (!systemList) return;
        const inboxLi = Array.from(systemList.children).find(
            (li) => li.id !== 'admin-flagged-folder-item' && li.querySelector('.flag-icon')
        );
        if (!inboxLi) return;
        let flaggedLi = doc.getElementById('admin-flagged-folder-item');
        if (!flaggedLi) {
            flaggedLi = doc.createElement('li');
            flaggedLi.id = 'admin-flagged-folder-item';
            flaggedLi.innerHTML = `<a href="#flagged" class="selectable" data-icon="☆" title="Favoritos"> Favoritos</a>`;
            flaggedLi.querySelector('a').addEventListener('click', (e) => {
                e.preventDefault();
                const flagIcon = inboxLi.querySelector('.flag-icon');
                if (flagIcon) flagIcon.click();
            });
        }
        if (inboxLi.nextElementSibling !== flaggedLi) {
            inboxLi.after(flaggedLi);
        }
        const isFlaggedView = /(?:^|[\/?&])flagged(?:[\/?&]|$)/i.test(doc.location.hash || '');
        const flaggedLink = flaggedLi.querySelector('a');
        const inboxLink = inboxLi.querySelector('a');
        if (flaggedLink) flaggedLink.classList.toggle('selected', isFlaggedView);
        if (inboxLink) inboxLink.classList.toggle('admin-muted-selected', isFlaggedView);
        if (!doc._adminFlaggedHashListener && doc.defaultView) {
            doc._adminFlaggedHashListener = true;
            doc.defaultView.addEventListener('hashchange', () => {
                syncFlaggedCategory(doc, doc.querySelector('#rl-left .b-folders-system'));
            });
        }
    }

    function ensureSidebarHeader(doc) {
        const app = doc.getElementById('rl-app');
        if (!app) return;
        let brand = doc.getElementById('admin-snappymail-brand');
        if (!brand) {
            brand = doc.createElement('div');
            brand.id = 'admin-snappymail-brand';
            brand.className = 'admin-snappymail-brand';
            brand.innerHTML = `
                <button type="button" class="admin-hamburger-btn" title="Recolher / expandir barra lateral" aria-label="Alternar barra lateral">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <button type="button" class="admin-back-btn" title="Voltar" aria-label="Voltar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <img src="/assets/images/rn-monogram.webp" alt="RN">
            `;
            brand.querySelector('.admin-hamburger-btn').addEventListener('click', () => {
                const orig = doc.querySelector('.b-footer .toggleLeft');
                if (orig) orig.click();
            });
            brand.querySelector('.admin-back-btn').addEventListener('click', () => {
                const back = doc.querySelector('#V-SettingsMenu a[data-i18n="GLOBAL/BACK"]');
                if (back) back.click();
            });
            app.prepend(brand);
        } else if (app.firstElementChild !== brand) {
            app.prepend(brand);
        }
        const composeText = doc.querySelector('#rl-left .buttonComposeText');
        if (composeText && composeText.textContent.trim() !== 'Escrever') {
            composeText.textContent = 'Escrever';
        }
        const composeBtn = doc.querySelector('#rl-left .buttonCompose');
        if (composeBtn && composeBtn.getAttribute('title') !== 'Escrever') {
            composeBtn.setAttribute('title', 'Escrever');
        }
        const composeToolbar = doc.querySelector('#rl-left #V-MailFolderList .b-toolbar');
        const systemList = doc.querySelector('#rl-left .b-folders-system');
        if (composeToolbar && systemList && systemList.nextElementSibling !== composeToolbar) {
            systemList.after(composeToolbar);
        }
        syncFlaggedCategory(doc, systemList);

        const msgActions = doc.querySelector('#V-MailMessageList .btn-toolbar');
        const searchBar = doc.querySelector('#V-MailMessageList .second-toolbar, #V-MailMessageList .b-list-toolbar');
        if (msgActions && searchBar) {
            msgActions.classList.add('admin-actions-bar');
            if (searchBar.nextElementSibling !== msgActions) {
                searchBar.after(msgActions);
            }
            const folderAddBtn = doc.querySelector('#V-MailFolderList .b-footer .icon-folder-add');
            if (folderAddBtn && folderAddBtn.parentElement !== msgActions) {
                const moreGroup = msgActions.querySelector('.btn-group:has(#more-list-dropdown-id)');
                if (moreGroup) {
                    msgActions.insertBefore(folderAddBtn, moreGroup);
                } else {
                    msgActions.appendChild(folderAddBtn);
                }
            }
        }

        const folderSettingsBtn = doc.querySelector('#V-MailFolderList .b-footer a[data-bind*="configureFolders"]');
        const sysToolbar = doc.querySelector('#V-SystemDropDown > .btn-toolbar');
        const accountDropdown = sysToolbar ? sysToolbar.querySelector('.btn-group.dropdown') : null;
        if (folderSettingsBtn && sysToolbar && accountDropdown && folderSettingsBtn.parentElement !== sysToolbar) {
            folderSettingsBtn.classList.add('admin-header-settings-btn');
            sysToolbar.insertBefore(folderSettingsBtn, accountDropdown);
        }
    }

    function pickSettingsIconSvg(labelText, href) {
        const key = (labelText + ' ' + href).toLowerCase();
        const wrap = (inner) => `<svg class="admin-settings-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${inner}</svg>`;
        if (/geral|general/.test(key)) {
            return wrap('<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>');
        }
        if (/conta|account|identit|perfil|profile/.test(key)) {
            return wrap('<circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/>');
        }
        if (/contato|contact/.test(key)) {
            return wrap('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>');
        }
        if (/pasta|folder/.test(key)) {
            return wrap('<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>');
        }
        if (/filtro|filter|sieve/.test(key)) {
            return wrap('<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>');
        }
        if (/tema|theme|apar|appear/.test(key)) {
            return wrap('<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.65-.75 1.65-1.69 0-.43-.18-.83-.44-1.12-.29-.29-.44-.65-.44-1.13a1.64 1.64 0 0 1 1.67-1.67h2c3.05 0 5.56-2.5 5.56-5.55C22 6.01 17.5 2 12 2Z"/>');
        }
        if (/segur|secur|2fa|totp|auth/.test(key)) {
            return wrap('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>');
        }
        if (/pgp|cript|encrypt|chave|key/.test(key)) {
            return wrap('<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>');
        }
        if (/sobre|about|info/.test(key)) {
            return wrap('<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>');
        }
        return wrap('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>');
    }

    function enhanceSettingsIcons(doc) {
        const links = doc.querySelectorAll('#V-SettingsMenu nav a');
        links.forEach((a) => {
            if (a.querySelector('.admin-settings-icon')) return;
            const label = (a.textContent || '').trim();
            const href = a.getAttribute('href') || a.getAttribute('data-target') || '';
            const holder = doc.createElement('span');
            holder.innerHTML = pickSettingsIconSvg(label, href);
            if (holder.firstElementChild) {
                a.prepend(holder.firstElementChild);
            }
        });
    }

    function moveQuotaToSidebar(doc) {
        const quota = doc.querySelector('.e-quota');
        const sidebarFolders = doc.querySelector('#rl-left .b-folders .b-content') || doc.querySelector('#rl-left .b-folders');
        if (!quota || !sidebarFolders) return;

        let widget = doc.getElementById('admin-quota-widget');
        if (!widget) {
            widget = doc.createElement('div');
            widget.id = 'admin-quota-widget';
            widget.className = 'admin-quota-widget';
            widget.innerHTML = `
                <svg class="admin-quota-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 13h16l-2-6H6l-2 6Z"></path>
                    <rect x="4" y="13" width="16" height="6" rx="1.5"></rect>
                    <circle cx="15.5" cy="16" r="0.8" fill="currentColor"></circle>
                    <circle cx="18" cy="16" r="0.8" fill="currentColor"></circle>
                </svg>
                <div class="admin-quota-track"><div class="admin-quota-fill"></div></div>
                <span class="admin-quota-pct">0%</span>
                <div class="admin-quota-tooltip">Armazenamento: 0%</div>
            `;
            sidebarFolders.appendChild(widget);
            const getFloatingTip = () => {
                let ft = doc.getElementById('admin-quota-floating-tooltip');
                if (!ft && doc.body) {
                    ft = doc.createElement('div');
                    ft.id = 'admin-quota-floating-tooltip';
                    ft.className = 'admin-quota-floating-tooltip';
                    doc.body.appendChild(ft);
                }
                return ft;
            };
            widget.addEventListener('mouseenter', () => {
                const tip = widget.querySelector('.admin-quota-tooltip');
                const ft = getFloatingTip();
                if (!tip || !ft) return;
                if (doc.documentElement.classList.contains('rl-left-panel-disabled')) {
                    const r = widget.getBoundingClientRect();
                    ft.textContent = tip.textContent || '';
                    ft.style.left = (r.right + 8) + 'px';
                    ft.style.top = (r.top + r.height / 2) + 'px';
                    ft.classList.add('visible');
                }
            });
            widget.addEventListener('mouseleave', () => {
                doc.getElementById('admin-quota-floating-tooltip')?.classList.remove('visible');
            });
        } else if (widget.parentElement !== sidebarFolders) {
            sidebarFolders.appendChild(widget);
        }

        if (quota.parentElement !== widget) {
            widget.appendChild(quota);
        }
        syncQuotaWidget(widget, quota);
    }

    function parseRgb(str) {
        const m = (str || '').match(/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\s*\)/i);
        if (!m) return null;
        return {
            r: Number(m[1]),
            g: Number(m[2]),
            b: Number(m[3]),
            a: m[4] === undefined ? 1 : Number(m[4])
        };
    }

    function luminance({ r, g, b }) {
        const toLin = (c) => {
            const s = c / 255;
            return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * toLin(r) + 0.7152 * toLin(g) + 0.0722 * toLin(b);
    }

    function contrastRatio(c1, c2) {
        const l1 = luminance(c1);
        const l2 = luminance(c2);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    function getEffectiveBg(el, stopAt, fallbackRgb) {
        let cur = el;
        while (cur && cur.nodeType === 1) {
            const bg = parseRgb(el.ownerDocument.defaultView.getComputedStyle(cur).backgroundColor);
            if (bg && bg.a >= 0.25) return bg;
            if (cur === stopAt) break;
            cur = cur.parentElement;
        }
        return fallbackRgb;
    }

    function enhanceComposeModal(doc) {
        const compose = doc.getElementById('V-PopupsCompose');
        if (!compose) return;
        const header = compose.querySelector('header');
        if (!header) return;

        let titleEl = header.querySelector('.admin-compose-title');
        if (!titleEl) {
            titleEl = doc.createElement('span');
            titleEl.className = 'admin-compose-title';
            titleEl.textContent = 'Nova mensagem';
            header.prepend(titleEl);
        }

        let footer = compose.querySelector('#admin-compose-footer');
        if (!footer) {
            footer = doc.createElement('footer');
            footer.id = 'admin-compose-footer';
            footer.className = 'admin-compose-footer g-ui-user-select-none';
            compose.appendChild(footer);
        }

        const sendBtn = header.querySelector('a[data-bind*="sendCommand"]');
        const saveBtn = header.querySelector('.button-save');
        const savedText = header.querySelector('.saved-text');
        if (sendBtn && sendBtn.parentElement !== footer) footer.appendChild(sendBtn);
        if (saveBtn && saveBtn.parentElement !== footer) footer.appendChild(saveBtn);
        if (savedText && savedText.parentElement !== footer) footer.appendChild(savedText);

        const attachmentList = compose.querySelector('ul.attachmentList');
        if (attachmentList && attachmentList.parentElement !== compose) {
            compose.insertBefore(attachmentList, footer);
        }

        compose.querySelectorAll('.squire-toolbar select[data-action="fontFamily"], .squire-toolbar select[data-action="fontSize"]').forEach((sel) => {
            const defOpt = sel.querySelector('option[value=""]');
            if (defOpt) {
                const wasDefault = sel.value === '';
                defOpt.remove();
                if (wasDefault) {
                    const fallback = sel.dataset.action === 'fontSize'
                        ? (sel.querySelector('option[value="13px"]') || sel.options[0])
                        : (sel.querySelector('option[value*="Arial"]') || sel.options[0]);
                    if (fallback) {
                        sel.value = fallback.value;
                        if (!sel.dataset.adminDefaultApplied) {
                            sel.dataset.adminDefaultApplied = '1';
                            sel.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                }
            }
        });

        const wysiwyg = compose.querySelector('.squire-wysiwyg');
        const tabBody = compose.querySelector('#tab-body');
        const tabAttachments = compose.querySelector('#tab-attachments');
        if (tabBody && tabAttachments) {
            const keepBodyTab = () => {
                if (tabAttachments.checked) tabBody.click();
            };
            if (!tabAttachments.dataset.adminDisabled) {
                tabAttachments.dataset.adminDisabled = '1';
                tabAttachments.disabled = true;
                tabAttachments.addEventListener('change', keepBodyTab);
            }
            keepBodyTab();
        }
        const fontSel = compose.querySelector('.squire-toolbar select[data-action="fontFamily"]');
        const sizeSel = compose.querySelector('.squire-toolbar select[data-action="fontSize"]');
        if (wysiwyg && !wysiwyg.dataset.adminFontSync) {
            wysiwyg.dataset.adminFontSync = '1';
            const syncToolbarFromSelection = () => {
                const selection = doc.getSelection();
                if (!selection || !selection.anchorNode || !wysiwyg.contains(selection.anchorNode)) return;
                const startEl = selection.anchorNode.nodeType === 1 ? selection.anchorNode : selection.anchorNode.parentElement;
                if (!startEl) return;
                const fontNode = startEl.closest('[style*="font-family"]');
                if (fontSel && fontNode && wysiwyg.contains(fontNode)) {
                    const ff = (fontNode.style.fontFamily || '').toLowerCase();
                    const matchOpt = Array.from(fontSel.options).find((o) => ff.includes((o.textContent || '').trim().toLowerCase()));
                    if (matchOpt && fontSel.value !== matchOpt.value) fontSel.value = matchOpt.value;
                }
                const sizeNode = startEl.closest('[style*="font-size"]');
                if (sizeSel && sizeNode && wysiwyg.contains(sizeNode)) {
                    const fsOpt = sizeSel.querySelector(`option[value="${sizeNode.style.fontSize}"]`);
                    if (fsOpt && sizeSel.value !== fsOpt.value) sizeSel.value = fsOpt.value;
                }
            };
            ['mouseup', 'keyup'].forEach((evt) => wysiwyg.addEventListener(evt, syncToolbarFromSelection));
        }

        const toolbar = compose.querySelector('.squire-toolbar');
        if (toolbar && !toolbar.dataset.adminOverflowInit) {
            toolbar.dataset.adminOverflowInit = '1';

            const alignIcons = {
                justifyLeft: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="10" x2="15" y2="10"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="3" y1="18" x2="15" y2="18"/></svg>',
                justifyCenter: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="10" x2="18" y2="10"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="6" y1="18" x2="18" y2="18"/></svg>',
                justifyRight: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="10" x2="21" y2="10"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="9" y1="18" x2="21" y2="18"/></svg>'
            };
            const caretSvg = '<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="6,9 18,9 12,16"/></svg>';

            const alignGroup = doc.createElement('div');
            alignGroup.className = 'btn-group admin-align-group';
            const alignBtn = doc.createElement('button');
            alignBtn.type = 'button';
            alignBtn.className = 'btn admin-align-btn';
            alignBtn.title = 'Alinhamento de texto';
            alignBtn.setAttribute('aria-label', 'Alinhamento de texto');
            alignBtn.innerHTML = alignIcons.justifyLeft + caretSvg;
            alignGroup.appendChild(alignBtn);

            const blockGroup = toolbar.querySelector('#squire-toolgroup-block');
            if (blockGroup) toolbar.insertBefore(alignGroup, blockGroup);
            else toolbar.appendChild(alignGroup);

            const alignMenu = doc.createElement('div');
            alignMenu.className = 'admin-align-menu';
            alignMenu.hidden = true;
            [
                ['justifyLeft', 'Alinhar à esquerda'],
                ['justifyCenter', 'Centralizar'],
                ['justifyRight', 'Alinhar à direita']
            ].forEach(([cmd, label]) => {
                const opt = doc.createElement('button');
                opt.type = 'button';
                opt.className = 'admin-align-option' + (cmd === 'justifyLeft' ? ' is-active' : '');
                opt.title = label;
                opt.setAttribute('aria-label', label);
                opt.dataset.alignCmd = cmd;
                opt.innerHTML = alignIcons[cmd];
                opt.addEventListener('mousedown', (e) => e.preventDefault());
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (wysiwyg && doc.activeElement !== wysiwyg) wysiwyg.focus();
                    doc.execCommand(cmd, false, null);
                    alignBtn.innerHTML = alignIcons[cmd] + caretSvg;
                    alignMenu.querySelectorAll('.admin-align-option').forEach((b) =>
                        b.classList.toggle('is-active', b.dataset.alignCmd === cmd)
                    );
                    alignMenu.hidden = true;
                });
                alignMenu.appendChild(opt);
            });
            compose.appendChild(alignMenu);

            alignBtn.addEventListener('mousedown', (e) => e.preventDefault());
            alignBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!alignMenu.hidden) {
                    alignMenu.hidden = true;
                    return;
                }
                const rect = alignBtn.getBoundingClientRect();
                alignMenu.style.top = (rect.bottom + 6) + 'px';
                alignMenu.style.left = rect.left + 'px';
                alignMenu.hidden = false;
            });
            doc.addEventListener('click', (e) => {
                if (!alignMenu.hidden && !alignMenu.contains(e.target) && !alignBtn.contains(e.target)) {
                    alignMenu.hidden = true;
                }
            });

            const targetsGroup = toolbar.querySelector('#squire-toolgroup-targets');
            const attachBtn = compose.querySelector('#composeUploadButton');
            if (targetsGroup && attachBtn && attachBtn.parentElement !== targetsGroup) {
                const attachRow = attachBtn.closest('.b-header > div');
                attachBtn.style.paddingLeft = '';
                attachBtn.style.paddingRight = '';
                attachBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
                attachBtn.dataset.adminLabel = attachBtn.getAttribute('title') || 'Anexar arquivos';
                targetsGroup.appendChild(attachBtn);
                if (attachRow) {
                    const hasVisibleBtn = Array.from(attachRow.querySelectorAll('a.btn')).some(
                        (b) => doc.defaultView.getComputedStyle(b).display !== 'none'
                    );
                    if (!hasVisibleBtn) attachRow.classList.add('admin-hidden-row');
                }
            }

            toolbar.querySelectorAll('button, .btn').forEach((btn) => {
                if (!btn.dataset.adminLabel) {
                    const rawTitle = (btn.getAttribute('title') || '').replace(/\s*\([^)]+\)\s*$/, '').trim();
                    const fallback = (btn.dataset.action || '').replace(/([A-Z])/g, ' $1').trim();
                    const label = rawTitle || fallback;
                    if (label) btn.dataset.adminLabel = label;
                }
            });

            const overflowWrap = doc.createElement('div');
            overflowWrap.className = 'admin-toolbar-overflow';
            overflowWrap.hidden = true;

            const overflowBtn = doc.createElement('button');
            overflowBtn.type = 'button';
            overflowBtn.className = 'admin-toolbar-overflow-btn';
            overflowBtn.title = 'Mais opções';
            overflowBtn.setAttribute('aria-label', 'Mais opções');
            overflowBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
            overflowWrap.appendChild(overflowBtn);
            toolbar.appendChild(overflowWrap);

            const overflowMenu = doc.createElement('div');
            overflowMenu.className = 'admin-toolbar-overflow-menu';
            overflowMenu.hidden = true;
            compose.appendChild(overflowMenu);

            ['#squire-toolgroup-dir', '#squire-toolgroup-colors'].forEach((sel) => {
                const grp = toolbar.querySelector(sel);
                if (grp) {
                    grp.classList.add('admin-pinned-overflow');
                    overflowMenu.appendChild(grp);
                }
            });

            const subBtn = toolbar.querySelector('button[data-action="sub"]');
            const supBtn = toolbar.querySelector('button[data-action="sup"]');
            if (subBtn || supBtn) {
                const scriptGroup = doc.createElement('div');
                scriptGroup.className = 'btn-group admin-pinned-overflow';
                if (subBtn) scriptGroup.appendChild(subBtn);
                if (supBtn) scriptGroup.appendChild(supBtn);
                overflowMenu.appendChild(scriptGroup);
            }

            const closeMenu = () => { overflowMenu.hidden = true; };

            overflowBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!overflowMenu.hidden) {
                    closeMenu();
                    return;
                }
                const rect = overflowBtn.getBoundingClientRect();
                overflowMenu.style.top = (rect.bottom + 6) + 'px';
                overflowMenu.style.right = Math.max(8, doc.defaultView.innerWidth - rect.right) + 'px';
                overflowMenu.hidden = false;
            });

            overflowMenu.addEventListener('click', (e) => {
                if (e.target && e.target.closest('button, .btn')) closeMenu();
            });
            doc.addEventListener('click', (e) => {
                if (!overflowMenu.hidden && !overflowMenu.contains(e.target) && !overflowBtn.contains(e.target)) {
                    closeMenu();
                }
            });

            const relayoutToolbar = () => {
                closeMenu();
                Array.from(overflowMenu.children)
                    .filter((g) => !g.classList.contains('admin-pinned-overflow'))
                    .forEach((g) => toolbar.insertBefore(g, overflowWrap));
                overflowWrap.hidden = !overflowMenu.children.length;

                const visibleGroups = Array.from(toolbar.children).filter((el) =>
                    el.classList.contains('btn-group') && doc.defaultView.getComputedStyle(el).display !== 'none'
                );
                if (toolbar.scrollWidth <= toolbar.clientWidth + 1 || visibleGroups.length <= 1) return;

                overflowWrap.hidden = false;
                for (let i = visibleGroups.length - 1; i >= 1; i--) {
                    if (toolbar.scrollWidth <= toolbar.clientWidth + 1) break;
                    overflowMenu.prepend(visibleGroups[i]);
                }
            };

            if (doc.defaultView.ResizeObserver) {
                new doc.defaultView.ResizeObserver(relayoutToolbar).observe(toolbar);
            }
            requestAnimationFrame(relayoutToolbar);
        }
    }

    function syncPreviewHeaderBar(doc, msgView) {
        if (!msgView) return;

        const applySearchBarHeight = () => {
            const searchBar = doc.querySelector('#V-MailMessageList .second-toolbar, #V-MailMessageList .b-list-toolbar');
            if (!searchBar) return;
            const h = searchBar.getBoundingClientRect().height;
            if (h > 20) {
                msgView.style.setProperty('--admin-preview-bar-h', h.toFixed(2) + 'px');
            }
            if (!searchBar.dataset.adminPreviewResizeBound && doc.defaultView && typeof doc.defaultView.ResizeObserver === 'function') {
                searchBar.dataset.adminPreviewResizeBound = '1';
                const ro = new doc.defaultView.ResizeObserver(() => {
                    const nextH = searchBar.getBoundingClientRect().height;
                    if (nextH > 20) {
                        msgView.style.setProperty('--admin-preview-bar-h', nextH.toFixed(2) + 'px');
                    }
                });
                ro.observe(searchBar);
            }
        };

        applySearchBarHeight();
        if (doc.defaultView && typeof doc.defaultView.requestAnimationFrame === 'function') {
            doc.defaultView.requestAnimationFrame(applySearchBarHeight);
        }

        const messageViewEl = msgView.querySelector('.messageView');
        if (!messageViewEl) return;

        // Remove barra antiga caso tenha sido inserida fora de .messageView
        const staleOuterBar = msgView.querySelector(':scope > #admin-preview-bar');
        if (staleOuterBar) staleOuterBar.remove();

        let bar = messageViewEl.querySelector(':scope > #admin-preview-bar');
        if (!bar) {
            bar = doc.createElement('div');
            bar.id = 'admin-preview-bar';
            bar.className = 'admin-preview-bar';

            const titleWrap = doc.createElement('div');
            titleWrap.className = 'admin-preview-title';
            const actionsWrap = doc.createElement('div');
            actionsWrap.className = 'admin-preview-actions';
            bar.appendChild(titleWrap);
            bar.appendChild(actionsWrap);

            messageViewEl.prepend(bar);
        } else if (messageViewEl.firstElementChild !== bar) {
            messageViewEl.prepend(bar);
        }

        const titleWrap = bar.querySelector('.admin-preview-title');
        const actionsWrap = bar.querySelector('.admin-preview-actions');
        const bMsg = msgView.querySelector('.b-message');
        const hasVisibleMessage = Boolean(bMsg && bMsg.style.display !== 'none');

        if (!hasVisibleMessage) {
            if (titleWrap && titleWrap.childNodes.length) titleWrap.textContent = '';
            if (actionsWrap && actionsWrap.childNodes.length) {
                // Não remove fixedToolbar se ainda estiver dentro de bMsg, só limpa elementos soltos
                Array.from(actionsWrap.children).forEach((el) => {
                    if (!el.classList.contains('message-fixed-button-toolbar')) el.remove();
                });
            }
            return;
        }

        const subjectParent = bMsg.querySelector('.messageItemHeader .subjectParent');
        const fixedToolbar = msgView.querySelector('.message-fixed-button-toolbar');
        const closeBtn = (subjectParent && subjectParent.querySelector('.close'))
            || bMsg.querySelector('.messageItemHeader .close')
            || actionsWrap.querySelector(':scope > .close');
        const tagsWrap = bMsg.querySelector('.messageTags');
        const tagTrigger = (tagsWrap
            ? Array.from(tagsWrap.children).find((el) => !el.matches('[data-i18n="MESSAGE/TAGS"], .messageAssignedTags'))
            : null) || actionsWrap.querySelector(':scope > .admin-preview-tag-trigger');

        if (subjectParent) {
            Array.from(titleWrap.children).forEach((el) => {
                if (el !== subjectParent) el.remove();
            });
            if (subjectParent.parentElement !== titleWrap) {
                titleWrap.appendChild(subjectParent);
            }
        }

        // Aguarda o i18nUpdate do Knockout traduzir os itens de data-i18n antes de mover para fora de .b-message
        const fixedToolbarReady = fixedToolbar && (
            fixedToolbar.parentElement === actionsWrap ||
            Array.from(fixedToolbar.querySelectorAll('a[data-i18n]')).some((a) => a.textContent.trim().length > 0)
        );
        if (fixedToolbar && !fixedToolbarReady) {
            setTimeout(() => syncPreviewHeaderBar(doc, msgView), 50);
        }

        // Remove botões antigos antes de anexar os da mensagem atual (fixedToolbar é reutilizada pelo Knockout)
        Array.from(actionsWrap.children).forEach((el) => {
            if (el !== fixedToolbar && el !== tagTrigger && el !== closeBtn) {
                el.remove();
            }
        });

        if (fixedToolbar && fixedToolbarReady && fixedToolbar.parentElement !== actionsWrap) {
            actionsWrap.appendChild(fixedToolbar);
        }
        if (tagTrigger) {
            tagTrigger.classList.add('admin-preview-tag-trigger');
            if (tagTrigger.parentElement !== actionsWrap) {
                if (fixedToolbar && fixedToolbar.parentElement === actionsWrap) {
                    actionsWrap.insertBefore(tagTrigger, fixedToolbar);
                } else {
                    actionsWrap.appendChild(tagTrigger);
                }
            }
        }
        if (closeBtn && closeBtn.parentElement !== actionsWrap) {
            actionsWrap.appendChild(closeBtn);
        }

        // Move botões Responder / Responder a todos para baixo do cabeçalho do remetente/destinatário
        const msgItemHeader = bMsg.querySelector('#messageItem > .messageItemHeader');
        if (msgItemHeader) {
            let replyBar = msgItemHeader.querySelector(':scope > .admin-reply-bar');
            const replyWrap = (fixedToolbar && fixedToolbar.querySelector('span:has(.buttonReply, .buttonReplyAll)'))
                || (replyBar && replyBar.querySelector('span:has(.buttonReply, .buttonReplyAll)'));
            if (replyWrap) {
                if (!replyBar) {
                    replyBar = doc.createElement('div');
                    replyBar.className = 'admin-reply-bar';
                    msgItemHeader.appendChild(replyBar);
                }
                if (replyWrap.parentElement !== replyBar) {
                    replyBar.textContent = '';
                    replyBar.appendChild(replyWrap);
                }
            }
        }
    }

    function enhanceMessageView(doc) {
        const msgView = doc.getElementById('V-MailMessageView');
        if (msgView) {
            syncPreviewHeaderBar(doc, msgView);
            const accountEmail = (doc.querySelector('#top-system-dropdown-id .accountPlace')?.getAttribute('title') || '').trim().toLowerCase();
            const senderEl = msgView.querySelector('.b-message > .messageItemHeader .from a, .b-message > .messageItemHeader .from, .informationFull tr:first-child td:last-child');
            const senderText = (senderEl ? senderEl.textContent : '').toLowerCase();
            const isOwnEmail = Boolean(accountEmail && senderText.includes(accountEmail));

            const editAsNewLi = msgView.querySelector('a[data-bind*="editAsNewCommand"]')?.closest('li');
            if (editAsNewLi) {
                const nextDisplay = isOwnEmail ? '' : 'none';
                if (editAsNewLi.style.display !== nextDisplay) editAsNewLi.style.display = nextDisplay;
            }
        }

        const infoRow = doc.querySelector('#V-MailMessageView .b-message > .messageItemHeader .informationShort');
        if (infoRow) {
            const staleAvatar = infoRow.querySelector(':scope > .admin-sender-avatar');
            if (staleAvatar) staleAvatar.remove();

            const fromEl = infoRow.querySelector('.from');
            if (fromEl && !fromEl.querySelector('.admin-sender-top')) {
                const rawText = (fromEl.textContent || '').trim();
                const linkEl = fromEl.querySelector('a');
                const emailText = linkEl ? linkEl.textContent.trim() : '';
                const ltIdx = rawText.indexOf('<');
                let displayName = ltIdx > 0 ? rawText.slice(0, ltIdx).trim() : rawText;
                displayName = displayName.replace(/^["']+|["']+$/g, '').trim();
                if (!displayName && emailText) {
                    displayName = emailText.split('@')[0];
                }

                const initialMatch = displayName.match(/[A-Za-zÀ-ÖØ-öø-ÿ0-9]/);
                const initial = initialMatch ? initialMatch[0].toUpperCase() : '?';

                const topRow = doc.createElement('div');
                topRow.className = 'admin-sender-top';

                const avatar = doc.createElement('span');
                avatar.className = 'admin-sender-avatar';
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = initial;

                const nameEl = doc.createElement('span');
                nameEl.className = 'admin-sender-name';
                nameEl.textContent = displayName || emailText;

                topRow.appendChild(avatar);
                topRow.appendChild(nameEl);

                let emailRow = null;
                if (emailText && emailText !== displayName) {
                    emailRow = doc.createElement('div');
                    emailRow.className = 'admin-sender-email';
                    emailRow.appendChild(linkEl || doc.createTextNode(emailText));
                }

                fromEl.textContent = '';
                fromEl.appendChild(topRow);
                if (emailRow) fromEl.appendChild(emailRow);
            }
        }

        const tagsWrap = doc.querySelector('#V-MailMessageView .messageTags');
        if (tagsWrap) {
            const assigned = tagsWrap.querySelector('.messageAssignedTags');
            let visibleCount = 0;
            if (assigned) {
                Array.from(assigned.children).forEach((tagEl) => {
                    const txt = (tagEl.textContent || '').trim();
                    if (txt.startsWith('$')) {
                        if (tagEl.style.display !== 'none') tagEl.style.display = 'none';
                    } else if (txt) {
                        visibleCount++;
                    }
                });
            }
            tagsWrap.classList.toggle('admin-no-user-tags', visibleCount === 0);
            tagsWrap.style.display = visibleCount === 0 ? 'none' : '';
            const labelEl = tagsWrap.querySelector('[data-i18n="MESSAGE/TAGS"]');
            if (labelEl) {
                labelEl.style.display = visibleCount === 0 ? 'none' : '';
            }
            // Oculta o ":" solto fora do span quando não há marcadores de usuário
            Array.from(tagsWrap.childNodes).forEach((node) => {
                if (node.nodeType === 3 && node.textContent.trim() === ':') {
                    node.textContent = visibleCount === 0 ? '' : ':';
                }
            });
        }
    }

    function fixEmailContrast(doc, isDark) {
        const win = doc.defaultView;
        if (!win) return;
        const containers = doc.querySelectorAll('.b-text-part, .b-message .b-view-content');
        const darkFallback = { r: 17, g: 24, b: 39, a: 1 };

        containers.forEach((root) => {
            const nodes = [root, ...root.querySelectorAll('*')];
            nodes.forEach((el) => {
                const tag = el.tagName;
                if (!tag || tag === 'SCRIPT' || tag === 'STYLE' || tag === 'IMG' || tag === 'SVG' || tag === 'PATH') return;

                if (el.dataset.origColor === undefined) {
                    el.dataset.origColor = el.style.getPropertyValue('color') || '';
                    el.dataset.origColorPri = el.style.getPropertyPriority('color') || '';
                }

                if (!isDark) {
                    if (el.dataset.origColor) {
                        el.style.setProperty('color', el.dataset.origColor, el.dataset.origColorPri);
                    } else {
                        el.style.removeProperty('color');
                    }
                    return;
                }

                const fg = parseRgb(win.getComputedStyle(el).color);
                if (!fg || fg.a < 0.1) return;

                const bg = getEffectiveBg(el, root, darkFallback);
                if (contrastRatio(fg, bg) >= 4.5) return;

                const bgIsDark = luminance(bg) < 0.25;
                if (bgIsDark) {
                    const isLink = tag === 'A' || !!el.closest('a');
                    const isBlueish = fg.b > fg.r + 30 && fg.b >= fg.g;
                    el.style.setProperty('color', (isLink || isBlueish) ? '#93c5fd' : '#e2e8f0', 'important');
                } else {
                    el.style.setProperty('color', '#0f172a', 'important');
                }
            });
        });
    }

    function applyBranding(doc) {
        if (doc.title && /snappymail/i.test(doc.title)) {
            doc.title = doc.title.replace(/snappymail/gi, 'RNMail');
        }
        const loadingDesc = doc.getElementById('rl-loading-desc');
        if (loadingDesc && !loadingDesc.querySelector('img.admin-rnmail-splash-logo')) {
            loadingDesc.innerHTML = '<img src="/assets/images/rn-monogram.webp" alt="RNMail" class="admin-rnmail-splash-logo" style="width: 72px; height: 72px; object-fit: contain; display: block; margin: 0 auto;">';
        }
        const loginDesc = doc.querySelector('.LoginView .descWrapper');
        if (loginDesc && loginDesc.textContent.trim() !== 'RNMail') {
            loginDesc.textContent = 'RNMail';
        }
    }

    function applyThemeToIframe() {
        try {
            const doc = frame.contentDocument;
            if (!doc || !doc.head) return;

            if (!doc.getElementById('admin-snappymail-fonts')) {
                const fontLink = doc.createElement('link');
                fontLink.id = 'admin-snappymail-fonts';
                fontLink.rel = 'stylesheet';
                fontLink.href = 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Poppins:wght@400;500;600&display=swap';
                doc.head.appendChild(fontLink);
            }

            const isDark = (document.documentElement.getAttribute('data-theme') || 'dark') === 'dark';
            let styleEl = doc.getElementById('admin-snappymail-theme');
            if (!styleEl) {
                styleEl = doc.createElement('style');
                styleEl.id = 'admin-snappymail-theme';
                doc.head.appendChild(styleEl);
            }
            styleEl.textContent = buildThemeCss(isDark);

            ensureSidebarHeader(doc);
            moveQuotaToSidebar(doc);
            enhanceSettingsIcons(doc);
            enhanceMessageView(doc);
            enhanceComposeModal(doc);
            applyBranding(doc);
            fixEmailContrast(doc, isDark);
            if (!doc._adminNoDblClick) {
                doc._adminNoDblClick = true;
                doc.addEventListener('dblclick', (e) => {
                    if (e.target && e.target.closest && e.target.closest('.messageItem, #V-MailMessageList, #V-MailMessageView')) {
                        e.stopImmediatePropagation();
                    }
                }, true);
            }
            if (!doc._adminLogoutHandler) {
                doc._adminLogoutHandler = true;
                doc.addEventListener('click', async (e) => {
                    const logoutBtn = e.target && e.target.closest && e.target.closest('#V-SystemDropDown .dropdown-menu a[data-i18n="GLOBAL/LOGOUT"]');
                    if (!logoutBtn) return;
                    const accountLinks = Array.from(doc.querySelectorAll('#V-SystemDropDown .dropdown-menu a.email-title'));
                    const activeAdditional = accountLinks.slice(1).find((a) => a.getAttribute('data-icon') === '✔');
                    if (!activeAdditional) return;
                    const email = (activeAdditional.getAttribute('title') || activeAdditional.textContent || '').trim();
                    if (!email) return;
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    try {
                        const appRes = await fetch('/webmail/?/AppData/', { credentials: 'same-origin' });
                        const appData = await appRes.json();
                        const token = appData?.System?.token || '';
                        const res = await fetch('/webmail/?/Json/', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                ...(token ? { 'X-SM-Token': token } : {}),
                            },
                            body: new URLSearchParams({ Action: 'AccountDelete', emailToDelete: email, XToken: token }),
                        });
                        const json = await res.json();
                        if (json && json.Result) {
                            doc.location.hash = '';
                            doc.location.reload();
                            return;
                        }
                    } catch (err) {
                        console.error('Erro ao sair da conta adicional:', err);
                    }
                }, true);
            }
            doc.documentElement.classList.remove('rl-fullscreen');
            if (!doc.body) {
                if ((applyThemeToIframe._bodyRetries || 0) < 40) {
                    applyThemeToIframe._bodyRetries = (applyThemeToIframe._bodyRetries || 0) + 1;
                    setTimeout(applyThemeToIframe, 50);
                }
                return;
            }
            applyThemeToIframe._bodyRetries = 0;
            checkWebmailRendered(doc);
            if (!doc._adminQuotaObserver) {
                let scheduled = false;
                doc._adminQuotaObserver = new MutationObserver((mutations) => {
                    const onlyOwnWidget = mutations.every((m) =>
                        m.target && m.target.closest && m.target.closest('#admin-quota-widget') && !m.target.classList.contains('e-quota')
                    );
                    if (onlyOwnWidget || scheduled) return;
                    scheduled = true;
                    requestAnimationFrame(() => {
                        scheduled = false;
                        const darkNow = (document.documentElement.getAttribute('data-theme') || 'dark') === 'dark';
                        ensureSidebarHeader(doc);
                        moveQuotaToSidebar(doc);
                        enhanceSettingsIcons(doc);
                        enhanceMessageView(doc);
                        enhanceComposeModal(doc);
                        applyBranding(doc);
                        fixEmailContrast(doc, darkNow);
                        checkWebmailRendered(doc);
                    });
                });
                doc._adminQuotaObserver.observe(doc.body, { childList: true, subtree: true });
            }
        } catch (_) {
            // Ignora se o iframe ainda não estiver no mesmo domínio
        }
    }

    const fallbackBanner = document.getElementById('email-fallback-banner');
    const reloadBtn = document.getElementById('btn-reload-webmail');
    let watchdogTimer = null;

    function isWebmailRendered(doc) {
        if (!doc || !doc.body) return false;
        const loading = doc.getElementById('rl-loading');
        const loadingVisible = loading && doc.defaultView && doc.defaultView.getComputedStyle(loading).display !== 'none';
        if (loadingVisible) return false;
        return Boolean(doc.querySelector('.LoginView, #V-MailFolderList, #V-SettingsMenu, #rl-app .b-content'));
    }

    function checkWebmailRendered(doc) {
        if (isWebmailRendered(doc)) {
            clearTimeout(watchdogTimer);
            if (fallbackBanner) fallbackBanner.hidden = true;
        }
    }

    function startWatchdog() {
        clearTimeout(watchdogTimer);
        if (fallbackBanner) fallbackBanner.hidden = true;
        watchdogTimer = setTimeout(() => {
            try {
                const doc = frame.contentDocument;
                if (!isWebmailRendered(doc) && fallbackBanner) {
                    fallbackBanner.hidden = false;
                }
            } catch (_) {
                if (fallbackBanner) fallbackBanner.hidden = false;
            }
        }, 9000);
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', () => {
            startWatchdog();
            frame.src = '/webmail/?_=' + Date.now();
        });
    }

    frame.addEventListener('load', () => {
        applyThemeToIframe();
    });

    startWatchdog();

    // Se o iframe já terminou de carregar (cache) antes do listener ser registrado
    try {
        const doc = frame.contentDocument;
        if (doc && doc.readyState === 'complete' && doc.location && doc.location.href !== 'about:blank') {
            applyThemeToIframe();
        }
    } catch (_) {}

    const observer = new MutationObserver(applyThemeToIframe);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
