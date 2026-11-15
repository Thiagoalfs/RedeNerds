<?php
$paginaAtiva = 'usuarios';
$tituloPagina = 'Usuários';
require_once __DIR__ . "/../includes/admin_header.php";

$atorId = (int)($_SESSION['usuario_id'] ?? 0);
$atorNivel = $_SESSION['cargo_nivel'] ?? null;

$usuarios = [];
$membrosDisponiveis = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $usuarios = $pdo->query("
            SELECT u.id, u.usuario, u.email, u.email_recuperacao, e.nick, e.cargo, ec.cor AS cargo_cor, ec.ordem AS cargo_ordem
            FROM usuarios u
            LEFT JOIN equipe e ON (u.equipe_id = e.id OR (u.equipe_id IS NULL AND LOWER(e.nick) COLLATE utf8mb4_unicode_ci = LOWER(u.usuario) COLLATE utf8mb4_unicode_ci))
            LEFT JOIN equipe_cargos ec ON ec.nome = e.cargo
            ORDER BY COALESCE(ec.ordem, 999) ASC, u.usuario ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $todosMembros = $pdo->query("
            SELECT e.id, e.nick, e.cargo
            FROM equipe e
            LEFT JOIN usuarios u ON (u.equipe_id = e.id OR (u.equipe_id IS NULL AND LOWER(u.usuario) COLLATE utf8mb4_unicode_ci = LOWER(e.nick) COLLATE utf8mb4_unicode_ci))
            LEFT JOIN equipe_cargos ec ON ec.nome = e.cargo
            WHERE u.id IS NULL
            ORDER BY COALESCE(ec.ordem, 999) ASC, e.nick ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todosMembros as $m) {
            if (podeCriarUsuarioParaCargo($atorNivel, normalizarCargoAdmin($m['cargo']))) {
                $membrosDisponiveis[] = $m;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar usuários: " . $e->getMessage());
    }
}

$erroEmailsDa = null;
$emailsDirectAdmin = listarEmailsDirectAdmin($erroEmailsDa);
$emailsVinculados = [];
foreach ($usuarios as $u) {
    if (!empty($u['email'])) {
        $emailsVinculados[mb_strtolower((string)$u['email'], 'UTF-8')] = true;
    }
}
$emailsOrfaos = array_values(array_filter(
    $emailsDirectAdmin,
    static fn(string $em): bool => !isset($emailsVinculados[$em])
));
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold">Usuários do Painel</h2>
        <p class="text-muted small mb-0">Contas vinculadas à tabela <strong>equipe</strong>. Permissões e hierarquia seguem o cargo atual do membro.</p>
    </div>
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnAbrirNovoUsuario" <?php echo empty($membrosDisponiveis) ? 'disabled title="Nenhum membro elegível sem conta"' : ''; ?>>
        <i class="fa-solid fa-user-plus"></i>
        <span>Novo Usuário</span>
    </button>
</div>

<div class="card admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 52px;"></th>
                        <th>Usuário (Nick)</th>
                        <th>E-Mail da Empresa</th>
                        <th>Cargo</th>
                        <th class="text-end" style="width: 110px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                            <?php
                            $nickExibir = $u['nick'] ?: $u['usuario'];
                            $alvoNivel = normalizarCargoAdmin($u['cargo'] ?? null);
                            $podeEditar = podeEditarUsuario($atorNivel, $atorId, $alvoNivel, (int)$u['id']);
                            $podeExcluir = podeDeletarUsuario($atorNivel, $atorId, $alvoNivel, (int)$u['id']);
                            $corCargo = !empty($u['cargo_cor']) ? $u['cargo_cor'] : '#64748b';
                            ?>
                            <tr id="usuario-row-<?php echo (int)$u['id']; ?>">
                                <td>
                                    <img class="rounded border" src="https://mc-heads.net/avatar/<?php echo urlencode($nickExibir); ?>" width="34" height="34" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve'">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ((int)$u['id'] === $atorId): ?>
                                        <span class="badge bg-primary-subtle text-primary ms-1">Você</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['email'])): ?>
                                        <span class="font-monospace small"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($u['email_recuperacao'])): ?>
                                        <div class="text-muted small font-monospace" title="E-mail de recuperação">
                                            <i class="fa-solid fa-life-ring me-1"></i><?php echo htmlspecialchars($u['email_recuperacao'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['cargo'])): ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($corCargo, ENT_QUOTES, 'UTF-8'); ?>22; color: <?php echo htmlspecialchars($corCargo, ENT_QUOTES, 'UTF-8'); ?>; border: 1px solid <?php echo htmlspecialchars($corCargo, ENT_QUOTES, 'UTF-8'); ?>55;">
                                            <?php echo htmlspecialchars($u['cargo'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sem vínculo na equipe</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <?php if ($podeEditar): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-primary btn-abrir-editar-usuario"
                                                    data-id="<?php echo (int)$u['id']; ?>"
                                                    data-nick="<?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-email="<?php echo htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-email-recuperacao="<?php echo htmlspecialchars((string)($u['email_recuperacao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="Editar e-mail / redefinir senha"
                                                    aria-label="Editar usuário <?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        <?php elseif ((int)$u['id'] === $atorId): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalAlterarPropriaSenha"
                                                    title="Alterar minha senha"
                                                    aria-label="Alterar minha senha">
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($podeExcluir): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    data-action="async-delete"
                                                    data-url="/admin/api/usuarios/deletar.php"
                                                    data-id="<?php echo (int)$u['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-confirm="Excluir a conta de <?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?>?"
                                                    title="Excluir usuário"
                                                    aria-label="Excluir usuário <?php echo htmlspecialchars($nickExibir, ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!$podeEditar && !$podeExcluir && (int)$u['id'] !== $atorId): ?>
                                            <span class="text-muted small" title="Protegido pela hierarquia"><i class="fa-solid fa-lock"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- E-MAILS ÓRFÃOS -->
<div class="card admin-card mt-4">
    <div class="card-header py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-bottom: 1px solid var(--border-color);">
        <div>
            <h3 class="h6 mb-0 fw-bold d-flex align-items-center gap-2" style="color: var(--text-main);">
                <i class="fa-solid fa-envelope-open-text text-warning"></i>
                <span>E-mails Órfãos</span>
            </h3>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($erroEmailsDa !== null): ?>
            <div class="p-3 small text-warning">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                Não foi possível listar as caixas de e-mail do servidor (<?php echo htmlspecialchars($erroEmailsDa, ENT_QUOTES, 'UTF-8'); ?>).
            </div>
        <?php elseif (empty($emailsOrfaos)): ?>
            <div class="text-center text-muted small py-4">Nenhum e-mail órfão encontrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-admin align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Endereço de E-Mail</th>
                            <th class="text-end" style="min-width: 168px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailsOrfaos as $emailOrfao): ?>
                            <tr>
                                <td><span class="font-monospace small text-break"><?php echo htmlspecialchars($emailOrfao, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-primary btn-editar-orfao"
                                                data-email="<?php echo htmlspecialchars($emailOrfao, ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Editar senha"
                                                aria-label="Editar senha">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-atribuir-orfao"
                                                data-email="<?php echo htmlspecialchars($emailOrfao, ENT_QUOTES, 'UTF-8'); ?>"
                                                aria-label="Atribuir Usuário"
                                                <?php echo empty($membrosDisponiveis) ? 'disabled title="Nenhum membro elegível disponível"' : 'title="Atribuir Usuário"'; ?>>
                                            <i class="fa-solid fa-user-plus"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm btn-danger btn-deletar-orfao"
                                                data-email="<?php echo htmlspecialchars($emailOrfao, ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Deletar e-mail"
                                                aria-label="Deletar e-mail">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL CRIAR USUÁRIO -->
<div class="modal fade" id="modalCriarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formCriarUsuario">
                <input type="hidden" id="criarVincularExistente" name="vincular_existente" value="">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModalCriarUsuario"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Criar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="erroCriarUsuario" class="alert alert-danger d-none small"></div>

                    <div class="mb-3">
                        <label for="selectEquipeId" class="form-label fw-semibold">Membro da Equipe</label>
                        <select class="form-select" id="selectEquipeId" name="equipe_id" required>
                            <option value="">Selecione um membro...</option>
                            <?php foreach ($membrosDisponiveis as $m): ?>
                                <option value="<?php echo (int)$m['id']; ?>">
                                    <?php echo htmlspecialchars($m['nick'] . ' (' . $m['cargo'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Apenas membros da equipe com cargo permitido que ainda não possuem conta.</div>
                    </div>

                    <div class="mb-3">
                        <label for="inputUsuarioEmail" class="form-label fw-semibold">E-Mail da Empresa</label>
                        <input type="email" class="form-control" id="inputUsuarioEmail" name="email" required placeholder="nome@redenerds.com.br" autocomplete="off">
                        <div class="form-text">Permite logar usando o e-mail e ativa o login automático no RNMail.</div>
                    </div>

                    <div class="mb-3">
                        <label for="inputUsuarioEmailRec" class="form-label fw-semibold">E-Mail de Recuperação <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="email" class="form-control" id="inputUsuarioEmailRec" name="email_recuperacao" placeholder="pessoal@gmail.com" autocomplete="off">
                        <div class="form-text">Se preenchido, links de redefinição de senha serão enviados para este e-mail.</div>
                    </div>

                    <div class="mb-0">
                        <label for="inputUsuarioSenha" class="form-label fw-semibold">Senha</label>
                        <input type="password" class="form-control" id="inputUsuarioSenha" name="senha" minlength="8" required placeholder="Mín. 8 caracteres, 1 número e 1 especial" autocomplete="new-password">
                        <div class="d-grid gap-1 mt-2 small text-muted" id="criarPwRules">
                            <div class="d-flex align-items-center gap-2" data-rule="len"><i class="fa-regular fa-circle"></i><span>Mínimo de 8 caracteres</span></div>
                            <div class="d-flex align-items-center gap-2" data-rule="num"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 número (0–9)</span></div>
                            <div class="d-flex align-items-center gap-2" data-rule="sym"><i class="fa-regular fa-circle"></i><span>Pelo menos 1 caractere especial (!@#$...)</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarUsuario" disabled>
                        <i class="fa-solid fa-check me-1"></i> Criar Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR USUÁRIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEditarUsuario">
                <input type="hidden" id="editUsuarioId" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Editar Usuário: <span id="editUsuarioNick"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="feedbackEditarUsuario" class="alert d-none small"></div>

                    <div class="mb-3">
                        <label for="editUsuarioEmail" class="form-label fw-semibold">E-Mail da Empresa</label>
                        <input type="email" class="form-control" id="editUsuarioEmail" name="email" required placeholder="nome@redenerds.com.br" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label for="editUsuarioEmailRec" class="form-label fw-semibold">E-Mail de Recuperação <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="email" class="form-control" id="editUsuarioEmailRec" name="email_recuperacao" placeholder="pessoal@gmail.com" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label for="editUsuarioNovaSenha" class="form-label fw-semibold">Nova Senha <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="password" class="form-control" id="editUsuarioNovaSenha" name="nova_senha" placeholder="Deixe em branco para manter a senha atual" autocomplete="new-password">
                        <div class="form-text">Preencha para alterar a senha do usuário e do e-mail diretamente, sem validação adicional.</div>
                    </div>

                    <div class="border-top pt-3 mt-3" id="blocoResetEmailUsuario">
                        <label class="form-label fw-semibold d-block mb-1">Redefinição de Senha</label>
                        <p class="text-muted small mb-2">Envia um link de uso único (válido por 1 hora) para o e-mail cadastrado.</p>
                        <button type="button" class="btn btn-outline-warning btn-sm d-inline-flex align-items-center gap-2" id="btnEnviarResetSenha">
                            <i class="fa-solid fa-envelope"></i>
                            <span>Enviar e-mail de redefinição de senha</span>
                        </button>
                    </div>

                    <div class="border-top pt-3 mt-3 d-none" id="blocoTrocarPropriaSenha">
                        <label class="form-label fw-semibold d-block mb-1">Minha Senha</label>
                        <p class="text-muted small mb-2">Altere sua própria senha diretamente.</p>
                        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2" id="btnAbrirPropriaSenhaDoEdit">
                            <i class="fa-solid fa-key"></i>
                            <span>Alterar minha senha</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarEdicaoUsuario">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR E-MAIL ÓRFÃO -->
<div class="modal fade" id="modalEditarOrfao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEditarOrfao">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2 text-primary"></i>Editar Senha do E-Mail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="feedbackEditarOrfao" class="alert d-none small"></div>
                    <div class="mb-3">
                        <label for="editOrfaoEmail" class="form-label fw-semibold">E-Mail</label>
                        <input type="email" class="form-control font-monospace" id="editOrfaoEmail" name="email" readonly>
                    </div>
                    <div class="mb-0">
                        <label for="editOrfaoNovaSenha" class="form-label fw-semibold">Nova Senha</label>
                        <input type="password" class="form-control" id="editOrfaoNovaSenha" name="nova_senha" required placeholder="Digite a nova senha" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarOrfao">
                        <i class="fa-solid fa-check me-1"></i> Salvar Senha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.getElementById('formCriarUsuario');
    const inputSenha = document.getElementById('inputUsuarioSenha');
    const btnCriar = document.getElementById('btnSalvarUsuario');

    const validarSenhaCriar = () => {
        if (!inputSenha || !btnCriar) return true;
        const v = inputSenha.value;
        const checks = {
            len: v.length >= 8,
            num: /[0-9]/.test(v),
            sym: /[^A-Za-z0-9]/.test(v)
        };
        Object.entries(checks).forEach(([k, ok]) => {
            const row = document.querySelector(`#criarPwRules [data-rule="${k}"]`);
            if (!row) return;
            row.classList.toggle('text-success', ok);
            row.classList.toggle('fw-semibold', ok);
            row.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
        });
        const valido = Object.values(checks).every(Boolean);
        btnCriar.disabled = !valido;
        return valido;
    };

    const modalCriarEl = document.getElementById('modalCriarUsuario');
    const tituloCriar = document.getElementById('tituloModalCriarUsuario');
    const inputVincularExistente = document.getElementById('criarVincularExistente');
    const inputEmailCriar = document.getElementById('inputUsuarioEmail');

    document.getElementById('btnAbrirNovoUsuario')?.addEventListener('click', () => {
        if (form) form.reset();
        if (inputVincularExistente) inputVincularExistente.value = '';
        if (inputEmailCriar) inputEmailCriar.readOnly = false;
        if (tituloCriar) tituloCriar.innerHTML = '<i class="fa-solid fa-user-plus me-2 text-primary"></i>Criar Usuário';
        validarSenhaCriar();
        bootstrap.Modal.getOrCreateInstance(modalCriarEl).show();
    });

    document.querySelectorAll('.btn-atribuir-orfao').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (form) form.reset();
            if (inputVincularExistente) inputVincularExistente.value = '1';
            if (inputEmailCriar) {
                inputEmailCriar.value = btn.dataset.email || '';
                inputEmailCriar.readOnly = true;
            }
            if (tituloCriar) tituloCriar.innerHTML = '<i class="fa-solid fa-link me-2 text-primary"></i>Atribuir Usuário ao E-Mail';
            validarSenhaCriar();
            bootstrap.Modal.getOrCreateInstance(modalCriarEl).show();
        });
    });

    if (inputSenha) {
        inputSenha.addEventListener('input', validarSenhaCriar);
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errEl = document.getElementById('erroCriarUsuario');
            if (!validarSenhaCriar()) {
                errEl.textContent = 'A senha precisa ter no mínimo 8 caracteres, 1 número e 1 caractere especial.';
                errEl.classList.remove('d-none');
                return;
            }
            errEl.classList.add('d-none');
            btnCriar.disabled = true;

            try {
                const fd = new FormData(form);
                fd.append('csrf_token', csrfToken);
                const res = await fetch('/admin/api/usuarios/criar.php', { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    errEl.textContent = data.erro || `Erro ao criar usuário (HTTP ${res.status}).`;
                    errEl.classList.remove('d-none');
                    btnCriar.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (err) {
                errEl.textContent = 'Erro de conexão ao criar usuário.';
                errEl.classList.remove('d-none');
                btnCriar.disabled = false;
            }
        });
    }

    const modalEditEl = document.getElementById('modalEditarUsuario');
    const formEdit = document.getElementById('formEditarUsuario');
    const fbEdit = document.getElementById('feedbackEditarUsuario');
    const btnReset = document.getElementById('btnEnviarResetSenha');
    const editId = document.getElementById('editUsuarioId');
    const editNick = document.getElementById('editUsuarioNick');
    const editEmail = document.getElementById('editUsuarioEmail');
    const editEmailRec = document.getElementById('editUsuarioEmailRec');
    const editNovaSenha = document.getElementById('editUsuarioNovaSenha');
    const blocoReset = document.getElementById('blocoResetEmailUsuario');
    const blocoPropriaSenha = document.getElementById('blocoTrocarPropriaSenha');
    const btnAbrirPropriaSenha = document.getElementById('btnAbrirPropriaSenhaDoEdit');
    const atorId = <?php echo (int)$atorId; ?>;

    const mostrarFbEdit = (msg, ok) => {
        fbEdit.textContent = msg;
        fbEdit.className = 'alert small ' + (ok ? 'alert-success' : 'alert-danger');
    };

    document.querySelectorAll('.btn-abrir-editar-usuario').forEach((btn) => {
        btn.addEventListener('click', () => {
            const ehProprio = Number(btn.dataset.id) === atorId;
            editId.value = btn.dataset.id || '';
            editNick.textContent = btn.dataset.nick || '';
            editEmail.value = btn.dataset.email || '';
            if (editEmailRec) editEmailRec.value = btn.dataset.emailRecuperacao || '';
            if (editNovaSenha) editNovaSenha.value = '';
            btnReset.disabled = !(btn.dataset.email || '').trim() && !(btn.dataset.emailRecuperacao || '').trim();
            blocoReset?.classList.toggle('d-none', ehProprio);
            blocoPropriaSenha?.classList.toggle('d-none', !ehProprio);
            fbEdit.className = 'alert d-none small';
            bootstrap.Modal.getOrCreateInstance(modalEditEl).show();
        });
    });

    if (btnAbrirPropriaSenha) {
        btnAbrirPropriaSenha.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(modalEditEl).hide();
            const modalPropria = document.getElementById('modalAlterarPropriaSenha');
            if (modalPropria) {
                bootstrap.Modal.getOrCreateInstance(modalPropria).show();
            }
        });
    }

    if (formEdit) {
        formEdit.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnSave = document.getElementById('btnSalvarEdicaoUsuario');
            btnSave.disabled = true;
            fbEdit.className = 'alert d-none small';
            try {
                const fd = new FormData(formEdit);
                fd.append('csrf_token', csrfToken);
                const res = await fetch('/admin/api/usuarios/editar.php', { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    mostrarFbEdit(data.erro || `Erro ao salvar alterações (HTTP ${res.status}).`, false);
                    btnSave.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (err) {
                mostrarFbEdit('Erro de conexão ao salvar.', false);
                btnSave.disabled = false;
            }
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', async () => {
            btnReset.disabled = true;
            fbEdit.className = 'alert d-none small';
            try {
                const fd = new FormData();
                fd.append('id', editId.value);
                fd.append('csrf_token', csrfToken);
                const res = await fetch('/admin/api/usuarios/enviar_reset.php', { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({}));
                mostrarFbEdit(data.success ? data.mensagem : (data.erro || `Erro ao enviar e-mail (HTTP ${res.status}).`), !!data.success);
            } catch (err) {
                mostrarFbEdit('Erro de conexão ao enviar e-mail.', false);
            } finally {
                btnReset.disabled = false;
            }
        });
    }

    const modalOrfaoEl = document.getElementById('modalEditarOrfao');
    const formOrfao = document.getElementById('formEditarOrfao');
    const fbOrfao = document.getElementById('feedbackEditarOrfao');
    const editOrfaoEmail = document.getElementById('editOrfaoEmail');
    const editOrfaoNovaSenha = document.getElementById('editOrfaoNovaSenha');

    document.querySelectorAll('.btn-editar-orfao').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (editOrfaoEmail) editOrfaoEmail.value = btn.dataset.email || '';
            if (editOrfaoNovaSenha) editOrfaoNovaSenha.value = '';
            if (fbOrfao) fbOrfao.className = 'alert d-none small';
            bootstrap.Modal.getOrCreateInstance(modalOrfaoEl).show();
        });
    });

    if (formOrfao) {
        formOrfao.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnSaveOrfao = document.getElementById('btnSalvarOrfao');
            btnSaveOrfao.disabled = true;
            fbOrfao.className = 'alert d-none small';
            try {
                const fd = new FormData(formOrfao);
                fd.append('acao', 'editar_senha');
                fd.append('csrf_token', csrfToken);
                const res = await fetch('/admin/api/usuarios/orfao.php', { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    fbOrfao.textContent = data.erro || `Erro ao atualizar senha (HTTP ${res.status}).`;
                    fbOrfao.className = 'alert alert-danger small';
                    btnSaveOrfao.disabled = false;
                    return;
                }
                fbOrfao.textContent = data.mensagem || 'Senha atualizada com sucesso!';
                fbOrfao.className = 'alert alert-success small';
                btnSaveOrfao.disabled = false;
                if (editOrfaoNovaSenha) editOrfaoNovaSenha.value = '';
            } catch (err) {
                fbOrfao.textContent = 'Erro de conexão ao atualizar senha.';
                fbOrfao.className = 'alert alert-danger small';
                btnSaveOrfao.disabled = false;
            }
        });
    }

    document.querySelectorAll('.btn-deletar-orfao').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const email = btn.dataset.email || '';
            if (!email || !confirm(`Tem certeza que deseja deletar a caixa de e-mail "${email}"? Esta ação não pode ser desfeita.`)) {
                return;
            }
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('acao', 'deletar');
                fd.append('email', email);
                fd.append('csrf_token', csrfToken);
                const res = await fetch('/admin/api/usuarios/orfao.php', { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    alert(data.erro || `Erro ao deletar e-mail (HTTP ${res.status}).`);
                    btn.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (err) {
                alert('Erro de conexão ao deletar e-mail.');
                btn.disabled = false;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
