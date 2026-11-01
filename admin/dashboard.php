<?php
$paginaAtiva = 'dashboard';
$tituloPagina = 'Dashboard';
require_once __DIR__ . "/includes/admin_header.php";
require_once __DIR__ . "/includes/servidores_admin.php";

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
        $servidoresMap = carregarServidoresAdmin($pdo)['mapa'];

        // Métricas consolidadas de pedidos em uma única query com range indexado
        $metricasPedidos = $pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) AS total_faturamento,
                COALESCE(SUM(CASE WHEN status = 'pago' AND criado_em >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01 00:00:00') THEN valor ELSE 0 END), 0) AS faturamento_mes,
                COUNT(CASE WHEN status = 'pago' THEN 1 END) AS pedidos_pagos,
                COUNT(CASE WHEN status = 'pendente' THEN 1 END) AS pedidos_pendentes
            FROM pedidos
        ")->fetch(PDO::FETCH_ASSOC);

        $totalFaturamento = (float)($metricasPedidos['total_faturamento'] ?? 0);
        $faturamentoMes = (float)($metricasPedidos['faturamento_mes'] ?? 0);
        $totalPedidosPagos = (int)($metricasPedidos['pedidos_pagos'] ?? 0);
        $totalPedidosPendentes = (int)($metricasPedidos['pedidos_pendentes'] ?? 0);

        // Cupons
        $totalCuponsAtivos = (int)$pdo->query("SELECT COUNT(*) FROM cupons WHERE ativo = 1 AND expira_em >= NOW()")->fetchColumn();
        $totalUsosCupons = (int)$pdo->query("SELECT COALESCE(SUM(usos_total), 0) FROM cupons")->fetchColumn();

        // Servidores & Novidades
        $totalServidores = (int)$pdo->query("SELECT COUNT(*) FROM servidores WHERE enabled = 1")->fetchColumn();
        $totalNoticias = (int)$pdo->query("SELECT COUNT(*) FROM novidades")->fetchColumn();

        // Últimos 8 Pedidos
        $stmtUltimos = $pdo->query("SELECT * FROM pedidos ORDER BY id DESC LIMIT 8");
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
        <a href="/admin/noticias/?action=novo" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Nova Novidade</a>
        <a href="/admin/cupons/?action=novo" class="btn btn-success btn-sm"><i class="fa-solid fa-tags me-1"></i> Novo Cupom</a>
        <a href="/admin/servidores/?action=novo" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-server me-1"></i> Novo Servidor</a>
    </div>
</div>

<!-- GRID DE CARDS KPI -->
<div class="kpi-grid">
    <!-- Card 1: Faturamento Mensal -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Faturamento do Mês</span>
            <div class="kpi-value text-success">R$ <?php echo number_format($faturamentoMes, 2, ',', '.'); ?></div>
            <span class="kpi-subtext">R$ <?php echo number_format($totalFaturamento, 2, ',', '.'); ?> no total</span>
        </div>
    </div>

    <!-- Card 2: Pedidos -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Vendas Aprovadas</span>
            <div class="kpi-value"><?php echo $totalPedidosPagos; ?></div>
            <span class="kpi-subtext text-warning fw-semibold"><?php echo pluralizar($totalPedidosPendentes, 'pedido pendente', 'pedidos pendentes'); ?></span>
        </div>
    </div>

    <!-- Card 3: Cupons Ativos -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Cupons Ativos</span>
            <div class="kpi-value text-purple"><?php echo $totalCuponsAtivos; ?></div>
            <span class="kpi-subtext"><?php echo pluralizar($totalUsosCupons, 'desconto aplicado', 'descontos aplicados'); ?></span>
        </div>
    </div>

    <!-- Card 4: Servidores Ativos -->
    <div class="kpi-card">
        <div class="kpi-info">
            <span class="kpi-label">Servidores Online</span>
            <div class="kpi-value"><?php echo $totalServidores; ?></div>
            <span class="kpi-subtext"><?php echo pluralizar($totalNoticias, 'novidade', 'novidades'); ?> no site</span>
        </div>
    </div>
</div>

<!-- TABELA DE ATIVIDADES RECENTES (ÚLTIMOS PEDIDOS) -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
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