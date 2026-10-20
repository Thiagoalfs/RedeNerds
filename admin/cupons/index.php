<?php
$paginaAtiva = 'cupons';
$tituloPagina = 'Cupons';
require_once __DIR__ . "/../includes/admin_header.php";
require_once __DIR__ . "/../includes/servidores_admin.php";

$servidoresData = carregarServidoresAdmin($pdo);
$servidores = $servidoresData['lista'];
$servidoresMap = $servidoresData['mapa'];

if (empty($_SESSION['cupons_schema_ok'])) {
    try {
        $colunasCupons = $pdo->query("SHOW COLUMNS FROM cupons")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('servidor_id', $colunasCupons, true)) {
            $pdo->exec("ALTER TABLE cupons ADD COLUMN servidor_id INT NULL DEFAULT 0");
        }
        $_SESSION['cupons_schema_ok'] = true;
    } catch (Exception $e) {
        error_log("Erro ao preparar schema de cupons: " . $e->getMessage());
    }
}

$cupons = [];
$cuponsAtivos = [];
$cuponsInativos = [];

function statusDoCupom(array $cupom, array $servidoresMap): array {
    $now = time();
    $expiraEm = strtotime($cupom['expira_em']);
    $isExpirado = ($expiraEm && $expiraEm < $now);
    $sId = (int)($cupom['servidor_id'] ?? 0);
    $srvObj = ($sId > 0 && isset($servidoresMap[$sId])) ? $servidoresMap[$sId] : null;
    $srvEnabled = $srvObj ? (int)($srvObj['enabled'] ?? 1) : 1;

    if ($isExpirado) {
        return [
            'label' => 'Expirado',
            'badge' => 'badge-status expirado',
            'ativo_real' => false,
            'motivo' => 'expirado'
        ];
    }

    if (!$cupom['ativo'] && $sId > 0 && $srvEnabled === 0) {
        return [
            'label' => 'Inativo',
            'badge' => 'badge-status inativo',
            'ativo_real' => false,
            'motivo' => 'ambos_inativos',
            'extra_label' => 'Servidor Oculto'
        ];
    }

    if (!$cupom['ativo']) {
        return [
            'label' => 'Inativo',
            'badge' => 'badge-status inativo',
            'ativo_real' => false,
            'motivo' => 'inativo'
        ];
    }

    if ($sId > 0 && $srvEnabled === 0) {
        return [
            'label' => 'Servidor Oculto',
            'badge' => 'badge-status pendente',
            'ativo_real' => false,
            'motivo' => 'servidor_oculto'
        ];
    }

    return [
        'label' => 'Ativo',
        'badge' => 'badge-status ativo',
        'ativo_real' => true,
        'motivo' => 'ativo'
    ];
}

try {
    $stmt = $pdo->query("SELECT * FROM cupons ORDER BY criado_em DESC");
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cupons as $c) {
        $st = statusDoCupom($c, $servidoresMap);
        $c['_status_info'] = $st;

        if ($st['ativo_real']) {
            $cuponsAtivos[] = $c;
        } else {
            $cuponsInativos[] = $c;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar lista de cupons: " . $e->getMessage());
    $cupons = [];
    $cuponsAtivos = [];
    $cuponsInativos = [];
}

function renderLinhaCupom(array $c, array $servidoresMap): string {
    $st = $c['_status_info'] ?? statusDoCupom($c, $servidoresMap);
    $sId = (int)($c['servidor_id'] ?? 0);
    $srv = ($sId > 0 && isset($servidoresMap[$sId])) ? $servidoresMap[$sId] : null;
    $srvNome = $srv ? $srv['servername'] : 'Todos os Servidores';
    $corSrv = !empty($srv['themecolor']) ? $srv['themecolor'] : '#6366f1';

    $expiraFormatada = date('d/m/Y H:i', strtotime($c['expira_em']));
    $expiraInput = date('Y-m-d\TH:i', strtotime($c['expira_em']));
    $criadoFormatada = date('d/m/Y H:i', strtotime($c['criado_em']));
    $isAtivo = !empty($c['ativo']) ? 1 : 0;

    $html = '<tr id="cupom-row-' . (int)$c['id'] . '" '
          . 'data-id="' . (int)$c['id'] . '" '
          . 'data-codigo="' . htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') . '" '
          . 'data-porcentagem="' . (float)$c['porcentagem_desconto'] . '" '
          . 'data-servidor-id="' . $sId . '" '
          . 'data-expira-em="' . $expiraInput . '" '
          . 'data-ativo="' . $isAtivo . '" '
          . 'data-usos="' . (int)$c['usos_total'] . '" '
          . 'data-criado-em="' . $criadoFormatada . '">';

    $html .= '<td>';
    $html .= '<span class="badge bg-light text-dark border font-monospace fs-6 px-2 py-1 js-cupom-codigo">';
    $html .= '<i class="fa-solid fa-tag text-primary me-1"></i>' . htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8');
    $html .= '</span></td>';

    $html .= '<td><strong class="text-success fw-bold js-cupom-desconto">' . number_format((float)$c['porcentagem_desconto'], 1, ',', '.') . '% OFF</strong></td>';

    $html .= '<td class="js-cupom-servidor">';
    if ($srv) {
        $html .= '<span class="badge" style="background-color: ' . htmlspecialchars($corSrv, ENT_QUOTES, 'UTF-8') . '; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">';
        $html .= '<i class="fa-solid fa-server me-1"></i>' . htmlspecialchars($srv['servername'], ENT_QUOTES, 'UTF-8');
        $html .= '</span>';
    } else {
        $html .= '<span class="badge bg-secondary-subtle text-body border">';
        $html .= '<i class="fa-solid fa-globe me-1 text-primary"></i> Todos os Servidores';
        $html .= '</span>';
    }
    $html .= '</td>';

    $html .= '<td class="js-cupom-validade"><i class="fa-regular fa-clock text-muted me-1"></i>' . $expiraFormatada . '</td>';

    $html .= '<td class="js-cupom-status">';
    $html .= '<span class="' . $st['badge'] . '">';
    if ($st['motivo'] === 'servidor_oculto') {
        $html .= '<i class="fa-solid fa-triangle-exclamation me-1"></i>';
    }
    $html .= $st['label'] . '</span>';
    if (!empty($st['extra_label'])) {
        $html .= '<span class="badge-status pendente ms-1" title="Servidor vinculado também está desativado">';
        $html .= '<i class="fa-solid fa-triangle-exclamation me-1"></i> ' . htmlspecialchars($st['extra_label'], ENT_QUOTES, 'UTF-8');
        $html .= '</span>';
    }
    $html .= '</td>';

    $html .= '<td><span class="badge bg-light text-dark border font-monospace js-cupom-usos">' . pluralizar((int)$c['usos_total'], 'compra', 'compras') . '</span></td>';
    $html .= '<td class="text-muted small">' . $criadoFormatada . '</td>';

    $html .= '<td class="text-end text-nowrap">';
    $html .= '<div class="d-inline-flex align-items-center justify-content-end gap-1">';
    $html .= '<button type="button" class="btn btn-sm btn-primary btn-abrir-editar-cupom" title="Editar Cupom"><i class="fa-solid fa-pen-to-square"></i></button>';
    $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="async-toggle" data-url="/admin/api/cupons/toggle.php" data-id="' . (int)$c['id'] . '" title="' . ($isAtivo ? 'Desativar cupom' : 'Ativar cupom') . '">';
    $html .= '<i class="fa-solid ' . ($isAtivo ? 'fa-eye' : 'fa-eye-slash') . '"></i>';
    $html .= '</button>';
    $html .= '<button type="button" class="btn btn-sm btn-danger" data-action="async-delete" data-url="/admin/api/cupons/deletar.php" data-id="' . (int)$c['id'] . '" data-name="' . htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') . '" data-confirm="Deletar o cupom ' . htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') . '? Essa ação não pode ser desfeita." title="Deletar">';
    $html .= '<i class="fa-solid fa-trash"></i>';
    $html .= '</button>';
    $html .= '</div></td>';
    $html .= '</tr>';

    return $html;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Cupons de Desconto</h4>
        <p class="text-muted small mb-0">
            <span data-item-counter><?php echo pluralizar(count($cupons), 'cupom', 'cupons'); ?></span> <?php echo count($cupons) === 1 ? 'cadastrado' : 'cadastrados'; ?>
            <span class="mx-1">•</span>
            <span class="text-success fw-medium" data-item-counter-ativos-summary><?php echo pluralizar(count($cuponsAtivos), 'ativo', 'ativos'); ?></span>
            <span class="mx-1">•</span>
            <span class="text-muted fw-medium" data-item-counter-inativos-summary><?php echo pluralizar(count($cuponsInativos), 'inativo', 'inativos'); ?></span>
        </p>
    </div>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarCupom">
        <i class="fa-solid fa-plus me-1"></i> Novo cupom
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- TABELA 1: CUPONS ATIVOS -->
<div class="admin-card mb-4">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Cupons Ativos
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-ativos>
            <?php echo pluralizar(count($cuponsAtivos), 'cupom', 'cupons'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-cupons-ativos">
                <thead>
                    <tr>
                        <th style="width: 170px;">Código do Cupom</th>
                        <th style="width: 130px;">Desconto</th>
                        <th style="width: 180px;">Servidor</th>
                        <th style="width: 180px;">Validade</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 90px;">Usos</th>
                        <th style="width: 140px;">Criado em</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-cupons-ativos">
                    <?php if (empty($cuponsAtivos)): ?>
                        <tr class="empty-row" id="linha-sem-cupons-ativos">
                            <td colspan="8" class="text-center py-4 text-muted">Nenhum cupom ativo no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuponsAtivos as $c): ?>
                            <?php echo renderLinhaCupom($c, $servidoresMap); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TABELA 2: CUPONS INATIVOS -->
<div class="admin-card">
    <div class="admin-card-header d-flex align-items-center justify-content-between">
        <h6 class="admin-card-title mb-0">
            Cupons Inativos ou Expirados
        </h6>
        <span class="text-muted small fw-medium" data-item-counter-inativos>
            <?php echo pluralizar(count($cuponsInativos), 'cupom', 'cupons'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="tabela-cupons-inativos">
                <thead>
                    <tr>
                        <th style="width: 170px;">Código do Cupom</th>
                        <th style="width: 130px;">Desconto</th>
                        <th style="width: 180px;">Servidor</th>
                        <th style="width: 180px;">Validade</th>
                        <th style="width: 160px;">Status</th>
                        <th style="width: 90px;">Usos</th>
                        <th style="width: 140px;">Criado em</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-cupons-inativos">
                    <?php if (empty($cuponsInativos)): ?>
                        <tr class="empty-row" id="linha-sem-cupons-inativos">
                            <td colspan="8" class="text-center py-4 text-muted">Nenhum cupom inativo.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cuponsInativos as $c): ?>
                            <?php echo renderLinhaCupom($c, $servidoresMap); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CRIAR CUPOM -->
<div class="modal fade" id="modalCriarCupom" tabindex="-1" aria-labelledby="modalCriarCupomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="formCriarCupom">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCriarCupomLabel">Criar Cupom de Desconto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="criar-codigo" class="form-label fw-bold">Código do Cupom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace text-uppercase" id="criar-codigo" name="codigo" placeholder="Ex: PROMO10, NERD2026" required maxlength="50">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="criar-porcentagem" class="form-label fw-bold">Desconto (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" max="100" class="form-control font-monospace" id="criar-porcentagem" name="porcentagem" value="10" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="criar-servidor-id" class="form-label fw-bold">Servidor Aplicável</label>
                            <select class="form-select" id="criar-servidor-id" name="servidor_id">
                                <option value="0">Todos os Servidores</option>
                                <?php foreach ($servidores as $srv): ?>
                                    <option value="<?php echo (int)$srv['id']; ?>">
                                        <?php echo htmlspecialchars($srv['nome'] ?? $srv['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="criar-expira-em" class="form-label fw-bold">Data e Hora de Expiração <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="criar-expira-em" name="expira_em" value="<?php echo date('Y-m-d\TH:i', strtotime('+30 days')); ?>" required>
                    </div>

                    <div class="pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="criar-ativo" name="ativo" value="1" checked>
                            <label class="form-check-label fw-bold" for="criar-ativo">Cupom Ativo (Pronto para uso)</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-criar-cupom">
                        <i class="fa-solid fa-check me-1"></i> Criar Cupom
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CUPOM -->
<div class="modal fade" id="modalEditarCupom" tabindex="-1" aria-labelledby="modalEditarCupomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="formEditarCupom">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id" id="edit-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditarCupomLabel">
                        Editar Cupom: <span id="modalEditarCupomCodigoHeader" class="text-primary font-monospace"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit-codigo" class="form-label fw-bold">Código do Cupom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace text-uppercase" id="edit-codigo" name="codigo" required maxlength="50">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="edit-porcentagem" class="form-label fw-bold">Desconto (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" max="100" class="form-control font-monospace" id="edit-porcentagem" name="porcentagem" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="edit-servidor-id" class="form-label fw-bold">Servidor Aplicável</label>
                            <select class="form-select" id="edit-servidor-id" name="servidor_id">
                                <option value="0">Todos os Servidores</option>
                                <?php foreach ($servidores as $srv): ?>
                                    <option value="<?php echo (int)$srv['id']; ?>">
                                        <?php echo htmlspecialchars($srv['nome'] ?? $srv['servername'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-expira-em" class="form-label fw-bold">Data e Hora de Expiração <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="edit-expira-em" name="expira_em" required>
                    </div>

                    <div class="pt-2 border-top">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit-ativo" name="ativo" value="1">
                            <label class="form-check-label fw-bold" for="edit-ativo">Cupom Ativo</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar-cupom">
                        <i class="fa-solid fa-check me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const servidoresMap = <?php echo json_encode($servidoresMap, JSON_UNESCAPED_UNICODE); ?>;

    const modalCriarEl = document.getElementById('modalCriarCupom');
    const modalEditarEl = document.getElementById('modalEditarCupom');
    const bsModalCriar = modalCriarEl ? bootstrap.Modal.getOrCreateInstance(modalCriarEl) : null;
    const bsModalEditar = modalEditarEl ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatarDataBR(dataStr) {
        if (!dataStr) return '';
        const d = new Date(dataStr);
        if (isNaN(d.getTime())) return dataStr;
        const dia = String(d.getDate()).padStart(2, '0');
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const ano = d.getFullYear();
        const hora = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${dia}/${mes}/${ano} ${hora}:${min}`;
    }

    function atualizarContadores() {
        const ativas = document.querySelectorAll('#lista-cupons-ativos tr[data-id]').length;
        const inativas = document.querySelectorAll('#lista-cupons-inativos tr[data-id]').length;
        const total = ativas + inativas;

        const totalEl = document.querySelector('[data-item-counter]');
        if (totalEl) totalEl.textContent = total + ' ' + (total === 1 ? 'cupom' : 'cupons');

        const totalAtivosSummary = document.querySelector('[data-item-counter-ativos-summary]');
        if (totalAtivosSummary) totalAtivosSummary.textContent = ativas + ' ' + (ativas === 1 ? 'ativo' : 'ativos');

        const totalInativosSummary = document.querySelector('[data-item-counter-inativos-summary]');
        if (totalInativosSummary) totalInativosSummary.textContent = inativas + ' ' + (inativas === 1 ? 'inativo' : 'inativos');

        const totalAtivosHeader = document.querySelector('[data-item-counter-ativos]');
        if (totalAtivosHeader) totalAtivosHeader.textContent = ativas + ' ' + (ativas === 1 ? 'cupom' : 'cupons');

        const totalInativosHeader = document.querySelector('[data-item-counter-inativos]');
        if (totalInativosHeader) totalInativosHeader.textContent = inativas + ' ' + (inativas === 1 ? 'cupom' : 'cupons');
    }

    function abrirModalEditarCupom(row) {
        if (!row || !modalEditarEl) return;
        const d = row.dataset;

        document.getElementById('edit-id').value = d.id || '';
        document.getElementById('edit-codigo').value = d.codigo || '';
        document.getElementById('modalEditarCupomCodigoHeader').textContent = d.codigo || '';
        document.getElementById('edit-porcentagem').value = d.porcentagem || '';
        document.getElementById('edit-servidor-id').value = d.servidorId || '0';
        document.getElementById('edit-expira-em').value = d.expiraEm || '';
        document.getElementById('edit-ativo').checked = (d.ativo === '1' || d.ativo === 1);

        if (bsModalEditar) bsModalEditar.show();
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-editar-cupom');
        if (!btn) return;
        const row = btn.closest('tr');
        if (row) abrirModalEditarCupom(row);
    });

    function criarElementoLinhaCupom(c) {
        const tr = document.createElement('tr');
        tr.id = `cupom-row-${c.id}`;
        tr.dataset.id = c.id;
        tr.dataset.codigo = c.codigo || '';
        tr.dataset.porcentagem = c.porcentagem_desconto || '0';
        tr.dataset.servidorId = c.servidor_id || '0';
        tr.dataset.expiraEm = (c.expira_em || '').substring(0, 16);
        tr.dataset.ativo = c.ativo ? '1' : '0';
        tr.dataset.usos = c.usos_total || '0';
        tr.dataset.criadoEm = formatarDataBR(c.criado_em);

        const sId = parseInt(c.servidor_id) || 0;
        const srv = (sId > 0 && servidoresMap[sId]) ? servidoresMap[sId] : null;
        const corSrv = (srv && srv.themecolor) ? srv.themecolor : '#6366f1';
        const srvBadge = srv 
            ? `<span class="badge" style="background-color: ${escapeHtml(corSrv)}; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);"><i class="fa-solid fa-server me-1"></i>${escapeHtml(srv.servername)}</span>`
            : `<span class="badge bg-secondary-subtle text-body border"><i class="fa-solid fa-globe me-1 text-primary"></i> Todos os Servidores</span>`;

        const isAtivo = Boolean(Number(c.ativo));
        const statusBadge = isAtivo 
            ? `<span class="badge-status ativo">Ativo</span>`
            : `<span class="badge-status inativo">Inativo</span>`;

        const descFormatada = Number(c.porcentagem_desconto || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        const validadeFormatada = formatarDataBR(c.expira_em);

        tr.innerHTML = `
            <td>
                <span class="badge bg-light text-dark border font-monospace fs-6 px-2 py-1 js-cupom-codigo">
                    <i class="fa-solid fa-tag text-primary me-1"></i>${escapeHtml(c.codigo)}
                </span>
            </td>
            <td><strong class="text-success fw-bold js-cupom-desconto">${descFormatada}% OFF</strong></td>
            <td class="js-cupom-servidor">${srvBadge}</td>
            <td class="js-cupom-validade"><i class="fa-regular fa-clock text-muted me-1"></i>${validadeFormatada}</td>
            <td class="js-cupom-status">${statusBadge}</td>
            <td><span class="badge bg-light text-dark border font-monospace js-cupom-usos">${c.usos_total || 0} compras</span></td>
            <td class="text-muted small">${tr.dataset.criadoEm}</td>
            <td class="text-end text-nowrap">
                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary btn-abrir-editar-cupom" title="Editar Cupom"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="async-toggle" data-url="/admin/api/cupons/toggle.php" data-id="${c.id}" title="${isAtivo ? 'Desativar cupom' : 'Ativar cupom'}">
                        <i class="fa-solid ${isAtivo ? 'fa-eye' : 'fa-eye-slash'}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-action="async-delete" data-url="/admin/api/cupons/deletar.php" data-id="${c.id}" data-name="${escapeHtml(c.codigo)}" data-confirm="Deletar o cupom ${escapeHtml(c.codigo)}? Essa ação não pode ser desfeita." title="Deletar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        return tr;
    }

    // SUBMIT: CRIAR CUPOM
    const formCriar = document.getElementById('formCriarCupom');
    if (formCriar) {
        formCriar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-criar-cupom');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Criando...';
            }

            try {
                const formData = new FormData(formCriar);
                const res = await AdminApi.post('/admin/api/cupons/criar.php', formData);

                if (bsModalCriar) bsModalCriar.hide();
                formCriar.reset();

                if (res.cupom) {
                    const isAtivo = Boolean(Number(res.cupom.ativo));
                    const tbody = isAtivo ? document.getElementById('lista-cupons-ativos') : document.getElementById('lista-cupons-inativos');
                    const emptyRow = isAtivo ? document.getElementById('linha-sem-cupons-ativos') : document.getElementById('linha-sem-cupons-inativos');
                    if (emptyRow) emptyRow.remove();

                    const novaLinha = criarElementoLinhaCupom(res.cupom);
                    if (tbody) tbody.prepend(novaLinha);
                    novaLinha.classList.add('table-row-highlight');
                    setTimeout(() => novaLinha.classList.remove('table-row-highlight'), 1000);
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Cupom criado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao criar cupom.');
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }
            }
        });
    }

    // SUBMIT: EDITAR CUPOM
    const formEditar = document.getElementById('formEditarCupom');
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-submit-editar-cupom');
            const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
            }

            try {
                const formData = new FormData(formEditar);
                const res = await AdminApi.post('/admin/api/cupons/editar.php', formData);

                if (bsModalEditar) bsModalEditar.hide();

                if (res.cupom) {
                    const c = res.cupom;
                    let tr = document.getElementById(`cupom-row-${c.id}`);
                    const isAtivo = Boolean(Number(c.ativo));
                    const destinoTbody = isAtivo ? document.getElementById('lista-cupons-ativos') : document.getElementById('lista-cupons-inativos');

                    if (tr) {
                        const novaLinha = criarElementoLinhaCupom(c);
                        tr.replaceWith(novaLinha);
                        tr = novaLinha;

                        // Se a linha estava na outra tabela, move para a tabela correta
                        if (tr.parentElement !== destinoTbody && destinoTbody) {
                            const emptyRow = isAtivo ? document.getElementById('linha-sem-cupons-ativos') : document.getElementById('linha-sem-cupons-inativos');
                            if (emptyRow) emptyRow.remove();
                            destinoTbody.prepend(tr);
                        }

                        tr.classList.add('table-row-highlight');
                        setTimeout(() => tr.classList.remove('table-row-highlight'), 1000);
                    }
                }

                atualizarContadores();
                AdminToast.success(res.mensagem || 'Cupom atualizado com sucesso!');
            } catch (err) {
                AdminToast.error(err.message || 'Erro ao atualizar cupom.');
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
        bsModalCriar.show();
    } else if (urlParams.has('edit')) {
        const editId = urlParams.get('edit');
        const row = document.getElementById(`cupom-row-${editId}`);
        if (row) {
            abrirModalEditarCupom(row);
        }
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>