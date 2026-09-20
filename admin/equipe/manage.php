<?php
$paginaAtiva = 'equipe';
$tituloPagina = 'Gerenciar Cargos da Equipe';
require_once __DIR__ . "/../includes/admin_header.php";

// Garante que a tabela equipe_cargos existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `equipe_cargos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nome` VARCHAR(100) NOT NULL UNIQUE,
        `cor` VARCHAR(20) NOT NULL DEFAULT '#27acff',
        `ordem` INT NOT NULL DEFAULT 0,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Garante que a coluna cor existe se a tabela foi criada anteriormente
    try {
        $pdo->exec("ALTER TABLE `equipe_cargos` ADD COLUMN `cor` VARCHAR(20) NOT NULL DEFAULT '#27acff' AFTER `nome`");
    } catch (Exception $ignored) {}
} catch (Exception $e) {}

// Busca todos os cargos com a contagem de membros vinculados e a cor
try {
    $stmt = $pdo->query("
        SELECT c.id, c.nome, c.cor, c.ordem, c.criado_em,
               COUNT(e.id) AS total_membros
        FROM equipe_cargos c
        LEFT JOIN equipe e ON e.cargo = c.nome
        GROUP BY c.id, c.nome, c.cor, c.ordem, c.criado_em
        ORDER BY c.ordem ASC, c.id ASC
    ");
    $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cargos = [];
}

$sucesso = $_GET['sucesso'] ?? '';
$erro = $_GET['erro'] ?? '';
$totalCargos = count($cargos);

$coresPredefinidas = [
    '#27acff' => 'Azul Claro',
    '#0d6efd' => 'Azul Primário',
    '#F1C40F' => 'Amarelo',
    '#FF8C00' => 'Laranja',
    '#E74C3C' => 'Vermelho',
    '#2ECC71' => 'Verde',
    '#9B59B6' => 'Roxo',
    '#34495E' => 'Cinza Escuro'
];
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Gerenciar Cargos da Equipe</h4>
        <p class="text-muted small mb-0">Crie, edite, exclua e personalize cores e hierarquia de exibição dos cargos no site.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Voltar para Equipe</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoCargo">
            <i class="fa-solid fa-plus me-1"></i> Novo Cargo
        </button>
    </div>
</div>

<!-- NAVEGAÇÃO DE SUB-ABAS -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-users me-1"></i> Membros da Equipe
    </a>
    <a href="manage.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-layer-group me-1"></i> Hierarquia de Cargos
    </a>
</div>

<!-- FEEDBACK ALERTS -->
<?php if ($sucesso === 'criado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Novo cargo criado com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php elseif ($sucesso === 'editado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Cargo atualizado com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php elseif ($sucesso === 'deletado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Cargo excluído com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php elseif ($sucesso === 'reordenado'): ?>
    <div class="alert alert-success alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> Hierarquia de cargos reordenada com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div id="toast-ajax" class="alert alert-success small d-none" role="alert" style="position: sticky; top: 10px; z-index: 999;">
    <i class="fa-solid fa-check me-1"></i> <span id="toast-ajax-msg">Hierarquia salva!</span>
</div>

<!-- TABELA DE CARGOS -->
<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-sitemap me-1 text-primary"></i> Hierarquia de Cargos</h6>
            <span class="text-muted small">Arraste as linhas ou use as setas para ajustar a prioridade (do topo para a base).</span>
        </div>
        <span class="badge bg-light text-dark border"><?php echo $totalCargos; ?> cargo(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-cargos">
                <thead>
                    <tr>
                        <th style="width: 140px;" class="text-center">Hierarquia</th>
                        <th>Nome do Cargo</th>
                        <th style="width: 140px;">Cor</th>
                        <th style="width: 150px;">Membros Ativos</th>
                        <th class="text-end" style="width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="sortable-cargos">
                    <?php if (empty($cargos)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Nenhum cargo cadastrado ainda.
                                <br><small>Clique no botão "+ Novo Cargo" acima para cadastrar.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cargos as $idx => $c): ?>
                            <?php $cargoCor = !empty($c['cor']) ? $c['cor'] : '#27acff'; ?>
                            <tr class="cargo-row" data-id="<?php echo (int)$c['id']; ?>" draggable="true">
                                <td class="text-center text-nowrap">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <i class="fa-solid fa-grip-vertical text-muted me-1 drag-handle" title="Arrastar para reordenar" style="cursor: grab;"></i>
                                        <span class="badge bg-dark fw-semibold">#<?php echo ($idx + 1); ?></span>
                                        
                                        <!-- Botões Up / Down -->
                                        <div class="btn-group btn-group-sm ms-1" role="group">
                                            <form method="POST" action="/admin/api/equipe/cargo_reordenar.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                                <input type="hidden" name="acao" value="subir">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Mover para cima" <?php echo ($idx === 0) ? 'disabled' : ''; ?>>
                                                    <i class="fa-solid fa-chevron-up" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="/admin/api/equipe/cargo_reordenar.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                                <input type="hidden" name="acao" value="descer">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Mover para baixo" <?php echo ($idx === $totalCargos - 1) ? 'disabled' : ''; ?>>
                                                    <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge fs-6 px-3 py-1 text-white shadow-sm" style="background-color: <?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?>; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                            <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <?php if ($idx === 0): ?>
                                            <span class="badge bg-warning text-dark small"><i class="fa-solid fa-crown me-1"></i> Topo</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle shadow-sm border" style="display:inline-block; width: 18px; height: 18px; background-color: <?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                        <code class="small text-dark fw-bold"><?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($c['total_membros'] > 0): ?>
                                        <span class="badge bg-secondary"><?php echo (int)$c['total_membros']; ?> membro(s)</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">0 membros</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalEditarCargo(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['nome']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($cargoCor), ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$c['ordem']; ?>)">
                                            <i class="fa-solid fa-pen-to-square"></i> Editar
                                        </button>
                                        <form method="POST" action="/admin/api/equipe/cargo_deletar.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir o cargo <?php echo htmlspecialchars(addslashes($c['nome']), ENT_QUOTES, 'UTF-8'); ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="<?php echo ($c['total_membros'] > 0) ? 'Mova ou exclua os membros deste cargo antes de deletar' : 'Excluir Cargo'; ?>" <?php echo ($c['total_membros'] > 0) ? 'disabled' : ''; ?>>
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- MODAL: NOVO CARGO -->
<div class="modal fade" id="modalNovoCargo" tabindex="-1" aria-labelledby="modalNovoCargoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/api/equipe/cargo_criar.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalNovoCargoLabel">
                        <i class="fa-solid fa-plus-circle text-success me-2"></i> Criar Novo Cargo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome do Cargo <span class="text-danger">*</span></label>
                        <input type="text" id="novo-cargo-nome" name="nome" class="form-control" placeholder="Ex: Moderadores, Suporte, Ajudantes" required autofocus maxlength="100">
                        <div class="form-text small">Recomendado utilizar no plural ou singular conforme seu padrão (ex: 'Moderadores').</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cor do Cargo (Hex Color) <span class="text-danger">*</span></label>
                        <div class="input-group mb-2">
                            <input type="color" class="form-control form-control-color p-1" id="novo-cargo-cor-picker" value="#27acff" title="Escolha a cor" style="width: 50px; flex: none;">
                            <input type="text" name="cor" id="novo-cargo-cor" class="form-control font-monospace text-uppercase" value="#27acff" maxlength="7" placeholder="#27acff" pattern="^#[0-9a-fA-F]{6}$" required>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                            <span class="small text-muted me-1">Paleta rápida:</span>
                            <?php foreach ($coresPredefinidas as $hex => $label): ?>
                                <button type="button" class="btn btn-sm p-0 rounded-circle border shadow-sm btn-paleta" data-cor="<?php echo $hex; ?>" data-target="novo" title="<?php echo $label . ' (' . $hex . ')'; ?>" style="width: 22px; height: 22px; background-color: <?php echo $hex; ?>;"></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-2 border rounded bg-light text-center">
                            <span class="small text-muted d-block mb-1">Prévia de Exibição:</span>
                            <span id="novo-cargo-preview" class="badge fs-6 px-3 py-1 text-white shadow-sm" style="background-color: #27acff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                Cargo
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Posição Inicial na Hierarquia</label>
                        <input type="number" name="ordem" class="form-control" value="<?php echo $totalCargos + 1; ?>" min="1">
                        <div class="form-text small">Números menores indicam maior prioridade/hierarquia no site (ex: 1 = Topo).</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check me-1"></i> Criar Cargo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CARGO -->
<div class="modal fade" id="modalEditarCargo" tabindex="-1" aria-labelledby="modalEditarCargoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/api/equipe/cargo_editar.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-cargo-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarCargoLabel">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Cargo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome do Cargo <span class="text-danger">*</span></label>
                        <input type="text" id="edit-cargo-nome" name="nome" class="form-control" required maxlength="100">
                        <div class="form-text small">Ao alterar o nome, os membros vinculados a ele terão o cargo atualizado automaticamente.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cor do Cargo (Hex Color) <span class="text-danger">*</span></label>
                        <div class="input-group mb-2">
                            <input type="color" class="form-control form-control-color p-1" id="edit-cargo-cor-picker" value="#27acff" title="Escolha a cor" style="width: 50px; flex: none;">
                            <input type="text" name="cor" id="edit-cargo-cor" class="form-control font-monospace text-uppercase" value="#27acff" maxlength="7" placeholder="#27acff" pattern="^#[0-9a-fA-F]{6}$" required>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                            <span class="small text-muted me-1">Paleta rápida:</span>
                            <?php foreach ($coresPredefinidas as $hex => $label): ?>
                                <button type="button" class="btn btn-sm p-0 rounded-circle border shadow-sm btn-paleta" data-cor="<?php echo $hex; ?>" data-target="edit" title="<?php echo $label . ' (' . $hex . ')'; ?>" style="width: 22px; height: 22px; background-color: <?php echo $hex; ?>;"></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-2 border rounded bg-light text-center">
                            <span class="small text-muted d-block mb-1">Prévia de Exibição:</span>
                            <span id="edit-cargo-preview" class="badge fs-6 px-3 py-1 text-white shadow-sm" style="background-color: #27acff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                Cargo
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Posição na Hierarquia</label>
                        <input type="number" id="edit-cargo-ordem" name="ordem" class="form-control" min="1">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i> Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.cargo-row.dragging {
    opacity: 0.5;
    background-color: #e9ecef !important;
}
.cargo-row.drag-over {
    border-top: 2px solid #0d6efd !important;
}
.drag-handle:hover {
    color: #0d6efd !important;
}
.btn-paleta:hover {
    transform: scale(1.2);
    transition: transform 0.15s ease;
}
</style>

<script>
const CSRF_TOKEN = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";

function padronizarHex(cor) {
    if (!cor) return '#27acff';
    cor = cor.trim();
    if (!cor.startsWith('#')) cor = '#' + cor;
    if (/^#[0-9a-fA-F]{6}$/.test(cor)) return cor;
    return '#27acff';
}

function abrirModalEditarCargo(id, nome, cor, ordem) {
    const corValida = padronizarHex(cor);
    document.getElementById('edit-cargo-id').value = id;
    document.getElementById('edit-cargo-nome').value = nome;
    document.getElementById('edit-cargo-cor').value = corValida.toUpperCase();
    document.getElementById('edit-cargo-cor-picker').value = corValida;
    document.getElementById('edit-cargo-ordem').value = ordem;
    
    // Atualiza preview no modal de edição
    const preview = document.getElementById('edit-cargo-preview');
    if (preview) {
        preview.textContent = nome || 'Cargo';
        preview.style.backgroundColor = corValida;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEditarCargo'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Sincronização e Preview no Modal NOVO CARGO
    const novoNomeInput = document.getElementById('novo-cargo-nome');
    const novoCorInput = document.getElementById('novo-cargo-cor');
    const novoCorPicker = document.getElementById('novo-cargo-cor-picker');
    const novoPreview = document.getElementById('novo-cargo-preview');

    function atualizarNovoPreview() {
        let cor = novoCorInput.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(cor)) {
            novoCorPicker.value = cor;
            novoPreview.style.backgroundColor = cor;
        }
        novoPreview.textContent = novoNomeInput.value.trim() || 'Cargo';
    }

    if (novoNomeInput) novoNomeInput.addEventListener('input', atualizarNovoPreview);
    if (novoCorPicker) {
        novoCorPicker.addEventListener('input', function() {
            novoCorInput.value = this.value.toUpperCase();
            atualizarNovoPreview();
        });
    }
    if (novoCorInput) {
        novoCorInput.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value.trim())) {
                atualizarNovoPreview();
            }
        });
    }

    // Sincronização e Preview no Modal EDITAR CARGO
    const editNomeInput = document.getElementById('edit-cargo-nome');
    const editCorInput = document.getElementById('edit-cargo-cor');
    const editCorPicker = document.getElementById('edit-cargo-cor-picker');
    const editPreview = document.getElementById('edit-cargo-preview');

    function atualizarEditPreview() {
        let cor = editCorInput.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(cor)) {
            editCorPicker.value = cor;
            editPreview.style.backgroundColor = cor;
        }
        editPreview.textContent = editNomeInput.value.trim() || 'Cargo';
    }

    if (editNomeInput) editNomeInput.addEventListener('input', atualizarEditPreview);
    if (editCorPicker) {
        editCorPicker.addEventListener('input', function() {
            editCorInput.value = this.value.toUpperCase();
            atualizarEditPreview();
        });
    }
    if (editCorInput) {
        editCorInput.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value.trim())) {
                atualizarEditPreview();
            }
        });
    }

    // Paleta rápida de botões
    document.querySelectorAll('.btn-paleta').forEach(btn => {
        btn.addEventListener('click', function() {
            const cor = this.getAttribute('data-cor');
            const target = this.getAttribute('data-target');
            if (target === 'novo') {
                novoCorInput.value = cor.toUpperCase();
                novoCorPicker.value = cor;
                atualizarNovoPreview();
            } else if (target === 'edit') {
                editCorInput.value = cor.toUpperCase();
                editCorPicker.value = cor;
                atualizarEditPreview();
            }
        });
    });

    // Drag and Drop para reordenação
    const tbody = document.getElementById('sortable-cargos');
    if (!tbody) return;

    let draggedRow = null;

    tbody.addEventListener('dragstart', function(e) {
        const row = e.target.closest('tr.cargo-row');
        if (!row) return;
        draggedRow = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.id);
    });

    tbody.addEventListener('dragend', function(e) {
        const row = e.target.closest('tr.cargo-row');
        if (row) row.classList.remove('dragging');
        document.querySelectorAll('.cargo-row').forEach(r => r.classList.remove('drag-over'));
        salvarNovaOrdem();
    });

    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const targetRow = e.target.closest('tr.cargo-row');
        if (!targetRow || targetRow === draggedRow) return;

        const bounding = targetRow.getBoundingClientRect();
        const offset = bounding.y + (bounding.height / 2);
        if (e.clientY - offset > 0) {
            targetRow.after(draggedRow);
        } else {
            targetRow.before(draggedRow);
        }
    });

    function salvarNovaOrdem() {
        const rows = tbody.querySelectorAll('tr.cargo-row');
        const ordemIds = [];
        rows.forEach((r, idx) => {
            ordemIds.push(parseInt(r.dataset.id, 10));
            const badge = r.querySelector('.badge.bg-dark');
            if (badge) badge.textContent = '#' + (idx + 1);
        });

        if (ordemIds.length === 0) return;

        fetch('/admin/api/equipe/cargo_reordenar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                csrf_token: CSRF_TOKEN,
                ordem_ids: ordemIds
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const toast = document.getElementById('toast-ajax');
                const toastMsg = document.getElementById('toast-ajax-msg');
                if (toast && toastMsg) {
                    toastMsg.textContent = 'Hierarquia reordenada com sucesso!';
                    toast.classList.remove('d-none');
                    setTimeout(() => {
                        toast.classList.add('d-none');
                    }, 3000);
                }
            } else {
                alert('Erro ao salvar hierarquia: ' + (data.erro || 'Falha na requisição'));
            }
        })
        .catch(err => {
            console.error('Erro ao reordenar:', err);
        });
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>

