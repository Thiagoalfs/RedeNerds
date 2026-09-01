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

try {
    if (isset($pdo) && $pdo instanceof PDO) {
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
        <a href="criar.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Nova Novidade</a>
        <a href="cupom_criar.php" class="btn btn-success btn-sm"><i class="fa-solid fa-tags me-1"></i> Novo Cupom</a>
        <a href="servidor_criar.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-server me-1"></i> Novo Servidor</a>
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
        <a href="pedidos.php" class="btn btn-outline-primary btn-sm">Ver todos os pedidos →</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 220px;">Jogador</th>
                        <th>Pacote VIP</th>
                        <th>Servidor</th>
                        <th>Método</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimosPedidos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum pedido registrado ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimosPedidos as $ped): 
                            $status = strtolower($ped['status'] ?? 'pendente');
                            $metodo = strtolower($ped['metodo_pagamento'] ?? 'pix');
                            $parcelas = (int)($ped['parcelas'] ?? 1);
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="https://mc-heads.net/avatar/<?php echo urlencode($ped['nick']); ?>/28" 
                                             class="rounded border" width="28" height="28" alt="Skin"
                                             onerror="this.src='https://mc-heads.net/avatar/MHF_Steve/28'">
                                        <div>
                                            <strong class="text-dark"><?php echo htmlspecialchars($ped['nick'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <small class="text-muted d-block font-monospace" style="font-size: 0.72rem;"><?php echo htmlspecialchars($ped['txid'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary"><?php echo htmlspecialchars($ped['vip_nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if (!empty($ped['cupom_codigo'])): ?>
                                        <span class="badge bg-light text-dark border ms-1" style="font-size: 0.68rem;"><i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($ped['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ped['servidor'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <?php if ($metodo === 'cartao'): ?>
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-credit-card me-1"></i> Cartão <?php echo ($parcelas > 1) ? "({$parcelas}x)" : ""; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fa-brands fa-pix me-1"></i> PIX</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark">R$ <?php echo number_format((float)$ped['valor'], 2, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <?php if ($status === 'pago'): ?>
                                        <span class="badge-status pago">Aprovado</span>
                                    <?php elseif ($status === 'pendente'): ?>
                                        <span class="badge-status pendente">Pendente</span>
                                    <?php else: ?>
                                        <span class="badge-status recusado">Recusado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('d/m/Y H:i', strtotime($ped['criado_em'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/admin_footer.php"; ?>