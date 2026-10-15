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
    if (isset($gruposBrutos[$cNome])) {
        $grupos[$cNome] = $gruposBrutos[$cNome];
        unset($gruposBrutos[$cNome]);
    }
}
// Adiciona eventuais cargos restantes que não estejam cadastrados na tabela
foreach ($gruposBrutos as $cNome => $lista) {
    $grupos[$cNome] = $lista;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Equipe</h4>
        <p class="text-muted small mb-0"><span data-item-counter><?php echo count($membros); ?> membro(s)</span> em <?php echo count($grupos); ?> categoria(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="manage.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-layer-group me-1"></i> Gerenciar Cargos</a>
        <a href="criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus me-1"></i> Novo Membro</a>
    </div>
</div>

<!-- NAVEGAÇÃO DE SUB-ABAS -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-users me-1"></i> Membros da Equipe
    </a>
    <a href="manage.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-layer-group me-1"></i> Hierarquia de Cargos
    </a>
</div>

<?php if (empty($grupos)): ?>
    <div class="admin-card p-4 text-center text-muted">
        Nenhum membro cadastrado ainda.
    </div>
<?php else: ?>
    <?php foreach ($grupos as $cargo => $lista): ?>
        <?php $cargoCor = $cargosCores[$cargo] ?? '#27acff'; ?>
        <div class="admin-card mb-3">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle shadow-sm" style="display:inline-block; width: 14px; height: 14px; background-color: <?php echo htmlspecialchars($cargoCor, ENT_QUOTES, 'UTF-8'); ?>;"></span>
                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?></h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border contador-membros"><?php echo count($lista); ?> membro(s)</span>
                    <a href="criar.php?cargo=<?php echo urlencode($cargo); ?>" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.78rem;" title="Adicionar membro neste cargo">
                        <i class="fa-solid fa-plus"></i> Adicionar
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-admin align-middle mb-0">
                        <colgroup>
                            <col style="width: 50px;">
                            <col>
                            <col style="width: 140px;">
                        </colgroup>
                        <tbody class="membros-dropzone" data-cargo="<?php echo htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if (empty($lista)): ?>
                                <tr class="membro-vazio"><td colspan="3" class="text-center text-muted small py-3"><i class="fa-solid fa-arrows-up-down me-1"></i> Arraste membros para este cargo</td></tr>
                            <?php else: ?>
                                <?php foreach ($lista as $m): ?>
                                    <tr class="membro-row" data-id="<?php echo (int)$m['id']; ?>" draggable="true">
                                        <td>
                                            <img class="rounded border" src="https://mc-heads.net/avatar/<?php echo urlencode($m['nick']); ?>/32" width="32" height="32" alt="" onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/32'">
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?php echo htmlspecialchars($m['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                                <a href="editar.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
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

<!-- Estado membro -> cargo (controlado pela barra de alterações) -->
<form class="js-unsaved-form" id="form-membros-cargos" data-save-endpoint="/admin/api/equipe/membros_cargos.php" style="display: none;">
    <?php foreach ($membros as $m): ?>
        <input type="hidden" name="membro_cargos[<?php echo (int)$m['id']; ?>]" value="<?php echo htmlspecialchars($m['cargo'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</form>

<script>
// Drag and drop de membros entre cargos (somente client-side)
(function() {
    const draggedClass = 'membro-dragging';
    const dragOverClass = 'membro-drop-hover';

    document.querySelectorAll('tr.membro-row').forEach(function(row) {
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
    });

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
                // Remove o placeholder de secao vazia do destino
                const placeholder = tbody.querySelector('tr.membro-vazio');
                if (placeholder) placeholder.remove();
                // Move a linha para o cargo de destino (client-side)
                tbody.appendChild(row);
                // Atualiza o campo oculto membro_cargos[id]
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
    document.querySelectorAll('tbody.membros-dropzone').forEach(function(tbody) {
        const card = tbody.closest('.admin-card');
        if (!card) return;
        const badge = card.querySelector('.badge.contador-membros');
        if (badge) {
            const qtd = tbody.querySelectorAll('tr.membro-row').length;
            badge.textContent = qtd + ' membro(s)';
        }
        // Garante o placeholder quando a secao fica vazia (area de drop)
        if (tbody.querySelectorAll('tr.membro-row').length === 0 && !tbody.querySelector('tr.membro-vazio')) {
            const tr = document.createElement('tr');
            tr.className = 'membro-vazio';
            tr.innerHTML = '<td colspan="3" class="text-center text-muted small py-3"><i class="fa-solid fa-arrows-up-down me-1"></i> Arraste membros para este cargo</td>';
            tbody.appendChild(tr);
        }
    });
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
        }
    });
    atualizarContadoresMembros();
}

document.addEventListener('DOMContentLoaded', function() {
    const formMembros = document.getElementById('form-membros-cargos');
    if (!formMembros) return;
    formMembros.addEventListener('unsaved:discarded', function() {
        aplicarMembrosCargos();
        if (window.AdminToast) {
            window.AdminToast.info('Alterações de membros descartadas.');
        }
    });
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
</style>

<?php require_once __DIR__ . "/../includes/unsaved_bar.php"; ?>
