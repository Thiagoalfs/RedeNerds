            </main>
        </div>
    </div>

    <!-- MODAL ALTERAR PRÓPRIA SENHA -->
    <div class="modal fade" id="modalAlterarPropriaSenha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formAlterarPropriaSenha">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2 text-primary"></i>Alterar Minha Senha</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="fbAlterarPropriaSenha" class="alert d-none small"></div>

                        <div class="mb-3">
                            <label for="propriaSenhaAtual" class="form-label fw-semibold">Senha Atual</label>
                            <input type="password" class="form-control" id="propriaSenhaAtual" name="senha_atual" required autocomplete="current-password">
                        </div>

                        <div class="mb-3">
                            <label for="propriaNovaSenha" class="form-label fw-semibold">Nova Senha</label>
                            <input type="password" class="form-control" id="propriaNovaSenha" name="nova_senha" minlength="8" required autocomplete="new-password" placeholder="Mín. 8 caracteres, 1 número e 1 especial">
                            <div class="d-grid gap-1 mt-2 small text-muted" id="propriaPwRules">
                                <div class="d-flex align-items-center gap-2" data-rule="len"><i class="fa-regular fa-circle"></i><span>Mínimo de 8 caracteres</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="num"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 número (0–9)</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="sym"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 caractere especial (!@#$...)</span></div>
                                <div class="d-flex align-items-center gap-2" data-rule="match"><i class="fa-regular fa-circle"></i><span>As duas senhas conferem</span></div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="propriaConfirmarSenha" class="form-label fw-semibold">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="propriaConfirmarSenha" name="confirmar_senha" minlength="8" required autocomplete="new-password">
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

        const formPwd = document.getElementById('formAlterarPropriaSenha');
        const p1 = document.getElementById('propriaNovaSenha');
        const p2 = document.getElementById('propriaConfirmarSenha');
        const btnPwd = document.getElementById('btnSalvarPropriaSenha');
        const fbPwd = document.getElementById('fbAlterarPropriaSenha');

        const validarPropriaSenha = () => {
            if (!p1 || !p2 || !btnPwd) return false;
            const v1 = p1.value;
            const v2 = p2.value;
            const checks = {
                len: v1.length >= 8,
                num: /[0-9]/.test(v1),
                sym: /[^A-Za-z0-9]/.test(v1),
                match: v1.length > 0 && v1 === v2
            };
            Object.entries(checks).forEach(([k, ok]) => {
                const row = document.querySelector(`#propriaPwRules [data-rule="${k}"]`);
                if (!row) return;
                row.classList.toggle('text-success', ok);
                row.classList.toggle('fw-semibold', ok);
                row.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
            });
            const ok = Object.values(checks).every(Boolean);
            btnPwd.disabled = !ok;
            return ok;
        };

        if (p1 && p2) {
            p1.addEventListener('input', validarPropriaSenha);
            p2.addEventListener('input', validarPropriaSenha);
        }

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
                    const data = await res.json();
                    fbPwd.textContent = data.success ? data.mensagem : (data.erro || 'Erro ao alterar senha.');
                    fbPwd.className = 'alert small ' + (data.success ? 'alert-success' : 'alert-danger');
                    if (data.success) {
                        formPwd.reset();
                        validarPropriaSenha();
                    } else {
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