<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Equipe';
require_once __DIR__ . "/../includes/admin_header.php";

try {
    $stmt = $pdo->query("SELECT id, nick, cargo FROM equipe ORDER BY id ASC");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membros = [];
}

// Busca a ordem e cores definidas na tabela equipe_cargos
$cargosOrdem = [];
$cargosCores = [];
try {
    $stmtCargos = $pdo->query("SELECT nome, cor FROM equipe_cargos ORDER BY ordem ASC, id ASC");
    $rowsCargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsCargos as $rc) {
        $cargosOrdem[] = $rc['nome'];
        $cargosCores[$rc['nome']] = $rc['cor'] ?? '#27acff';
    }
} catch (Exception $e) {
    $cargosOrdem = [];
    $cargosCores = [];
}

$gruposBrutos = [];
foreach ($membros as $m) {
    if (empty($m['cargo'])) continue;
    $gruposBrutos[$m['cargo']][] = $m;
}

// Ordena os grupos conforme a hierarquia de equipe_cargos
$grupos = [];
foreach ($cargosOrdem as $cNome) {
    $grupos[$cNome] = $gruposBrutos[$cNome] ?? [];
    unset($gruposBrutos[$cNome]);
}
// Adiciona eventuais cargos restantes que não estejam cadastrados na tabela
foreach ($gruposBrutos as $cNome => $lista) {
    $grupos[$cNome] = $lista;
}

$todosCargos = array_unique(array_merge($cargosOrdem, array_keys($grupos)));
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Equipe</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo pluralizar(count($membros), 'membro', 'membros'); ?></span> em <?php echo pluralizar(count($grupos), 'categoria', 'categorias'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="manage.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-layer-group me-1"></i> Gerenciar Cargos</a>
        <button type="button" class="btn btn-success btn-sm btn-abrir-criar-membro" data-cargo="">
            <i class="fa-solid fa-plus me-1"></i> Novo Membro
        </button>
    </div>
</div>

<?php if (empty($grupos)): ?>
    <div class="admin-card p-4 text-center text-muted">
        Nenhum cargo ou membro cadastrado ainda.
    </div>
<?php else: ?>
    <?php foreach ($grupos as $cargo => $lista): ?>
        <?php $cargoCor = $cargosCores[$cargo] ?? '#27acff'; ?>
        <div class="admin-card mb-3 card-grupo-cargo" data-cargo-nome="<?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle shadow-sm" style="display:inline-block; width: 14px; height: 14px; background-color: <?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?></h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border contador-membros"><?php echo pluralizar(count($lista), 'membro', 'membros'); ?></span>
                    <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 py-1 px-2 btn-abrir-criar-membro" data-cargo="<?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 0.78rem;" title="Adicionar membro neste cargo">
                        <i class="fa-solid fa-plus"></i> Adicionar
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <colgroup>
                            <col style="width: 36px;">
                            <col style="width: 50px;">
                            <col>
                            <col style="width: 140px;">
                        </colgroup>
                        <tbody class="membros-dropzone" data-cargo="<?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if (empty($lista)): ?>
                                <tr class="membro-vazio"><td colspan="4" class="text-center text-muted small py-3"><i class="fa-solid fa-arrows-up-down me-1"></i> Arraste membros para este cargo</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $m): ?>
                                    <tr class="membro-row" id="membro-row-<?php echo (int)$m['id']; ?>" data-id="<?php echo (int)$m['id']; ?>" data-nick="<?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?>" data-cargo="<?php echo htmlspecialchars($m['cargo'], ENT_QUOTES, 'UTF-8'); ?>" draggable="true">
                                        <td class="membro-drag-handle" title="Arrastar para outro cargo"><i class="fa-solid fa-grip-vertical text-muted"></i></td>
                                        <td>
                                            <img class="rounded border js-membro-avatar" src="https://mc-heads.net/avatar/<?php echo urlencode($m['nick']); ?>/32" width="32" height="32" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                                        </td>
                                        <td>
                                            <strong class="text-dark js-membro-nick"><?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                                <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-membro" title="Editar Membro">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-action="async-delete" 
                                                        data-url="/admin/api/equipe/deletar.php" 
                                                        data-id="<?php echo (int)$m['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                        data-confirm="Remover <?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?> da equipe?" 
                                                        title="Deletar">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
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
    <?php endforeach; ?>
<?php endif; ?>

<!-- MODAL: CRIAR MEMBRO -->
<div class="modal fade" id="modalCriarMembro" tabindex="-1" aria-labelledby="modalCriarMembroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="formCriarMembro">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarMembroLabel">
                        <i class="fa-solid fa-user-plus text-success me-2"></i> Adicionar Membro da Equipe
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="criar-membro-nick" class="form-label fw-bold">Nick do Jogador (Minecraft) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text p-1">
                                <img id="criar-membro-preview" src="https://mc-heads.net/avatar/MHF_Steve/32" width="32" height="32" class="rounded" alt="Avatar" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                            </span>
                            <input type="text" class="form-control" id="criar-membro-nick" name="nick" placeholder="Ex: Steve" required maxlength="16" autofocus>
                        </div>
                        <div class="form-text small">Nick de 2 a 16 caracteres alfanuméricos. A skin é buscada automaticamente.</div>
                    </div>

                    <div class="mb-3">
                        <label for="criar-membro-cargo" class="form-label fw-bold">Cargo / Grupo <span class="text-danger">*</span></label>
                        <select class="form-select" id="criar-membro-cargo" name="cargo" required>
                            <option value="">Selecione um cargo...</option>
                            <?php foreach ($todosCargos as $cNome): ?>
                                <option value="<?php echo htmlspecialchars($cNome, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cNome, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-membro">
                        <i class="fa-solid fa-check me-1"></i> Adicionar Membro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR MEMBRO -->
<div class="modal fade" id="modalEditarMembro" tabindex="-1" aria-labelledby="modalEditarMembroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="formEditarMembro">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-membro-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarMembroLabel">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Membro: <span id="modalEditarMembroNickHeader" class="text-primary font-monospace"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit-membro-nick" class="form-label fw-bold">Nick do Jogador (Minecraft) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text p-1">
                                <img id="edit-membro-preview" src="https://mc-heads.net/avatar/MHF_Steve/32" width="32" height="32" class="rounded" alt="Avatar" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                            </span>
                            <input type="text" class="form-control" id="edit-membro-nick" name="nick" required maxlength="16">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-membro-cargo" class="form-label fw-bold">Cargo / Grupo <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit-membro-cargo" name="cargo" required>
                            <option value="">Selecione um cargo...</option>
                            <?php foreach ($todosCargos as $cNome): ?>
                                <option value="<?php echo htmlspecialchars($cNome, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cNome, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-membro">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Estado membro -> cargo (controlado pela barra de alterações - modo diff) -->
<form class="js-unsaved-form" id="form-membros-cargos" data-save-endpoint="/admin/api/equipe/membros_cargos.php" data-save-mode="diff" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($membros as $m): ?>
        <input type="hidden" name="membro_cargos[<?php echo (int)$m['id']; ?>]" value="<?php echo htmlspecialchars($m['cargo'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</form>

<script>
// Drag and drop de membros entre cargos (somente client-side)
(function() {
    const draggedClass = 'membro-dragging';
    const dragOverClass = 'membro-drop-hover';

    function setupDragRow(row) {
        row.addEventListener('dragstart', function(e) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.id);
            row.classList.add(draggedClass);
        });
        row.addEventListener('dragend', function() {
            row.classList.remove(draggedClass);
            document.querySelectorAll('.' + dragOverClass).forEach(function(el) {
                el.classList.remove(dragOverClass);
            });
        });
    }

    document.querySelectorAll('tr.membro-row').forEach(setupDragRow);
    window.setupDragRow = setupDragRow;

    document.querySelectorAll('tbody.membros-dropzone').forEach(function(tbody) {
        tbody.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            tbody.classList.add(dragOverClass);
        });
        tbody.addEventListener('dragleave', function(e) {
            if (!tbody.contains(e.relatedTarget)) {
                tbody.classList.remove(dragOverClass);
            }
        });
        tbody.addEventListener('drop', function(e) {
            e.preventDefault();
            tbody.classList.remove(dragOverClass);
            const membroId = e.dataTransfer.getData('text/plain');
            const row = document.querySelector('tr.membro-row[data-id="' + membroId + '"]');
            if (row && row.closest('tbody') !== tbody) {
                const placeholder = tbody.querySelector('tr.membro-vazio');
                if (placeholder) placeholder.remove();
                tbody.appendChild(row);
                row.dataset.cargo = tbody.dataset.cargo;
                const input = document.querySelector('#form-membros-cargos input[name="membro_cargos[' + membroId + ']"]');
                if (input) {
                    input.value = tbody.dataset.cargo;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                atualizarContadoresMembros();
            }
        });
    });
})();

// Atualiza o contador de membros de cada secao apos mover linhas
function atualizarContadoresMembros() {
    let totalMembros = 0;
    document.querySelectorAll('tbody.membros-dropzone').forEach(function(tbody) {
        const card = tbody.closest('.admin-card');
        if (!card) return;
        const badge = card.querySelector('.badge.contador-membros');
        const qtd = tbody.querySelectorAll('tr.membro-row').length;
        totalMembros += qtd;
        if (badge) {
            badge.textContent = qtd + (qtd === 1 ? ' membro' : ' membros');
        }
        if (qtd === 0 && !tbody.querySelector('tr.membro-vazio')) {
            const tr = document.createElement('tr');
            tr.className = 'membro-vazio';
            tr.innerHTML = '<td colspan="4" class="text-center text-muted small py-3"><i class="fa-solid fa-arrows-up-down me-1"></i> Arraste membros para este cargo</td>';
            tbody.appendChild(tr);
        }
    });

    const counterTop = document.querySelector('[data-item-counter]');
    if (counterTop) {
        counterTop.textContent = totalMembros + (totalMembros === 1 ? ' membro' : ' membros');
    }
}

// Re-renderiza as secoes a partir dos campos ocultos quando a barra descarta
function aplicarMembrosCargos() {
    document.querySelectorAll('#form-membros-cargos input[name^="membro_cargos"]').forEach(function(input) {
        const match = input.name.match(/\[(\d+)\]/);
        if (!match) return;
        const membroId = match[1];
        const cargo = input.value;
        const row = document.querySelector('tr.membro-row[data-id="' + membroId + '"]');
        const tbody = document.querySelector('tbody.membros-dropzone[data-cargo="' + cargo + '"]');
        if (row && tbody) {
            tbody.appendChild(row);
            row.dataset.cargo = cargo;
        }
    });
    atualizarContadoresMembros();
}

document.addEventListener('DOMContentLoaded', function() {
    const formMembros = document.getElementById('form-membros-cargos');
    if (formMembros) {
        formMembros.addEventListener('unsaved:discarded', function() {
            aplicarMembrosCargos();
            if (window.AdminToast) {
                window.AdminToast.info('Alterações de membros descartadas.');
            }
        });
    }

    document.addEventListener('admin:row-deleted', function(e) {
        if (e.detail && e.detail.id) {
            const input = document.querySelector('#form-membros-cargos input[name="membro_cargos[' + e.detail.id + ']"]');
            if (input) input.remove();
            atualizarContadoresMembros();
        }
    });

    // Modais e formulários AJAX de Membro
    const modalCriarEl = document.getElementById('modalCriarMembro');
    const modalEditarEl = document.getElementById('modalEditarMembro');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    // Atualização do preview de avatar enquanto digita
    const criarNickInput = document.getElementById('criar-membro-nick');
    const criarPreviewImg = document.getElementById('criar-membro-preview');
    if (criarNickInput && criarPreviewImg) {
        criarNickInput.addEventListener('input', function() {
            const n = this.value.trim() || 'MHF_Steve';
            criarPreviewImg.src = 'https://mc-heads.net/avatar/' + encodeURIComponent(n) + '/32';
        });
    }

    const editNickInput = document.getElementById('edit-membro-nick');
    const editPreviewImg = document.getElementById('edit-membro-preview');
    if (editNickInput && editPreviewImg) {
        editNickInput.addEventListener('input', function() {
            const n = this.value.trim() || 'MHF_Steve';
            editPreviewImg.src = 'https://mc-heads.net/avatar/' + encodeURIComponent(n) + '/32';
        });
    }

    // Abertura do modal Criar Membro
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-criar-membro');
        if (!btn || !modalCriarEl) return;
        const cargoPre = btn.dataset.cargo || '';
        const selectCargo = document.getElementById('criar-membro-cargo');
        if (selectCargo && cargoPre) selectCargo.value = cargoPre;
        if (bsModalCriar) bsModalCriar.show();
    });

    // Abertura do modal Editar Membro
    function abrirModalEditarMembro(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;
        document.getElementById('edit-membro-id').value = d.id || '';
        document.getElementById('edit-membro-nick').value = d.nick || '';
        document.getElementById('modalEditarMembroNickHeader').textContent = d.nick || '';
        document.getElementById('edit-membro-cargo').value = d.cargo || '';
        if (editPreviewImg) {
            editPreviewImg.src = 'https://mc-heads.net/avatar/' + encodeURIComponent(d.nick || 'MHF_Steve') + '/32';
        }
        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-membro');
        if (!btn) return;
        const row = btn.closest('tr.membro-row');
        if (row) abrirModalEditarMembro(row);
    });

    function criarElementoLinhaMembro(m) {
        const tr = document.createElement('tr');
        tr.className = 'membro-row';
        tr.id = `membro-row-${m.id}`;
        tr.dataset.id = m.id;
        tr.dataset.nick = m.nick || '';
        tr.dataset.cargo = m.cargo || '';
        tr.draggable = true;

        tr.innerHTML = `
            <td class="membro-drag-handle" title="Arrastar para outro cargo"><i class="fa-solid fa-grip-vertical text-muted"></i></td>
            <td>
                <img class="rounded border js-membro-avatar" src="https://mc-heads.net/avatar/${encodeURIComponent(m.nick)}/32" width="32" height="32" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
            </td>
            <td>
                <strong class="text-dark js-membro-nick">${m.nick}</strong>
            </td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-membro" title="Editar Membro">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" 
                            data-action="async-delete" 
                            data-url="/admin/api/equipe/deletar.php" 
                            data-id="${m.id}" 
                            data-name="${m.nick}" 
                            data-confirm="Remover ${m.nick} da equipe?" 
                            title="Deletar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        if (window.setupDragRow) window.setupDragRow(tr);
        return tr;
    }

    // SUBMIT: CRIAR MEMBRO
    const formCriar = document.getElementById('formCriarMembro');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar-membro');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Adicionando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/equipe/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();
                if (criarPreviewImg) criarPreviewImg.src = 'https://mc-heads.net/avatar/MHF_Steve/32';

                if (res.membro) {
                    const tbody = document.querySelector(`tbody.membros-dropzone[data-cargo="${res.membro.cargo}"]`);
                    if (tbody) {
                        const placeholder = tbody.querySelector('tr.membro-vazio');
                        if (placeholder) placeholder.remove();
                        const novaLinha = criarElementoLinhaMembro(res.membro);
                        tbody.appendChild(novaLinha);
                        novaLinha.classList.add('table-row-highlight');
                        setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                    }

                    // Adiciona input ao form-membros-cargos para manter o estado sincronizado
                    const formMembrosCargos = document.getElementById('form-membros-cargos');
                    if (formMembrosCargos) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = `membro_cargos[${res.membro.id}]`;
                        hiddenInput.value = res.membro.cargo;
                        formMembrosCargos.appendChild(hiddenInput);
                    }
                }

                atualizarContadoresMembros();
                AdminToast.success(res.mensagem || 'Membro adicionado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao adicionar membro.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR MEMBRO
    const formEditar = document.getElementById('formEditarMembro');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar-membro');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/equipe/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.membro) {
                    const m = res.membro;
                    let row = document.getElementById(`membro-row-${m.id}`);
                    const destinoTbody = document.querySelector(`tbody.membros-dropzone[data-cargo="${m.cargo}"]`);

                    if (row) {
                        row.dataset.nick = m.nick;
                        row.dataset.cargo = m.cargo;
                        const avatarImg = row.querySelector('.js-membro-avatar');
                        if (avatarImg) avatarImg.src = `https://mc-heads.net/avatar/${encodeURIComponent(m.nick)}/32`;
                        const nickText = row.querySelector('.js-membro-nick');
                        if (nickText) nickText.textContent = m.nick;

                        const btnDel = row.querySelector('[data-action="async-delete"]');
                        if (btnDel) {
                            btnDel.dataset.name = m.nick;
                            btnDel.dataset.confirm = `Remover ${m.nick} da equipe?`;
                        }

                        // Se mudou de cargo, move para a nova seção
                        if (destinoTbody && row.closest('tbody') !== destinoTbody) {
                            const placeholder = destinoTbody.querySelector('tr.membro-vazio');
                            if (placeholder) placeholder.remove();
                            destinoTbody.appendChild(row);
                        }

                        // Atualiza campo no form-membros-cargos
                        const input = document.querySelector(`#form-membros-cargos input[name="membro_cargos[${m.id}]"]`);
                        if (input) input.value = m.cargo;

                        row.classList.add('table-row-highlight');
                        setTimeout(() => row.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadoresMembros();
                AdminToast.success(res.mensagem || 'Membro atualizado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar membro.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // Tratamento de URL ?action=novo e ?edit=ID
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'novo' && bsModalCriar) {
        if (urlParams.get('cargo')) {
            const selectCargo = document.getElementById('criar-membro-cargo');
            if (selectCargo) selectCargo.value = urlParams.get('cargo');
        }
        bsModalCriar.show();
    } else if (urlParams.has('edit')) {
        const editId = urlParams.get('edit');
        const row = document.getElementById(`membro-row-${editId}`);
        if (row) {
            abrirModalEditarMembro(row);
        }
    }
});
</script>

<style>
tr.membro-row { cursor: grab; }
tr.membro-row:active { cursor: grabbing; }
tr.membro-row.membro-dragging { opacity: 0.4; }
tbody.membros-dropzone.membro-drop-hover {
    outline: 2px dashed #2563eb;
    outline-offset: -2px;
    background: rgba(37, 99, 235, 0.05);
}
td.membro-drag-handle {
    cursor: grab;
    text-align: center;
    width: 36px;
    color: #cbd5e1;
    transition: color 0.15s ease, transform 0.15s ease;
    vertical-align: middle;
}
td.membro-drag-handle:hover {
    color: #2563eb;
    transform: scaleX(1.15);
}
tr.membro-row:hover td.membro-drag-handle {
    color: #2563eb;
}
tr.membro-row:hover td {
    background-color: #f1f5f9;
}
tr.membro-row.membro-dragging td.membro-drag-handle {
    color: #2563eb;
}
</style>

<?php require_once __DIR__ . "/../includes/unsaved_bar.php"; ?>
<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
