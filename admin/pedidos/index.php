<?php
$paginaAtiva = 'pedidos';
$tituloPagina = 'Pedidos VIP & Vendas';
require_once __DIR__ . "/../includes/admin_header.php";

const POR_PAGINA = 15;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * POR_PAGINA;

$filtroStatus = trim($_GET['status'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$busca = trim($_GET['busca'] ?? '');

$where = [];
$params = [];

if (!empty($filtroStatus)) {
    $where[] = "status = :status";
    $params[':status'] = $filtroStatus;
}

if (!empty($filtroMetodo)) {
    $where[] = "metodo_pagamento = :metodo";
    $params[':metodo'] = $filtroMetodo;
}

if (!empty($busca)) {
    $where[] = "(nick LIKE :busca OR txid LIKE :busca OR payer_email LIKE :busca OR payer_cpf LIKE :busca OR servidor LIKE :busca OR vip_nome LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$totalPedidos = 0;
$pedidos = [];
$servidoresMap = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtSrv = $pdo->query("SELECT id, servername, nome, themecolor, icon FROM servidores ORDER BY servername ASC");
        $servidoresList = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($servidoresList as $s) {
            $servidoresMap[$s['servername']] = $s;
            if (!empty($s['nome'])) {
                $servidoresMap[$s['nome']] = $s;
            }
        }

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pedidos_vip $whereSql");
        $stmtCount->execute($params);
        $totalPedidos = (int)$stmtCount->fetchColumn();

        $totalPaginas = max(1, (int)ceil($totalPedidos / POR_PAGINA));

        $stmt = $pdo->prepare("
            SELECT * FROM pedidos_vip 
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
        <h4 class="fw-bold mb-1">Pedidos VIP & Vendas</h4>
        <p class="text-muted small mb-0"><?php echo $totalPedidos; ?> pedido(s) registrado(s)</p>
    </div>
</div>

<!-- FILTROS E BUSCA -->
<div class="admin-card mb-3">
    <div class="p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por Nick, TXID, E-mail ou CPF..." value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Status: Todos</option>
                    <option value="pago" <?php echo ($filtroStatus === 'pago') ? 'selected' : ''; ?>>Aprovados</option>
                    <option value="pendente" <?php echo ($filtroStatus === 'pendente') ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="recusado" <?php echo ($filtroStatus === 'recusado') ? 'selected' : ''; ?>>Recusados / Cancelados</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="metodo" class="form-select form-select-sm">
                    <option value="">Método: Todos</option>
                    <option value="pix" <?php echo ($filtroMetodo === 'pix') ? 'selected' : ''; ?>>PIX</option>
                    <option value="cartao" <?php echo ($filtroMetodo === 'cartao') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                <?php if (!empty($busca) || !empty($filtroStatus) || !empty($filtroMetodo)): ?>
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

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
