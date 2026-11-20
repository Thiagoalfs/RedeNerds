            </main>
        </div>
    </div>

    <!-- MODAL ALTERAR PRÓPRIA SENHA -->
    <div class="modal fade" id="modalAlterarPropriaSenha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formAlterarPropriaSenha">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Alterar Minha Senha</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="fbAlterarPropriaSenha" class="alert d-none small"></div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="propriaSenhaAtual" class="form-label fw-semibold mb-0">Senha Atual</label>
                                <a href="/admin/esqueci_senha.php" class="small text-decoration-none" style="color: var(--color-primary);">Esqueci minha senha</a>
                            </div>
                            <div class="input-group">
                                <input type="password" class="form-control" id="propriaSenhaAtual" name="senha_atual" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-pwd="propriaSenhaAtual" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha atual"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="propriaNovaSenha" class="form-label fw-semibold">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="propriaNovaSenha" name="nova_senha" minlength="8" required autocomplete="new-password" placeholder="Mín. 8 caracteres, 1 número e 1 especial">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-pwd="propriaNovaSenha" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar nova senha"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <div class="d-grid gap-1 mt-2 small text-muted" id="propriaPwRules">
                                <div class="d-flex align-items-center gap-2" data-rule="len"><i class="fa-regular fa-circle"></i><span>Mínimo de 8 caracteres</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="num"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 número (0–9)</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="sym"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 caractere especial (!@#$...)</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="match"><i class="fa-regular fa-circle"></i><span>As duas senhas conferem</span></div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="propriaConfirmarSenha" class="form-label fw-semibold">Confirmar Nova Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="propriaConfirmarSenha" name="confirmar_senha" minlength="8" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-pwd="propriaConfirmarSenha" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar confirmação de senha"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSalvarPropriaSenha" disabled>
                            <i class="fa-solid fa-check me-1"></i> Salvar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL COMPARTILHADO DE CONFIRMAÇÃO -->
    <div class="modal fade" id="modalConfirmacaoAdmin" tabindex="-1" aria-labelledby="modalConfirmacaoAdminTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalConfirmacaoAdminTitulo">Confirmar Ação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <p class="mb-0" id="modalConfirmacaoAdminMensagem"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" id="modalConfirmacaoAdminBtn">
                        <i class="fa-solid fa-trash me-1"></i> <span id="modalConfirmacaoAdminBtnTexto">Excluir</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- BOOTSTRAP BUNDLE JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ASYNC ADMIN JS -->
    <script src="/admin/js/admin_async.js?v=<?php echo file_exists(__DIR__ . '/../js/admin_async.js') ? filemtime(__DIR__ . '/../js/admin_async.js') : '2'; ?>"></script>

    <!-- SCRIPTS DE INTERAÇÃO DO PAINEL -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const btnToggle = document.getElementById('btn-toggle-sidebar');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (btnToggle && sidebar && overlay) {
            btnToggle.addEventListener('click', () => {
                const aberto = sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
                btnToggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                btnToggle.setAttribute('aria-expanded', 'false');
            });
        }

        window.AdminSenhaUI = {
            validarForte(inputEl, rulesContainer, submitBtn, confirmEl = null) {
                if (!inputEl || !submitBtn) return true;
                const v = inputEl.value;
                const checks = {
                    len: v.length >= 8,
                    num: /[0-9]/.test(v),
                    sym: /[^A-Za-z0-9]/.test(v),
                };
                if (confirmEl) {
                    checks.match = v.length > 0 && v === confirmEl.value;
                }
                if (rulesContainer) {
                    Object.entries(checks).forEach(([k, ok]) => {
                        const row = rulesContainer.querySelector(`[data-rule="${k}"]`);
                        if (!row) return;
                        row.classList.toggle('text-success', ok);
                        row.classList.toggle('fw-semibold', ok);
                        const icon = row.querySelector('i');
                        if (icon) icon.className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
                    });
                }
                const valido = Object.values(checks).every(Boolean);
                submitBtn.disabled = !valido;
                return valido;
            },
            resetarVisibilidade(modalEl) {
                if (!modalEl) return;
                modalEl.querySelectorAll('[data-toggle-pwd]').forEach((btn) => {
                    const inp = document.getElementById(btn.dataset.togglePwd);
                    if (inp) inp.type = 'password';
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-eye';
                });
            }
        };

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-toggle-pwd]');
            if (!btn) return;
            const inp = document.getElementById(btn.dataset.togglePwd);
            if (!inp) return;
            const mostrar = inp.type === 'password';
            inp.type = mostrar ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) icon.className = mostrar ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });

        const modalPwdEl = document.getElementById('modalAlterarPropriaSenha');
        const formPwd = document.getElementById('formAlterarPropriaSenha');
        const p1 = document.getElementById('propriaNovaSenha');
        const p2 = document.getElementById('propriaConfirmarSenha');
        const btnPwd = document.getElementById('btnSalvarPropriaSenha');
        const fbPwd = document.getElementById('fbAlterarPropriaSenha');
        const rulesPwd = document.getElementById('propriaPwRules');

        const validarPropriaSenha = () => window.AdminSenhaUI.validarForte(p1, rulesPwd, btnPwd, p2);

        if (p1 && p2) {
            p1.addEventListener('input', validarPropriaSenha);
            p2.addEventListener('input', validarPropriaSenha);
        }

        modalPwdEl?.addEventListener('hidden.bs.modal', () => {
            formPwd?.reset();
            if (fbPwd) fbPwd.className = 'alert d-none small';
            window.AdminSenhaUI.resetarVisibilidade(modalPwdEl);
            validarPropriaSenha();
        });

        if (formPwd) {
            formPwd.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!validarPropriaSenha()) return;
                btnPwd.disabled = true;
                fbPwd.className = 'alert d-none small';
                try {
                    const fd = new FormData(formPwd);
                    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                    const res = await fetch('/admin/api/conta/alterar_senha.php', { method: 'POST', body: fd });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        bootstrap.Modal.getInstance(modalPwdEl)?.hide();
                        window.AdminToast?.success(data.mensagem || 'Senha alterada com sucesso!');
                    } else {
                        fbPwd.textContent = data.erro || `Erro ao alterar senha (HTTP ${res.status}).`;
                        fbPwd.className = 'alert small alert-danger';
                        btnPwd.disabled = false;
                    }
                } catch (err) {
                    fbPwd.textContent = 'Erro de conexão ao alterar senha.';
                    fbPwd.className = 'alert small alert-danger';
                    btnPwd.disabled = false;
                }
            });
        }
    });
    </script>
</body>
</html>