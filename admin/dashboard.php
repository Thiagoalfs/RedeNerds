<?php
$paginaAtiva = 'dashboard';
$tituloPagina = 'Visão Geral';
require_once __DIR__ . "/includes/admin_header.php";

// 1. Consulta Métricas & KPIs
$totalFaturamento = 0.00;
$faturamentoMes = 0.00;
$totalPedidosPagos = 0;
$totalPedidosPendentes = 0;
$totalCuponsAtivos = 0;
$totalUsosCupons = 0;
$totalServidores = 0;
$totalNoticias = 0;
$ultimosPedidos = [];
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

        // Faturamento Total
        $totalFaturamento = (float)$pdo->query("SELECT COALESCE(SUM(valor), 0) FROM pedidos_vip WHERE status = 'pago'")->fetchColumn();

        // Faturamento do Mês Atual
        $faturamentoMes = (float)$pdo->query("SELECT COALESCE(SUM(valor), 0) FROM pedidos_vip WHERE status = 'pago' AND MONTH(criado_em) = MONTH(CURRENT_DATE()) AND YEAR(criado_em) = YEAR(CURRENT_DATE())")->fetchColumn();

        // Pedidos Pagos vs Pendentes
        $totalPedidosPagos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos_vip WHERE status = 'pago'")->fetchColumn();
        $totalPedidosPendentes = (int)$pdo->query("SELECT COUNT(*) FROM pedidos_vip WHERE status = 'pendente'")->fetchColumn();

        // Cupons
        $totalCuponsAtivos = (int)$pdo->query("SELECT COUNT(*) FROM cupons WHERE ativo = 1 AND expira_em >= NOW()")->fetchColumn();
        $totalUsosCupons = (int)$pdo->query("SELECT COALESCE(SUM(usos_total), 0) FROM cupons")->fetchColumn();

        // Servidores & Novidades
        $totalServidores = (int)$pdo->query("SELECT COUNT(*) FROM servidores WHERE enabled = 1")->fetchColumn();
        $totalNoticias = (int)$pdo->query("SELECT COUNT(*) FROM novidades")->fetchColumn();

        // Últimos 8 Pedidos
        $stmtUltimos = $pdo->query("SELECT * FROM pedidos_vip ORDER BY id DESC LIMIT 8");
        $ultimosPedidos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro no dashboard admin: " . $e->getMessage());
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Olá, <?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="text-muted small mb-0">Aqui está o resumo em tempo real da saúde da Rede Nerds.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/noticias/criar.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Nova Novidade</a>
        <a href="/admin/cupons/criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-tags me-1"></i> Novo Cupom</a>
        <a href="/admin/servidores/criar.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-server me-1"></i> Novo Servidor</a>
    </div>
</div>

<!-- GRID DE CARDS KPI -->
<div class="kpi-grid">
    <!-- Card 1: Faturamento Total -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Faturamento Aprovado</span>
            <div class="kpi-value text-success">R$ <?php echo number_format($totalFaturamento, 2, ',', '.'); ?></div>
            <span class="kpi-subtext">R$ <?php echo number_format($faturamentoMes, 2, ',', '.'); ?> este mês</span>
        </div>
        <div class="kpi-icon-box kpi-icon-green">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>

    <!-- Card 2: Pedidos VIP -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Vendas VIP Aprovadas</span>
            <div class="kpi-value"><?php echo $totalPedidosPagos; ?></div>
            <span class="kpi-subtext text-warning fw-semibold"><?php echo $totalPedidosPendentes; ?> pedido(s) pendente(s)</span>
        </div>
        <div class="kpi-icon-box kpi-icon-blue">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <!-- Card 3: Cupons Ativos -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Cupons Ativos</span>
            <div class="kpi-value text-purple"><?php echo $totalCuponsAtivos; ?></div>
            <span class="kpi-subtext"><?php echo $totalUsosCupons; ?> desconto(s) aplicado(s)</span>
        </div>
        <div class="kpi-icon-box kpi-icon-purple">
            <i class="fa-solid fa-tags"></i>
        </div>
    </div>

    <!-- Card 4: Servidores Ativos -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Servidores Online</span>
            <div class="kpi-value"><?php echo $totalServidores; ?></div>
            <span class="kpi-subtext"><?php echo $totalNoticias; ?> novidade(s) no site</span>
        </div>
        <div class="kpi-icon-box kpi-icon-yellow">
            <i class="fa-solid fa-server"></i>
        </div>
    </div>
</div>

<!-- TABELA DE ATIVIDADES RECENTES (ÚLTIMOS PEDIDOS) -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="fa-solid fa-clock-rotate-left text-primary"></i>
            Últimos Pedidos da Loja
        </h5>
        <a href="/admin/pedidos/" class="btn btn-outline-primary btn-sm">Ver todos os pedidos →</a>
    </div>
    <div class="card-body p-0">
        <?php 
            $pedidos = $ultimosPedidos;
            $emptyMessage = 'Nenhum pedido registrado ainda.';
            $mostrarPaginacao = false;
            require __DIR__ . "/includes/pedidos_table.php";
        ?>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>