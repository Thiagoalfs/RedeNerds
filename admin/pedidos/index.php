<?php
$paginaAtiva = 'pedidos';
$tituloPagina = 'Pedidos';
require_once __DIR__ . "/../includes/admin_header.php";

if (!defined('POR_PAGINA')) {
    define('POR_PAGINA', 15);
}
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$filtroStatus = trim($_GET['status'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$filtroServidor = trim($_GET['servidor'] ?? '');
$busca = trim($_GET['busca'] ?? '');

$totalPedidos = 0;
$pedidos = [];
$servidoresMap = [];
$servidoresList = [];

$where = [];
$params = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon FROM servidores ORDER BY servername ASC");
        $servidoresList = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($servidoresList as $s) {
            $servidoresMap[$s['servername']] = $s;
            if (!empty($s['nome'])) {
                $servidoresMap[$s['nome']] = $s;
            }
            $servidoresMap[$s['id']] = $s;
        }

        if (!empty($filtroStatus)) {
            $where[] = "status = :status";
            $params[':status'] = $filtroStatus;
        }

        if (!empty($filtroMetodo)) {
            $where[] = "metodo_pagamento = :metodo";
            $params[':metodo'] = $filtroMetodo;
        }

        if (!empty($filtroServidor)) {
            $srvMatch = null;
            foreach ($servidoresList as $s) {
                if ((string)$s['id'] === $filtroServidor || strcasecmp($s['servername'], $filtroServidor) === 0 || strcasecmp($s['nome'], $filtroServidor) === 0) {
                    $srvMatch = $s;
                    break;
                }
            }

            if ($srvMatch) {
                $where[] = "(servidor_id = :srv_id OR servidor = :srv_name OR servidor = :srv_nome OR servidor LIKE :srv_like)";
                $params[':srv_id'] = (int)$srvMatch['id'];
                $params[':srv_name'] = $srvMatch['servername'];
                $params[':srv_nome'] = $srvMatch['nome'];
                $params[':srv_like'] = "%{$srvMatch['servername']}%";
            } else {
                $where[] = "(servidor_id = :srv_id OR servidor LIKE :srv_like)";
                $params[':srv_id'] = is_numeric($filtroServidor) ? (int)$filtroServidor : 0;
                $params[':srv_like'] = "%{$filtroServidor}%";
            }
        }

        if (!empty($busca)) {
            $where[] = "(nick LIKE :busca OR txid LIKE :busca OR payer_email LIKE :busca OR payer_cpf LIKE :busca OR servidor LIKE :busca OR vip_nome LIKE :busca)";
            $params[':busca'] = "%{$busca}%";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pedidos $whereSql");
        $stmtCount->execute($params);
        $totalPedidos = (int)$stmtCount->fetchColumn();

        $totalPaginas = max(1, (int)ceil($totalPedidos / POR_PAGINA));

        $stmt = $pdo->prepare("
            SELECT * FROM pedidos 
            $whereSql 
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao listar pedidos: " . $e->getMessage());
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Pedidos</h4>
        <p class="text-muted small mb-0"><?php echo $totalPedidos; ?> pedido(s) registrado(s)</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalExportarPedidos">
            <i class="fa-solid fa-file-csv"></i> Exportar
        </button>
    </div>
</div>

<!-- FILTROS E BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por Nick, TXID, E-mail..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="servidor" class="form-select form-select-sm">
                    <option value="">Servidor: Todos</option>
                    <?php foreach ($servidoresList as $srv): ?>
                        <option value="<?php echo htmlspecialchars($srv['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string)$filtroServidor === (string)$srv['id'] || $filtroServidor === $srv['servername'] || $filtroServidor === $srv['nome']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($srv['servername'] ?: $srv['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Status: Todos</option>
                    <option value="pago" <?php echo ($filtroStatus === 'pago') ? 'selected' : ''; ?>>Aprovados</option>
                    <option value="pendente" <?php echo ($filtroStatus === 'pendente') ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="recusado" <?php echo ($filtroStatus === 'recusado') ? 'selected' : ''; ?>>Recusados / Cancelados</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="metodo" class="form-select form-select-sm">
                    <option value="">Método: Todos</option>
                    <option value="pix" <?php echo ($filtroMetodo === 'pix') ? 'selected' : ''; ?>>PIX</option>
                    <option value="cartao" <?php echo ($filtroMetodo === 'cartao') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                <?php if (!empty($busca) || !empty($filtroStatus) || !empty($filtroMetodo) || !empty($filtroServidor)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Limpar Filtros"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA DE PEDIDOS -->
<div class="admin-card">
    <div class="card-body p-0">
        <?php 
            $emptyMessage = 'Nenhum pedido encontrado com os filtros selecionados.';
            $mostrarPaginacao = true;
            require __DIR__ . "/../includes/pedidos_table.php";
        ?>
    </div>
</div>

<!-- MODAL EXPORTAR PEDIDOS -->
<div class="modal fade" id="modalExportarPedidos" tabindex="-1" aria-labelledby="modalExportarPedidosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="exportar.php" target="_blank" id="formExportarPedidos">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalExportarPedidosLabel">
                        <i class="fa-solid fa-file-excel text-success me-2"></i> Exportar Pedidos (.CSV)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Selecione o período desejado ou marque para exportar todo o histórico de pedidos para Excel (.csv).
                    </p>

                    <!-- FILTRO DE PERÍODO -->
                    <div class="row g-2 mb-3" id="containerDatasExport">
                        <div class="col-6">
                            <label for="exportDataInicio" class="form-label small fw-semibold">De (Data Inicial):</label>
                            <input type="date" class="form-control form-control-sm" id="exportDataInicio" name="data_inicio" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-6">
                            <label for="exportDataFim" class="form-label small fw-semibold">Até (Data Final):</label>
                            <input type="date" class="form-control form-control-sm" id="exportDataFim" name="data_fim" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- CHECKBOX TODO O PERÍODO -->
                    <div class="form-check p-2 bg-light rounded border mb-2">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="exportTodoPeriodo" name="todo_periodo" value="1">
                        <label class="form-check-label small fw-bold text-dark" for="exportTodoPeriodo" style="cursor: pointer;">
                            Todo o período
                        </label>
                        <div class="text-muted mt-1" style="font-size: 0.76rem;">
                            Ao marcar, todos os registros de VIPs serão exportados.
                        </div>
                    </div>

                    <!-- CHECKBOX APENAS APROVADOS -->
                    <div class="form-check p-2 bg-light rounded border mb-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="exportApenasAprovados" name="apenas_aprovados" value="1" checked>
                        <label class="form-check-label small fw-bold text-dark" for="exportApenasAprovados" style="cursor: pointer;">
                            Apenas aprovados
                        </label>
                        <div class="text-muted mt-1" style="font-size: 0.76rem;">
                            Exporta somente os pedidos confirmados e pagos com sucesso.
                        </div>
                    </div>

                    <!-- FILTROS ADICIONAIS -->
                    <div class="border-top pt-3">
                        <label class="form-label small text-muted mb-2 fw-semibold"><i class="fa-solid fa-sliders me-1"></i> Filtros Adicionais (Opcional):</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="exportServidor" class="form-label small text-muted">Servidor:</label>
                                <select name="servidor" id="exportServidor" class="form-select form-select-sm">
                                    <option value="">Todos os Servidores</option>
                                    <?php foreach ($servidoresList as $srv): ?>
                                        <option value="<?php echo htmlspecialchars($srv['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string)$filtroServidor === (string)$srv['id'] || $filtroServidor === $srv['servername'] || $filtroServidor === $srv['nome']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($srv['servername'] ?: $srv['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="exportStatus" class="form-label small text-muted">Status:</label>
                                <select name="status" id="exportStatus" class="form-select form-select-sm">
                                    <option value="">Todos os Status</option>
                                    <option value="pago" <?php echo ($filtroStatus === 'pago') ? 'selected' : ''; ?>>Aprovados</option>
                                    <option value="pendente" <?php echo ($filtroStatus === 'pendente') ? 'selected' : ''; ?>>Pendentes</option>
                                    <option value="recusado" <?php echo ($filtroStatus === 'recusado') ? 'selected' : ''; ?>>Recusados / Cancelados</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-2">
                        <i class="fa-solid fa-download"></i> Baixar .CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkTodoPeriodo = document.getElementById('exportTodoPeriodo');
    const inputDataInicio = document.getElementById('exportDataInicio');
    const inputDataFim = document.getElementById('exportDataFim');
    const checkApenasAprovados = document.getElementById('exportApenasAprovados');
    const selectStatus = document.getElementById('exportStatus');

    if (checkTodoPeriodo && inputDataInicio && inputDataFim) {
        function toggleDatas() {
            const isTodo = checkTodoPeriodo.checked;
            inputDataInicio.disabled = isTodo;
            inputDataFim.disabled = isTodo;
            if (isTodo) {
                inputDataInicio.classList.add('bg-light');
                inputDataFim.classList.add('bg-light');
            } else {
                inputDataInicio.classList.remove('bg-light');
                inputDataFim.classList.remove('bg-light');
            }
        }

        checkTodoPeriodo.addEventListener('change', toggleDatas);
        toggleDatas();
    }

    if (checkApenasAprovados && selectStatus) {
        function toggleStatus() {
            const apenasAprovados = checkApenasAprovados.checked;
            selectStatus.disabled = apenasAprovados;
            if (apenasAprovados) {
                selectStatus.classList.add('bg-light');
            } else {
                selectStatus.classList.remove('bg-light');
            }
        }

        checkApenasAprovados.addEventListener('change', toggleStatus);
        toggleStatus();
    }
});
</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
